<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Service\Method;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 各決済方法の決済処理を行う.
 */
class CommonMethod implements PaymentMethodInterface
{
    /**
     * @var Order
     */
    protected $order;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

    /**
     * ユーティリティ
     */
    private $util;

    /**
     * CreditCard constructor.
     *
     * @param PurchaseFlow $shoppingPurchaseFlow
     */
    public function __construct(
        ContainerInterface $container,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow
    ) {
        $this->container             = $container;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow          = $shoppingPurchaseFlow;
        $this->util                  = $container->get('vt4g_plugin.service.util');
    }

    /**
     * 注文確認画面遷移時に呼び出される.
     *
     * @return PaymentResult
     */
    public function verify()
    {
        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * 注文時に呼び出される.
     *
     * 注文ステータス, 決済ステータスを更新する.
     * ここでは決済サーバとの通信は行わない.
     *
     * @return PaymentDispatcher
     */
    public function apply()
    {
        // 注文ステータスを決済処理中へ変更
        $orderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->order->setOrderStatus($orderStatus);

        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->purchaseFlow->prepare($this->order, new PurchaseContext());

        // 購入フロー画面にリダイレクト
        $dispatcher = new PaymentDispatcher();
        $dispatcher->setResponse($this->util->redirectToRoute('vt4g_shopping_payment'));

        return $dispatcher;
    }

    /**
     * 注文時に呼び出される.
     * applyでリダイレクトするためcheckoutは使用しない
     *
     * @return PaymentResult
     */
    public function checkout()
    {
        return new PaymentResult();
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
        $this->order = $order;
    }
}
