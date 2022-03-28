<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Payment;

use Eccube\Entity\Order;
use Plugin\VeriTrans4G\Form\Type\Shopping\PaymentCVSType;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;

// コンビニ決済関連処理
class CVSService extends BaseService
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
     * @param object $order      Orderクラスインスタンス
     * @param array $formData    入力フォームからの値
     * @param array $paymentInfo 決済ごとの設定情報
     * @param array $error       エラー内容
     * @return boolean|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function cvsCommit($order, $formData, $paymentInfo, &$error)
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
            return $payment;
        }
        return false;
    }

    /**
     * コンビニ情報入力フォームを生成
     * @param  array $paymentInfo 決済設定
     * @return object             情報入力フォーム
     */
    public function createCVSForm($paymentInfo)
    {
        return $this->container->get('form.factory')
                    ->create(PaymentCVSType::class, compact('paymentInfo'));
    }


    /**
     * ベリトランスとの通信処理
     *
     * @param \Eccube\Entity\Order $order
     * @param array $formData      入力データ
     * @param array $paymentInfo   決済処理データ
     * @param array $error         エラー内容
     * @return boolean             レスポンスを正常に処理したかどうか
     */
    public function connectVT4G($order, $formData, $paymentInfo, &$error)
    {
        $this->mdkLogger->info(
            sprintf(
                trans('vt4g_plugin.payment.shopping.mdk.start'),
                $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_20']
            )
        );

        $objRequest = new \CvsAuthorizeRequestDto();
        $this->setRequestParam($objRequest, $order, $paymentInfo, $formData->get('payment_cvs')['conveni']);

        $objTransaction = new \TGMDK_Transaction();
        $objResponse = $objTransaction->execute($objRequest);
        // コンビニタイプ
        $paymentCVS = $formData->get('payment_cvs');

        // レスポンス初期化
        $this->initPaymentResult();

        if (isset($objResponse) == false) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.mdk.error'));
            $error['payment'] = trans('vt4g_plugin.payment.shopping.error'). '<br />';
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
            $this->paymentResult['isOK']              = true;
            $this->paymentResult['orderId']           = $objResponse->getOrderId();       // 取引ID取得
            $this->paymentResult['limitDate']         = $objRequest->getPayLimit();       // 有効期限
            $this->paymentResult['telNo']             = $objRequest->getTelNo();          // 電話番号
            $this->paymentResult['receiptNo']         = $objResponse->getReceiptNo();     // 受付番号
            $this->paymentResult['haraikomiUrl']      = $objResponse->getHaraikomiUrl();  // 払込URL(一部店舗のみ)
            $this->paymentResult['tradUrl']           = '';                               // 2018/05 trAdは廃止になりました
            $this->paymentResult['payStatus']         = $this->getNewPaymentStatus($paymentMethod);    // 決済状態を保存
            $this->paymentResult['serviceOptionType'] = $paymentCVS['conveni'];           // 選択したタイプ(店舗)
            $this->paymentResult['captureTotal']      = floor($order->getPaymentTotal()); // 決済金額

            $this->mdkLogger->info(print_r($this->paymentResult, true));
        // 異常終了
        } else {
            $error['payment']  = trans('vt4g_plugin.payment.shopping.error'). '<br />';
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
     * キャンセル処理
     *
     * @param  array $payload キャンセル処理に使用するデータ
     * @return array          キャンセル処理結果データ
     */
    public function operateCancel($payload)
    {
        // キャンセル共通処理
        list($operationResult, $mdkResponse) = parent::operateCancel($payload);

        // ログの出力
        $this->mdkLogger->info(print_r($operationResult, true));

        return $operationResult;
    }

    /**
     * リクエストパラメータの設定
     *
     * @param  object $objRequest        CvsAuthorizeRequestDto
     * @param  Order  $order             注文データ
     * @param  array  $paymentInfo       決済処理データ
     * @param  string $serviceOptionType 決済サービスオプション
     * @return void
     */
    public function setRequestParam(&$objRequest, $order, $paymentInfo, $serviceOptionType)
    {
        $objRequest->setOrderId($this->getMdkOrderId($order->getId()));
        $objRequest->setAmount(floor($order->getPaymentTotal()));
        $name1 = $order->getName01();
        $name2 = $order->getName02();
        $phoneNumber = $order->getPhoneNumber();
        $payLimit = $this->util->getAddDateFormat($paymentInfo['payment_term_day']);
        $paymentType = '0'; // 固定

        $isSej = $serviceOptionType == $this->vt4gConst['VT4G_CONVENI']['SEVENELEVEN']['CODE'];
        $isOther = $serviceOptionType == $this->vt4gConst['VT4G_CONVENI']['DAILYYAMAZAKI']['CODE'];
        $isFamima = $serviceOptionType == $this->vt4gConst['VT4G_CONVENI']['FAMILYMART']['CODE'];

        $free1 = !$isSej
            ? $paymentInfo['free1']
            : '';
        $free2 = ($isOther || $isFamima)
            ? $paymentInfo['free2']
            : '';

        $objRequest->setServiceOptionType($serviceOptionType);
        $objRequest->setName1(mb_substr(mb_convert_kana($name1,"NKRV"),0,10));
        $objRequest->setName2(mb_substr(mb_convert_kana($name2,"NKRV"),0,10));
        $objRequest->setTelNo($phoneNumber);
        $objRequest->setPayLimit($payLimit);
        $objRequest->setPaymentType($paymentType);
        $objRequest->setFree1($free1);
        $objRequest->setFree2($free2);
    }

    /**
     * メール内容の設定と完了画面の内容設定
     *
     * @param  Order $order         注文データ
     * @param  array $paymentResult 決済レスポンスデータ
     * @return array $mailData      メールの説明文
     */
    public function setMail($order, $paymentResult = null)
    {
        if (is_null($paymentResult)) {
            $paymentResult = $this->paymentResult;
        }

        $this->mailData = [];
        $paymentMethod = $this->util->getPaymentMethod($order->getPayment()->getId());
        $paymentName  = $this->util->getPayName($paymentMethod->getMemo03());

        $conveniConst = $this->vt4gConst['VT4G_CONVENI'];

        $arrNo = $this->translateRecpNo(
            $paymentResult['serviceOptionType'],
            $paymentResult['receiptNo'],
            $paymentResult['telNo'],
            true
        );
        // メール記載情報
        $this->setMailTitle($paymentName);
        $this->setMailInfo('お支払い先ストア', $this->util->getConveniNameByCode($paymentResult['serviceOptionType']));
        foreach ($arrNo as $key => $val) {
            $this->setMailInfo($key, $val);
        }
        // 文字列の比較
        if (strcmp($paymentResult['serviceOptionType'], $conveniConst['SEVENELEVEN']['CODE']) == 0) {
            $this->setMailInfo('払込票URL', $paymentResult['haraikomiUrl']);
        }
        $this->setMailInfo('お支払い期限' , $paymentResult['limitDate']);
        $this->setMailAd($paymentResult['vResultCode'], $paymentResult['tradUrl']); // 広告
        $this->setMailAdminSetting($paymentMethod); // 決済案内タイトル/本文
        $this->setMailInfo('', $this->getExplain($paymentResult['serviceOptionType'])); // 説明取得

        return $this->mailData;
    }

    /**
     * ログ出力内容
     *
     * @param \Eccube\Entity\Order $order
     * @return array
     */
    public function setLog($order)
    {
        $Vt4gPayment = $this->em->getRepository(Vt4gPaymentMethod::class);
        $paymentMethod = $Vt4gPayment->find($order->getPayment()->getId());

        $arrNo = $this->translateRecpNo(
            $this->paymentResult['serviceOptionType'],
            $this->paymentResult['receiptNo'],
            $this->paymentResult['telNo'],
            true
        );
        $this->setLogHead($paymentMethod->getMemo03());
        $this->setLogInfo('店舗', $this->util->getConveniNameByCode($this->paymentResult['serviceOptionType']));
        // コンビニ受付番号等
        foreach ($arrNo as $key => $val){
            $this->setLogInfo($key, $val);
        }
        $this->setLogInfo('支払期限', $this->paymentResult['limitDate']);

        return $this->logData;
    }

    /**
     * コンビニ決済受付番号から店舗別の項目を生成
     *
     * @access protected
     * @param string $optionType サービスオプションタイプ
     * @param string $receiptNo  受付番号
     * @param string $telNo      電話番号(ローソン・セイコーマート用)
     * @param boolean $isArray  true:返値を文字列 false:配列
     * @return mixed 結果
     */
    public function translateRecpNo($optionType, $receiptNo, $telNo, $isArray = false)
    {
        $arrReturn = array();
        $conveniConst = $this->vt4gConst['VT4G_CONVENI'];

        switch ($optionType) {
            case $conveniConst['SEVENELEVEN']['CODE']:                 // セブンイレブン
                $arrReturn['払込票番号'] = $receiptNo;
                break;
            case $conveniConst['LAWSON']['CODE']:                      // ローソン
            case $conveniConst['FAMILYMART']['CODE']:                  // ファミリーマート
            case $conveniConst['ECON']['CODE']:                        // ローソン・ファミリーマート・ミニストップ・セイコーマート
                $arrReturn['受付番号'] = $receiptNo;
                $arrReturn['お客様番号(お申込み時電話番号)'] = $telNo;
                break;
            case $conveniConst['DAILYYAMAZAKI']['CODE']:               // デイリーヤマザキ
                $arrReturn['オンライン決済番号'] = $receiptNo;
                break;
            default :
                break;
        }

        if ($isArray == false) {
            $return = '';
            foreach ($arrReturn as $k => $v) {
                $return .= sprintf(' %s[%s]', $k, $v);
            }
            return $return;
        }
        return $arrReturn;
    }
}
