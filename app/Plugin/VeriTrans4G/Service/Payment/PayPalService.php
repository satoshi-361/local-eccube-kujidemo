<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Payment;

use Plugin\VeriTrans4G\Form\Type\Shopping\PaymentPayPalType;


class PayPalService extends BaseService
{
    /**
     * コミット処理
     *
     * @param object $order       注文情報
     * @param array  $formData    入力フォームからの値
     * @param array  $paymentInfo 決済ごとの設定情報
     * @param array  $error       エラー内容
     * @return boolean|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function PayPalCommit($order, $formData, $paymentInfo, &$error)
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
            // 完了処理
            $this->setOrderPayment($order, $payment, $logData, $mailData);
            // 決済ログテーブルにログを追加
            $this->setOrderLog($order);
            return $payment['entryForm'];
        }
        return false;
    }

    /**
     * PayPal決済から戻った時の処理を行います。
     *
     * @param object    $request ブラウザからのリクエスト
     * @param object    $order   注文情報
     * @param array    &$error   エラー表示用配列
     * @return boolean
     */
    public function PayPalComplete($request, $order, &$error)
    {
        // 決済後(do)からレスポンス
        $paymentData = $this->PayPalDoExecute($request, $order, $error);
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
    public function createPayPalForm()
    {
        return $this->container->get('form.factory')
                    ->create(PaymentPayPalType::class);
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
                $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_70']
            )
        );
        // 要求電文パラメータ値の指定
        $objRequest = $this->getPayPalDto($paymentInfo['withCapture']);
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
            $this->paymentResult['entryForm']    = $objResponse->getLoginUrl();      // リダイレクト用HTML
            $this->paymentResult['captureTotal'] = floor($order->getPaymentTotal()); // 決済金額
            $this->paymentResult['withCapture']  = $paymentInfo['withCapture'];      // 処理区分
            $this->paymentResult['refundFlg']    = false;                            // 返金フラグ

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
        $setType = $this->vt4gConst['VT4G_PAYPAL_ACTION']['CREDIT'];
        $shippingFlag = $this->vt4gConst['VT4G_PAYPAL_SHIPPING']['DISABLED'];
        $discription = $paymentInfo['order_description'];
        // 受注番号(ゼロパディング)
        $objRequest->setOrderId($this->getMdkOrderId($order->getId()));
        // 決済金額
        $objRequest->setAmount(floor($order->getPaymentTotal()));
        // アクションタイプ : 固定値"set"
        $objRequest->setAction($setType);
        // 戻り先URL
        $objRequest->setReturnUrl($this->util->generateUrl('vt4g_shopping_payment', ['mode' => 'exec', 'orderId' => $objRequest->getOrderId()]));
        // キャンセルURL
        $objRequest->setCancelUrl($this->util->generateUrl('vt4g_shopping_payment', ['mode' => 'back']));
        // 配送先フラグ : 固定値"0":無効
        $objRequest->setShippingFlag($shippingFlag);
        // オーダー説明
        $objRequest->setOrderDescription($discription);
    }

    /**
     * PayPal側での決済後、結果を検証
     *
     * @param  object  $request ブラウザからのリクエスト
     * @param  object  $order   注文情報
     * @param  array  &$error   エラー表示用配列
     * @return array   $arrRes  レスポンス配列
     */
    public function PayPalDoExecute($request, $order, &$error)
    {
        $response = $request->query->all();
        foreach ($response as $key => $value) {
            $response[$key] = htmlspecialchars($value);
        }
        $setType = $this->vt4gConst['VT4G_PAYPAL_ACTION']['COMPLETE'];
        $paymentId = $order->getPayment()->getId();
        $paymentMethod = $this->util->getPaymentMethod($paymentId);
        // 売上フラグ
        $isCapture = $this->getWithCapture($order);
        // 要求電文パラメータ値の指定
        $objRequest = $this->getPayPalDto($isCapture);
        // アクションタイプ : 固定値"do"
        $objRequest->setAction($setType);
        // 顧客ID
        $objRequest->setPayerId($response['PayerID']);
        // トークン
        $objRequest->setToken($response['token']);
        // 実行
        $this->mdkLogger->info(trans('vt4g_plugin.payment.shopping.paypal.payment.start'));
        $objTransaction = new \TGMDK_Transaction();
        $objResponse = $objTransaction->execute($objRequest);

        // レスポンス初期化
        $arrRes = $this->initPaymentResult();
        // レスポンス検証
        if (isset($objResponse) == false ) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.get.result.error'));
            $error['payment'] = trans('vt4g_plugin.payment.shopping.get.result.error.msg').'<br />';
            return $arrRes;
        }

        $arrRes['mStatus']     = $objResponse->getMStatus();     // 結果取得
        $arrRes['vResultCode'] = $objResponse->getVResultCode(); // 詳細コード
        $arrRes['mErrMsg']     = $objResponse->getMerrMsg();     // エラーメッセージ

        // 正常終了
        if ($arrRes['mStatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $arrRes['isOK']          = true;
            $arrRes['orderId']       = $objResponse->getOrderId();  // 取引ID取得
            // 決済状態を保存
            $arrRes['payStatus']     = $this->getNewPaymentStatus($paymentMethod,$isCapture);
            $arrRes['withCapture']   = $isCapture;
        // 異常終了
        } else {
            $error['payment']  = trans('vt4g_plugin.payment.shopping.error').'<br />';
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
        $paymentMethod  = $this->util->getPaymentMethod($order->getPayment()->getId());
        $paymentName    = $this->util->getPayName($paymentMethod->getMemo03());

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
            $this->setLogInfo($this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_70'], $message);
        } else {
            $this->setLogHead($this->vt4gConst['VT4G_PAYTYPEID_PAYPAL'], '', $payment);
        }

        return $this->logData;
    }

    /**
     * Paypal 売上の要求Dtoクラスを返す
     *
     * @param  integer|boolean $withCapture 売上フラグ
     * @return object                       Paypal Dtoクラス
     */
    private function getPayPalDto ($withCapture)
    {
        return $withCapture
            // 与信同時売上
            ? new \PayPalCaptureRequestDto()
            // 与信のみ
            : new \PaypalAuthorizeRequestDto();
    }


    /**
     * 売上処理
     *
     * @param  array $payload 売上処理に使用するデータ
     * @return array          売上処理結果データ
     */
    public function operateCapture($payload)
    {
        // 共通売上処理の実行
        list($operationResult, $mdkResponse) = parent::operateCapture($payload);

        if ($operationResult['mStatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['PENDING']) {
            $this->pendingProcess($payload, $operationResult, $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']);
        }

        return $this->getOperateResponse($operationResult, $mdkResponse);
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

        if ($operationResult['mStatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['PENDING']) {
            $this->pendingProcess($payload, $operationResult, $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL']['VALUE']);
        }

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

        if ($operationResult['mStatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['PENDING']) {
            $this->pendingProcess($payload, $operationResult, $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']['VALUE']);
        }

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
            // 決済状況の判定
            $isRefund  = $operationResult['payStatus'] == $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']['VALUE'];

            if ($isRefund) {
                $this->setLogInfo('減額金額', number_format($operationResult['amount']));
            }
        }
        return $operationResult;
    }

    /**
     * 処理結果が保留の時の処理を行います。
     * @param array  $payload   決済処理に使用するデータ
     * @param array  $result    決済処理結果データ
     * @param string $payStatus 決済処理に関連する決済ステータス(売上確定処理なら売上など)
     */
    private function pendingProcess($payload, $result, $payStatus)
    {
        $result['orderId']   = $payload['orderPayment']->getMemo01();
        $result['payStatus'] = $payStatus;
        $this->setLogHead($payload['orderPayment']->getMemo03(), '', $result);
        $this->setOrderLog($payload['order']);
    }
}
