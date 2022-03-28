<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Payment;

use Plugin\VeriTrans4G\Form\Type\Shopping\PaymentAlipayType;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;

class AlipayService extends BaseService
{
    /**
     * コミット処理
     *
     * @param $order
     * @param array $formData 入力フォームからの値
     * @param array $paymentInfo 決済ごとの設定情報
     * @param array $error エラー内容
     * @return boolean|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function alipayCommit($order, $formData, $paymentInfo, &$error)
    {
        // ベリトランスとの通信
        $this->connectVT4G($order, $formData, $paymentInfo, $error);
        // 決済情報 (memo05)
        $payment = $this->paymentResult;
        if ($payment['isOK'] == true) {
            // メール情報(memo06)
            $mailData = $this->setMail($order);
            // 決済変更ログ情報(plg_vt4g_order_logテーブル)
            $logData = $this->setLog($payment, 'フォーム取得');
            // 決済データを登録
            $this->setOrderPayment($order, $payment, $logData, $mailData);
            // 決済ログテーブルにログを追加
            $this->setOrderLog($order);
            return $payment['entryForm'];
        }
        return false;
    }

    /**
     * Alipay決済センターから戻った時の処理を行います。
     *
     * @param object    $request ブラウザからのリクエスト
     * @param object    $order   注文情報
     * @param array    &$error   エラー表示用配列
     * @return boolean
     */
    public function alipayComplete($request, $order, &$error)
    {
        // 決済結果画面からのレスポンス
        $paymentData = $this->getResponse($request, $order, $error);

        // 成功時
        if ($paymentData['isOK'] == true){
            // メール情報(memo06)
            $arrMail = $this->setMail($order);

            // 決済変更ログ情報(plg_vt4g_order_logテーブル)
            $arrLog = $this->setLog($paymentData);

            // 完了処理
            $this->completeOrder($order, $paymentData, $arrLog, $arrMail);

            return true;
        }

        return false;
    }

    /**
     * 入力フォームを生成
     *
     * @return object 情報入力フォーム
     */
    public function createAlipayForm()
    {
        return $this->container->get('form.factory')
                    ->create(PaymentAlipayType::class);
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
                $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_50']
            )
        );

        $objRequest = new \AlipayAuthorizeRequestDto();
        $this->setRequestParam($objRequest, $order, $paymentInfo);

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
            // ログ出力用
            $this->paymentResult['isOK']         = true;
            $this->paymentResult['orderId']      = $objResponse->getOrderId();       // 取引ID取得
            $this->paymentResult['entryForm']    = $objResponse->getEntryForm();     // リダイレクト用HTML
            $this->paymentResult['captureTotal'] = floor($order->getPaymentTotal()); // 決済金額

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
        $objRequest->setAmount(floor($order->getPaymentTotal()));

        $currency   = $this->vt4gConst['VT4G_ALIPAY_CURRENCY'];
        $successUrl = $this->util->generateUrl(
            'vt4g_shopping_payment',
            ['mode' => 'success']
        );
        $errorUrl   = $this->util->generateUrl(
            'vt4g_shopping_payment',
            ['mode' => 'error']
        );
        $commodityName = $paymentInfo['commodity_name'];
        $commodityDescription = '';
        $withCapture = 'true';


        $objRequest->setCurrency($currency);
        $objRequest->setSuccessUrl($successUrl);
        $objRequest->setErrorUrl($errorUrl);
        $objRequest->setCommodityName($commodityName);
        $objRequest->setCommodityDescription($commodityDescription);
        $objRequest->setWithCapture($withCapture);
    }

    /**
     * Alipayからの結果取得
     *
     * @param  object  $request ブラウザからのリクエスト
     * @param  object  $order   注文情報
     * @param  array  &$error   エラー表示用配列
     * @return array   $arrRes  レスポンス配列
     */
    public function getResponse($request, $order, &$error)
    {
        $response = $request->request->all();
        foreach ($response as $key => $value) {
            $response[$key] = htmlspecialchars($value);
        }
        $this->mdkLogger->info(
            sprintf(
                trans('vt4g_plugin.payment.shopping.get.result.start'),
                $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_50']
            )
        );
        // レスポンス初期化
        $arrRes = $this->initPaymentResult();

        // レスポンス検証
        if (isset($response) == false ) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.get.result.error'));
            $error['payment'] = trans('vt4g_plugin.payment.shopping.get.result.error.msg').'<br />';
            return $arrRes;
        }

        if ($this->util->checkAuthInfo($response["authInfo"]) == false) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.auth.result.error'));
            $this->mdkLogger->info($response);
            $error['payment'] = trans('vt4g_plugin.payment.shopping.auth.result.error.msg').'<br />';
            return $arrRes;
        }

        $arrRes['mStatus']     = $response['mstatus'];     // 結果取得
        $arrRes['vResultCode'] = $response['vResultCode']; // 詳細コード
        $arrRes['mErrMsg']     = $response['merrMsg'];     // エラーメッセージ

        // 正常終了
        if ($arrRes['mStatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $paymentMethod = $this->em->getRepository(Vt4gPaymentMethod::class)->find($order->getPayment()->getId());
            $arrRes['isOK']           = true;
            $arrRes['orderId']        = $response['orderId'];  // 取引ID取得
            $arrRes['serviceType']    = $response["serviceType"];
            $arrRes['custTxn']        = $response["custTxn"];
            $arrRes['settleAmount']   = $response["settleAmount"];
            $arrRes['settleCurrency'] = $response["settleCurrency"];
            $arrRes['centerTradeId']  = $response["centerTradeId"];
            $arrRes['payStatus']      = $this->getNewPaymentStatus($paymentMethod);
        // 異常終了
        } else {
            $error['payment']  = trans('vt4g_plugin.payment.shopping.system.error').'<br />';
            $error['payment'] .= $arrRes['vResultCode'] . ':' . $arrRes['mErrMsg'] . '<br />';
        }
        $this->mdkLogger->info(print_r($arrRes, true));

        return $arrRes;
    }

    /**
     * メール内容の設定と完了画面の内容設定
     *
     * @param  \Eccube\Entity\Order $order    注文データ
     * @return array $mailData メール情報
     */
    public function setMail($order)
    {
        $this->mailData = [];
        $paymentMethod = $this->util->getPaymentMethod($order->getPayment()->getId());
        $paymentName  = $this->util->getPayName($paymentMethod->getMemo03());

        // メール記載情報
        $this->setMailTitle($paymentName);
        $this->setMailAdminSetting($paymentMethod);

        return $this->mailData;
    }

    /**
     * ログ出力内容
     *
     * @param  array  $payment 決済データ
     * @param  string $message ログ用メッセージ
     * @return array
     */
    private function setLog($payment, $message = '')
    {
        if (!empty($message)) {
            $this->setLogInfo('決済取引ID', $payment['orderId']);
            $this->setLogInfo($this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_50'], $message);
        } else {
            $this->setLogHead($this->vt4gConst['VT4G_PAYTYPEID_ALIPAY'], '', $payment);
            $this->setLogInfo('精算金額', number_format($payment['settleAmount']));
            $this->setLogInfo('精算通貨種類', $payment['settleCurrency']);
        }

        return $this->logData;
    }

    /**
     * キャンセル処理
     *
     * @param  array $payload キャンセル処理に使用するデータ
     * @return array          キャンセル処理結果データ
     */
    public function operateCancel($payload)
    {
        // 共通キャンセル処理の実行
        list($operationResult, $mdkResponse) = parent::operateCancel($payload);

        return $this->getOperateResponse($operationResult, $mdkResponse);
    }

    /**
     * 返金処理
     *
     * @param  array $payload 返金処理に使用するデータ
     * @return array          返金処理結果データ
     */
    public function operateRefund($payload)
    {
        // 共通返金処理の実行
        list($operationResult, $mdkResponse) = parent::operateRefund($payload);

        return $this->getOperateResponse($operationResult, $mdkResponse);
    }

    /**
     * 決済操作方法の処理結果データから記録に必要なものを取得する
     *
     * @param array  $operationResult 共通な処理結果データ
     * @param object $mdkResponse     決済操作方法の通信結果結果データ
     * @return array $operationResult 決済操作方法処理結果データ
     */
    private function getOperateResponse($operationResult, $mdkResponse)
    {
        if (!empty($operationResult) && !empty($mdkResponse)
            && $operationResult['mStatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $operationResult['centerTradeId'] = $mdkResponse->getCenterTradeId();
            $this->setLogInfo('決済センターとの取引ID', $operationResult['centerTradeId']);
            $this->setLogInfo('取引金額', number_format($operationResult['amount']));
        }
        return $operationResult;
    }
}
