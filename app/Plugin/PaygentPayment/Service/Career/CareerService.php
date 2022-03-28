<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright (c) 2006 PAYGENT Co.,Ltd. All rights reserved.
 *
 * https://www.paygent.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Plugin\PaygentPayment\Service\Career;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PluginRepository;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Common\EccubeConfig;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Service\MailService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Repository\BaseInfoRepository;
use Plugin\PaygentPayment\Service\CacheConfig;
use Plugin\PaygentPayment\Service\PaygentBaseService;

/**
 * 携帯キャリア決済差分通知機能のクラス
 */
class CareerService extends PaygentBaseService {

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var PluginRepository
     */
    protected $pluginRepository;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var CareerOperationService
     */
    protected $careerOperationService;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

    /**
     * @var BaseInfoRepository
     */
    protected $BaseInfo;

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var \Swift_Mailer
     */
    protected $mailer;

    /**
     * @var CareerTypeService
     */
    protected $careerTypeService;

    /**
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param PluginRepository $pluginRepository
     * @param ConfigRepository $configRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param EntityManagerInterface $entityManager
     * @param MailService $mailService
     * @param EccubeConfig $eccubeConfig
     * @param CareerOperationService $careerOperationService
     * @param CacheConfig $cacheConfig
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param BaseInfoRepository $baseInfoRepository
     * @param \Twig_Environment $twig
     * @param \Swift_Mailer $mailer
     * @param CareerTypeService $careerTypeService
     */
    public function __construct(
        OrderRepository $orderRepository,
        PluginRepository $pluginRepository,
        ConfigRepository $configRepository,
        OrderStatusRepository $orderStatusRepository,
        EntityManagerInterface $entityManager,
        MailService $mailService,
        EccubeConfig $eccubeConfig,
        CareerOperationService $careerOperationService,
        CacheConfig $cacheConfig,
        PurchaseFlow $shoppingPurchaseFlow,
        BaseInfoRepository $baseInfoRepository,
        \Twig_Environment $twig,
        \Swift_Mailer $mailer,
        CareerTypeService $careerTypeService
        ) {
            $this->orderRepository = $orderRepository;
            $this->pluginRepository = $pluginRepository;
            $this->configRepository = $configRepository;
            $this->orderStatusRepository = $orderStatusRepository;
            $this->entityManager = $entityManager;
            $this->mailService = $mailService;
            $this->eccubeConfig = $eccubeConfig;
            $this->careerOperationService = $careerOperationService;
            $this->cacheConfig = $cacheConfig;
            $this->purchaseFlow = $shoppingPurchaseFlow;
            $this->BaseInfo = $baseInfoRepository->get();
            $this->twig = $twig;
            $this->mailer = $mailer;
            $this->careerTypeService = $careerTypeService;
    }

    /**
     * ペイジェントステータスの更新
     * @param array $arrRet ペイジェントから送られてくるリクエストパラメータ
     */
    public function updatePaygentOrder($arrRet){
        // 受注ステータスの初期化
        $arrVal = [];
        $arrVal['status'] = null;
        // 決済日時の初期化
        $arrVal['payment_date'] = null;

        // 受注情報テーブルから、決済IDを取得
        $order = $this->orderRepository->find($arrRet['trading_id']);
        if (empty($order['paygent_payment_id'])) {
            $arrVal['paygent_payment_id'] = $arrRet['payment_id'];
        }

        // paygent_payment_status = 決済ステータス
        $arrVal['paygent_payment_status'] = $arrRet['payment_status'];
        // payment_notice_id = 決済通知ID
        $arrVal['payment_notice_id'] = $arrRet['payment_notice_id'];

        // 受注対応状況の設定
        $arrVal['status'] = $this->getOrderStatusFromPaygentStatus($arrRet['payment_status']);

        // 消込済・消込完了かつ支払日時が含まれる
        if (in_array($arrRet['payment_status'], [$this->eccubeConfig['paygent_payment']['status_pre_cleared'], $this->eccubeConfig['paygent_payment']['status_complete_cleared']], true)
            && $arrRet['payment_date'] != "") {
            // 入金日時 = 応答情報.支払日時
            $arrVal['payment_date'] = $arrRet['payment_date'];
        }

        // 初回通知の場合にキャリアに対応したpaygentPaymentMethodをセットする
        if ($order->getPaygentPaymentMethod() == $this->eccubeConfig['paygent_payment']['paygent_career']) {
            $this->setPaygentMethodByCareerType($arrRet, $order);
        }

        // 受注情報（dtb_order）の更新
        $this->updateOrderStatus($arrRet, $arrVal);
    }

    /**
     * PaygentStatusから対応するOrderStatusの取得
     * @param $paymentStatus
     * @return int|null
     */
    public function getOrderStatusFromPaygentStatus($paymentStatus)
    {
        switch ($paymentStatus) {

            case $this->eccubeConfig['paygent_payment']['status_pre_cleared']: // "40"：消込済
                // 受注状態 = "6"：入金済み
                return OrderStatus::PAID;

            case $this->eccubeConfig['paygent_payment']['status_authority_ok']: // "20"：オーソリOK
            case $this->eccubeConfig['paygent_payment']['status_authority_completed']: // "21"：オーソリ完了
                // 受注状態 = "1"：新規受付
                return  OrderStatus::NEW;

            case $this->eccubeConfig['paygent_payment']['status_authority_canceled']: // "32"：オーソリ取消済
            case $this->eccubeConfig['paygent_payment']['status_authority_expired']: // "33"：オーソリ期限切
            case $this->eccubeConfig['paygent_payment']['status_pre_sales_cancellation']: // "60"：売上取消済
                // 受注状態 = "3"：キャンセル
                return OrderStatus::CANCEL;
        }

        return null;
    }

    /**
     * 受注.対応状況の更新
     * @param  array $arrRet
     * @param  array $arrParams
     */
    public function updateOrderStatus($arrRet, $arrParams)
    {
        // 受注情報テーブルから、決済IDを取得
        $order = $this->orderRepository->find($arrRet['trading_id']);

        if (empty($order['paygent_payment_id'])) {
            $order = $this->orderRepository->findOneBy(['id' => $arrRet['trading_id']]);
            // 決済ID を更新条件（update 文の where 句）に含めないようにする
            $arrRet['payment_id'] = null;
        } else {
            $order = $this->orderRepository->findOneBy(['id' => $arrRet['trading_id'], 'paygent_payment_id' => $arrRet['payment_id']]);
        }

        if ($order) {
            // 初回通知時だとオーソリNG時にも新規受付となりメールも送られてしまうため、
            // ステータスが "7":決済処理中 かつ オーソリOK または オーソリ完了 通知時にメールを送信する
            // 決済処理中条件がない場合、オーソリ・売上変更時の オーソリOK または オーソリ完了 差分通知でも注文完了メールが送信されてしまう
            if ($order->getOrderStatus()->getId() == OrderStatus::PENDING && $arrParams['status'] == OrderStatus::NEW){
                // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
                $this->purchaseFlow->commit($order, new PurchaseContext());
                // メールの送信
                $this->mailService->sendOrderMail($order);
            }
            // dtb_orderの更新
            if (isset($arrParams['status'])) {
                $orderStatus = $this->orderStatusRepository->find($arrParams['status']);
                $order->setOrderStatus($orderStatus);
            }
            if (isset($arrParams['payment_date'])) {
                $order->setPaymentDate(\DateTime::createFromFormat('YmdHis', $arrParams['payment_date']));
            }
            if (isset($arrParams['paygent_payment_id'])) {
                $order->setPaygentPaymentId($arrParams['paygent_payment_id']);
            }
            $order->setPaygentPaymentStatus($arrParams['paygent_payment_status']);
            $order->setPaymentNoticeId($arrParams['payment_notice_id']);
            $this->entityManager->persist($order);
            $this->entityManager->flush();
        }
    }

    /**
     * 差分通知 ステータスチェック
     * @param string paymentStatus ペイジェントから送られてくるステータスパラメータ
     * @return 正常:True 異常:False
     */
    function isValidStatus($paymentStatus){
        // 差分通知対象のステータス
        $arrIsValidStatus = [
            $this->eccubeConfig['paygent_payment']['status_authority_ok'],
            $this->eccubeConfig['paygent_payment']['status_authority_completed'],
            $this->eccubeConfig['paygent_payment']['status_authority_canceled'],
            $this->eccubeConfig['paygent_payment']['status_authority_expired'],
            $this->eccubeConfig['paygent_payment']['status_pre_cleared'],
            $this->eccubeConfig['paygent_payment']['status_complete_cleared'],
            $this->eccubeConfig['paygent_payment']['status_pre_sales_cancellation']
        ];

        if(in_array($paymentStatus, $arrIsValidStatus)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 決済ステータス不整合メール送信判定
     * @param $settlementDivision
     * @param $paymentStatus
     * @param $order
     * @return bool
     */
    function isAlertMail($settlementDivision, $paymentStatus, $order) {

        // 決済処理中の場合は処理を行わない
        if ($order->getOrderStatus()->getId() == OrderStatus::PENDING) {
            return false;
        }

        // リンク型、モジュール共通
        // 20 : オーソリOK
        if ($paymentStatus == $this->eccubeConfig['paygent_payment']['status_authority_ok']) {
            return true;
        }

        return false;
    }

    /**
     * キャリアに対応したpaygentPaymentMethodをセットする
     *
     * @param $arrParam
     * @param $order
     * @return void
     */
    private function setPaygentMethodByCareerType($arrParam, $order)
    {
        // 決済情報を照会する
        $settlementDetail = $this->getSettlementDetail($arrParam);

        if ($settlementDetail['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_success'] && isset($settlementDetail['career_type'])) {
            $paygentMethod = $this->careerTypeService->getPaygentMethodByCareerType($settlementDetail['career_type']);

            if ($paygentMethod) {
                $order->setPaygentPaymentMethod($paygentMethod);
                $this->entityManager->persist($order);
                $this->entityManager->flush();
            }
        }
    }
}
