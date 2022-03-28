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
use Eccube\Entity\Master\OrderStatus;
use Eccube\Exception\ShoppingException;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\CartService;
use Eccube\Service\MailService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\PaygentPayment\Form\Type\ATMPaymentType;
use Plugin\PaygentPayment\Service\Atm\FrontAtmService;
use Plugin\PaygentPayment\Service\Error\ErrorDetailMsg;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * モジュール型ATM決済のコントローラー
 */
class PaymentAtmController extends AbstractController
{
    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var FrontAtmService
     */
    protected $frontAtmService;

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
     * @var ErrorDetailMsg
     */
    private $errorDetailMsg;

    /**
     * コンストラクタ
     * @param CartService $cartService
     * @param MailService $mailService
     * @param FrontAtmService $frontAtmService
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param ErrorDetailMsg $errorDetailMsg
     */
    public function __construct(
        CartService $cartService,
        MailService $mailService,
        FrontAtmService $frontAtmService,
        PurchaseFlow $shoppingPurchaseFlow,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        ErrorDetailMsg $errorDetailMsg
    ) {
        $this->cartService = $cartService;
        $this->mailService = $mailService;
        $this->frontAtmService = $frontAtmService;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->errorDetailMsg = $errorDetailMsg;
    }

    /**
     * @Route("/paygent_payment/payment_atm", name="paygent_payment/payment_atm")
     * @Template("@PaygentPayment/default/payment_atm.twig")
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

        $form = $this->createForm(ATMPaymentType::class);
        $form->handleRequest($request);

        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            logs('paygent_payment')->info("ATM決済開始");

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
            $ret = $this->frontAtmService->applyProcess($order);

            if ($ret['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_success']) {
                // 受注ステータスを新規受付へ変更
                $orderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
                $order->setOrderStatus($orderStatus);

                // 注文完了画面、注文完了メールのメッセージを追加
                $order->appendCompleteMessage($this->frontAtmService->makeCompleteMessage($ret));
                $order->appendCompleteMailMessage($this->frontAtmService->makeCompleteMailMessage($ret));

                // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
                $this->purchaseFlow->commit($order, new PurchaseContext());
                $this->entityManager->flush();

                // メール送信
                $this->mailService->sendOrderMail($order);

                // カート削除
                $this->cartService->clear();

                // 完了画面を表示するため, 受注IDをセッションに保持する
                $this->session->set('eccube.front.shopping.order.id', $order->getId());

                // 注文完了画面にリダイレクト
                return $this->redirectToRoute('shopping_complete');
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

        $response = $this->frontAtmService->setDefaultHeader(new Response());

        $arrReturn = [
            'form' => $form->createView(),
            'error' => $error,
        ];

        return $this->render('@PaygentPayment/default/payment_atm.twig', $arrReturn, $response);
    }

    /**
     * @Route("/paygent_payment/payment_atm/back", name="paygent_payment/payment_atm_back")
     */
    public function back()
    {
        // ご注文手続き画面へ遷移
        return $this->redirectToRoute('shopping');
    }
}
