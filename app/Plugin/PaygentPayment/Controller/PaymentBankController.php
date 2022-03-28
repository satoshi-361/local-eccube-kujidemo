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

use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Exception\ShoppingException;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\PaygentPayment\Form\Type\BankPaymentType;
use Plugin\PaygentPayment\Service\Bank\FrontBankService;
use Plugin\PaygentPayment\Service\Error\ErrorDetailMsg;
use Plugin\PaygentPayment\Service\PaygentRecoverCartService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * モジュール型Bank決済のコントローラー
 */
class PaymentBankController extends AbstractController
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var FrontBankService
     */
    protected $frontBankService;

    /**
     * @var ErrorDetailMsg
     */
    private $errorDetailMsg;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var PaygentRecoverCartService
     */
    private $paygentRecoverCartService;

    /**
     * コンストラクタ
     * @param EccubeConfig $eccubeConfig
     * @param CartService $cartService
     * @param FrontBankService $frontBankService
     * @param ErrorDetailMsg $errorDetailMsg
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param PaygentRecoverCartService $paygentRecoverCartService
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        CartService $cartService,
        FrontBankService $frontBankService,
        ErrorDetailMsg $errorDetailMsg,
        PurchaseFlow $shoppingPurchaseFlow,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        PaygentRecoverCartService $paygentRecoverCartService
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->cartService = $cartService;
        $this->frontBankService = $frontBankService;
        $this->errorDetailMsg = $errorDetailMsg;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paygentRecoverCartService = $paygentRecoverCartService;
    }

    /**
     * @Route("/paygent_payment_asp_cancel", name="paygent_payment_asp_cancel")
     */
    public function aspCancel(Request $request)
    {
        // ASP画面からの遷移時
        // セッションからpre_order_idを取得
        $preOrderId = $this->session->get($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // セッションからpre_order_idを削除
        $this->session->remove($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        $order = $this->orderRepository->findOneBy([
            'id' => $request->get('order_id'),
            'OrderStatus' => OrderStatus::PENDING,
            'pre_order_id' => $preOrderId,
        ]);

        if (!$order) {
            return $this->redirectToRoute('shopping_error');
        }

        // 受注ステータスを購入処理中へ変更
        $orderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
        $order->setOrderStatus($orderStatus);
        $this->entityManager->flush();

        // カートの復元
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
    }

    /**
     * @Route("/paygent_payment_asp_complete", name="paygent_payment_asp_complete")
     */
    public function aspComplete(Request $request)
    {
        // ASP画面からの遷移時
        // セッションからpre_order_idを取得
        $preOrderId = $this->session->get($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // セッションからpre_order_idを削除
        $this->session->remove($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        $order = $this->orderRepository->findOneBy([
            'id' => $request->get('trading_id'),
            'OrderStatus' => OrderStatus::PENDING,
            'pre_order_id' => $preOrderId
        ]);

        if (!$order) {
            return $this->redirectToRoute('shopping_error');
        }

        if ($request->get('result') === $this->eccubeConfig['paygent_payment']['result_success']) {
            // 完了画面を表示するため, 受注IDをセッションに保持する
            $this->session->set('eccube.front.shopping.order.id', $order->getId());

            return $this->redirectToRoute('shopping_complete');

        } elseif ($request->get('result') === $this->eccubeConfig['paygent_payment']['result_error']) {
            // 決済処理異常時
            // 受注ステータスを購入処理中へ変更
            $orderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $order->setOrderStatus($orderStatus);
            $this->entityManager->flush();

            // カートの復元
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

        } elseif ($request->get('result') === $this->eccubeConfig['paygent_payment']['result_unknown']) {
            // result = 2 : 結果不明 TOPページへ遷移
            return $this->redirectToRoute('homepage');
        }

        return $this->redirectToRoute('shopping_error');
    }

    /**
     * @Route("/paygent_payment/payment_bank", name="paygent_payment/payment_bank")
     * @Template("@PaygentPayment/default/payment_bank.twig")
     */
    public function index(Request $request)
    {
        // 注文前処理
        $order = $this->orderRepository->findOneBy([
            'pre_order_id' => $this->cartService->getPreOrderId(),
            'OrderStatus' => OrderStatus::PROCESSING,
        ]);

        if (!$order) {
            return $this->redirectToRoute('shopping_error');
        }

        $builder = $this->formFactory->createBuilder(BankPaymentType::class);
        $form = $builder->getForm();
        $form->handleRequest($request);
        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            logs('paygent_payment')->info('銀行ネット決済開始');

            try {
                // 注文仮確定
                $this->purchaseFlow->prepare($order, new PurchaseContext());

                // 受注ステータスを決済処理中へ変更
                $orderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
                $order->setOrderStatus($orderStatus);
                $this->entityManager->flush();
            } catch (ShoppingException $e) {
                // 在庫切れの場合
                $this->addError($e->getMessage());
                return $this->redirectToRoute('shopping_error');
            }

            // 電文送信
            $ret = $this->frontBankService->applyProcess($order);

            if ($ret['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_success']) {
                // カート削除
                $this->cartService->clear();

                // ペイジェントお支払い画面にリダイレクト
                logs('paygent_payment')->info('銀行ネットASPへ遷移します');

                // pre_order_idをセッションに保持
                $this->session->set($this->eccubeConfig['paygent_payment']['session_pre_order_id'], $order->getPreOrderId());

                return $this->redirect($ret['asp_url']);
            } else {
                // 受注ステータスを購入処理中へ変更
                $orderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
                $order->setOrderStatus($orderStatus);

                // purchaseFlow::rollbackを呼び出し, 購入処理をロールバックする.
                $this->purchaseFlow->rollback($order, new PurchaseContext());
                $this->entityManager->flush();

                $strErrorDetailMsg = $this->errorDetailMsg->getErrorDetailMsg($ret['responseCode'], $ret['responseDetail'], $order->getPaygentPaymentMethod());

                if ($strErrorDetailMsg != $this->eccubeConfig['paygent_payment']['no_mapping_message']) {
                    if($order->getCustomer()){
                        $strErrorDetailMsg .= "\n" . $this->eccubeConfig['paygent_payment']['telegram_error_member'];
                    } else {
                        $strErrorDetailMsg .= "\n" . $this->eccubeConfig['paygent_payment']['module_telegram_error_guest'];
                    }
                }
                $error = '決済に失敗しました。' . $ret['response'] . "\n" . $strErrorDetailMsg;

                logs('paygent_payment')->error("エラーが発生しました。");
                logs('paygent_payment')->error("ERROR_CODE => ". $ret['responseCode']);
                logs('paygent_payment')->error("ERROR_DETAIL => ".$ret['responseDetail']);
                logs('paygent_payment')->error("ERROR_MESSAGE => ".$error);
            }
        }

        $response = $this->frontBankService->setDefaultHeader(new Response());

        $arrReturn = [
            'form' => $form->createView(),
            'error' => $error,
        ];

        return $this->render('@PaygentPayment/default/payment_bank.twig', $arrReturn, $response);
    }

    /**
     * @Route("/paygent_payment/payment_bank/back", name="paygent_payment/payment_bank_back")
     */
    public function back()
    {
        // ご注文手続き画面へ遷移
        return $this->redirectToRoute('shopping');
    }
}
