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

namespace Plugin\PaygentPayment\Service\Method;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Symfony\Component\Form\FormInterface;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Service\Payment\PaymentResult;
use Plugin\PaygentPayment\Service\PaygentRequestService;
use Plugin\PaygentPayment\Service\CacheConfig;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * 携帯キャリア決済の決済処理を行うクラス.
 */
class Career implements PaymentMethodInterface
{
    /**
     * @var Order
     */
    protected $Order;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

    /**
     * @var PaygentRequestService
     */
    protected $paygentRequestService;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * コンストラクタ
     * @param OrderStatusRepository $orderStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param PaygentRequestService $paygentRequestService
     * @param CacheConfig $cacheConfig
     * @param EccubeConfig $eccubeConfig
     * @param SessionInterface $session
     */
    public function __construct(
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        PaygentRequestService $paygentRequestService,
        CacheConfig $cacheConfig,
        EccubeConfig $eccubeConfig,
        SessionInterface $session
        ) {
            $this->orderStatusRepository = $orderStatusRepository;
            $this->purchaseFlow = $shoppingPurchaseFlow;
            $this->paygentRequestService = $paygentRequestService;
            $this->cacheConfig = $cacheConfig;
            $this->eccubeConfig = $eccubeConfig;
            $this->session = $session;
    }

    /**
     * 注文確認画面遷移時に呼び出される.
     *
     * サーバ内でしか行えないような関連チェックを行う.
     *
     */
    public function verify()
    {
    }

    /**
     * 注文時に呼び出される.
     *
     * 受注ステータス, 決済ステータスを更新する.
     * ここでは決済サーバとの通信は行わない.
     *
     * @return PaymentDispatcher|null
     */
    public function apply()
    {
        if ($this->Order->getPaymentTotal() > 0) {
            logs('paygent_payment')->info("PAYGENT決済開始");

            $dispatcher = new PaymentDispatcher();

            // プラグイン設定情報の取得
            $config = $this->cacheConfig->getConfig();

            // リクエストパラメータをセット
            $arrParameter = $this->paygentRequestService->setParameter($this->Order, $config, $this->eccubeConfig['paygent_payment']['payment_type_career']);

            // pre_order_idをセッションに保持
            $this->session->set($this->eccubeConfig['paygent_payment']['session_pre_order_id'], $this->Order->getPreOrderId());

            // リクエスト
            $arrRet =  $this->paygentRequestService->sendRequest($config->getLinkUrl(), $arrParameter);

            $dispatcher = $this->paygentRequestService->linkPaygentPage($dispatcher, $arrRet, $this->eccubeConfig['paygent_payment']['paygent_career'], $this->Order);

            return $dispatcher;
        }
    }

    /**
     * 注文時に呼び出される.
     * 0円決済(全額をポイントに充てて0円になる決済)の場合のみ呼び出される.
     */
    public function checkout() {

        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());

        // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
        $this->purchaseFlow->commit($this->Order, new PurchaseContext());

        // 受注ステータスを入金済みへ変更
        // 0円決済の場合は差分照会/通知が来ないのでここで入金済みにする
        $orderStatus = $this->orderStatusRepository->find(OrderStatus::PAID);
        $this->Order->setOrderStatus($orderStatus);

        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormType(FormInterface $form)
    {
        $this->form = $form;
    }

    /**
     * {@inheritdoc}
     */
    public function setOrder(Order $order)
    {
        $this->Order = $order;
    }
}
