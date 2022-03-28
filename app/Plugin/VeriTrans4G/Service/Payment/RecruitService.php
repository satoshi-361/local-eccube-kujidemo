<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Payment;

use Plugin\VeriTrans4G\Form\Type\Shopping\PaymentRecruitType;


class RecruitService extends BaseService
{
    /**
     * コミット処理
     *
     * @param object $order       注文情報
     * @param array  $formData    入力フォームからの値
     * @param array  $paymentInfo 決済ごとの設定情報
     * @param array  $error       エラー内容
     * @return boolean|string
     */
    public function recruitCommit($order, $formData, $paymentInfo, &$error)
    {
        // ベリトランスとの通信
        $this->connectVT4G($order, $formData, $paymentInfo, $error);
        // 決済情報 (memo05)
        $payment = $this->paymentResult;
        // 成功時
        if ($this->paymentResult['isOK'] == true) {
            // メール情報(memo06)
            $mailData = $this->setMail($order);
            // 決済変更ログ情報(plg_vt4g_order_logテーブル)
            $logData = $this->setLog($payment, 'フォーム取得');
            // 決済データを登録
            $this->setOrderPayment($order, $this->paymentResult, $logData, $mailData);
            // 決済ログテーブルにログを追加
            $this->setOrderLog($order);
            return $this->paymentResult['redirectUrl'];
        }
        return false;
    }

    /**
     * 入力フォームを生成
     *
     * @return object 情報入力フォーム
     */
    public function createRecruitForm()
    {
        return $this->container->get('form.factory')
        ->create(PaymentRecruitType::class);
    }

    /**
     * ベリトランスとの通信処理
     *
     * @param object $order       注文情報
     * @param array  $formData    入力データ
     * @param array  $paymentInfo 決済ごとの設定情報
     * @param array  $error       エラー内容
     * @return boolean            レスポンスを正常に処理したかどうか
     */
    public function connectVT4G($order, $formData, $paymentInfo, &$error)
    {
        $this->mdkLogger->info(
            sprintf(
                trans('vt4g_plugin.payment.shopping.mdk.start'),
                $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_61']
                )
            );

        $objRequest = new \RecruitAuthorizeRequestDto();

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
            $this->paymentResult['isOK']         = true;
            $this->paymentResult['orderId']      = $objResponse->getOrderId();       // 取引ID
            $this->paymentResult['redirectUrl']  = $objResponse->getRedirectUrl();   // リダイレクト用HTML
            $this->paymentResult['captureTotal'] = floor($order->getPaymentTotal()); // 決済金額
            $this->paymentResult['withCapture']  = $paymentInfo['withCapture'];      // 処理区分

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
     * リクエストパラメータの設定
     *
     * @param object &$objRequest  RecruitAuthorizeRequestDto
     * @param object  $order       注文情報
     * @param array   $paymentInfo 決済ごとの設定情報
     */
    private function setRequestParam(&$objRequest, $order, $paymentInfo)
    {
        $objRequest->setOrderId($this->getMdkOrderId($order->getId()));
        $objRequest->setAmount(floor($order->getPaymentTotal()));
        $objRequest->setWithCapture($paymentInfo['withCapture']);
        $objRequest->setItemId('');
        $objRequest->setItemName($paymentInfo['item_name']);
        $objRequest->setSuccessUrl($this->util->generateUrl('vt4g_shopping_payment', ['mode' => 'success']));
        $objRequest->setErrorUrl($this->util->generateUrl('vt4g_shopping_payment', ['mode' => 'error']));
        $objRequest->setPushUrl('');
    }

    /**
     * メール内容の設定と完了画面の内容設定
     *
     * @param  object $order 注文情報
     * @return array         メールの説明文
     */
    private function setMail($order)
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
            $this->setLogInfo($this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_61'], $message);
        } else {
            $this->setLogHead($this->vt4gConst['VT4G_PAYTYPEID_RECRUIT'], '', $payment);
            $this->setLogInfo('リクルート取引ID'     , $payment['recruitOrderId']);
            $this->setLogInfo('利用ポイント'         , number_format($payment['usePoint']));
            $this->setLogInfo('付与ポイント'         , number_format($payment['givePoint']));
            $this->setLogInfo('リクルートポイント'   , number_format($payment['recruitCoupon']));
            $this->setLogInfo('マーチャントクーポン' , number_format($payment['merchantCoupon']));
        }

        return $this->logData;
    }

    /**
     * リクルートから戻った時の処理を行います。
     *
     * @param object    $request ブラウザからのリクエスト(リクルートからのレスポンス)
     * @param object    $order   注文情報
     * @param array    &$error   エラー表示用配列
     * @return boolean
     */
    public function recruitComplete($request, $order, &$error)
    {
        // リクルート結果画面からのレスポンス
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
     * リクルートからのレスポンスを取得します。
     * @param  object  $request ブラウザからのリクエスト(リクルートからのレスポンス)
     * @param  object  $order   注文情報
     * @param  array  &$error   エラー表示用配列
     * @return array   $arrRes  レスポンス配列
     */
    public function getResponse($request, $order, &$error)
    {
        $response = $request->query->all();
        foreach ($response as $key => $value) {
            $response[$key] = htmlspecialchars($value);
        }

        $this->mdkLogger->info(
            sprintf(
                trans('vt4g_plugin.payment.shopping.get.result.start'),
                $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_61']
            )
        );

        // レスポンス検証
        $arrRes = $this->initPaymentResult();
        if (isset($response) == false ) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.get.result.error'));
            $error['payment'] = trans('vt4g_plugin.payment.shopping.get.result.error.msg').'<br />';
            return $arrRes;
        }

        if ($this->util->checkVAuthInfo($response) == false) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.auth.result.error'));
            $this->mdkLogger->info($response);
            $error['payment'] = trans('vt4g_plugin.payment.shopping.auth.result.error.msg').'<br />';
            return $arrRes;
        }

        $arrRes['mStatus']     = $response['mstatus'];     // 結果取得
        $arrRes['vResultCode'] = $response['vResultCode']; // 詳細コード
        $arrRes['mErrMsg']     = '';                       // リクルートはエラーメッセージが無い

        //正常終了
        if ($arrRes['mStatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $paymentMethod = $this->util->getPaymentMethod($order->getPayment()->getId());
            $withCapture   = $this->getWithCapture($order);

            $arrRes['isOK']           = true;
            $arrRes['orderId']        = $response['orderId'];
            $arrRes['recruitOrderId'] = $response["recruitOrderId"];
            $arrRes['usePoint']       = $response["usePoint"];
            $arrRes['givePoint']      = $response["givePoint"];
            $arrRes['recruitCoupon']  = $response["recruitCoupon"];
            $arrRes['merchantCoupon'] = $response["merchantCoupon"];
            $arrRes['authParams']     = $response["authParams"];
            $arrRes['payStatus']      = $this->getNewPaymentStatus($paymentMethod,$withCapture);
            $arrRes['withCapture']    = $withCapture;

            // 異常終了
        } else {
            $error['payment']  = trans('vt4g_plugin.payment.shopping.error');
            $error['payment'] .= '[' . $arrRes['vResultCode'] . ']';
        }

        $this->mdkLogger->info(print_r($arrRes, true));

        return $arrRes;
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
            // 決済状況の判定
            $isCapture = $operationResult['payStatus'] == $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE'];
            $isCancel  = $operationResult['payStatus'] == $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL']['VALUE'];
            $isRefund  = $operationResult['payStatus'] == $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']['VALUE'];

            $operationResult['usePoint']       = $mdkResponse->getUsePoint();
            $operationResult['givePoint']      = $mdkResponse->getGivePoint();
            $operationResult['recruitCoupon']  = $mdkResponse->getRecruitCoupon();
            $operationResult['merchantCoupon'] = $mdkResponse->getMerchantCoupon();
            $operationResult['balance']        = $mdkResponse->getBalance();
            if ($isCancel || $isRefund) {
                $operationResult['cancelDatetime']  = $mdkResponse->getCancelDatetime();
            }
            if ($isCapture) {
                $operationResult['captureDatetime'] = $mdkResponse->getCaptureDatetime();
            }
            // ログ
            $this->setOperateLog($operationResult);
        }
        return $operationResult;
    }

    /**
     * 決済操作方法のログ出力内容
     *
     * @param  array  $payment 決済データ
     * @return void
     */
    private function setOperateLog($payment)
    {
        // 決済状況の判定
        $isCapture = $payment['payStatus'] == $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE'];
        $isCancel  = $payment['payStatus'] == $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL']['VALUE'];
        $isRefund  = $payment['payStatus'] == $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']['VALUE'];

        if ($isRefund) {
            $this->setLogInfo('減額金額'   , number_format($payment['amount']));
        }

        if ($isCancel || $isRefund) {
            $this->setLogInfo('取消日時'   , $this->util->toDate($payment['cancelDatetime']));
        }

        if ($isCapture) {
            $this->setLogInfo('売上日時'   , $this->util->toDate($payment['captureDatetime']));
        }

        $this->setLogInfo('利用ポイント'     , number_format($payment['usePoint']));
        $this->setLogInfo('付与ポイント'     , number_format($payment['givePoint']));
        $this->setLogInfo('リクルートポイント'  , number_format($payment['recruitCoupon']));
        $this->setLogInfo('マーチャントクーポン' , number_format($payment['merchantCoupon']));
        $this->setLogInfo('残高'          , number_format($payment['balance']));
    }
}
