<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Payment;

use Eccube\Entity\BaseInfo;
use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Cart;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Plugin\VeriTrans4G\Entity\Vt4gOrderPayment;
use Plugin\VeriTrans4G\Entity\Vt4gSubscOrder;
use Plugin\VeriTrans4G\Entity\Vt4gSubscOrderItem;
use Plugin\VeriTrans4G\Entity\Master\Vt4gSubscSaleType;

/**
 * 結果(入金)通知受信処理クラス
 */
class PaymentRecvService extends BaseService
{
    /**
     * 速報確報フラグ
     */
    private $fixed;

    /**
     * プラグイン設定情報
     */
    private $pluginSetting;

    /**
     * ネットバンク決済 注文完了メール送信タイミング
     */
    private $bankOrderMailSetting = null;

    /**
     * 楽天ペイ 要求結果メール送信対象
     */
    private $rakutenMailSetting = null;

    /**
     * 楽天ペイ 要求結果メールメッセージ
     */
    public $rakutenMailMsg;

    /**
     * 結果通知エラー報知メールメッセージ
     */
    public $errorMailMsg;

    /**
     * 結果通知前に想定されるorderstatusの値
     */
    const BEFORE_PUSH_RESULT_ORDER_STATUS = [
        'id' => [
            OrderStatus::NEW,
            OrderStatus::PENDING
        ]
    ];

    /**
     * ヘッダーのチェックを行います。
     *
     * @param array $requestHeaders リクエストヘッダ情報の配列
     * @return boolean true:チェックOK false:チェックNG
     */
    public function checkHeader($requestHeaders)
    {
        // ベリトランスペイメントゲートウェイからの通知電文を取得
        $headers = [];
        foreach ($requestHeaders as $header => $value) {
            $headers[$header] = $value[0];
            $this->mdkLogger->debug("$header: $value[0]");
        }

        $headers = array_change_key_case($headers,CASE_LOWER);

        if(!array_key_exists('content-length', $headers) || $headers['content-length'] <= 0){
            // 読み込めないので 500 を応答
            $this->mdkLogger->error(trans('vt4g_plugin.payment.recv.not.exist.length'));
            return false;
        }

        $body = "";
        $fp = fopen("php://input", "r");
        if ($fp == false) {
            $this->mdkLogger->error(trans('vt4g_plugin.payment.recv.recv.failed'));
            // 読み込めないので 500 を応答
            return false;
        }

        while (!feof($fp)) {
            $body .= fgets($fp);
        }
        fclose($fp);
        $this->mdkLogger->debug("Body: $body");

        // Content-HMAC を利用して電文の改竄チェックを行う
        $hmac = array_key_exists('content-hmac', $headers) ? $headers['content-hmac'] : "";
        if (strlen($hmac) <= 0) {
            // Content-HMACがない 500 を応答
            $this->mdkLogger->error(trans('vt4g_plugin.payment.recv.not.exist.hmac'));
            return false;
        }

        if (!\TGMDK_MerchantUtility::checkMessage($body, $hmac)) {
            $this->mdkLogger->error(trans('vt4g_plugin.payment.recv.valid.failed'));
            // 改竄の疑いあり 500 を応答
            return false;
        }

        $this->mdkLogger->info(trans('vt4g_plugin.payment.recv.valid.success'));
        return true;
    }

    /**
     * 結果(入金)通知データを取得します。
     *
     * @param array $arrPost
     * @return array
     */
    public function getRecord($arrPost)
    {
        $arrRecord = [];
        $arrHead   = [];

        // ヘッダー情報の取得
        foreach ($this->vt4gConst['VT4G_RECV_RECODE_HEAD'] as $val){
            if (array_key_exists($val, $arrPost)){
                $arrHead[$val] = $arrPost[$val];
            }else{
                // ヘッダー情報なし
                return [[],[]];
            }
        }

        $this->fixed = array_key_exists('fixed', $arrPost) ? $arrPost['fixed'] : null;

        // 明細情報
        foreach ($arrPost as $key => $val) {
            // 連番がついている項目のフィールド名と連番を取得する
            // [ orderId0000=>123, total0000=>10 ]、[ orderId0001=>456 total0001=>20 ] という形で受信するので、
            // [ 0 => [orderId => 123, total=>10 ], 1 => [orderId => 456, total=>20 ] ] という形にする。
            $match = [];
            if (preg_match('/^([^0-9]+)([0-9]{4})$/', $key, $match) == false) {
                continue;
            }

            $arrRecord[(int)$match[2]][$match[1]] = $val;
        }

        return [$arrHead, $arrRecord];
    }

    /**
     * 結果(入金)通知情報の反映を行います。
     * @param array $arrRecord
     */
    public function saveRecvData($arrRecord)
    {
        $this->pluginSetting = $this->util->getPluginSetting();

        foreach ($arrRecord as $key => $record){

            $count = $key + 1;
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.data.count'),$count) . print_r($record, true));

            // 結果通知の項目から決済内部IDを取得する
            $payTypeId = $this->getPayTypeId($record);

            // 注文情報とそれに紐付く決済情報を取得する
            $orderPayment = $this->em->getRepository(Vt4gOrderPayment::class)->findOneBy(['memo01'=> $record['orderId']]);
            if (empty($orderPayment)) {
                $order = '';
            } else {
                $order = $this->em->getRepository(Order::class)->find($orderPayment->getOrderId());
            }

            // どちらかとれない場合はエラーメール対象。
            // 申込結果の失敗通知ならメールは出さない。
            if (empty($order) || empty($orderPayment)) {

                if ($this->checkAuthFailure($payTypeId, $record)) {
                    $this->mdkLogger->info(trans('vt4g_plugin.payment.recv.order.auth.failure'));
                } else {
                    $this->mdkLogger->warn(trans('vt4g_plugin.payment.recv.order.not.exist'));
                    $this->errorMailMsg[] = $this->makeErrorPushResult($record,trans('vt4g_plugin.payment.recv.order.not.exist'),'');
                }

                continue;
            }

            if (empty($payTypeId)) {
                $this->mdkLogger->warn(trans('vt4g_plugin.payment.recv.order.not.payment'));
                continue;
            }

            // 決済ごとの通知受信処理
            $method = "exec{$this->vt4gConst['VT4G_CODE_PAYTYPEID_'.$payTypeId]}RecvProcess";
            $error = $this->$method($order, $orderPayment, $record, $payTypeId);
            if (!empty($error)) {
                $this->errorMailMsg[] = $error;
            }
        }
    }


    /**
     * 結果通知の項目から決済内部IDを取得します。
     *
     * @param  array   $record 結果通知の項目
     * @return string          決済内部ID 一致するものがない場合は空文字
     */
    private function getPayTypeId($record)
    {
        foreach ($this->vt4gConst['VT4G_RECV_RECODE_BODY'] as $key => $params) {
            if ($this->arrayKeysExists($params, $record)) {
                return $this->vt4gConst["$key"];
            }
        }

        return '';
    }

    /**
     * array_key_existsの配列版
     *
     * @param array $arrFormat
     * @param array $arrRecord
     * @return boolean
     */
    private function arrayKeysExists($arrFormat, $arrRecord)
    {
        foreach ($arrFormat as $key){
            if (!array_key_exists($key, $arrRecord)){
                return false;
            }
        }
        return true;
    }

    /**
     * 決済申込の失敗結果通知であるかを調べます。
     * @param  string $payTypeId 決済方法内部ID
     * @param  array  $record    結果通知の項目
     * @return bool   $isValid   決済申込の失敗結果通知ならtrue、異なるならfalse
     */
    private function checkAuthFailure($payTypeId, $record)
    {
        $isValid = false;

        switch ($payTypeId) {
            case $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']:

                if (   $record['mpiMstatus']  != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']
                    || $record['cardMstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']){

                    $isValid = true;
                }
                break;

            case $this->vt4gConst['VT4G_PAYTYPEID_UPOP']:
            case $this->vt4gConst['VT4G_PAYTYPEID_ALIPAY']:

                if (   $record['txnType'] == $this->vt4gConst['VT4G_RECV_TXN_TYPE_AUTH']
                    && $record['mstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {

                    $isValid = true;
                }
                break;

            case $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL']:

                if (   $record['txnType'] == $this->vt4gConst['VT4G_RECV_TXN_TYPE_CAPTURE']
                    && $this->fixed       == $this->vt4gConst['VT4G_RECV_PAYPAL_FIXED']
                    && $record['mstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK'] ) {

                    $isValid = true;
                }
                break;

            default:
                break;
        }

        return $isValid;
    }

    /**
     * 本人認証の結果通知処理
     * @param Order            $order        注文情報
     * @param Vt4gOrderPayment $orderPayment 決済情報
     * @param array            $record       結果通知の項目
     * @param string           $payTypeId    決済方法内部ID
     */
    private function execCreditRecvProcess($order, $orderPayment, $record, $payTypeId)
    {
        // 本人認証はトランザクションタイプが本人認証結果(3Dセキュア2.0を含む)の場合、処理する
        if (!in_array($record['txnType'], $this->vt4gConst['VT4G_RECV_TXN_TYPE_CREDIT'])) {
            $this->mdkLogger->warn(sprintf(trans('vt4g_plugin.payment.recv.not.txn_type'),$record['txnType']));
            return '';
        }

        $shouldSendMail = false;
        $record['sentOrderMail'] = false;

        $payName = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_12'];

        // 申込時の処理区分から与信/売上の通知区分を判定
        $withCapture  = $this->getWithCapture($order);
        $recvType     = $withCapture === 1
                      ? $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE'] // 売上結果
                      : $this->vt4gConst['VT4G_PAY_STATUS']['AUTH'];   // 申込結果
        $newPayStatus = $recvType['VALUE'];

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.start'),$recvType['RECV_NAME'],$payName));

        // 結果通知の処理結果
        // MPI 単体サービスの通知はcardMstatusが空ですが、MPI 単体サービスは設定できないので考慮しません
        if (   $record['mpiMstatus']  != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']
            || $record['cardMstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {

            $status = 'mpiMstatus:'.$record['mpiMstatus'].' cardMstatus:'.$record['cardMstatus'];
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.mstatus.error'),$recvType['RECV_NAME'],$status));
            // 結果通知項目の登録
            $this->registPushRecord($orderPayment, $record);

        } else {
            if ($this->pluginSetting['order_mail_timing_flg'] == $this->vt4gConst['VT4G_MAIL_TIMING']['ORDER']['ON_PAYMENT']
                && !$this->hasSentOrderMail($orderPayment)) {

                $shouldSendMail = true;
                $record['sentOrderMail'] = true;
            }

            $this->clearCartByOrder($order);
            $this->updateMpiPushResult($order, $orderPayment, $record, $newPayStatus, $withCapture);
            // 継続課金注文の登録
            $this->saveSubscOrder($order);
            if ($shouldSendMail) {
                $this->sendOrderMail($order);
            }
        }

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.end'),$recvType['RECV_NAME'],$payName));

        return '';
    }

    /**
     * コンビニ決済の結果通知処理
     * @param Order            $order        注文情報
     * @param Vt4gOrderPayment $orderPayment 決済情報
     * @param array            $record       結果通知の項目
     * @param string           $payTypeId    決済方法内部ID
     */
    private function execCVSRecvProcess($order, $orderPayment, $record, $payTypeId)
    {
        $error = '';
        $payName = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payTypeId];

        $recvType       = $this->vt4gConst['VT4G_PAY_STATUS']['DEPOSIT']; // 入金結果
        $newPayStatus   = $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE'];
        $newOrderStatus = OrderStatus::PAID;
        $payStatuses    = [ $this->vt4gConst['VT4G_PAY_STATUS']['REQUEST']['VALUE'] ];

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.start'),$recvType['RECV_NAME'],$payName));

        // 決済ログ作成
        $this->setLogHeadPushResult($record, $payTypeId, $recvType);
        $this->setLogInfo('お支払店舗' , $this->getPayConveni($record['cvsType']));
        $this->setLogInfo('受付番号'   , $record['receiptNo']);
        $this->setLogInfo('受付日付'   , $this->util->toDate($record['receiptDate']));
        $this->setLogInfo('入金金額'   , preg_match('/^[0-9]+$/', $record['rcvAmount']) ? number_format($record['rcvAmount']) : $record['rcvAmount']);

        // 現在の支払方法検証
        $error .= $this->checkPaymentTypeIDPushResult($order, $orderPayment, $newPayStatus, $newOrderStatus);

        // 決済ステータス検証
        $error .= $this->checkPaymentStatusPushResult($orderPayment, $payStatuses, $newPayStatus, $newOrderStatus);

        // 注文ステータス検証
        $error .= $this->checkOrderStatusPushResult($order, self::BEFORE_PUSH_RESULT_ORDER_STATUS, $newOrderStatus);

        $this->updatePushResult($order, $orderPayment, $newPayStatus, $newOrderStatus, $record);

        if (!empty($error)) {
            $error = $this->makeErrorPushResult($record, $error, $order->getId());
        }

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.end'),$recvType['RECV_NAME'],$payName));

        return $error;
    }

    /**
     * ネットバンク・ATM決済の結果通知処理
     * @param Order            $order        注文情報
     * @param Vt4gOrderPayment $orderPayment 決済情報
     * @param array            $record       結果通知の項目
     * @param string           $payTypeId    決済方法内部ID
     */
    private function execBankRecvProcess($order, $orderPayment, $record, $payTypeId)
    {
        $error = '';
        $shouldSendMail = false;

        // ネットバンクとATMの通知は同じ項目なのでテーブルデータで判定
        if ($orderPayment->getMemo03() == $this->vt4gConst['VT4G_PAYTYPEID_ATM']) {
            $payTypeId = $this->vt4gConst['VT4G_PAYTYPEID_ATM'];
        } else {
            // テーブルデータもネットバンクなら注文完了メール送信の判定
            if (is_null($this->bankOrderMailSetting)) {
                $paymentInfo = $this->util->getPaymentMethodInfoByPayId($payTypeId);
                $this->bankOrderMailSetting = $paymentInfo['mailTiming'];
            }

            $shouldSendMail = $this->bankOrderMailSetting == $this->vt4gConst['VT4G_MAIL_TIMING']['BANK']['ON_RECEIVE'];
        }

        $payName = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payTypeId];

        $recvType       = $this->vt4gConst['VT4G_PAY_STATUS']['DEPOSIT']; // 入金結果
        $newPayStatus   = $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE'];
        $newOrderStatus = OrderStatus::PAID;
        $payStatuses    = [ '', $this->vt4gConst['VT4G_PAY_STATUS']['REQUEST']['VALUE'] ];

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.start'),$recvType['RECV_NAME'],$payName));

        // 決済ログ作成
        $this->setLogHeadPushResult($record, $payTypeId, $recvType);

        $this->setLogInfo('収納機関コード' , $record['kikanNo']);
        $this->setLogInfo('収納企業コード' , $record['kigyoNo']);
        $this->setLogInfo('収納日時'       , $this->util->toDate($record['rcvDate']));
        $this->setLogInfo('お客様番号'     , $record['customerNo']);
        $this->setLogInfo('確認番号'       , $record['confNo']);
        $this->setLogInfo('入金金額'       , preg_match('/^[0-9]+$/', $record['rcvAmount']) ? number_format($record['rcvAmount']) : $record['rcvAmount']);

        // 現在の支払方法検証
        $error .= $this->checkPaymentTypeIDPushResult($order, $orderPayment, $newPayStatus, $newOrderStatus);

        // 決済ステータス検証
        $error .= $this->checkPaymentStatusPushResult($orderPayment, $payStatuses, $newPayStatus, $newOrderStatus);

        // 注文ステータス検証
        $error .= $this->checkOrderStatusPushResult($order, self::BEFORE_PUSH_RESULT_ORDER_STATUS, $newOrderStatus);
        if (!empty($error)) {
            $error = $this->makeErrorPushResult($record, $error, $order->getId());
        } elseif ($shouldSendMail) {
            $this->sendOrderMail($order);
        }

        $this->updatePushResult($order, $orderPayment, $newPayStatus, $newOrderStatus, $record);

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.end'),$recvType['RECV_NAME'],$payName));

        return $error;
    }

    /**
     * 銀聯ネット決済の結果通知処理
     * @param Order            $order        注文情報
     * @param Vt4gOrderPayment $orderPayment 決済情報
     * @param array            $record       結果通知の項目
     * @param string           $payTypeId    決済方法内部ID
     */
    private function execUPOPRecvProcess($order, $orderPayment, $record, $payTypeId)
    {
        $error = '';
        $payName = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payTypeId];
        $shouldSendMail = false;
        $record['sentOrderMail'] = false;
        $payStatuses = [];

        switch ($record['txnType']) {
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_AUTH']:
                // 申込時の処理区分から与信/売上の通知区分を判定
                $withCapture    = $this->getWithCapture($order);
                $recvType       = $withCapture === 1
                                ? $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE'] // 売上結果
                                : $this->vt4gConst['VT4G_PAY_STATUS']['AUTH'];   // 申込結果
                $newPayStatus   = $recvType['VALUE'];
                $newOrderStatus = $withCapture === 1 ? OrderStatus::PAID : OrderStatus::NEW;
                $payStatuses    = [ '', $recvType['VALUE'] ];

                if ($this->pluginSetting['order_mail_timing_flg'] == $this->vt4gConst['VT4G_MAIL_TIMING']['ORDER']['ON_PAYMENT']
                    && !$this->hasSentOrderMail($orderPayment)) {

                    $shouldSendMail = true;
                }

                break;
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_CAPTURE']:
                $recvType       = $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']; // 売上結果
                $newPayStatus   = '';
                $newOrderStatus = '';
                break;
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_REFUND']:
                // 返金の場合、現在の決済ステータスで判別する
                $recvType       = $this->getRefundRecvSetting($orderPayment);
                $newPayStatus   = '';
                $newOrderStatus = '';
                break;
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_CANCEL']:
                $recvType       = $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL'];  // 取消結果
                $newPayStatus   = '';
                $newOrderStatus = '';
                break;
            default:
                $this->mdkLogger->warn(sprintf(trans('vt4g_plugin.payment.recv.not.txn_type'),$record['txnType']));
                return '';
        }

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.start'),$recvType['RECV_NAME'],$payName));

        // 決済ログ作成
        $this->setLogHeadPushResult($record, $payTypeId, $recvType);
        $this->setLogInfo('システム追跡番号' , $record['traceNumber']);
        $this->setLogInfo('認証サービス日時' , $this->util->toDateMMDD($record['traceTime']) . '(中国時間)');
        $this->setLogInfo('精算金額'         , preg_match('/^[0-9]+$/', $record['settleAmount']) ? number_format($record['settleAmount']) : $record['settleAmount']);
        $this->setLogInfo('精算日付'         , $this->util->toDateMMDD($record['settleDate']));
        if (!(empty($record['settleRate']) || $record['settleRate'] == 'null')){
            $this->setLogInfo('精算レート'   , $record['settleRate']);
        }

        if ($record['mstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.mstatus.error'),$recvType['RECV_NAME'],$record['mstatus']));
            $newPayStatus   = '';
            $newOrderStatus = '';
            $shouldSendMail = false;
        }

        // 現在の支払方法検証
        $error .= $this->checkPaymentTypeIDPushResult($order, $orderPayment, $newPayStatus, $newOrderStatus);

        // 決済ステータス検証
        $error .= $this->checkPaymentStatusPushResult($orderPayment, $payStatuses, $newPayStatus, $newOrderStatus);

        // 過去の受信情報と比較
        $memo09 = $orderPayment->getmemo09();
        if (!empty($memo09)) {
            $oldRecords = unserialize($memo09);

            foreach ($oldRecords as $oldRecord ) {

                if (   $oldRecord['txnType']     == $record['txnType']
                    && $oldRecord['traceNumber'] == $record['traceNumber'] ) {

                    $diff = array_diff(
                        [
                            $oldRecord['mstatus'],
                            $oldRecord['traceTime'],
                            $oldRecord['settleAmount'],
                            $oldRecord['settleDate'],
                            $oldRecord['settleRate'],
                        ],
                        [
                            $record['mstatus'],
                            $record['traceTime'],
                            $record['settleAmount'],
                            $record['settleDate'],
                            $record['settleRate'],
                        ]);

                    if (!empty($diff)) {
                        $error .= $this->makeErrorMsg(trans('vt4g_plugin.payment.recv.result.different'));
                        $newPayStatus   = '';
                        $newOrderStatus = '';
                        break;
                    }
                }
            }

        }

        if (!empty($error)) {
            $error = $this->makeErrorPushResult($record, $error, $order->getId());
        } else {
            if (   $record['txnType'] == $this->vt4gConst['VT4G_RECV_TXN_TYPE_AUTH']
                && $record['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {

                $this->clearCartByOrder($order);
            }

            if ($shouldSendMail) {
                $this->sendOrderMail($order);
                $record['sentOrderMail'] = true;
            }
        }

        $this->updatePushResult($order, $orderPayment, $newPayStatus, $newOrderStatus, $record);
        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.end'),$recvType['RECV_NAME'],$payName));

        return $error;
    }

    /**
     * Alipayの結果通知処理
     * @param Order            $order        注文情報
     * @param Vt4gOrderPayment $orderPayment 決済情報
     * @param array            $record       結果通知の項目
     * @param string           $payTypeId    決済方法内部ID
     */
    private function execAlipayRecvProcess($order, $orderPayment, $record, $payTypeId)
    {
        $error = '';
        $payName = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payTypeId];
        $shouldSendMail = false;
        $record['sentOrderMail'] = false;
        $payStatuses = [];

        switch ($record['txnType']) {
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_AUTH']:
                // Alipayは与信同時売上のみ
                $recvType       = $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']; // 売上結果
                $newPayStatus   = $recvType['VALUE'];
                $newOrderStatus = OrderStatus::PAID;
                $payStatuses    = [ '', $recvType['VALUE'] ];

                if ($this->pluginSetting['order_mail_timing_flg'] == $this->vt4gConst['VT4G_MAIL_TIMING']['ORDER']['ON_PAYMENT']
                    && !$this->hasSentOrderMail($orderPayment)) {

                    $shouldSendMail = true;
                }

                break;
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_REFUND']:
                // 返金の場合、現在の決済ステータスで判別する
                $recvType       = $this->getRefundRecvSetting($orderPayment);
                $newPayStatus   = '';
                $newOrderStatus = '';
                break;
            default:
                $this->mdkLogger->warn(sprintf(trans('vt4g_plugin.payment.recv.not.txn_type'),$record['txnType']));
                return '';
        }

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.start'),$recvType['RECV_NAME'],$payName));

        // 決済ログ作成
        $this->setLogHeadPushResult($record, $payTypeId, $recvType);
        $this->setLogInfo('決済センターとの取引ID', $record['centerTradeId']);

        if ($record['mstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.mstatus.error'),$recvType['RECV_NAME'],$record['mstatus']));
            $newPayStatus   = '';
            $newOrderStatus = '';
            $shouldSendMail = false;
        }

        // 現在の支払方法検証
        $error .= $this->checkPaymentTypeIDPushResult($order, $orderPayment, $newPayStatus, $newOrderStatus);

        // 決済ステータス検証
        $error .= $this->checkPaymentStatusPushResult($orderPayment, $payStatuses, $newPayStatus, $newOrderStatus);

        // 過去の受信情報と比較
        $memo09 = $orderPayment->getmemo09();
        if (!empty($memo09)) {
            $oldRecords = unserialize($memo09);

            foreach ($oldRecords as $oldRecord ) {

                if (   $oldRecord['txnType']       == $record['txnType']
                    && $oldRecord['centerTradeId'] == $record['centerTradeId']
                    && $oldRecord['mstatus']       != $record['mstatus']) {

                    $error .= $this->makeErrorMsg(trans('vt4g_plugin.payment.recv.result.different'));
                    $newPayStatus   = '';
                    $newOrderStatus = '';
                    break;
                }
            }
        }

        if (!empty($error)) {
            $error = $this->makeErrorPushResult($record, $error, $order->getId());
        } else {

            if (   $record['txnType'] == $this->vt4gConst['VT4G_RECV_TXN_TYPE_AUTH']
                && $record['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {

                $this->clearCartByOrder($order);
            }

            if ($shouldSendMail) {
                $this->sendOrderMail($order);
                $record['sentOrderMail'] = true;
            }
        }

        $this->updatePushResult($order, $orderPayment, $newPayStatus, $newOrderStatus, $record);

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.end'),$recvType['RECV_NAME'],$payName));

        return $error;
    }

    /**
     * 楽天ペイの結果通知処理
     * @param Order            $order        注文情報
     * @param Vt4gOrderPayment $orderPayment 決済情報
     * @param array            $record       結果通知の項目
     * @param string           $payTypeId    決済方法内部ID
     */
    private function execRakutenRecvProcess($order, $orderPayment, $record, $payTypeId)
    {
        $error = '';
        $payName = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payTypeId];
        $shouldSendMail  = false;
        $record['sentOrderMail'] = false;
        $isRequest = false;

        switch ($record['txnType']) {
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_AUTH']:
                // 申込時の処理区分から与信/売上の通知区分を判定
                $withCapture    = $this->getWithCapture($order);
                $recvType       = $withCapture === 1
                                ? $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE'] // 売上結果
                                : $this->vt4gConst['VT4G_PAY_STATUS']['AUTH'];   // 申込結果
                $newPayStatus   = $recvType['VALUE'];
                $newOrderStatus = $withCapture === 1 ? OrderStatus::PAID : OrderStatus::NEW;
                $payStatuses    = [ '', $recvType['VALUE'] ];

                if ($this->pluginSetting['order_mail_timing_flg'] == $this->vt4gConst['VT4G_MAIL_TIMING']['ORDER']['ON_PAYMENT']
                    && !$this->hasSentOrderMail($orderPayment)) {

                    $shouldSendMail = true;
                }

                break;
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_CAPTURE']:
                $recvType       = $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']; // 売上結果
                $newPayStatus   = $recvType['VALUE'];
                $newOrderStatus = $order->getOrderStatus()->getId() === OrderStatus::NEW ? OrderStatus::PAID : '';
                $payStatuses    = [ $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE_REQUEST']['VALUE'] ];
                $isRequest      = true;
                break;
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_CANCEL']:
                // 取消の場合、現在の決済ステータスで判別する
                $recvType       = $this->getRefundRecvSetting($orderPayment);
                $newPayStatus   = $recvType['VALUE'] == $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']['VALUE']
                                ? $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']
                                : $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL']['VALUE'];
                $newOrderStatus = '';
                $payStatuses    = $recvType['VALUE'] == $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']['VALUE']
                                ? [ $this->vt4gConst['VT4G_PAY_STATUS']['REDUCTION_REQUEST']['VALUE'] ]
                                : [ $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL_REQUEST']['VALUE'] ];
                $isRequest      = true;
                break;
            default:
                $this->mdkLogger->warn(sprintf(trans('vt4g_plugin.payment.recv.not.txn_type'),$record['txnType']));
                return '';
        }

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.start'),$recvType['RECV_NAME'],$payName));

        // 決済ログ作成
        $this->setLogHeadPushResult($record, $payTypeId, $recvType);
        $this->setLogInfo('処理日時'                   , $this->util->toDate($record['txnTime']));
        $this->setLogInfo('楽天スーパーポイント利用額' , preg_match('/^[0-9]+$/', $record['usedPoint']) ? number_format($record['usedPoint']) : $record['usedPoint']);
        if (array_key_exists('rakutenOrderId', $record)) {
            $this->setLogInfo('楽天取引ID' , $record['rakutenOrderId']);
        }
        if (array_key_exists('balance', $record)) {
            $this->setLogInfo('残高' , preg_match('/^[0-9]+$/', $record['balance']) ? number_format($record['balance']) : $record['balance']);
        }
        if (array_key_exists('rakutenApiErrorCode', $record)) {
            $this->setLogInfo('楽天API エラーコード' , $record['rakutenApiErrorCode']);
        }
        if (array_key_exists('rakutenOrderErrorCode', $record)) {
            $this->setLogInfo('楽天取引エラーコード' , $record['rakutenOrderErrorCode']);
        }

        if ($record['mstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.mstatus.error'),$recvType['RECV_NAME'],$record['mstatus']));
            $newPayStatus   = '';
            $newOrderStatus = '';
            $shouldSendMail = false;
        }

        // 現在の支払方法検証
        $error .= $this->checkPaymentTypeIDPushResult($order, $orderPayment, $newPayStatus, $newOrderStatus);

        // 決済ステータス検証
        $error .= $this->checkPaymentStatusPushResult($orderPayment, $payStatuses, $newPayStatus, $newOrderStatus);

        if (!empty($error)) {
            $error = $this->makeErrorPushResult($record, $error, $order->getId());
        } else {

            // 売上と取消の結果通知の場合はメール本文の作成と処理結果コードをチェック
            if ($isRequest) {
                if (is_null($this->rakutenMailSetting)) {
                    $memo05 = $this->util->getPaymentMethodInfoByPayId($payTypeId);
                    if (array_key_exists('result_mail_target',$memo05)) {
                        $this->rakutenMailSetting = $memo05['result_mail_target'];
                    } else {
                        // 未登録の場合は設定画面の初期値と同じ値とする
                        $this->rakutenMailSetting = $this->vt4gConst['VT4G_MAIL_TARGET']['RAKUTEN']['FAILURE'];
                    }
                }

                // 要求結果メールのメッセージを設定
                if ($this->rakutenMailSetting == $this->vt4gConst['VT4G_MAIL_TARGET']['RAKUTEN']['ALL']) {
                    $this->rakutenMailMsg[] .= $this->makeRakutenReqMsg($record, $order->getId(), $recvType);
                } elseif (   $this->rakutenMailSetting == $this->vt4gConst['VT4G_MAIL_TARGET']['RAKUTEN']['FAILURE']
                    && $record['mstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
                        $this->rakutenMailMsg[] .= $this->makeRakutenReqMsg($record, $order->getId(), $recvType);
                }

                // 減額の通知で成功以外なら決済失敗にする。それ以外の通知で成功以外なら元の決済ステータスに戻す。
                if ($record['mstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
                    if ($recvType['VALUE'] == $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']['VALUE']) {
                        $newPayStatus = $this->vt4gConst['VT4G_PAY_STATUS']['FAILURE']['VALUE'];
                    } else {
                        $memo10 = unserialize($orderPayment->getMemo10());
                        $newPayStatus = $memo10['lastPayStatus'];
                    }
                } else {
                    // 処理成功で取消なら決済情報の決済金額を0にする。
                    if ($recvType['VALUE'] == $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL']['VALUE']) {
                        $memo10 = unserialize($orderPayment->getMemo10());
                        $memo10['captureTotal'] = 0;
                        $orderPayment->setMemo10(serialize($memo10));
                    }
                }
            }

            // 先に結果通知が届くケースがあるので処理が成功した注文に紐付くカートをクリア
            if (   $record['txnType'] == $this->vt4gConst['VT4G_RECV_TXN_TYPE_AUTH']
                && $record['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {

                $this->clearCartByOrder($order);
            }

            if ($shouldSendMail) {
                $this->sendOrderMail($order);
                $record['sentOrderMail'] = true;
            }
        }

        $this->updatePushResult($order, $orderPayment, $newPayStatus, $newOrderStatus, $record);

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.end'),$recvType['RECV_NAME'],$payName));

        return $error;
    }

    /**
     * リクルートかんたん支払いの結果通知処理
     * @param Order            $order        注文情報
     * @param Vt4gOrderPayment $orderPayment 決済情報
     * @param array            $record       結果通知の項目
     * @param string           $payTypeId    決済方法内部ID
     */
    private function execRecruitRecvProcess($order, $orderPayment, $record, $payTypeId)
    {
        $error = '';
        $payName = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payTypeId];
        $shouldSendMail = false;
        $record['sentOrderMail'] = false;

        switch ($record['txnType']) {
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_AUTH']:
                // 申込時の処理区分から与信/売上の通知区分を判定
                $withCapture    = $this->getWithCapture($order);
                $recvType       = $withCapture === 1
                                ? $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE'] // 売上結果
                                : $this->vt4gConst['VT4G_PAY_STATUS']['AUTH'];   // 申込結果
                $newPayStatus   = $recvType['VALUE'];
                $newOrderStatus = $withCapture === 1 ? OrderStatus::PAID : OrderStatus::NEW;
                $payStatuses    = [ '', $recvType['VALUE']];

                if ($this->pluginSetting['order_mail_timing_flg'] == $this->vt4gConst['VT4G_MAIL_TIMING']['ORDER']['ON_PAYMENT']
                    && !$this->hasSentOrderMail($orderPayment)) {

                    $shouldSendMail = true;
                }

                break;
            default:
                $this->mdkLogger->warn(sprintf(trans('vt4g_plugin.payment.recv.not.txn_type'),$record['txnType']));
                return '';
        }

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.start'),$recvType['RECV_NAME'],$payName));

        // 決済ログ作成
        $this->setLogHeadPushResult($record, $payTypeId, $recvType);
        $this->setLogInfo('処理日時'             , $this->util->toDate($record['txnTime']));
        $this->setLogInfo('リクルート取引ID'     , $record['recruitOrderId']);
        $this->setLogInfo('利用ポイント'         , preg_match('/^[0-9]+$/', $record['usePoint']) ? number_format($record['usePoint']) : $record['usePoint']);
        $this->setLogInfo('付与ポイント'         , preg_match('/^[0-9]+$/', $record['givePoint']) ? number_format($record['givePoint']) : $record['givePoint']);
        $this->setLogInfo('リクルートクーポン'   , preg_match('/^[0-9]+$/', $record['recruitCoupon']) ? number_format($record['recruitCoupon']) : $record['recruitCoupon']);
        $this->setLogInfo('マーチャントクーポン' , preg_match('/^[0-9]+$/', $record['merchantCoupon']) ? number_format($record['merchantCoupon']) : $record['merchantCoupon']);

        if ($record['mstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.mstatus.error'),$recvType['RECV_NAME'],$record['mstatus']));
            $newPayStatus   = '';
            $newOrderStatus = '';
        }

        // 現在の支払方法検証
        $error .= $this->checkPaymentTypeIDPushResult($order, $orderPayment, $newPayStatus, $newOrderStatus);

        // 決済ステータス検証
        $error .= $this->checkPaymentStatusPushResult($orderPayment, $payStatuses, $newPayStatus, $newOrderStatus);

        if (!empty($error)) {
            $error = $this->makeErrorPushResult($record, $error, $order->getId());
        } else {
            // 先に結果通知が届くケースがあるので処理が成功した注文に紐付くカートをクリア
            if ($record['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
                $this->clearCartByOrder($order);
            }

            if ($shouldSendMail) {
                $this->sendOrderMail($order);
                $record['sentOrderMail'] = true;
            }
        }

        $this->updatePushResult($order, $orderPayment, $newPayStatus, $newOrderStatus, $record);

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.end'),$recvType['RECV_NAME'],$payName));

        return $error;
    }

    /**
     * LINE Payの結果通知処理
     * @param Order            $order        注文情報
     * @param Vt4gOrderPayment $orderPayment 決済情報
     * @param array            $record       結果通知の項目
     * @param string           $payTypeId    決済方法内部ID
     */
    private function execLINEPayRecvProcess($order, $orderPayment, $record, $payTypeId)
    {
        $error = '';
        $payName = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payTypeId];
        $shouldSendMail = false;
        $record['sentOrderMail'] = false;

        switch ($record['txnType']) {
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_AUTH']:
                // 申込時の処理区分から与信/売上の通知区分を判定
                $withCapture    = $this->getWithCapture($order);
                $recvType       = $withCapture === 1
                                ? $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE'] // 売上結果
                                : $this->vt4gConst['VT4G_PAY_STATUS']['AUTH'];   // 申込結果
                $newPayStatus   = $recvType['VALUE'];
                $newOrderStatus = $withCapture === 1 ? OrderStatus::PAID : OrderStatus::NEW;
                $payStatuses    = [ '', $recvType['VALUE']];

                if ($this->pluginSetting['order_mail_timing_flg'] == $this->vt4gConst['VT4G_MAIL_TIMING']['ORDER']['ON_PAYMENT']
                    && !$this->hasSentOrderMail($orderPayment)) {

                    $shouldSendMail = true;
                }

                break;
            default:
                $this->mdkLogger->warn(sprintf(trans('vt4g_plugin.payment.recv.not.txn_type'),$record['txnType']));
                return '';
        }

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.start'),$recvType['RECV_NAME'],$payName));

        // 決済ログ作成
        $this->setLogHeadPushResult($record, $payTypeId, $recvType);
        $this->setLogInfo('処理日時'         , $this->util->toDate($record['txnTime']));
        $this->setLogInfo('LINE Pay 取引番号', $record['linepayOrderId']);

        if ($record['mstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.mstatus.error'),$recvType['RECV_NAME'],$record['mstatus']));
            $newPayStatus   = '';
            $newOrderStatus = '';
            $shouldSendMail = false;
        }

        // 現在の支払方法検証
        $error .= $this->checkPaymentTypeIDPushResult($order, $orderPayment, $newPayStatus, $newOrderStatus);

        // 決済ステータス検証
        $error .= $this->checkPaymentStatusPushResult($orderPayment, $payStatuses, $newPayStatus, $newOrderStatus);

        if (!empty($error)) {
            $error = $this->makeErrorPushResult($record, $error, $order->getId());
        } else {
            // 先に結果通知が届くケースがあるので処理が成功した注文に紐付くカートをクリア
            if ($record['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
                $this->clearCartByOrder($order);
            }

            if ($shouldSendMail) {
                $this->sendOrderMail($order);
                $record['sentOrderMail'] = true;
            }
        }

        $this->updatePushResult($order, $orderPayment, $newPayStatus, $newOrderStatus, $record);

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.end'),$recvType['RECV_NAME'],$payName));

        return $error;
    }

    /**
     * PayPal決済の結果通知処理
     * @param Order            $order        注文情報
     * @param Vt4gOrderPayment $orderPayment 決済情報
     * @param array            $record       結果通知の項目
     * @param string           $payTypeId    決済方法内部ID
     */
    private function execPayPalRecvProcess($order, $orderPayment, $record, $payTypeId)
    {
        $error = '';
        $payName = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payTypeId];
        $shouldSendMail = false;
        $record['sentOrderMail'] = false;
        $payStatuses = [];
        // 速報確報フラグをレコードに持たせる
        $record['fixed'] = $this->fixed;

        switch ($record['txnType']) {
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_CAPTURE']:
                $recvType       = $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']; // 売上結果

                if ($record['fixed'] != $this->vt4gConst['VT4G_RECV_PAYPAL_FIXED']) {
                    $newPayStatus   = $recvType['VALUE'];
                    $newOrderStatus = $order->getOrderStatus()->getId() == (OrderStatus::NEW || OrderStatus::PENDING) ? OrderStatus::PAID : '';
                    $payStatuses    = [
                                        '',
                                        $this->vt4gConst['VT4G_PAY_STATUS']['AUTH']['VALUE'],
                                        $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE'],
                                      ];
                    if ($this->pluginSetting['order_mail_timing_flg'] == $this->vt4gConst['VT4G_MAIL_TIMING']['ORDER']['ON_PAYMENT']
                        && !$this->hasSentOrderMail($orderPayment)) {

                        $shouldSendMail = true;
                    }

                } else {
                    $newPayStatus = '';
                    $newOrderStatus = '';
                }

                break;
            case $this->vt4gConst['VT4G_RECV_TXN_TYPE_REFUND']:
                // 返金の場合、現在の決済ステータスで判別する
                $recvType       = $this->getRefundRecvSetting($orderPayment);
                $newPayStatus   = '';
                $newOrderStatus = '';
                break;
            default:
                $this->mdkLogger->warn(sprintf(trans('vt4g_plugin.payment.recv.not.txn_type'),$record['txnType']));
                return '';
        }

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.start'),$recvType['RECV_NAME'],$payName));

        // 決済ログ作成
        $this->setLogHeadPushResult($record, $payTypeId, $recvType);
        $this->setLogInfo('受付日時'  , $this->util->toDate($record['receivedDatetime']));
        $this->setLogInfo('金額'      , preg_match('/^[0-9]+$/', $record['amount']) ? number_format($record['amount']) : $record['amount']);
        $this->setLogInfo('お客様番号', $record['payerId']);
        $this->setLogInfo('取引識別子', $record['centerTxnId']);

        if ($record['mstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.mstatus.error'),$recvType['RECV_NAME'],$record['mstatus']));
            $newPayStatus   = '';
            $newOrderStatus = '';
            $shouldSendMail = false;
        }

        // 現在の支払方法検証
        $error .= $this->checkPaymentTypeIDPushResult($order, $orderPayment, $newPayStatus, $newOrderStatus);

        // 決済ステータス検証
        $error .= $this->checkPaymentStatusPushResult($orderPayment, $payStatuses, $newPayStatus, $newOrderStatus);

        // 過去の受信情報と比較
        $memo09 = $orderPayment->getmemo09();
        if (!empty($memo09)) {
            $oldRecords  = unserialize($memo09);
            $promptCnt   = 0;
            $diffRecode  = false;
            $failerFixed = false;

            foreach ($oldRecords as $oldRecord ) {
                // payerId、centerTxnId、fixedが同じで、異なる結果を受信したらエラーメール
                // 一つでも見つかればエラーメールなので、発見済みならスキップ
                if (   $oldRecord['payerId']     == $record['payerId']
                    && $oldRecord['centerTxnId'] == $record['centerTxnId']
                    && $oldRecord['fixed']       == $record['fixed']
                    && !$diffRecode) {

                    $diff = array_diff(
                        [
                            $oldRecord['mstatus'],
                            $oldRecord['receivedDatetime'],
                            $oldRecord['amount'],
                        ],
                        [
                            $record['mstatus'],
                            $record['receivedDatetime'],
                            $record['amount'],
                        ]);

                    $diffRecode = !empty($diff);
                }

                // 今回が確報なら速報の結果を確認
                if ($record['fixed'] == $this->vt4gConst['VT4G_RECV_PAYPAL_FIXED']) {
                    if (   $oldRecord['payerId']     == $record['payerId']
                        && $oldRecord['centerTxnId'] == $record['centerTxnId']
                        && $oldRecord['fixed']       != $this->vt4gConst['VT4G_RECV_PAYPAL_FIXED'] ) {

                        $promptCnt++;

                        // 過去に成功の速報を受信していて、今回が成功以外の確報ならエラーメール対象
                        if (   $oldRecord['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']
                            && $record['mstatus']    != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {

                            $failerFixed = true;
                        }
                    }
                }

            }

            // 過去の受信結果と異なる結果だった場合
            if ($diffRecode) {
                $error .= $this->makeErrorMsg(trans('vt4g_plugin.payment.recv.result.different'));
                $newPayStatus   = '';
                $newOrderStatus = '';
            }

            if ($record['fixed'] == $this->vt4gConst['VT4G_RECV_PAYPAL_FIXED']) {
                if ($failerFixed) {
                    $error .= $this->makeErrorMsg(trans('vt4g_plugin.payment.recv.paypal.fixed.failure'));
                }
                // 速報がないのに確報の成功結果が届いたらエラーメール
                if ($promptCnt == 0 && $record['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
                    $error .= $this->makeErrorMsg(trans('vt4g_plugin.payment.recv.paypal.fixed.not.prompt'));
                }
            }

        } else {
            // 受信結果がないのに確報の成功結果が届いたらエラーメール
            if (   $record['fixed']   == $this->vt4gConst['VT4G_RECV_PAYPAL_FIXED']
                && $record['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {

                $error .= $this->makeErrorMsg(trans('vt4g_plugin.payment.recv.paypal.fixed.not.prompt'));
            }
        }

        if (!empty($error)) {
            $error = $this->makeErrorPushResult($record, $error, $order->getId());
        } else {
            if (   $record['txnType'] == $this->vt4gConst['VT4G_RECV_TXN_TYPE_CAPTURE']
                && $record['fixed']   != $this->vt4gConst['VT4G_RECV_PAYPAL_FIXED']
                && $record['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {

                $this->clearCartByOrder($order);
            }

            if ($shouldSendMail) {
                $this->sendOrderMail($order);
                $record['sentOrderMail'] = true;
            }
        }

        $this->updatePushResult($order, $orderPayment, $newPayStatus, $newOrderStatus, $record);
        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.save.end'),$recvType['RECV_NAME'],$payName));

        return $error;
    }

    /**
     * 結果通知の決済ログヘッダ部分の作成
     *
     * @param array   $record       通知レコード
     * @param integer $payTypeId    支払方法内部ID
     * @param array   $recvType     通知ごとの設定値
     */
    private function setLogHeadPushResult($record, $payTypeId, $recvType)
    {
        $statusLabel = $recvType['LABEL'] == $this->vt4gConst['VT4G_PAY_STATUS']['DEPOSIT']['LABEL']
                    ? $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['LABEL']
                    : $recvType['LABEL'];

        // ログの共通ヘッダの作成
        $this->logData = [];
        $log_message  = '';

        $log_message .= "[{$statusLabel}] ";
        $log_message .= "{$recvType['RECV_NAME']}通知受信";

        // PayPalの通知は速報、確報を付けて表示する
        if ($payTypeId == $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL']){
            $log_message .= $record['fixed'] == $this->vt4gConst['VT4G_RECV_PAYPAL_FIXED'] ? '(確報)' : '(速報)';
        }

        // 入金済みにする入金通知に成功、失敗はないので飛ばす
        if ($recvType['VALUE'] != $this->vt4gConst['VT4G_PAY_STATUS']['DEPOSIT']['VALUE']){

            if ($record['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
                $msg = '成功';
            } elseif ($record['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['PENDING']) {
                $msg = '保留';
            } else {
                $msg = '失敗';
            }

            $log_message .= ' ' . $msg;
        }

        $this->setLogInfo('決済取引ID', $record['orderId']);
        $this->setLogInfo($this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payTypeId], $log_message);
    }

    /**
     * 入金通知上のコンビニ名を取得します。
     *
     * @param  string $cvsType CVSタイプ
     * @return string          コンビニ名
     */
    private function getPayConveni($cvsType)
    {
        $arrConveni = $this->vt4gConst['VT4G_RECV_CVS_TYPE'];

        if (array_key_exists($cvsType, $arrConveni)) {
            return $arrConveni[$cvsType];
        } else {
            return '不明';
        }
    }

    /**
     * 注文と支払方法設定データの支払方法内部IDをチェックします。
     *
     * @param  Order             $order          注文情報
     * @param  Vt4gOrderPayment  $orderPayment   決済情報
     * @param  string           &$newPayStatus   更新予定の決済ステータス
     * @param  string           &$newOrderStatus 更新予定の注文ステータス
     * @return string            $error          エラーのときはメッセージ、それ以外は空文字
     */
    private function checkPaymentTypeIDPushResult($order, $orderPayment, &$newPayStatus, &$newOrderStatus)
    {
        $paymentMethod = $this->util->getPaymentMethod($order->getPayment()->getId());

        if(!empty($paymentMethod->getMemo03()) && $paymentMethod->getMemo03() == $orderPayment->getMemo03()) {
            return '';
        }

        // 以下エラーの場合
        $msg = sprintf(trans('vt4g_plugin.payment.recv.payment_type_id.error'),$orderPayment->getMemo03(),$paymentMethod->getMemo03());
        $error = $this->makeErrorMsg($msg);
        $newPayStatus = '';
        $newOrderStatus = '';
        return $error;
    }

    /**
     * 通知受信前の決済ステータスが想定通りであるかをチェックします。
     * チェックNGの場合は更新予定の決済ステータスと注文ステータスを空にします。
     * 更新予定の決済ステータスが空で渡された場合はOKとします。
     * @param  Vt4gOrderPayment $orderPayment   決済情報
     * @param  array            $payStatuses    想定される決済ステータスの配列
     * @param  string          &$newPayStatus   更新予定の決済ステータス
     * @param  string          &$newOrderStatus 更新予定の注文ステータス
     * @return string           $error          エラーのときはメッセージ、それ以外は空文字
     */
    private function checkPaymentStatusPushResult($orderPayment, $payStatuses, &$newPayStatus, &$newOrderStatus)
    {
        if (empty($newPayStatus) || in_array($orderPayment->getMemo04(), $payStatuses)) {
            return '';
        }

        // 以下エラーの場合
        $names = [];
        foreach ($payStatuses as $payStatus) {
            $names[] = empty($payStatus) ? '値なし' : $this->util->getPaymentStatusName($payStatus);
        }

        $msg = sprintf(trans('vt4g_plugin.payment.recv.payment_status.error'),
                        $this->util->getPaymentStatusName($orderPayment->getMemo04()),
                        implode(' ',$names));
        $error = $this->makeErrorMsg($msg);
        $newPayStatus   = '';
        $newOrderStatus = '';
        return $error;
    }

    /**
     * 通知受信前の注文ステータスが想定通りであるかをチェックします。
     * チェックNGの場合は更新予定の注文ステータスを空にします。
     * 更新予定の注文ステータスが空で渡された場合はOKとします。
     * @param  Order   $Order          注文情報
     * @param  array   $orderStatus    想定される注文ステータス
     * @param  string  $newOrderStatus 更新予定の注文ステータス
     * @return string  $error          エラーのときはメッセージ、それ以外は空文字
     */
    private function checkOrderStatusPushResult($order, $orderStatus, &$newOrderStatus)
    {

        if (empty($newOrderStatus) || in_array($order->getOrderStatus()->getId(),$orderStatus['id'])) {
            return '';
        }

        // 以下エラーの場合
        $statusArray = [];
        $beforePushResultOrderStatus = $this->em->getRepository(OrderStatus::class)->findBy($orderStatus);
        foreach($beforePushResultOrderStatus as $status ){
            $statusArray[] = $status->getName();
        }

        $msg = sprintf(trans('vt4g_plugin.payment.recv.order_status.error'),
                        $order->getOrderStatus()->getName(),
                        implode(' ',$statusArray));
        $error = $this->makeErrorMsg($msg);
        $newOrderStatus = '';
        return $error;
    }

    /**
     * 本人認証結果通知の情報で受注を更新します。
     * @param Order            $order        注文情報
     * @param Vt4gOrderPayment $orderPayment 決済情報
     * @param array            $recode       結果通知の項目
     * @param integer          $newPayStatus 通知受信後の決済ステータス
     * @param integer          $withCapture  決済申込時点の処理区分
     * @param array $record 通知レコード
     */
    private function updateMpiPushResult($order, $orderPayment, $record, $newPayStatus, $withCapture)
    {
        $creditService = $this->container->get('vt4g_plugin.service.payment_credit');
        $creditService->logData = [];

        $creditService->paymentResult['isOK']         = true;
        $creditService->paymentResult['vResultCode']  = $record['vResultCode'];
        $creditService->paymentResult['mErrMsg']      = "";
        $creditService->paymentResult['mStatus']      = $record['cardMstatus'];
        $creditService->paymentResult['orderId']      = $orderPayment->getMemo01();

        $memo10 = unserialize($orderPayment->getMemo10());
        $creditService->paymentResult['paymentType']  = substr($memo10['card_type'], 0, 2);
        $creditService->paymentResult['paymentCount'] = substr($memo10['card_type'], 2);

        $creditService->paymentResult['payStatus']    = $newPayStatus;
        $creditService->paymentResult['mpiHosting']   = true;
        $creditService->paymentResult['withCapture']  = $withCapture;

        // フラグをオンにしてcompleteOrderでメール送信とappendOrderMessageをやらない
        $creditService->isPaymentRecv = true;
        $creditService->completeCreditOrder($order);

        // 結果通知項目の登録
        $this->registPushRecord($orderPayment, $record);

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.order.update'), $order->getId()));
    }

    /**
     * ステータスの更新、結果通知項目の登録、決済ログの登録を行います。<br>
     * $newOrderStatusに値がある場合はdtb_orderの更新を行います。<br>
     * $newPayStatusに値がある場合はplg_vt4g_order_paymentの更新を行います。
     * @param Order            $order          注文情報
     * @param Vt4gOrderPayment $orderPayment   決済情報
     * @param integer          $newPayStatus   更新予定の決済ステータス
     * @param integer          $newOrderStatus 更新予定の注文ステータス
     * @param array            $record         結果通知の項目
     */
    private function updatePushResult($order, $orderPayment, $newPayStatus, $newOrderStatus, $record)
    {
        $orderId = $order->getId();

        // 注文ステータスの更新
        if (!empty($newOrderStatus)) {

            if (empty($orderPayment->getMemo04())) {
                $this->purchaseFlow->commit($order, new PurchaseContext());
            }

            $orderStatus = $this->em->getRepository(OrderStatus::class)->find($newOrderStatus);
            $this->em->getRepository(Order::class)->changeStatus($orderId, $orderStatus);
        }

        // 決済ステータスの更新
        if (!empty($newPayStatus)) {
            $orderPayment->setMemo04($newPayStatus);
            $this->em->persist($orderPayment);
            $this->em->flush();
        }

        // 結果通知項目の登録
        $this->registPushRecord($orderPayment, $record);

        // 決済ログテーブルにログを追加
        $this->setOrderLog($order);

        $this->em->commit();

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.order.update'), $orderId));
    }

    /**
     * エラー出力
     *
     * @param  array  $record    結果通知の項目
     * @param  string $error     メッセージ
     * @param  string $order_id  注文番号
     * @return string $new_error エラーメッセージ
     */
    private function makeErrorPushResult($record, $error, $order_id = '')
    {
        $orderId = $record['orderId'];
        $newError = <<<__EOS__
決済取引ID : $orderId
  注文番号 : $order_id
エラー原因 : $error

__EOS__;

        return $newError;
    }

    /**
     * 楽天ペイ 要求結果メッセージ作成
     * @param  array  $record   結果通知の項目
     * @param  string $order_id 注文番号
     * @param  array  $recvType 結果通知ごとの設定値
     * @return string $payLabel 決済方法の名称
     */
    private function makeRakutenReqMsg($record, $order_id = '', $recvType)
    {
        $orderId = $record['orderId'];
        $ret  = $recvType['VALUE'] == $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']['VALUE']
                ? $this->vt4gConst['VT4G_LABEL_RENAME']['RAKUTEN']['REFUND']
                : $recvType['LABEL'];
        $ret .= $record['mstatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK'] ? '成立' : '不成立';

        if (   $recvType['VALUE'] == $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']['VALUE']
            && $record['mstatus'] != $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {

            $ret .= sprintf('(%s)',trans('vt4g_plugin.payment.recv.order.retry'));
        }

        $msg = <<<__EOS__
決済取引ID : $orderId
  注文番号 : $order_id
  要求結果 : $ret

__EOS__;

        return $msg;
    }

    /**
     * エラーとなった情報をメールで送信します。
     * @param array $arrHead 結果通知ヘッダー情報
     */
    public function sendErrorMail($arrHead)
    {
        $pushDateTime = $this->util->toDate($arrHead['pushTime']);
        $errors = implode(LF, $this->errorMailMsg);

        $content = $this->container->get('twig')->render(
            "VeriTrans4G/Resource/template/default/Mail/vt4g_payment_recv_error.twig",
            [
                'pluginName' => $this->vt4gConst['VT4G_SERVICE_NAME'],
                'pushDateTime' => $pushDateTime,
                'pushId' => $arrHead['pushId'],
                'errors' => $errors
            ],
            'text/html'
            );

        $this->mdkLogger->debug(trans('vt4g_plugin.payment.recv.show.error_mail') .LF. $content);
        $this->sendMail($this->vt4gConst['VT4G_SERVICE_NAME'] . $this->vt4gConst['VT4G_RECV_MAIL_SUBJECT']['ERROR'], $content);
        $this->mdkLogger->info(trans('vt4g_plugin.payment.recv.send.error_mail'));
    }

    /**
     * 楽天ペイの要求結果メールを送信します。
     * @param array $arrHead 結果通知ヘッダー情報
     */
    public function sendRakutenReqMail($arrHead)
    {
        $pushDateTime = $this->util->toDate($arrHead['pushTime']);
        $msg = implode(LF, $this->rakutenMailMsg);

        $content = $this->container->get('twig')->render(
            "VeriTrans4G/Resource/template/default/Mail/vt4g_payment_recv_request_result.twig",
            [
                'paymentName' => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_60'],
                'pushDateTime' => $pushDateTime,
                'pushId' => $arrHead['pushId'],
                'msg' => $msg
            ],
            'text/html'
            );

        $subject = $this->vt4gConst['VT4G_SERVICE_NAME']
                  . '- '
                  . $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_60']
                  . $this->vt4gConst['VT4G_RECV_MAIL_SUBJECT']['RESULT'];

        $this->sendMail($subject, $content);
        $this->mdkLogger->info(trans('vt4g_plugin.payment.recv.send.request_mail'));
    }

    /**
     * メールを送信します。
     * @param string   $subject  件名
     * @param string   $content  本文
     */
    private function sendMail($subject, $content)
    {
        // 基本情報取得
        $baseInfo = $this->em->getRepository(BaseInfo::class)->get();

        // メール送信クラス生成
        $message = (new \Swift_Message())
        ->setSubject($subject)
        ->setFrom([$baseInfo->getEmail03() => $baseInfo->getShopName()])
        ->setTo([$baseInfo->getEmail01()])
        ->setBcc($baseInfo->getEmail01())
        ->setReplyTo($baseInfo->getEmail04())
        ->setReturnPath($baseInfo->getEmail04())
        ->setBody($content);

        $this->container->get('mailer')->send($message);

    }

    /**
     * 注文情報に紐付くカート情報を削除します。
     * @param Order $order 注文情報
     */
    private function clearCartByOrder($order)
    {
        $cart = $this->em
                ->getRepository(Cart::class)
                ->findOneBy(['Customer' => $order->getCustomer(),'pre_order_id' => $order->getPreOrderId()]);

        if (!empty($cart)) {
            $this->em->remove($cart);
            $this->em->flush($cart);
        }

    }

    /**
     * 決済情報に結果通知項目を登録します。
     * @param Vt4gOrderPayment $orderPayment 決済情報
     * @param array            $record       結果通知の項目
     */
    private function registPushRecord($orderPayment, $record)
    {
        $memo09 = [];
        $records = $orderPayment->getmemo09();
        if (!empty($records)) {
            $memo09 = unserialize($records);
        }

        $memo09[] = $record;
        $orderPayment->setmemo09(serialize($memo09));
        $this->em->persist($orderPayment);
        $this->em->flush();
    }

    /**
     * 返金結果通知の設定値を取得します。
     *
     * @param  Vt4gOrderPayment $orderPayment 決済情報
     * @return array                          通知ごとの設定値
     */
    private function getRefundRecvSetting($orderPayment)
    {
        // 売上か減額要求中で返金、削除通知が来た場合は「返金通知」
        // それ以外で返金、削除通知が来た場合は「取消通知」とする
        switch ($orderPayment->getMemo04()) {
            case $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']:
            case $this->vt4gConst['VT4G_PAY_STATUS']['REDUCTION_REQUEST']['VALUE']:
                return $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']; // 返金結果
            default:
                return $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL']; // 取消結果
        }
    }

    /**
     * 決済ログとエラーメール用のメッセージを作成します。
     * @param  string $msg メッセージ
     * @return string      エラーメール用メッセージ
     */
    private function makeErrorMsg($msg)
    {
        $this->setLogInfo('結果通知エラー', $msg);
        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.payment.recv.reason.error_mail'),$msg));
        return $msg.PHP_EOL;
    }

    /**
     * メールを送信したかを確認します。
     * @param  Vt4gOrderPayment $orderPayment 決済情報
     * @return bool                           送信済みならtrue
     */
    private function hasSentOrderMail($orderPayment)
    {
        $memo09 = $orderPayment->getmemo09();
        if (!empty($memo09)) {
            $records = unserialize($memo09);
            foreach ($records as $record ) {
                if ($record['sentOrderMail']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 継続課金注文・明細データの保存
     * @param  Object   $order  注文
     *
     * @return boolean
     */
    public function saveSubscOrder($order)
    {
        // 注文明細
        $order_items = $order->getOrderItems();
        // 継続課金の販売種別の場合のみ保存
        $saleTypeId = $order->getSaleTypes()[0]->getId();
        $subscSaleType = $this->em->getRepository(Vt4gSubscSaleType::class)->findOneBy(['sale_type_id' => $saleTypeId]);

        if (is_null($subscSaleType)) {
            // 継続課金の販売種別でない場合は登録しない
            return false;
        }

        $order_id = $order->getId();
        $customer_id = $order->getCustomer()->getId();

        // 継続課金注文データが登録済みならスキップ
        $subscOrder = $this->em->getRepository(Vt4gSubscOrder::class)->find($order_id);
        if (!empty($subscOrder)){
            return false;
        }

        // 決済センター戻りと結果通知を同時受信してデッドロックが発生したら、
        // 一方の通信の処理でデータが登録されるはずなので、次の処理に進む
        try {
            // 継続課金注文データの保存
            $subscOrder = new Vt4gSubscOrder();
            $subscOrder ->setOrderId($order_id)
                        ->setCustomerId($customer_id)
                        ->setSubscSaleTypeId($saleTypeId);
            $this->em->persist($subscOrder);
            $this->em->flush();

            // 継続課金注文明細データの保存
            $idxSubscOrderItem = [];
            foreach ($order_items as $oder_item) {
                // 商品マスタ情報を保持している注文明細のみ保存する（送料などを除外する）
                if ($puroduct = $oder_item->getProduct()) {
                    $subscOrder = new Vt4gSubscOrderItem();
                    $puroduct_id = $puroduct->getId();
                    $prod_class_id = $oder_item->getProductClass()->getId();
                    $shipping_id = $oder_item->getShipping()->getId();
                    $idx = sprintf('%s-%s-%s-%s', $order_id, $puroduct_id, $prod_class_id, $shipping_id);
                    if (in_array($idx, $idxSubscOrderItem)) continue; // 重複した商品は登録しない
                    $idxSubscOrderItem[] = $idx;
                    $subscOrder ->setOrderId($order_id)
                                ->setProductId($puroduct_id)
                                ->setProductClassId($prod_class_id)
                                ->setShippingId($shipping_id)
                                ->setSubscStatus($this->vt4gConst['VTG4_SUBSC_STATUS_SUBSC']); // 継続
                    $this->em->persist($subscOrder);
                }
            }
            $this->em->flush();
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.shopping.credit.saved.subsc.order'),$order_id));

        } catch (\Doctrine\DBAL\DBALException $e) {

            $msg = $e->getMessage();
            if(strpos($msg,'deadlock') !== false || strpos($msg,'Deadlock') !== false) {
                $this->mdkLogger->info(sprintf(trans('vt4g_plugin.db.deadlock'),'結果通知の継続課金注文保存処理'));
            } else {
                $this->mdkLogger->error($e->getMessage());
                throw $e;
            }

        }

    }
}
