<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Payment;

use Plugin\VeriTrans4G\Form\Type\Shopping\PaymentATMType;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;
use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;

class BankService extends BaseService
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
    public function bankCommit($order, $formData, $paymentInfo, &$error)
    {
        // ベリトランスとの通信
        $this->connectVT4G($order, $formData, $paymentInfo, $error);
        // 決済情報 (memo05)
        $payment = $this->paymentResult;
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
     * 入力フォームを生成
     *
     * @return object 情報入力フォーム
     */
    public function createBankForm()
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
    function connectVT4G($order, $formData, $paymentInfo, &$error)
    {
        $this->mdkLogger->info(
            sprintf(
                trans('vt4g_plugin.payment.shopping.mdk.start'),
                $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_30']
            )
        );

        $objRequest = new \BankAuthorizeRequestDto();
        $this->setRequestParam($objRequest, $order, $paymentInfo, $formData);
        $amount = $objRequest->getAmount();
        $objRequest->setAmount(floor($amount));
        // URL設定
        $redirectionUri = $this->util->generateUrl(
            'vt4g_shopping_payment_complete',
            ['no' => $order->getId()]
        );
        $objRequest->setTermUrl($redirectionUri);

        $objTransaction = new \TGMDK_Transaction();
        $objResponse = $objTransaction->execute($objRequest);

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
            $this->paymentResult['orderId']           = $objResponse->getOrderId();         // 取引ID取得
            $this->paymentResult['shunoKikanNo']      = $objResponse->getShunoKikanNo();    // 収納機関番号
            $this->paymentResult['customerNo']        = $objResponse->getCustomerNo();      // お客様番号
            $this->paymentResult['confirmNo']         = $objResponse->getConfirmNo();       // 確認番号
            $this->paymentResult['limitDate']         = $objRequest->getPayLimit();         // 有効期限
            $this->paymentResult['billPattern']       = $objResponse->getBillPattern();     // 支払パターン
            $this->paymentResult['bill']              = $objResponse->getBill();            // 支払暗号文字列
            $this->paymentResult['url']               = $objResponse->getUrl();             // URL
            $this->paymentResult['view']              = $objResponse->getView();            // 画面情報
            $this->paymentResult['setTermUrl']        = $objRequest->getTermUrl();          // 決済状態を保存
            $this->paymentResult['payStatus']         = $this->getNewPaymentStatus($paymentMethod); // 決済状態を保存
            $this->paymentResult['captureTotal']      = floor($order->getPaymentTotal());   // 決済金額

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
     * リクエストパラメータの設定
     *
     * @param object $objRequest   BankAuthorizeRequestDto
     * @param \Eccube\Entity\Order $order
     * @param array  $paymentInfo  決済処理データ
     */
    public function setRequestParam(&$objRequest, $order, $paymentInfo)
    {
        $objRequest->setOrderId($this->getMdkOrderId($order->getId()));
        $objRequest->setAmount($order->getPaymentTotal());

        $serviceOptionType = $this->vt4gConst['VT4G_SERVICE_PAYTYPEID_30'];
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
     * ネットバンク決済にて、注文/決済ステータスを新規受付、申込に変更する。
     *
     * @param \Eccube\Entity\Order $order 注文データ
     * @param object $orderPayment        決済データ
     */
    public function changePayStatus($order, $orderPayment)
    {
        //更新前のステータス
        $nowOrderStatus = $order->getOrderStatus()->getId();
        $nowPayStatus = $orderPayment->getMemo04();

        $updatedOrderStatus = true;

        //更新前の注文ステータス = 決済処理中、決済ステータス = 空白の時のみステータス更新を行う。
        if($nowOrderStatus === OrderStatus::PENDING && $nowPayStatus === ''){
            
            try {
                //注文ステータスを新規受付に更新
                $orderStatus = $this->em->getRepository(OrderStatus::class)->find(OrderStatus::NEW);
                if (!empty($orderStatus)) {
                    $this->em->getRepository(Order::class)->changeStatus($order->getId(), $orderStatus);
                } else {
                    $this->mdkLogger->error(sprintf(trans('vt4g_plugin.shopping.bank.error.status_update'), $order->getId(), OrderStatus::NEW));
                    $updatedOrderStatus = false;
                }

                //決済ステータスを申込に更新
                $orderPayment->setMemo04($this->vt4gConst['VT4G_PAY_STATUS']['REQUEST']['VALUE']);

                $this->em->persist($orderPayment);
                $this->em->flush();
                $this->em->commit();

                //注文ステータス更新の成否に応じて、infoメッセージを切り替える。
                if(!$updatedOrderStatus){
                    $this->mdkLogger->info(sprintf(trans('vt4g_plugin.shopping.bank.payment.part.complete'), $order->getId()));
                } else {
                    $this->mdkLogger->info(sprintf(trans('vt4g_plugin.shopping.bank.payment.complete'), $order->getId()));
                }

            } catch (\Doctrine\DBAL\DBALException $e) {
                //キャッチした場合はエラー内容をmdk.logに表示する。
                $this->mdkLogger->error($e->getMessage());
            }
        }
    }

    /**
     * メール内容の設定と完了画面の内容設定
     *
     * @param  \Eccube\Entity\Order $order    注文データ
     * @return array $mailData メール情報
     */
    public function setMail($order)
    {
        $this->mailData = array();
        $paymentMethod = $this->util->getPaymentMethod($order->getPayment()->getId());
        $paymentName  = $this->util->getPayName($paymentMethod->getMemo03());

        // メール記載情報
        $this->setMailTitle($paymentName);
        $this->setMailAdminSetting($paymentMethod);
        if ($this->shouldSendMail($order)) {
            $this->setMailInfo('支払期限', $this->util->toDate($this->paymentResult['limitDate']));
            $this->setMailInfo('金融機関選択URL', $this->paymentResult['url']);
            $this->setMailInfo('注1', trans('vt4g_plugin.payment.mail.expla.bank.note1'));
            $this->setMailInfo('注2', trans('vt4g_plugin.payment.mail.expla.bank.note2'));
        }

        return $this->mailData;
    }

    /**
     * ログ出力内容
     *
     * @param  \Eccube\Entity\Order  $order  注文データ
     * @return array  $logData
     */
    public function setLog($order)
    {
        $vt4gPayment = $this->em->getRepository(Vt4gPaymentMethod::class);
        $paymentMethod = $vt4gPayment->find($order->getPayment()->getId());
        //決済ステータスは空白で登録しているが、ログには申込と表示するためにpayStatusに申込の値を代入する。
        $this->paymentResult['payStatus'] = $this->vt4gConst['VT4G_PAY_STATUS']['REQUEST']['VALUE'];
        $this->setLogHead($paymentMethod->getMemo03());
        if ($this->shouldSendMail($order)) {
            $this->setLogInfo('支払期限', $this->util->toDate($this->paymentResult['limitDate']));
            $this->setLogInfo('金融機関選択URL', $this->paymentResult['url']);
            $this->setLogInfo('注1', trans('vt4g_plugin.payment.mail.expla.bank.note1'));
            $this->setLogInfo('注2', trans('vt4g_plugin.payment.mail.expla.bank.note2'));
        }

        return $this->logData;
    }
}
