<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Service\Admin\Order;

use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;
use Plugin\VeriTrans4G\Entity\Vt4gOrderPayment;
use Plugin\VeriTrans4G\Form\Type\Admin\OrderEditCvsType;
use Plugin\VeriTrans4G\Form\Type\Admin\OrderEditCreditType;
use Eccube\Entity\Payment;
use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Eccube\Service\Payment\Method\Cash;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 受注管理一覧画面 拡張用クラス
 */
class EditExtensionService
{
    /**
     * コンテナ
     */
    private $container;

    /**
     * エンティティーマネージャー
     */
    private $em;

    /**
     * ユーティリティサービス
     */
    private $util;

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * MDK Logger
     */
    private $mdkLogger;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->util = $container->get('vt4g_plugin.service.util');
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $this->container->getParameter('vt4g_plugin.const');
    }

    /**
     * 受注管理詳細画面 レンダリング時のイベントリスナ
     *
     * @param  TemplateEvent $event イベントデータ
     * @return void
     */
    public function onRenderBefore(TemplateEvent $event)
    {
        $orderId = $event->getParameter('id');

        // 新規登録の場合
        if (is_null($orderId)) {
            $hasVt4gOrderPayment = false;
            $removePaymentMethodIdList = $this->getRemovePaymentIdList();

            $extension = compact(
                'hasVt4gOrderPayment',
                'removePaymentMethodIdList'
            );
            $event->setParameter('vt4g', $extension);
            $event->addSnippet('@VeriTrans4G/admin/Order/edit.twig');
            return;
        }

        $const        = $this->vt4gConst;
        $order        = $event->getParameter('Order');
        $orderPayment = $this->util->getOrderPayment($orderId);

        // ベリトランス決済データの存在フラグ
        $hasVt4gOrderPayment = !empty($orderPayment);

        // ベリトランス決済データが存在しない場合
        if (!$hasVt4gOrderPayment) {
            /**
             * 購入処理中でMDKリクエスト前はベリトランス決済の場合でも
             * レコードが存在しないためpaymentId基準で判定
             */
            if ($this->util->isVt4gPayment($order)) {
                // 現在の決済方法以外をプルダウンの削除対象に設定
                $removePaymentMethodIdList = $this->util->getPaymentIdList(true, $order->getPayment());
            } elseif ($order->getPayment()->getMethodClass() == Cash::class) {
                // 受注管理で使用できないベリトランス決済方法IDをプルダウンの削除対象に設定
                $removePaymentMethodIdList = $this->getRemovePaymentIdList();
            } else {
                // ベリトランスの決済方法IDを支払方法プルダウンの削除対象に設定
                $removePaymentMethodIdList = array_merge(
                    $this->util->getPaymentIdList(),
                    $this->util->getRemovedVt4gPaymentIdList($order->getPayment())
                );
            }

            $extension = compact(
                'hasVt4gOrderPayment',
                'removePaymentMethodIdList'
            );
            $event->setParameter('vt4g', $extension);
            $event->addSnippet('@VeriTrans4G/admin/Order/edit.twig');
            return;
        }

        // 決済操作
        $operationList = $this->util->getPaymentOperations($order);

        // コンビニリスト
        $conveniList = $this->getConveniList($order);

        // 支払方法プルダウンから削除する決済方法IDを取得
        $removePaymentMethodIdList = $this->getRemovePaymentIdList($order);

        // 決済データを取得
        $orderPayment = $this->util->getOrderPayment($orderId);

        // 決済方法の内部ID
        $payId = $orderPayment->getMemo03();

        // 決済ステータス
        $paymentStatus = $orderPayment->getMemo04();
        $paymentStatusText = $this->getPaymentStatusText($orderPayment);

        // 決済種別
        $payName = !empty($this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payId]) ? $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payId] : $order->getPaymentMethod();

        // 決済情報
        $paymentInfo = unserialize($orderPayment->getMemo06());
        if (!empty($paymentInfo)) {
            $paymentInfo['message'] = array_map(function ($info) {
                return [
                    'title'   => $info['title'] ?? '',
                    'content' => empty($info['content'])
                        ? ''
                        : $this->util->escapeNewLines($info['content'])
                ];
            }, $paymentInfo['message'] ?? []);
        }
        // 決済ログ
        $paymentLogList = array_map(function($log) {
            return unserialize($log->getVt4gLog());
        }, $this->util->getOrderLog($orderId));

        // 注文された商品が継続課金用の販売種別かつ少額与信の販売種別かチェック
        $subscFewCreditExists = $this->util->checkSubscFewCreditExists($orderId);

        // 決済金額が変更となった場合に決済操作を促すメッセージを出力
        $arrMemo10    = unserialize($orderPayment->getMemo10());
        $cardAmount   = $arrMemo10['card_amount'] ?? false;
        $captureTotal = $arrMemo10['captureTotal'] ?? $cardAmount;
        if ($captureTotal !== false && $captureTotal != $order->getPaymentTotal() && $order->getPaymentTotal() > 0) {
            if (!$subscFewCreditExists) {
                $this->container->get('session')->getFlashBag()->add('eccube.admin.warning', 'お支払い合計と決済の金額が異なります。');
            }
        }

        // コンビニ選択用フォーム
        if ($payId == $this->vt4gConst['VT4G_PAYTYPEID_CVS']) {
            $formBuilder = $this->container->get('form.factory')
            ->create(OrderEditCvsType::class, [
                'paymentMethodInfo' => $this->util->getPaymentMethodInfo($order->getPayment()->getId())
            ]);
        }
        // クレジット情報選択用フォーム
        if ($paymentStatus == $this->vt4gConst['VT4G_PAY_STATUS']['NEWLY']['VALUE'] && $payId == $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']) {
            $creditInfo = $this->getCreditInfo($order);
            if(!empty($creditInfo)) {
                $formBuilder = $this->container->get('form.factory')
                ->create(OrderEditCreditType::class, [
                    'paymentMethodInfo' => $this->util->getPaymentMethodInfo($order->getPayment()->getId()),
                    'creditInfo' => $creditInfo
                ]);
            } else {
                $this->container->get('session')->getFlashBag()->add('eccube.admin.danger', 'クレジットカード決済に必要な情報がありません。他の支払方法を選択してください。');
                $hasVt4gOrderPayment = false;
            }
        }
        $form = (isset($formBuilder)) ? $formBuilder->createView() : null;

        // テンプレートに渡すデータ
        $extension = compact(
            'const',
            'hasVt4gOrderPayment',
            'form',
            'order',
            'orderId',
            'paymentStatus',
            'paymentStatusText',
            'paymentInfo',
            'paymentLogList',
            'removePaymentMethodIdList',
            'operationList',
            'conveniList',
            'subscFewCreditExists',
            'payName'
        );
        $event->setParameter('vt4g', $extension);

        // テンプレートの読み込みを追加
        $event->addSnippet('@VeriTrans4G/admin/Order/edit.twig');
    }

    /**
     * 受注管理詳細画面 更新時のイベントリスナ
     *
     * @param  EventArgs $event イベントデータ
     * @return void
     */
    public function onEditComplete(EventArgs $event)
    {
        // 注文データ
        $order = $event->getArgument('TargetOrder');
        // 変更前の決済方法
        $originPayment = $event->getArgument('OriginOrder')->getPayment();
        // 変更後の決済方法のid
        $paymentId = $order->getPayment()->getId();
        // ベリトランス決済データ
        $orderPayment = $this->util->getOrderPayment($order->getId());

        // 変更後のベリトランス決済方法 選択肢
        $vt4gPaymentMap = [
            $this->vt4gConst['VT4G_PAYTYPEID_CVS'] => $this->vt4gConst['VT4G_PAYTYPEID_CVS'],
            $this->vt4gConst['VT4G_PAYTYPEID_ATM'] => $this->vt4gConst['VT4G_PAYTYPEID_ATM'],
            $this->vt4gConst['VT4G_PAYTYPEID_CREDIT'] => $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']
        ];
        //変更後のベリトランス決済方法
        $Vt4gPaymentMethod = $this->util->getPaymentMethod($paymentId);

        // 変更後のベリトランス決済情報が空でない場合はベリトランス決済IDを取得
        $payId = '';
        if (!empty($Vt4gPaymentMethod)) {
            $payId = $Vt4gPaymentMethod->getMemo03();
        }
        // ベリトランス決済データがない かつ 変更後のベリトランス決済方法が選択肢にないとき、先に進まない
        if (empty($orderPayment) && !array_search($payId, $vt4gPaymentMap)) {
            return;
        }
        // 注文情報の変更 かつ 決済方法の変更が無い場合は先に進まない
        if (!empty($originPayment) && $originPayment->getId() === $paymentId) {
            return;
        }

        // ベリトランス決済データを更新
        $this->em->beginTransaction();
        try {
            if (empty($orderPayment)) {
                $orderPayment = new Vt4gOrderPayment;
                $orderPayment->setOrderId($order->getId());
            }
            $orderPayment
                ->setMemo01(null)
                ->setMemo02(null)
                ->setMemo03(null)
                ->setMemo04(null)
                ->setMemo05(null)
                ->setMemo06(null)
                ->setMemo07(null)
                ->setMemo08(null)
                ->setMemo09(null)
                ->setMemo10(null);
            $payId = !empty($Vt4gPaymentMethod) ? array_search($Vt4gPaymentMethod->getMemo03(), $vt4gPaymentMap) : false;
            if ($payId !== false) {
                $orderPayment
                    ->setMemo03($payId)
                    ->setMemo04($this->vt4gConst['VT4G_PAY_STATUS']['NEWLY']['VALUE']);
            }

            $this->em->persist($orderPayment);
            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
        }
    }

    /**
     * 新規決済処理を実行
     *
     * @param  array $payload 決済に使用するデータ
     * @return array          決済結果データ
     */
    public function operateNewLy($payload)
    {
        $operationResult = [];
        $payId = $payload['orderPayment']->getMemo03();

        switch ($payId) {
            case $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']: // クレジットカード決済
                $paymentService = $this->container->get('vt4g_plugin.service.payment_credit');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_CVS']: // コンビニ決済
                $paymentService = $this->container->get('vt4g_plugin.service.payment_cvs');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_ATM']: // ATM決済
                $paymentService = $this->container->get('vt4g_plugin.service.payment_atm');
                break;
            default:
                return $operationResult;
        }

        // 決済処理 実行
        $operationResult = $paymentService->operateNewly($payload);

        if ($operationResult['isOK']) {
            $mailData = $paymentService->setMail($payload['order'], $operationResult);

            // 更新処理
            $operationResult['isNewly'] = true;
            $paymentService->updateByAdmin($payload['orderPayment'], $operationResult);

            // メール送信
            $paymentService->sendOrderMail($payload['order']);

            // ステータスの更新 : クレジットカード決済かつ、与信 + 売上の場合は入金済みに更新する。
            $findOrderStatus = ($payId == $this->vt4gConst['VT4G_PAYTYPEID_CREDIT'] && $payload['inputs']->get('withCapture'))
                ? OrderStatus::PAID
                : OrderStatus::NEW;

            $orderStatus = $this->em->getRepository(OrderStatus::class)->find($findOrderStatus);
            if (!empty($orderStatus)) {
                $this->em->getRepository(Order::class)->changeStatus($payload['order']->getId(), $orderStatus);
            }

            $this->em->flush();
        }

        return $operationResult;
    }

    /**
     * 売上処理を実行
     *
     * @param  array $payload 決済に使用するデータ
     * @return array          決済結果データ
     */
    public function operateCapture($payload)
    {
        $operationResult = [];
        $orderStatus = $payload['order']->getOrderStatus()['id'];
        $payId = $payload['orderPayment']->getMemo03();

        switch ($payId) {
            case $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']: // クレジットカード決済
                $paymentService = $this->container->get('vt4g_plugin.service.payment_credit');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_UPOP']: // 銀聯
                $paymentService = $this->container->get('vt4g_plugin.service.payment_upop');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN']: // 楽天
                $paymentService = $this->container->get('vt4g_plugin.service.payment_rakuten');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RECRUIT']: // リクルート
                $paymentService = $this->container->get('vt4g_plugin.service.payment_recruit');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_LINEPAY']: // ライン
                $paymentService = $this->container->get('vt4g_plugin.service.payment_linepay');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL']: // paypal
                $paymentService = $this->container->get('vt4g_plugin.service.payment_paypal');
                break;
            default:
                return $operationResult;
        }

        // 売上処理 実行
        $operationResult = $paymentService->operateCapture($payload);

        if ($operationResult['isOK']) {
            // 更新処理
            $paymentService->updateByAdmin($payload['orderPayment'], $operationResult, $orderStatus);
            $this->em->flush();
        }

        return $operationResult;
    }

    /**
     * キャンセル処理を実行
     *
     * @param  array $payload 決済処理に使用するデータ
     * @return array          決済処理結果データ
     */
    public function operateCancel($payload)
    {
        $operationResult = [];
        $payId = $payload['orderPayment']->getMemo03();

        switch ($payId) {
            case $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']: // クレジットカード決済
                $paymentService = $this->container->get('vt4g_plugin.service.payment_credit');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_CVS']: // コンビニ決済
                $paymentService = $this->container->get('vt4g_plugin.service.payment_cvs');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_UPOP']: // 銀聯
                $paymentService = $this->container->get('vt4g_plugin.service.payment_upop');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN']: // 楽天
                $paymentService = $this->container->get('vt4g_plugin.service.payment_rakuten');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RECRUIT']: // リクルート
                $paymentService = $this->container->get('vt4g_plugin.service.payment_recruit');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_LINEPAY']: // ライン
                $paymentService = $this->container->get('vt4g_plugin.service.payment_linepay');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_ALIPAY']: // アリペイ
                $paymentService = $this->container->get('vt4g_plugin.service.payment_alipay');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL']: // paypal
                $paymentService = $this->container->get('vt4g_plugin.service.payment_paypal');
                break;
            default:
                return $operationResult;
        }

        // キャンセル処理 実行
        $operationResult = $paymentService->operateCancel($payload);
        if ($operationResult['isOK']) {
            // 更新処理
            $paymentService->updateByAdmin($payload['orderPayment'], $operationResult);
            $this->em->flush();
        }

        return $operationResult;
    }

    /**
     * 返金処理を実行
     *
     * @param  array $payload 決済処理に使用するデータ
     * @return array          決済処理結果データ
     */
    public function operateRefund($payload)
    {
        $operationResult = [];
        $payId = $payload['orderPayment']->getMemo03();
        switch ($payId) {
            case $this->vt4gConst['VT4G_PAYTYPEID_UPOP']: // 銀聯
                $paymentService = $this->container->get('vt4g_plugin.service.payment_upop');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN']: // 楽天
                $paymentService = $this->container->get('vt4g_plugin.service.payment_rakuten');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RECRUIT']: // リクルート
                $paymentService = $this->container->get('vt4g_plugin.service.payment_recruit');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_LINEPAY']: // ライン
                $paymentService = $this->container->get('vt4g_plugin.service.payment_linepay');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_ALIPAY']: // アリペイ
                $paymentService = $this->container->get('vt4g_plugin.service.payment_alipay');
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL']: // paypal
                $paymentService = $this->container->get('vt4g_plugin.service.payment_paypal');
                break;
            default:
                return $operationResult;
        }

        // 返金処理 実行
        $operationResult = $paymentService->operateRefund($payload);
        if ($operationResult['isOK']) {
            // 更新処理
            $paymentService->updateByAdmin($payload['orderPayment'], $operationResult);
            $this->em->flush();
        }

        return $operationResult;
    }

    /**
     * 再決済処理を実行
     *
     * @param  array $payload 再決済処理に使用するデータ
     * @return array          再決済処理結果データ
     */
    public function operateAuth($payload)
    {
        $operationResult = [];
        $payId = $payload['orderPayment']->getMemo03();

        // 再決済前の取引ID
        $originPaymentOrderId = $payload['orderPayment']->getMemo01();

        switch ($payId) {
            case $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']: // クレジットカード決済
                $paymentService = $this->container->get('vt4g_plugin.service.payment_credit');
                break;
            default:
                return $operationResult;
        }

        // 再決済処理 実行
        $authOperationResult = $paymentService->operateAuth($payload, $this->vt4gConst['VT4G_PAY_STATUS']['AUTH']['VALUE']);

        if ($authOperationResult['isOK']) {
            // 更新処理
            $paymentService->updateByAdmin($payload['orderPayment'], $authOperationResult);
            $this->em->flush();

            // 再決済後の取引ID
            $newPaymentOrderId = $authOperationResult['orderId'];

            // 決済ログ情報を初期化
            $paymentService->logData = [];

            // 再決済前の決済情報を取消
            $payload['orderPayment']->setMemo01($originPaymentOrderId);
            $cancelOperationResult = $paymentService->operateCancel($payload);

            // 再決済後の取引IDを再設定
            $payload['orderPayment']->setMemo01($newPaymentOrderId);
            // memo10更新
            $memo10 = unserialize($payload['orderPayment']->getMemo10());
            $memo10['card_amount'] = floor($payload['order']->getPaymentTotal());
            $payload['orderPayment']->setMemo10(serialize($memo10));

            // キャンセル処理が異常終了の場合
            if (!$cancelOperationResult['isOK']) {
                return $cancelOperationResult;
            }

            // 再更新処理
            $paymentService->updateByAdmin($payload['orderPayment'], $authOperationResult);
            $this->em->flush();
        }

        return $authOperationResult;
    }

    /**
     * 削除対象の決済方法IDリストを取得
     *
     * @param  object $order 注文データ
     * @return array         削除対象の決済方法IDリスト
     */
    private function getRemovePaymentIdList($order = null)
    {
        if($order) {
            $targetOrderPayment = $this->util->getOrderPayment($order->getId());
            // 対象の決済方法ID
            $targetPaymentId = $order->getPayment()->getId();
            // 対象の決済ステータス
            $targetPaymentStatus = $targetOrderPayment->getMemo04();
        } else {
            $targetPaymentStatus = null;
        }
        // 全決済方法リスト
        $allPaymentList = $this->em->getRepository(Payment::class)->findAll();

        // 受注管理で選択可能な決済方法
        $availablePaymentList = [
            $this->vt4gConst['VT4G_PAYTYPEID_CREDIT'], // クレジットカード決済
            $this->vt4gConst['VT4G_PAYTYPEID_CVS'],    // コンビニ決済
            $this->vt4gConst['VT4G_PAYTYPEID_ATM'],    // ATM決済
        ];

        // プラグイン設定画面で有効にしている決済内部ID
        $enablePayIdList = $this->util->getEnablePayIdList();

        // プラグイン設定画面で有効であれば削除対象外とする
        $showPaymentIdList = [];
        foreach ($availablePaymentList as $payId ) {
            if (in_array($payId, $enablePayIdList)) {
                $showPaymentIdList[] = (string)$payId;
            }
        }

        // 削除対象ベリトランス決済方法名リスト
        $removeVt4gPaymentIdList = $this->em->getRepository(Vt4gPaymentMethod::class)
            ->setConst($this->vt4gConst)
            ->getPaymentMethodList($showPaymentIdList);

        // 決済ステータスがnullか取消か新規決済の場合に削除対象外を判定
        $shouldExclude = in_array($targetPaymentStatus, [
            null,
            $this->vt4gConst['VT4G_PAY_STATUS']['CANCEL']['VALUE'],
            $this->vt4gConst['VT4G_PAY_STATUS']['NEWLY']['VALUE']
        ]);

        $removePaymentIdList = [];
        foreach ($allPaymentList as $payment) {
            $paymentId     = $payment->getId();
            $paymentMethod = $payment->getMethod();

            // 削除済みのベリトランス決済方法の場合
            if (strpos($paymentMethod, $this->vt4gConst['VT4G_REMOVED_PAYNAME_LABEL']) !== false) {
                $removePaymentIdList[] = $paymentId;
                continue;
            }

            if ($order) {
                // 対象の決済方法IDの場合
                if ($paymentId == $targetPaymentId) {
                    continue;
                }
            }

            // 削除対象外の場合
            if ($shouldExclude && !in_array($paymentId, $removeVt4gPaymentIdList)) {
                continue;
            }

            $removePaymentIdList[] = $paymentId;
        }

        return $removePaymentIdList;
    }

    /**
     * 決済ステータス表示テキスト 取得
     * (クレジットカード決済の場合に支払回数を含める)
     *
     * @param  object $orderPayment 決済データ
     * @return string               決済ステータス表示テキスト
     */
    private function getPaymentStatusText($orderPayment)
    {
        $paymentStatus = $this->util->getPaymentStatusName($orderPayment->getMemo04());

        // クレジットカード決済以外の場合
        if ($orderPayment->getMemo03() != $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']) {
            return $paymentStatus;
        }

        $payMethodMap = array_flip($this->vt4gConst['VT4G_FORM']['CHOICES']['CREDIT_PAY_METHOD']);
        $memo10 = unserialize($orderPayment->getMemo10());

        $payMethod = '';
        // シリアライズされていない場合
        if ($memo10 === false) {
            $payMethod = $payMethodMap[$memo10] ?? '';
        } else {
            $cardType = $memo10['card_type'] ?? null;
            $payMethod = is_null($cardType)
                ? ''
                : $payMethodMap[$cardType];
        }

        return empty($payMethod)
            ? $paymentStatus
            : $paymentStatus." ({$payMethod})";
    }

    /**
     * コンビニ支払設定で有効なコンビニを取得
     *
     * @param  object $order 注文データ
     * @return array         設定で有効なコンビニ
     */
    private function getConveniList($order)
    {
        $paymentId = $order->getPayment()->getId();
        $paymentMethod = $this->util->getPaymentMethod($paymentId);

        // コンビニ決済以外の場合
        if (empty($paymentMethod) || $this->util->getPayId($paymentId) != $this->vt4gConst['VT4G_PAYTYPEID_CVS']) {
            return [];
        }

        // 全コンビニリスト
        $conveniList = array_values($this->vt4gConst['VT4G_FORM']['CHOICES']['CONVENI']);

        // 各決済方法の設定データ 取得
        $paymentInfo = $this->util->getPaymentMethodInfo($order->getPayment()->getId());

        return array_filter($conveniList, function ($conveni) use ($paymentInfo) {
            return in_array($conveni, $paymentInfo['conveni'] ?? []);
        });
    }

    /**
     * クレジット決済に必要なカード情報を取得
     *
     * @param  object $order 注文データ
     * @return array         かんたん決済、ベリトランス会員IDのカード情報
     */
    private function getCreditInfo($order)
    {
        $customer = $order->getCustomer();
        if (!$customer) {
            return [];
        }
        $customerId = $customer->getId();
        $vt4gAccountId = $customer->vt4g_account_id;
        $credit = $this->container->get('vt4g_plugin.service.payment_credit');
        $reTradeCards = $credit->getReTradeCards($customerId);
        if (count($reTradeCards) == 0 && !$vt4gAccountId) {
            return [];
        }
        $vt4gAccountService = $this->container->get('vt4g_plugin.service.vt4g_account_id');
        $accountCards = $vt4gAccountId ? $vt4gAccountService->getAccountCards($vt4gAccountId) : [];
        if (count($reTradeCards) == 0 && count($accountCards) == 0) {
            return [];
        }
        return [
            'reTradeCards' => $reTradeCards,
            'accountCards' => $accountCards,
        ];
    }
}
