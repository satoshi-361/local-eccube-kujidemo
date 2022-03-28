<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Payment;

use Plugin\VeriTrans4G\Form\Type\Shopping\PaymentATMType;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;

class ATMService extends BaseService
{
    /**
     * コンテナ
     */
    protected $container;

    /**
     * エンティティーマネージャー
     */
    protected $em;

    /**
     * VT用固定値配列
     */
    protected $vt4gConst;

    /**
     * 汎用処理用ユーティリティ
     */
    protected $util;

    /**
     * MDK Logger
     */
    protected $mdkLogger;

    /**
     * コミット処理
     *
     * @param $order
     * @param array $formData 入力フォームからの値
     * @param array $paymentInfo 決済ごとの設定情報
     * @param array $error エラー内容
     * @return boolean|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function atmCommit($order, $formData, $paymentInfo, &$error)
    {
        // ベリトランスとの通信
        $this->connectVT4G($order, $formData, $paymentInfo, $error);
        // 決済情報 (memo05)
        $payment = $this->paymentResult;
        // 成功時
        if ($payment['isOK'] == true) {
            // メール情報(memo06)
            $mailData = $this->setMail($order);
            // 決済変更ログ情報(plg_vt4g_order_logテーブル)
            $logData = $this->setLog($order);
            // 完了処理
            $this->completeOrder($order, $payment, $logData, $mailData);
            return true;
        }
        return false;
    }

    /**
     * 入力フォームを生成
     *
     * @return object 情報入力フォーム
     */
    public function createATMForm()
    {
        return $this->container->get('form.factory')
            ->create(PaymentATMType::class);
    }

    /**
     * ベリトランスとの通信処理
     *
     * @param \Eccube\Entity\Order $order
     * @param array $formData     入力データ
     * @param array $paymentInfo  決済処理データ
     * @param array $error        エラー内容
     * @return boolean            レスポンスを正常に処理したかどうか
     */
    public function connectVT4G($order, $formData, $paymentInfo, &$error)
    {
        $this->mdkLogger->info(
            sprintf(
                trans('vt4g_plugin.payment.shopping.mdk.start'),
                $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_31']
            )
        );

        $objRequest = new \BankAuthorizeRequestDto();
        $this->setRequestParam($objRequest, $order, $paymentInfo, $formData);

        $objTransaction = new \TGMDK_Transaction();
        $objResponse = $objTransaction->execute($objRequest);

        // レスポンス初期化
        $this->initPaymentResult();

        if (isset($objResponse) == false) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.mdk.error'));
            $error['payment'] = $this->defaultErrorMessage;
            return false;
        }
        // 結果コード
        $this->paymentResult['mStatus']     = $objResponse->getMStatus();
        // 詳細コード
        $this->paymentResult['vResultCode'] = $objResponse->getVResultCode();
        // エラーメッセージ
        $this->paymentResult['mErrMsg']     = $objResponse->getMerrMsg();

        // 正常終了
        if ($this->paymentResult['mStatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $paymentMethod = $this->util->getPaymentMethod($order->getPayment()->getId());
            // ログ出力用
            $this->paymentResult['isOK']         = true;
            $this->paymentResult['orderId']      = $objResponse->getOrderId();         // 取引ID取得
            $this->paymentResult['shunoKikanNo'] = $objResponse->getShunoKikanNo();    // 収納機関番号
            $this->paymentResult['customerNo']   = $objResponse->getCustomerNo();      // お客様番号
            $this->paymentResult['confirmNo']    = $objResponse->getConfirmNo();       // 確認番号
            $this->paymentResult['limitDate']    = $objRequest->getPayLimit();         // 有効期限
            $this->paymentResult['payStatus']    = $this->getNewPaymentStatus($paymentMethod); // 決済状態を保存
            $this->paymentResult['tradUrl']      = '';         // 2018/05 trAdは廃止になりました
            $this->paymentResult['captureTotal'] = floor($order->getPaymentTotal());   // 決済金額

            $this->mdkLogger->info(print_r($this->paymentResult, true));
        // 異常終了
        } else {
            $error['payment']  = $this->defaultErrorMessage;
            $error['payment'] .= $this->paymentResult['vResultCode'] . ':';
            $error['payment'] .= $this->paymentResult['mErrMsg']     . '<br />';

            $this->mdkLogger->info(print_r($this->paymentResult, true));

            return false;
        }

        return true;
    }

    /**
     * 決済処理の実行
     *
     * @param  array $payload 決済に使用するデータ
     * @return array          決済結果データ
     */
    public function operateNewly($payload)
    {
        // 新規決済処理の実行
        $operationResult = parent::operateNewly($payload);

        // ログの出力
        $this->mdkLogger->info(print_r($operationResult, true));

        return $operationResult;
    }

    /**
     * リクエストパラメータの設定
     *
     * @param object $objRequest   BankAuthorizeRequestDto
     * @param \Eccube\Entity\Order $order
     * @param array  $paymentInfo  決済処理データ
     */
    public function setRequestParam(&$objRequest, $order, $paymentInfo)
    {
        $objRequest->setOrderId($this->getMdkOrderId($order->getId()));
        $objRequest->setAmount(floor($order->getPaymentTotal()));

        $serviceOptionType = $this->vt4gConst['VT4G_SERVICE_PAYTYPEID_31'];
        $name1 = $order->getName01();
        $name2 = $order->getName02();
        $kana1 = $order->getKana01();
        $kana2 = $order->getKana02();
        $payLimit = $this->util->getAddDateFormat($paymentInfo['payment_term_day'], 'Ymd');
        $contents = $paymentInfo['contents'] ? $paymentInfo['contents'] : '';
        $contentsKana = $paymentInfo['contents_kana'] ? $paymentInfo['contents_kana'] : '';
        $payCsv = '';

        $objRequest->setServiceOptionType($serviceOptionType);
        $objRequest->setName1(mb_substr(mb_convert_kana($name1,"NKRV"),0,10));
        $objRequest->setName2(mb_substr(mb_convert_kana($name2,"NKRV"),0,10));
        $objRequest->setKana1(mb_substr(mb_convert_kana($kana1,"NKRV"),0,10));
        $objRequest->setKana2(mb_substr(mb_convert_kana($kana2,"NKRV"),0,10));
        $objRequest->setPayLimit($payLimit);
        $objRequest->setContents($contents);
        $objRequest->setContentsKana($contentsKana);
        $objRequest->setPayCsv($payCsv);
    }

    /**
     * メール内容の設定と完了画面の内容設定
     *
     * @param  object $order         注文データ
     * @param  array  $paymentResult 決済レスポンスデータ
     * @return array                 メールの説明文
     */
    public function setMail($order, $paymentResult = null)
    {
        if (is_null($paymentResult)) {
            $paymentResult = $this->paymentResult;
        }

        $this->mailData = [];
        $paymentMethod = $this->util->getPaymentMethod($order->getPayment()->getId());
        $paymentName  = $this->util->getPayName($paymentMethod->getMemo03());

        // メール記載情報
        $this->setMailTitle($paymentName);
        $this->setMailExplan(trans('vt4g_plugin.payment.mail.expla.atm'));

        $this->setMailInfo('収納機関番号', $paymentResult['shunoKikanNo']);
        $this->setMailInfo('お客様番号'  , $paymentResult['customerNo']);
        $this->setMailInfo('ご確認番号'  , $paymentResult['confirmNo']);
        $this->setMailInfo('お支払期限'  , $this->util->toDate($paymentResult['limitDate']));
        $this->setMailAd($paymentResult['vResultCode'], $paymentResult['tradUrl']); // 広告
        $this->setMailAdminSetting($paymentMethod);
        $this->setMailInfo('' , $this->getExplain($this->vt4gConst['VT4G_SERVICE_PAYTYPEID_31'])); // 説明取得

        return $this->mailData;
    }

    /**
     * ログ出力内容
     *
     * @param \Eccube\Entity\Order $order
     * @return array
     */
    public function setLog($order, $paymentResult = null)
    {
        if (is_null($paymentResult)) {
            $paymentResult = $this->paymentResult;
        }

        $Vt4gPayment = $this->em->getRepository(Vt4gPaymentMethod::class);
        $paymentMethod = $Vt4gPayment->find($order->getPayment()->getId());
        $this->setLogHead($paymentMethod->getMemo03(), '', $paymentResult);
        $this->setLogInfo('収納機関番号', $paymentResult['shunoKikanNo']);
        $this->setLogInfo('お客様番号', $paymentResult['customerNo']);
        $this->setLogInfo('ご確認番号', $paymentResult['confirmNo']);
        $this->setLogInfo('お支払期限', $this->util->toDate($paymentResult['limitDate']));

        return $this->logData;
    }
}
