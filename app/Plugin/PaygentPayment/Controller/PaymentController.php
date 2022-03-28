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

namespace Plugin\PaygentPayment\Controller;

use Eccube\Controller\AbstractController;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Plugin\PaygentPayment\Service\PaygentDifferenceNotice;
use Plugin\PaygentPayment\Service\PaygentBaseService;
use Plugin\PaygentPayment\Service\PaygentRecoverCartService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * リンク式決済の注文/戻る/完了通知を処理する.
 */
class PaymentController extends AbstractController
{
    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var PaygentDifferenceNotice
     */
    protected $paygentDifferenceNotice;

    /**
     * @var PaygentRecoverCartService
     */
    protected $paygentRecoverCartService;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * コンストラクタ
     * @param CartService $cartService
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param PaygentDifferenceNotice $paygentDifferenceNotice
     * @param PaygentRecoverCartService $paygentRecoverCartService
     * @param SessionInterface $session
     */
    public function __construct(
        CartService $cartService,
        PurchaseFlow $shoppingPurchaseFlow,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        PaygentDifferenceNotice $paygentDifferenceNotice,
        PaygentRecoverCartService $paygentRecoverCartService,
        SessionInterface $session
    ) {
        $this->cartService = $cartService;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paygentDifferenceNotice = $paygentDifferenceNotice;
        $this->paygentRecoverCartService = $paygentRecoverCartService;
        $this->session = $session;
    }

    /**
     * 差分通知
     * @Route("/paygent_payment_difference_notice", name="paygent_payment_difference_notice")
     * @return paygent_difference_notice.twig
     */
    public function differenceNotice() {
        $result = $this->paygentDifferenceNotice->mainProcess();
        return new Response("result = ". $result);
    }

    /**
     * ペイジェント決済エラー画面
     *
     * @Route("/shopping/paygent_error", name="shopping_paygent_error")
     * @Template("@PaygentPayment/default/Shopping/shopping_error.twig")
     */
    function errorPage() {
        $errorMsg = $this->session->get('paygent_payment.shopping_error');
        if (isset($errorMsg)){
            $this->session->set('paygent_payment.shopping_error', null);
        }

        return [
            'error_message' => $errorMsg,
        ];
    }

    /**
     * order_idをセッションにセットしてshopping/completeへ遷移する処理
     *
     * @Route("/paygent_payment_shopping_complete", name="paygent_payment_shopping_complete")
     */
    function shoppingComplete(Request $request) {
        // セッションからpre_order_idを取得
        $preOrderId = $this->session->get($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // セッションからpre_order_idを削除
        $this->session->remove($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // 受注情報の取得
        $order = $this->orderRepository->findOneBy([
            'id' => $request->get('order_id'),
            'OrderStatus' => OrderStatus::PENDING,
            'pre_order_id' => $preOrderId,
        ]);

        if ($order) {
            // 完了画面を表示するため, 受注IDをセッションに保持する
            $this->session->set('eccube.front.shopping.order.id', $order->getId());
            // 注文完了画面にリダイレクト
            return $this->redirectToRoute('shopping_complete');
        }

        return $this->redirectToRoute('shopping_error');
    }

    /**
     * カート復元を行い、カート画面へ戻す
     * カートセッションに紐付くpre_order_idはOrderの新規生成を行う為nullを設定する
     *
     * @Route("/paygent_payment_recovery_cart", name="paygent_payment_recovery_cart")
     */
    function recoveryCart(Request $request) {
        // セッションからpre_order_idを取得
        $preOrderId = $this->session->get($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // セッションからpre_order_idを削除
        $this->session->remove($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // 受注情報の取得
        $order = $this->orderRepository->findOneBy([
            'id' => $request->get('order_id'),
            'OrderStatus' => OrderStatus::PENDING,
            'pre_order_id' => $preOrderId,
        ]);

        if ($order) {
            // 受注ステータスを購入処理中へ変更
            $orderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $order->setOrderStatus($orderStatus);
            $this->entityManager->flush();

            // カート復元
            $errorMessages = $this->paygentRecoverCartService->recoverCart($order->getId());
            if ($errorMessages) {
                foreach ($errorMessages as $errorMessage) {
                    $this->addRequestError($errorMessage);
                }
            }
            
            // purchaseFlow::rollbackを呼び出し, 購入処理をロールバックする.
            $this->purchaseFlow->rollback($order, new PurchaseContext());
            $this->entityManager->flush();

            // カートセッションのpre_order_idにnullをセット
            $this->cartService->setPreOrderId(null);
            $this->cartService->save();

            // カート画面へ遷移
            return $this->redirectToRoute('cart');
        } else {
            return $this->redirectToRoute('shopping_error');
        }
    }
}
