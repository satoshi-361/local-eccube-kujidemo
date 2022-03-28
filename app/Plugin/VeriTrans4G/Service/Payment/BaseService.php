<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Payment;

use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;
use Plugin\VeriTrans4G\Entity\Vt4gOrderPayment;
use Plugin\VeriTrans4G\Entity\Vt4gOrderLog;
use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Service\MailService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Security\Core\Encoder\PasswordEncoder;
use Eccube\Service\CartService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;


/**
 * 決済処理 基底クラス
 */
class BaseService
{
    public $mailData = [];
    public $logData  = [];
    public $timeKey = '';
    public $isPaymentRecv = false;

    /**
     * コンテナ
     */
    protected $container;

    /**
     * エンティティーマネージャー
     */
    protected $em;

    /**
     * 汎用処理用サービス
     */
    protected $util;

    /**
     * VT用固定値配列
     */
    protected $vt4gConst;

    /**
     * MDK Logger
     */
    protected $mdkLogger;

    /**
     * パスワードエンコーダ
     */
    protected $passwordEncoder;

    /**
     * カートサービス
     */
    protected $cartService;

    /**
     * メールサービス
     */
    protected $mailService;

    /**
     * 決済結果
     */
    protected $paymentResult;

    /**
     * 決済エラー時デフォルトメッセージ
     */
    protected $defaultErrorMessage;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * コンストラクタ
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function __construct(
        ContainerInterface $container,
        PurchaseFlow $shoppingPurchaseFlow,
        PasswordEncoder $passwordEncoder,
        CartService $cartService,
        MailService $mailService
    ){
        $this->container = $container;
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->util = $container->get('vt4g_plugin.service.util');
        $this->passwordEncoder = $passwordEncoder;
        $this->cartService = $cartService;
        $this->mailService = $mailService;

        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $this->container->getParameter('vt4g_plugin.const');
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->defaultErrorMessage = trans('vt4g_plugin.payment.shopping.error');
    }

    /**
     * 各決済方法の共通チェック処理
     *
     * @param  object         $cartService CartServiceクラスインスタンス
     * @param  object         $order       注文データ
     * @return boolean|object              チェック結果|ビューレスポンス
     */
    public function checkPaymentData($cartService, $order)
    {
        // 注文データが存在しない場合
        if (empty($order)) {
            return $this->makeErrorResponse();
        }

        // 注文ステータスを取得
        $orderStatus = $order->getOrderStatus()->getId();
        // 新規受付
        $isNew = $orderStatus == OrderStatus::NEW;
        // 入金済み
        $isPaid = $orderStatus == OrderStatus::PAID;
        // 決済処理中
        $isPending = $orderStatus == OrderStatus::PENDING;

        // 新規受付 or 入金済み or 決済処理中以外の場合はエラー画面を表示
        return !($isNew || $isPaid || $isPending)
            ? $this->makeErrorResponse()
            : true;
    }

    /**
     * 管理画面からの決済操作時の更新処理
     *
     * @param  object $orderPayment    決済データ
     * @param  object $operationResult MDKリクエスト結果データ
     * @param  object $orderStatus 注文ステータス
     * @return void
     */
    public function updateByAdmin($orderPayment, $operationResult, $orderStatus='')
    {
        $payStatus = $operationResult['payStatus'];
        $hasCaptureTotal = isset($operationResult['captureTotal']);
        $hasCardAmout = isset($operationResult['card_amount']);

        if ($hasCaptureTotal) {
            $memo10 = unserialize($orderPayment->getMemo10());
            $memo10['captureTotal'] = $operationResult['captureTotal'];

            // 返金操作 かつ 残りの金額がある場合は「売上」・ない場合は「取消」
            if ($payStatus == $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']['VALUE']) {
                $payStatus = $operationResult['captureTotal'] == 0
                    ? $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL']['VALUE']
                    : $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE'];
            }
            // 取消の場合 売上時金額を0にする
            if ($payStatus == $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL']['VALUE']) {
                $memo10['captureTotal'] = 0;
            }
        }

        if ($operationResult['isNewly'] ?? false) {
            $orderPayment->setMemo01($operationResult['orderId']);
            if (isset($operationResult['customerId'])) {
                $orderPayment->setMemo02($operationResult['customerId']);
            }
            $orderPayment->setMemo06(serialize($this->mailData));

            if($hasCardAmout) {
                $memo10 = unserialize($orderPayment->getMemo10());
                $memo10['card_amount'] = $operationResult['card_amount'];
                $memo10['card_type'] = $operationResult['card_type'];
                $orderPayment->setMemo07($operationResult['cardNumber']);
            }
        }

        $orderPayment->setMemo04($payStatus);
        $orderPayment->setMemo05(serialize($operationResult));
        if ($hasCaptureTotal || $hasCardAmout) {
            $orderPayment->setMemo10(serialize($memo10));
        }
        $this->em->persist($orderPayment);

        //決済ステータスが売上に更新し、注文ステータスが新規受付の場合は注文ステータスを入金済に変更する。
        if($orderStatus === OrderStatus::NEW && $payStatus === $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']){
            $orderStatus = $this->em->getRepository(OrderStatus::class)->find(OrderStatus::PAID);
            if(!empty($orderStatus)){
                $this->em->getRepository(Order::class)->changeStatus($orderPayment->getOrderId(), $orderStatus);                
            }
        }

        // ログ情報
        $orderLog = new Vt4gOrderLog;
        $orderLog->setOrderId($orderPayment->getOrderId());
        $orderLog->setVt4gLog(serialize($this->logData));

        $this->em->persist($orderLog);
    }

    /**
     * MDK仕様の受注番号を返す
     *
     * @param  integer $orderId 受注番号
     * @return string         MDK仕様の受注番号
     */
    protected function getMdkOrderId($orderId)
    {
        $pluginSetting = $this->util->getPluginSetting();
        $orderIdPrefix = $pluginSetting['vt4g_order_id_prefix'];

        // ランダム文字列を生成
        $random = bin2hex($this->passwordEncoder->createSalt(4));

        // プレフィックス + ランダム文字列 + 0埋めされた受注番号
        return $orderIdPrefix.$random.$this->util->zeroPadding($orderId, 11);
    }

    /**
     * 注文ステータスの変更
     *
     * @param  object  $order       注文データ
     * @param  integer $withCapture 処理区分
     * @return boolean              変更したかどうか
     */
    protected function setNewOrderStatus($order, $withCapture)
    {
        $paymentMethod = $this->util->getPaymentMethod($order->getPayment()->getId());

        // 各決済の新規注文時の決済ステータスを判定
        $status = $this->getNewOrderStatus($paymentMethod, $withCapture);

        // ステータスの設定が存在しなければエラー
        if (empty($status)) {
            return false;
        }

        // ステータスの登録
        $orderStatus = $this->em->getRepository(OrderStatus::class)->find($status);
        if (empty($orderStatus)) {
            return false;
        }
        $this->em->getRepository(Order::class)->changeStatus($order->getId(), $orderStatus);

        return true;
    }

    /**
     * レスポンスの初期化
     *
     * @return array 初期化されたレスポンス
     */
    protected function initPaymentResult()
    {
        $this->paymentResult = [
            'isOK'        => false,
            'vResultCode' => 'error',
            'mErrMsg'     => trans('vt4g_plugin.payment.shopping.system.error')
        ];

        return $this->paymentResult;
    }

    /**
     * 受注完了処理
     *
     * @param  Order  $order    注文データ
     * @param  array  $payment  決済データ
     * @param  array  $logData  ログ情報
     * @param  array  $mailData メール情報
     * @param  string $token    MDKトークン
     * @return void
     */
    protected function completeOrder($order, $payment, $logData = [], $mailData = [], $token = '')
    {

        // メッセージ追加(結果通知のときは画面を出さないので追加しない)
        if (!$this->isPaymentRecv) {
            $this->addOrderCompleteMessage($order);
        }

        try {
            // 受注更新
            $this->updateOrder($order, $payment, $logData, $mailData, $token);
        } catch (\Doctrine\DBAL\DBALException $e) {

            $msg = $e->getMessage();
            if(strpos($msg,'deadlock') !== false || strpos($msg,'Deadlock') !== false) {
                $this->isPaymentRecv
                    ? $this->mdkLogger->info(sprintf(trans('vt4g_plugin.db.deadlock'),'結果通知'))
                    : $this->mdkLogger->info(sprintf(trans('vt4g_plugin.db.deadlock'),'決済センター戻り'));
            } else {
                $this->mdkLogger->error($e->getMessage());
                throw $e;
            }

        }


        // カートを削除
        $cartService = $this->cartService;
        $cartService->clear();

        $this->em->commit();

        // メール送信
        if ($this->shouldSendMail($order)) {
            $this->sendOrderMail($order);
        }
    }

    /**
     * 決済情報への追加処理
     *
     * @param  Order            $order    注文データ
     * @param  array            $payment  決済データ
     * @param  array            $logData  ログ情報
     * @param  array            $mailData メール情報
     * @param  string           $token    MDKトークン
     * @return Vt4gOrderPayment           決済情報
     */
    protected function setOrderPayment($order, $payment, $logData = [], $mailData = [], $token = '')
    {
        $paymentMethod = $this->em->getRepository(Vt4gPaymentMethod::class)->find($order->getPayment()->getId());
        $orderPayment = $this->util->getOrderPayment($order->getId());
        $customer = $order->getCustomer();

        // 決済情報が存在しない場合は新規作成
        if (empty($orderPayment)) {
            $orderPayment = new Vt4gOrderPayment;
        }

        if (empty($payment)) {
            return $orderPayment;
        }

        // 各カラムに値を設定
        $orderPayment->setOrderId($order->getId());
        $orderPayment->setMemo01($payment['orderId']);
        if (!empty($customer)) {
            $orderPayment->setMemo02($customer->getId());
        }
        $orderPayment->setMemo03($paymentMethod->getMemo03());
        if (isset($payment['payStatus'])) {
            $orderPayment->setMemo04($payment['payStatus']);
        }
        $orderPayment->setMemo05(serialize($payment));
        if (!empty($mailData)) {
            $orderPayment->setMemo06(serialize($mailData));
        }
        if (isset($payment['cardNumber'])) {
            $orderPayment->setMemo07($payment['cardNumber']);
        }

        if (!empty($token)) {
            $orderPayment->setMemo08($token);
        }

        if (isset($payment['captureTotal'])) {
            $memo10 = unserialize($orderPayment->getMemo10());
            $memo10['captureTotal'] = $payment['captureTotal'];
            $orderPayment->setMemo10(serialize($memo10));
        }

        if (isset($payment['refundFlg'])) {
            $memo10 = unserialize($orderPayment->getMemo10());
            $memo10['refundFlg'] = $payment['refundFlg'];
            $orderPayment->setMemo10(serialize($memo10));
        }

        if (isset($payment['lastPayStatus'])) {
            $memo10 = unserialize($orderPayment->getMemo10());
            $memo10['lastPayStatus'] = $payment['lastPayStatus'];
            $orderPayment->setMemo10(serialize($memo10));
        }

        if (isset($payment['cardType'])) {
            $memo10 = [];
            if (unserialize($orderPayment->getMemo10()) === false) {
                $memo10['card_amount'] = '';
            } else {
                $memo10 = unserialize($orderPayment->getMemo10());
            }

            $memo10['card_type'] = $payment['cardType'];
            $orderPayment->setMemo10(serialize($memo10));
        }

        if (isset($payment['cardAmount'])) {
            $memo10 = unserialize($orderPayment->getMemo10());
            $memo10['card_amount'] = $payment['cardAmount'];
            $orderPayment->setMemo10(serialize($memo10));
        }

        // 少額与信の使用有無を記録
        if (isset($payment['useFewCredit'])) {
            $memo10 = unserialize($orderPayment->getMemo10());
            $memo10['useFewCredit'] = $payment['useFewCredit'];
            $orderPayment->setMemo10(serialize($memo10));
        }

        // かんたん決済の利用有無を記録
        if (isset($payment['doRegistReTradeCardinfo'])) {
            $memo10 = unserialize($orderPayment->getMemo10());
            $memo10['doRegistReTradeCardinfo'] = $payment['doRegistReTradeCardinfo'];
            $orderPayment->setMemo10(serialize($memo10));
        }

        // カード名義を記録
        if (isset($payment['cardName'])) {
            $memo10 = unserialize($orderPayment->getMemo10());
            $memo10['cardName'] = $payment['cardName'];
            $orderPayment->setMemo10(serialize($memo10));
        }

        $this->em->persist($orderPayment);
        $this->em->flush();

        return $orderPayment;
    }

    /**
     * 決済ログテーブルにログを追加
     *
     * @param  Order $order    注文データ
     */
    protected function setOrderLog($order)
    {
        if (empty($this->logData)) {
            return false;
        }
        $orderLogMethod = new Vt4gOrderLog;
        $orderLogMethod->setOrderId($order->getId());
        $orderLogMethod->setVt4gLog(serialize($this->logData));

        $this->em->persist($orderLogMethod);
        $this->em->flush();
        return $orderLogMethod;
    }

    /**
     * 注文データの更新
     *
     * @param  Order  $order    注文データ
     * @param  array  $payment  決済データ
     * @param  array  $logData  ログ情報
     * @param  array  $mailData メール情報
     * @param  string $token    MDKトークン
     * @return void
     */
    protected function updateOrder($order, $payment, $logData = [], $mailData = [], $token = '')
    {
        $orderPayment = $this->util->getOrderPayment($order->getId());

        // 注文情報の確定(決済ステータスが値なしの場合のみ実行)
        if (empty($orderPayment) || empty($orderPayment->getMemo04())) {
            $this->purchaseFlow->commit($order, new PurchaseContext());
        }

        // 決済情報の更新
        $this->setOrderPayment($order, $payment, $logData, $mailData, $token);
        // 決済ログテーブルにログを追加
        $this->setOrderLog($order);

        // ステータスの更新
        $withCapture = isset($payment['withCapture']) ? $payment['withCapture'] : 0;
        $this->setNewOrderStatus($order, $withCapture);

        $this->em->flush();
    }

    /**
     * メールタイトルの設定
     *
     * @param  string $title タイトル
     * @return void
     */
    protected function setMailTitle($title)
    {
        $this->mailData['title'] = $title;
    }

    /**
     * メールの説明
     *
     * @param string $explan 説明文
     * @return void
     */
    public function setMailExplan($explan)
    {
        $this->mailData['explan'] = $explan;
    }

    /**
     * メール情報の追加
     *
     * @param  string $title   タイトル
     * @param  string $content 本文
     * @return void
     */
    protected function setMailInfo($title, $content)
    {
        $this->mailData['message'][] = compact('title', 'content');
    }

    /**
     * メール情報の追加（広告）
     *
     * @param string $title    タイトル
     * @param string $content  本文
     * @return void
     */
    public function setMailAd($vResultCode, $tradUrl)
    {
        $this->mailData['message'][] = array(
                'vResultCode'=>$vResultCode,
                'tradUrl'=>$tradUrl,
        );
    }

    /**
     * 決済方法ごとのメール設定を反映
     *
     * @param  object $paymentMethod PaymentMethodクラスインスタンス
     * @return void
     */
    protected function setMailAdminSetting($paymentMethod)
    {
        if (empty($paymentMethod)) {
            return;
        }

        $memo05 = unserialize($paymentMethod->getMemo05());
        $title  = $memo05['order_mail_title1'] ?? null;
        $body   = $memo05['order_mail_body1'] ?? null;

        // タイトル・本文が両方設定されている場合のみ追加
        if (isset($title, $body)) {
            $this->setMailInfo($title, $body);
        }
    }

    /**
     * ログ情報の先頭部分
     *
     * @param  integer $payId         決済方法の内部ID
     * @param  string  $timeKey       ログキー(現在時間)
     * @param  array   $paymentResult 決済リクエスト結果データ
     * @return void
     */
    public function setLogHead($payId, $timeKey = '', $paymentResult = null)
    {
        if (is_null($paymentResult)) {
            $paymentResult = $this->paymentResult;
        }

        $this->timeKey = $timeKey;
        $this->logData = array();
        $pay_name        = $this->util->getPayName($payId);
        $pay_status_name = $this->util->getPaymentStatusName($paymentResult['payStatus']);

        if ($paymentResult['mStatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $msg = '成功';
        } elseif ($paymentResult['mStatus'] == $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['PENDING']) {
            $msg = '保留';
        } else {
            $msg = '失敗';
        }

        $this->setLogInfo('決済取引ID', $paymentResult['orderId']);
        $this->setLogInfo($pay_name, "[$pay_status_name]" . $msg);
    }

    /**
     * ログ情報の追加
     *
     * @param  string $title   タイトル
     * @param  string $content 出力内容
     * @return void
     */
    protected function setLogInfo($title, $content)
    {
        if (empty($this->timeKey)) {
            $this->timeKey = date('Y-m-d H:i:s');
        }

        $this->logData[$this->timeKey][] = compact('title', 'content');
    }

    /**
     * ロールバック
     *
     * @param  Order $order 注文データ
     * @return void
     */
    public function rollback($order, $useTransaction = true)
    {
        // 購入処理中の場合は何も行わない
        if ($order->getOrderStatus()->getId() === OrderStatus::PROCESSING) {
            return;
        }

        if ($useTransaction) {
            $this->em->beginTransaction();
        }

        // 決済データの削除
        $orderPayment = $this->em->getRepository(Vt4gOrderPayment::class)->find($order->getId());
        if (!empty($orderPayment)) {
            $this->em->remove($orderPayment);
        }

        // EC-CUBE側 購入処理のロールバック
        $this->purchaseFlow->rollback($order, new PurchaseContext());

        // 購入処理中へ更新
        $order->setOrderStatus($this->em->getRepository(OrderStatus::class)->find(OrderStatus::PROCESSING));

        $this->em->flush();

        if ($useTransaction) {
            $this->em->commit();
        }
    }

    /**
     * 各決済の新規の注文ステータスを取得
     * ネットバンクはvt4g_shopping_payment_completeアクセス時に値を新規受付に更新する
     *
     * @param  object  $paymentMethod 決済方法データ
     * @param  integer $withCapture   処理区分
     * @return integer                注文ステータス
     */
    private function getNewOrderStatus($paymentMethod, $withCapture)
    {
        switch ($paymentMethod->getMemo03()) {
            case $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']:  // クレジットカード決済
            case $this->vt4gConst['VT4G_PAYTYPEID_UPOP']:    // 銀聯
            case $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN']: // 楽天
            case $this->vt4gConst['VT4G_PAYTYPEID_RECRUIT']: // リクルート
            case $this->vt4gConst['VT4G_PAYTYPEID_LINEPAY']: // ライン
            case $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL']:  // PayPal
                return $withCapture === 1
                    ? OrderStatus::PAID // 入金済み
                    : OrderStatus::NEW; // 新規受付
            case $this->vt4gConst['VT4G_PAYTYPEID_CVS']:    // コンビニ決済
            case $this->vt4gConst['VT4G_PAYTYPEID_ATM']:    // ATM決済
                return OrderStatus::NEW; // 新規受付
            case $this->vt4gConst['VT4G_PAYTYPEID_BANK']:   // ネットバンク決済
                return OrderStatus::PENDING; // 決済処理中
            case $this->vt4gConst['VT4G_PAYTYPEID_ALIPAY']:  // Alipay
                return OrderStatus::PAID; // 入金済み
        }

        return '';
    }

    /**
     * 各決済の新規の決済ステータスを取得
     * ネットバンクはvt4g_shopping_payment_completeアクセス時に値を申込に更新する
     *
     * @param  object  $paymentMethod 決済方法データ
     * @param  integer $withCapture   処理区分
     * @return integer $status        決済ステータス
     */
    public function getNewPaymentStatus($paymentMethod, $withCapture = 0)
    {
        $status = '';
        switch ($paymentMethod->getMemo03()) {
            case $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']:  // クレジット
            case $this->vt4gConst['VT4G_PAYTYPEID_UPOP']:    // 銀聯
            case $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN']: // 楽天
            case $this->vt4gConst['VT4G_PAYTYPEID_RECRUIT']: // リクルート
            case $this->vt4gConst['VT4G_PAYTYPEID_LINEPAY']: // ライン
            case $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL']:  // PayPal
                $status = $withCapture === 1
                    ? $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']
                    : $this->vt4gConst['VT4G_PAY_STATUS']['AUTH']['VALUE'];
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_CVS']:  // コンビニ
            case $this->vt4gConst['VT4G_PAYTYPEID_ATM']:  // ATM
                $status = $this->vt4gConst['VT4G_PAY_STATUS']['REQUEST']['VALUE']; // 申込
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_BANK']: // ネットバンク
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_ALIPAY']: // アリペイ
                $status = $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']; // 売上
                break;
            default:
                break;
        }
        return $status;
    }

    /**
     * 決済時の処理区分を取得
     * @param  object $order 注文データ
     * @return integer||null 処理区分、取得できない場合はnull
     */
    public function getWithCapture($order)
    {
        $orderPayment = $this->util->getOrderPayment($order);
        $memo05 = method_exists($orderPayment, 'getMemo05')
                ? unserialize($orderPayment->getMemo05())
                : null;

        if (isset($memo05['withCapture'])) {
            return $memo05['withCapture'];
        } else {
            return null;
        }
    }

    /**
     * 決済エラー用レスポンスを生成
     *
     * @param  string   $errorMessage エラーメッセージ
     * @return Response               ビューレスポンス
     */
    public function makeErrorResponse($errorMessage = null)
    {
        $engine = $this->container->get('templating');
        $content = $engine->render('error.twig', [
            'error_title'   => '決済エラー',
            'error_message' => $errorMessage ?? trans('vt4g_plugin.payment.shopping.not.order'),
        ]);

        return new Response($content);
    }

    /**
     * 決済完了メッセージを取得
     *
     * @param  array   $mailData メール情報
     * @param  boolean $forMail  メール用テンプレートを使用するかどうか
     * @return string            決済完了メッセージ
     */
    public function getCompleteMessage($mailData = [], $forMail = false)
    {
        if (empty($mailData)) {
            $mailData = $this->mailData;
        }

        // メール情報が未設定 もしくは メッセージが未設定の場合は何もしない
        if (empty($mailData) || !array_key_exists('message', $mailData)) {
            return '';
        }
        $engine = $this->container->get('twig');
        $param = ['arrOther' => $mailData];
        $dir = $this->vt4gConst['VT4G_CODE'].'/Resource/template/default/';
        $path = $forMail
            ? 'Mail/'
            : 'Shopping/';
        $filename = $forMail
            ? 'vt4g_order_complete.twig'
            : 'vt4g_payment_complete.twig';
        $template = $dir.$path.$filename;

        return $engine->render($template, $param, null);
    }

    /**
     * 完了メッセージを追加
     *
     * @param  Order &$order Orderクラスインスタンス
     * @return void
     */
    protected function addOrderCompleteMessage(&$order)
    {
        $paymentId = $order->getPayment()->getId();
        $paymentMethod = $this->util->getPaymentMethod($paymentId);
        $paymentMethodId = $paymentMethod->getMemo03();
        // ネットバンクのみ完了画面メッセージ固定
        $bankMailData = [];
        if ($paymentMethodId == $this->vt4gConst['VT4G_PAYTYPEID_BANK']) {
            $bankMailData['title'] = $this->mailData['title'];
            $memo05 = unserialize($paymentMethod->getMemo05());
            $title = $memo05['order_mail_title1'] ?? null;
            $content = $memo05['order_mail_body1'] ?? null;
            // タイトル・本文が両方設定されている場合のみ追加
            if (isset($title, $content)) {
                $bankMailData['message'][] = compact('title', 'content');
            }
        }
        $message = !empty($bankMailData)
            ? $this->getCompleteMessage($bankMailData)
            : $this->getCompleteMessage();

        if (!empty($message)) {
            // 注文完了画面にメッセージを追加
            $order->appendCompleteMessage($message);
        }
    }

    /**
     * メール送信
     *
     * @param  Order &$order Orderクラスインスタンス
     * @return void
     */
    public function sendOrderMail(&$order)
    {
        $this->mdkLogger->info(trans('vt4g_plugin.payment.mail.start'));
        $this->mailService->sendOrderMail($order);
        $this->mdkLogger->info(trans('vt4g_plugin.payment.mail.complete'));

        // 本体側の処理でflushが実行されないためここで実行
        $this->em->flush();
    }

    /**
     * メールを送信するか判定
     *
     * @param  Order   $order 注文データ
     * @return boolean        メール送信フラグ
     */
    protected function shouldSendMail($order)
    {
        $pluginSetting = $this->util->getPluginSetting();
        $paymentId = $order->getPayment()->getId();
        $paymentInfo = $this->util->getPaymentMethodInfo($paymentId);
        $paymentId = $this->util->getPaymentMethod($paymentId)->getMemo03();
        // 決済の判定
        $isCredit = $paymentId == $this->vt4gConst['VT4G_PAYTYPEID_CREDIT'];
        $isATM    = $paymentId == $this->vt4gConst['VT4G_PAYTYPEID_ATM'];
        $isCVS    = $paymentId == $this->vt4gConst['VT4G_PAYTYPEID_CVS'];
        $isBank   = $paymentId == $this->vt4gConst['VT4G_PAYTYPEID_BANK'];

        // クレジットカード決済 かつ 本人認証なしの場合
        // ATM決済の場合
        // コンビニ決済の場合
        if ($isCredit && !$paymentInfo['mpi_flg']
            || $isATM
            || $isCVS
        ) {
            return true;
        }

        // ネットバンク決済の場合
        // 注文完了メール送信タイミングが決済申込完了時ではない場合
        if ($isBank) {
            return $paymentInfo['mailTiming'] != $this->vt4gConst['VT4G_MAIL_TIMING']['BANK']['ON_PAYMENT'] ? false : true;
        }

        // 注文完了メール送信タイミングが注文完了画面表示時ではない場合
        if ($pluginSetting['order_mail_timing_flg'] != $this->vt4gConst['VT4G_MAIL_TIMING']['ORDER']['ON_COMPLETE']) {
            return false;
        }
        // 結果(入金)通知の場合は結果通知プログラムで判定するのでfalse
        if ($this->isPaymentRecv) {
            return false;
        }

        return true;
    }

    /**
     * 決済処理の実行
     *
     * @param  array $payload 決済処理に使用するデータ
     * @return array          決済処理結果データ
     */
    protected function operateNewly($payload)
    {
        $paymentId     = $payload['order']->getPayment()->getId();
        $payId         = $payload['orderPayment']->getMemo03();
        $payName       = $this->util->getPayName($payId);
        $paymentInfo   = $this->util->getPaymentMethodInfo($paymentId);
        $paymentMethod = $this->util->getPaymentMethod($paymentId);
        $serviceOptionType = ($payId == $this->vt4gConst['VT4G_PAYTYPEID_CVS'])
            ? $payload['inputs']->get('conveni')
            : null;

        $this->mdkLogger->info(
            sprintf(
                trans('vt4g_plugin.admin.order.credit.newly.start'),
                $payName
            )
        );

        // レスポンス初期化
        $operationResult = $this->initPaymentResult();

        switch ($payId) {
            case $this->vt4gConst['VT4G_PAYTYPEID_CVS']:
                if (is_null($serviceOptionType)) {
                    return $operationResult;
                }
                $mdkRequest = new \CvsAuthorizeRequestDto();
                $this->setRequestParam($mdkRequest, $payload['order'], $paymentInfo, $serviceOptionType);
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_ATM']:
                $mdkRequest = new \BankAuthorizeRequestDto();
                $this->setRequestParam($mdkRequest, $payload['order'], $paymentInfo);
                break;
            default:
                return $operationResult;
        }

        $mdkTransaction = new \TGMDK_Transaction();
        $mdkResponse = $mdkTransaction->execute($mdkRequest);

        // レスポンス検証
        if (!isset($mdkResponse)) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.mdk.error'));
            $operationResult['message'] = trans('vt4g_plugin.payment.shopping.error');
            return $operationResult;
        }

        // 結果コード
        $operationResult['mStatus'] = $mdkResponse->getMStatus();
        // 詳細コード
        $operationResult['vResultCode'] = $mdkResponse->getVResultCode();
        // エラーメッセージ
        $operationResult['mErrMsg'] = $mdkResponse->getMErrMsg();

        // 異常終了レスポンスの場合
        if ($operationResult['mStatus'] !== $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $operationResult['message']  = $operationResult['vResultCode'].':';
            $operationResult['message'] .= $operationResult['mErrMsg'];

            return $operationResult;
        }

        $operationResult['isOK']      = true;
        // 有効期限
        $operationResult['limitDate'] = $mdkRequest->getPayLimit();
        // 取引ID
        $operationResult['orderId']   = $mdkResponse->getOrderId();
        // trAd URL
        $operationResult['tradUrl']   = $mdkResponse->getTradUrl();
        // 決済ステータス
        $operationResult['payStatus'] = $this->getNewPaymentStatus($paymentMethod);
        // 決済金額
        $operationResult['captureTotal'] = floor($payload['order']->getPaymentTotal());
        switch ($payId) {
            case $this->vt4gConst['VT4G_PAYTYPEID_CVS']: // コンビニ決済
                // 電話番号
                $operationResult['telNo']        = $mdkRequest->getTelNo();
                // 受付番号
                $operationResult['receiptNo']    = $mdkResponse->getReceiptNo();
                // 払込URL(一部店舗のみ)
                $operationResult['haraikomiUrl'] = $mdkResponse->getHaraikomiUrl();
                // 決済サービスオプション
                $operationResult['serviceOptionType'] = $serviceOptionType;

                $cvsName = $this->util->getConveniNameByCode($serviceOptionType);
                $cvsData = $this->translateRecpNo($serviceOptionType, $operationResult['receiptNo'], $operationResult['telNo'], true);

                // ログの生成
                $this->setLogHead($payId, '', $operationResult);
                $this->setLogInfo('店舗', $cvsName);
                // コンビニ受付番号等
                foreach ($cvsData as $key => $val) {
                    $this->setLogInfo($key, $val);
                }
                $this->setLogInfo('支払期限', $operationResult['limitDate']);
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_ATM']: // ATM決済
                // 収納期間番号
                $operationResult['shunoKikanNo'] = $mdkResponse->getShunoKikanNo();
                // お客様番号
                $operationResult['customerNo']   = $mdkResponse->getCustomerNo();
                // 確認番号
                $operationResult['confirmNo']    = $mdkResponse->getConfirmNo();

                // ログ情報の設定
                $this->setLog($payload['order'], $operationResult);
                break;
            default:
                break;
        }

        return $operationResult;
    }

    /**
     * キャンセル処理の実行
     *
     * @param  array $payload キャンセル処理に使用するデータ
     * @return array          キャンセル処理結果データ
     */
    public function operateCancel($payload)
    {
        $payId   = $payload['orderPayment']->getMemo03();
        $payName = $this->util->getPayName($payId);

        $isRakuten = $payId == $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN'];
        $payStatusName = $isRakuten
            ? $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL_REQUEST']['LABEL']
            : $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL']['LABEL'];
        $this->mdkLogger->info("管理者{$payName}[{$payStatusName}]通信実行");
        // 決済申込時の結果データ
        $prevPaymentResult = unserialize($payload['orderPayment']->getMemo05());
        // レスポンス初期化
        $operationResult = $this->initPaymentResult();
        // キャンセル対象の取引ID
        $paymentOrderId = $payload['orderPayment']->getMemo01();
        // memo01から取得できない場合
        if (empty($paymentOrderId)) {
            // 決済申込時のレスポンスから取得できない場合
            if (empty($prevPaymentResult['orderId'])) {
                $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.mdk.error'));
                $operationResult['message'] = trans('vt4g_plugin.payment.shopping.error');
                return [$operationResult, []];
            }
            // 決済申込時の結果から取得
            $paymentOrderId = $prevPaymentResult['orderId'];
        }

        switch ($payId) {
            case $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']: // クレジットカード決済
                $mdkRequest = new \CardCancelRequestDto();
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_CVS']: // コンビニ決済
                $mdkRequest = new \CvsCancelRequestDto();
                // 決済サービスオプションを設定
                $mdkRequest->setServiceOptionType($prevPaymentResult['serviceOptionType']);
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_UPOP']: // 銀聯
                $mdkRequest = new \UpopCancelRequestDto();
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN']: // 楽天
                $mdkRequest = new \RakutenCancelRequestDto();
                $arrMemo10 = unserialize($payload['orderPayment']->getMemo10());
                $arrMemo10['lastPayStatus'] = $payload['orderPayment']->getMemo04();
                $payload['orderPayment']->setMemo10(serialize($arrMemo10));
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RECRUIT']: // リクルート
                $mdkRequest = new \RecruitCancelRequestDto();
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_LINEPAY']: // ライン
                $mdkRequest = new \LinepayCancelRequestDto();
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_ALIPAY']: // アリペイ
                $paymentId   = $payload['order']->getPayment()->getId();
                $paymentInfo = $this->util->getPaymentMethodInfo($paymentId);
                $arrMemo10   = unserialize($payload['orderPayment']->getMemo10());

                $mdkRequest = new \AlipayRefundRequestDto();
                $mdkRequest->setAmount($arrMemo10['captureTotal']);
                $mdkRequest->setReason($paymentInfo['refund_reason']);
                $operationResult['amount']        = $arrMemo10['captureTotal'];
                $operationResult['refund_reason'] = $paymentInfo["refund_reason"];
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL']: // paypal
                $mdkRequest = new \PayPalCancelRequestDto();
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_CARRIER']: // carrier
                print_r('carrier cancel'); exit;
                $mdkRequest = new \CarrierCancelRequestDto();
                break;
            default:
                return [$operationResult, []];
        }

        // キャンセル対象の取引IDを設定
        $mdkRequest->setOrderId($paymentOrderId);

        $mdkTransaction = new \TGMDK_Transaction();
        $mdkResponse = $mdkTransaction->execute($mdkRequest);

        // レスポンス検証
        if (!isset($mdkResponse)) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.mdk.error'));
            $operationResult['message'] = trans('vt4g_plugin.payment.shopping.error');
            return [$operationResult, []];
        }

        // 結果コード
        $operationResult['mStatus'] = $mdkResponse->getMStatus();
        // 詳細コード
        $operationResult['vResultCode'] = $mdkResponse->getVResultCode();
        // エラーメッセージ
        $operationResult['mErrMsg'] = $mdkResponse->getMErrMsg();

        // 正常終了レスポンス以外の場合
        if ($operationResult['mStatus'] !== $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $operationResult['message']  = $operationResult['vResultCode'].':';
            $operationResult['message'] .= $operationResult['mErrMsg'];

            return [$operationResult, $mdkResponse];
        }

        $operationResult['isOK']         = true;
        // 取引ID
        $operationResult['orderId']      = $mdkResponse->getOrderId();
        // 決済サービスタイプ
        $operationResult['serviceType']  = $mdkResponse->getServiceType();
        // 決済ステータス
        $operationResult['payStatus']    = $isRakuten
            ? $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL_REQUEST']['VALUE']
            : $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL']['VALUE'];
        // 売上金額
        $operationResult['captureTotal'] = floor($payload['order']->getPaymentTotal());

        // ログの生成
        $this->setLogHead($payload['orderPayment']->getMemo03(), '', $operationResult);

        return [$operationResult, $mdkResponse];
    }

    /**
     * 売上処理
     *
     * @param  array $payload 売上処理に使用するデータ
     * @return array          売上処理結果データ
     */
    public function operateCapture($payload)
    {
        $payId   = $payload['orderPayment']->getMemo03();
        $payName = $this->util->getPayName($payId);
        $isRakuten = $payId == $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN'];
        $payStatusName = $isRakuten
            ? $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE_REQUEST']['LABEL']
            : $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['LABEL'];
        $this->mdkLogger->info("管理者{$payName}[{$payStatusName}]通信実行");
        // 決済申込時の結果データ
        $prevPaymentResult = unserialize($payload['orderPayment']->getMemo05());
        // レスポンス初期化
        $operationResult = $this->initPaymentResult();
        // リクエストの取得
        switch ($payId) {
            case $this->vt4gConst['VT4G_PAYTYPEID_UPOP']: // 銀聯
                $mdkRequest = new \UpopCaptureRequestDto();
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN']: // 楽天
                $mdkRequest = new \RakutenCaptureRequestDto();
                $arrMemo10 = unserialize($payload['orderPayment']->getMemo10());
                $arrMemo10['lastPayStatus'] = $payload['orderPayment']->getMemo04();
                $payload['orderPayment']->setMemo10(serialize($arrMemo10));
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RECRUIT']: // リクルート
                $mdkRequest = new \RecruitCaptureRequestDto();
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_LINEPAY']: // ライン
                $mdkRequest = new \LinepayCaptureRequestDto();
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL']: // paypal
                $mdkRequest = new \PayPalCaptureRequestDto();
                $mdkRequest->setAction('capture');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_CARRIER']: // carrier
                print_r('carrier capture'); exit;
                $mdkRequest = new \CarrierCaptureRequestDto();
                break;
            default:
                return [$operationResult, []];
        }

        $mdkRequest->setAmount(floor($payload['order']->getPaymentTotal()));
        $mdkRequest->setOrderId($prevPaymentResult['orderId']);

        $objTransaction = new \TGMDK_Transaction();
        $mdkResponse = $objTransaction->execute($mdkRequest);
        if (!isset($mdkResponse)) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.mdk.error'));
            $operationResult['message'] = trans('vt4g_plugin.payment.shopping.error');
            return [$operationResult, []];
        }

        // 結果コード
        $operationResult['mStatus']     = $mdkResponse->getMStatus();
        // 詳細コード
        $operationResult['vResultCode'] = $mdkResponse->getVResultCode();
        // エラーメッセージ
        $operationResult['mErrMsg']     = $mdkResponse->getMErrMsg();

        // 正常終了レスポンス以外の場合
        if ($operationResult['mStatus'] !== $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $operationResult['message']  = $operationResult['vResultCode'].':';
            $operationResult['message'] .= $operationResult['mErrMsg'];

            return [$operationResult, $mdkResponse];
        }

        $operationResult['isOK']         = true;
        // 取引ID
        $operationResult['orderId']      = $mdkResponse->getOrderId();
        // 決済サービスタイプ
        $operationResult['serviceType']  = $mdkResponse->getServiceType();
        // 決済ステータス
        $operationResult['payStatus']    = $isRakuten
            ? $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE_REQUEST']['VALUE']
            : $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE'];
        // 売上金額
        $operationResult['captureTotal'] = floor($payload['order']->getPaymentTotal());
        // ログの生成
        $this->setLogHead($payId, '', $operationResult);

        return [$operationResult, $mdkResponse];
    }

    /**
     * 返金処理
     *
     * @param  array $payload 返金処理に使用するデータ
     * @return array          返金処理結果データ
     */
    public function operateRefund($payload)
    {
        $payId   = $payload['orderPayment']->getMemo03();
        $payName = $this->util->getPayName($payId);
        $isRakuten = $payId == $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN'];
        if ($isRakuten) {
            $payStatusName = $this->vt4gConst['VT4G_PAY_STATUS']['REDUCTION_REQUEST']['LABEL'];
        } else if (isset($payload['refundAll']) && $payload['refundAll']) {
            $payStatusName = $this->vt4gConst['VT4G_OPERATION_NAME']['REFUND_ALL'];
        } else {
            $payStatusName = $this->vt4gConst['VT4G_OPERATION_NAME']['REFUND'];
        }
        $this->mdkLogger->info("管理者{$payName}[{$payStatusName}]通信実行");
        // 決済申込時のレスポンス
        $prevPaymentResult = unserialize($payload['orderPayment']->getMemo05());
        // 減額
        $amount    = 0;
        $arrMemo10 = unserialize($payload['orderPayment']->getMemo10());
        if (isset($arrMemo10['captureTotal'])) {
            if (isset($payload['refundAll']) && $payload['refundAll']) {
                $amount = $arrMemo10['captureTotal'];
            } else {
                $amount = $arrMemo10['captureTotal'] - $payload['order']->getPaymentTotal();
            }
        }
        // レスポンス初期化
        $operationResult = $this->initPaymentResult();
        // リクエストの取得
        switch ($payId) {
            case $this->vt4gConst['VT4G_PAYTYPEID_UPOP']: // 銀聯
                $mdkRequest = new \UpopRefundRequestDto();
                $arrMemo10['refundFlg'] = true;
                $payload['orderPayment']->setMemo10(serialize($arrMemo10));
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN']: // 楽天
                $mdkRequest = new \RakutenCancelRequestDto();
                $arrMemo10['lastPayStatus'] = $payload['orderPayment']->getMemo04();
                $payload['orderPayment']->setMemo10(serialize($arrMemo10));
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RECRUIT']: // リクルート
                $mdkRequest = new \RecruitCancelRequestDto();
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_LINEPAY']: // ライン
                $mdkRequest = new \LinepayCancelRequestDto();
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_ALIPAY']:
                $paymentId   = $payload['order']->getPayment()->getId();
                $paymentInfo = $this->util->getPaymentMethodInfo($paymentId);
                $mdkRequest  = new \AlipayRefundRequestDto();
                $mdkRequest->setReason($paymentInfo['refund_reason']);
                $operationResult['refund_reason'] = $paymentInfo['refund_reason'];
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL']: // paypal
                $mdkRequest = new \PayPalRefundRequestDto();
                $arrMemo10['refundFlg'] = true;
                $payload['orderPayment']->setMemo10(serialize($arrMemo10));
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_CARRIER']: // carrier
                print_r('carrier refund'); exit;
                $mdkRequest = new \CarrierRefundRequestDto();
                break;
            default:
                return [$operationResult, []];
        }

        $mdkRequest->setAmount($amount);
        $mdkRequest->setOrderId($prevPaymentResult['orderId']);

        $objTransaction = new \TGMDK_Transaction();
        $mdkResponse = $objTransaction->execute($mdkRequest);

        if (!isset($mdkResponse)) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.mdk.error'));
            $operationResult['message'] = trans('vt4g_plugin.payment.shopping.error');
            return [$operationResult, []];
        }

        // 結果コード
        $operationResult['mStatus']     = $mdkResponse->getMStatus();
        // 詳細コード
        $operationResult['vResultCode'] = $mdkResponse->getVResultCode();
        // エラーメッセージ
        $operationResult['mErrMsg']     = $mdkResponse->getMErrMsg();

        // 正常終了レスポンス以外の場合
        if ($operationResult['mStatus'] !== $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $operationResult['message']  = $operationResult['vResultCode'].':';
            $operationResult['message'] .= $operationResult['mErrMsg'];

            return [$operationResult, $mdkResponse];
        }

        $operationResult['isOK']         = true;
        // 取引ID
        $operationResult['orderId']      = $mdkResponse->getOrderId();
        // 決済サービスタイプ
        $operationResult['serviceType']  = $mdkResponse->getServiceType();
        // 決済ステータス
        $operationResult['payStatus']    = $isRakuten
            ? $this->vt4gConst['VT4G_PAY_STATUS']['REDUCTION_REQUEST']['VALUE']
            : $this->vt4gConst['VT4G_PAY_STATUS']['REFUND']['VALUE'];
        // 売上金額
        $operationResult['captureTotal'] = (isset($payload['refundAll']) && $payload['refundAll']) ? 0 : floor($payload['order']->getPaymentTotal());
        // 減額
        $operationResult['amount']       = $amount;
        // ログの生成
        $this->setLogHead($payId, '', $operationResult);

        return [$operationResult, $mdkResponse];
    }

    /**
     * ログのマージ
     *
     * @param  array $storedLogMap データベースに保存済みのログ
     * @param  array $newLogMap    新規追加のログ
     * @return array               マージされたログ
     */
    private function getMergeLog($storedLogMap, $newLogMap)
    {
        // 保存済みのログリスト
        if (empty($storedLogMap)) {
            return $newLogMap;
        }

        $keys = array_keys($newLogMap);
        $changes = [];
        foreach ($keys as $index => $key) {
            if (array_key_exists($key, $storedLogMap)) {
                $changes["{$key} {$index}"] = $newLogMap[$key];
            } else {
                $changes[$key] = $newLogMap[$key];
            }
        }

        return array_merge($storedLogMap, $changes);
    }

    /**
     * 支払方法の説明を取得
     *
     * @param string $optionType サービスオプションタイプ
     * @return string 結果
     */
    public function getExplain($optionType)
    {
        // サービスオプションタイプからファイル名生成
        $path = $this->container->getParameter('plugin_realdir'). '/' . $this->vt4gConst['VT4G_CODE'] . $this->vt4gConst['VT4G_DOC_PATH'];
        $path .= sprintf($this->vt4gConst['VT4G_EXPLAIN_FILE_NAME_FMT'], $optionType);

        // あれば取得
        if (file_exists($path) == true) {
            $rtnExplain = file_get_contents($path);
        }

        return $rtnExplain ?? '';
    }
}
