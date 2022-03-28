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

namespace Plugin\PaygentPayment\Service;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Exception\CartException;
use Eccube\Repository\OrderRepository;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Paygent決済カートの復元を行うクラス
 */
class PaygentRecoverCartService {
    
    use ControllerTrait;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param EntityManagerInterface $entityManager
     * @param ContainerInterface $container
     * @param CartService $cartService
     * @param PurchaseFlow $cartPurchaseFlow
     */
    public function __construct(
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager,
        ContainerInterface $container,
        CartService $cartService,
        PurchaseFlow $cartPurchaseFlow
    ) {
        $this->orderRepository = $orderRepository;
        $this->entityManager = $entityManager;
        $this->container = $container;
        $this->cartService = $cartService;
        $this->purchaseFlow = $cartPurchaseFlow;
    }

        /**
     * カートの中身を元に戻す。
     * @param string $orderId
     */
    public function recoverCart($orderId) {
        $order = $this->orderRepository->findOneBy([
            'id' => $orderId,
        ]);
        if ($order) {
            // エラーメッセージの配列
            $errorMessages = [];

            foreach ($order->getOrderItems() as $orderItem) {
                try {
                    if (!$orderItem->getProduct() || !$orderItem->getProductClass()) {
                        continue;
                    }

                    // 在庫の調整 プラス
                    // addProductを行いカートへ商品情報を復元する際、商品の在庫チェックが発生するため購入数より現在の在庫が足りないと失敗する
                    // 一時的に在庫を増やしaddProductにて在庫不足によるエラーが発生しないようにする。
                    $this->stockAdjustment($orderItem, 'plus');

                    $this->cartService->addProduct($orderItem->getProductClass(), $orderItem->getQuantity());

                    // 明細の正規化
                    $errorMessages = array_merge($errorMessages, $this->normalizationOfItems($this->purchaseFlow, $orderItem));

                    $this->cartService->save();

                    // 在庫の調整 マイナス
                    // カート追加時に一時的に増やした在庫を戻して減らす、在庫戻しはpurchaseFlow::rollbackにて実施
                    $this->stockAdjustment($orderItem, 'minus');

                } catch (CartException $e) {
                    $errorMessages[] = $e->getMessage();
                }
            }

            $cart = $this->cartService->getCart();
            $cart->setTotalPrice($order->getSubtotal());
            $cart->setDeliveryFeeTotal($order->getDeliveryFeeTotal());
            $cart->setPreOrderId($order->getPreOrderId());
            $cart->setAddPoint($order->getAddPoint());
            $cart->setUsePoint($order->getUsePoint());
            $this->cartService->save();

            return $errorMessages;
        }
    }

    
    /**
     * 在庫戻しの時の在庫の調整
     */
    private function stockAdjustment($orderItem, $sign = null) {
        $productClass = $orderItem->getProductClass();
        if ($orderItem->isProduct() && !$productClass->isStockUnlimited()) {
            $quantity = $orderItem->getQuantity();

            if ($sign == 'plus') {
                $newStock = $productClass->getStock() + $quantity;
            } elseif ($sign == 'minus') {
                $newStock = $productClass->getStock() - $quantity;
            } else {
                return null;
            }

            $productClass->setStock($newStock);
            $this->entityManager->flush($productClass);

            $productStock = $productClass->getProductStock();
            $productStock->setStock($newStock);
            $this->entityManager->flush($productStock);
        }
    }

    /**
     * 明細を正規化
     *
     * @param PurchaseFlow $purchaseFlow
     * @param OrderItem $orderItem
     * @return array
     */
    private function normalizationOfItems($purchaseFlow, $orderItem)
    {
        $errorMessages = [];

        $carts = $this->cartService->getCarts();
        foreach ($carts as $cart) {
            $result = $purchaseFlow->validate($cart, new PurchaseContext($cart, $this->getUser()));
            // 復旧不可のエラーが発生した場合は追加した明細を削除.
            if ($result->hasError()) {
                $this->cartService->removeProduct($orderItem->getProductClass());
                foreach ($result->getErrors() as $error) {
                    $errorMessages[] = $error->getMessage();
                }
            }
            foreach ($result->getWarning() as $warning) {
                $errorMessages[] = $warning->getMessage();
            }
        }

        return $errorMessages;
    }
}
