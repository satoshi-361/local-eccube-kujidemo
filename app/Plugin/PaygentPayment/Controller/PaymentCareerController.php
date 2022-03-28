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
use Plugin\PaygentPayment\Form\Type\CareerPaymentType;
use Plugin\PaygentPayment\Service\Career\FrontCareerService;
use Plugin\PaygentPayment\Service\PaygentRecoverCartService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * モジュール型携帯キャリア決済のコントローラー
 */
class PaymentCareerController extends AbstractController
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
     * @var FrontCareerService
     */
    protected $frontCareerService;

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
     * @param CartService $cartService
     * @param MailService $mailService
     * @param FrontCareerService $frontCareerService
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param PaygentRecoverCartService $paygentRecoverCartService
     */
    public function __construct(
        CartService $cartService,
        MailService $mailService,
        FrontCareerService $frontCareerService,
        PurchaseFlow $shoppingPurchaseFlow,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        PaygentRecoverCartService $paygentRecoverCartService
    ) {
        $this->cartService = $cartService;
        $this->mailService = $mailService;
        $this->frontCareerService = $frontCareerService;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paygentRecoverCartService = $paygentRecoverCartService;
    }

    /**
     * @Route("/paygent_payment/payment_career", name="paygent_payment/payment_career")
     * @Template("@PaygentPayment/default/payment_career.twig")
     */
    public function index(Request $request)
    {
        $redirectHtml = $this->session->get($this->eccubeConfig['paygent_payment']['career_session']['redirect_html']);
        $error = $this->session->get($this->eccubeConfig['paygent_payment']['career_session']['error']);

        $form = $this->createForm(CareerPaymentType::class);
        $form->handleRequest($request);

        if ($error) {
            $this->session->remove($this->eccubeConfig['paygent_payment']['career_session']['error']);
        }

        if ($redirectHtml) {
            $this->session->remove($this->eccubeConfig['paygent_payment']['career_session']['redirect_html']);

        } else {
            // 注文前処理
            $order = $this->orderRepository->findOneBy([
                'pre_order_id' => $this->cartService->getPreOrderId(),
                'OrderStatus' => OrderStatus::PROCESSING,
            ]);

            if (!$order) {
                return $this->redirectToRoute('shopping_error');
            }

            if ($form->isSubmitted() && $form->isValid()) {
                logs('paygent_payment')->info("携帯キャリア決済開始");

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

                // 入力項目 値取得
                $inputParams = $form->getData();

                // career_typeからtelegram_kindを取得する
                switch ($inputParams['career_type']) {
                    // docomo 又は auの場合 認証電文処理
                    case $this->eccubeConfig['paygent_payment']['career_division_id']['career_type_au']:
                    case $this->eccubeConfig['paygent_payment']['career_division_id']['career_type_docomo']:
                        $this->session->set($this->eccubeConfig['paygent_payment']['career_session']['career_type'], $inputParams['career_type']);
                        $telegramKind = $this->eccubeConfig['paygent_payment']['paygent_career_commit_auth'];
                        break;
                    // softbankの場合 申込電文処理
                    case $this->eccubeConfig['paygent_payment']['career_division_id']['career_type_softbank']:
                        $telegramKind = $this->eccubeConfig['paygent_payment']['paygent_career'];
                        break;
                }

                // 電文の送信
                $ret = $this->frontCareerService->applyProcess($order, $inputParams, $telegramKind);

                if ($ret['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_success']) {
                    logs('paygent_payment')->info("キャリア決済画面へ遷移します");

                    // pre_order_idをセッションに保持
                    $this->session->set($this->eccubeConfig['paygent_payment']['session_pre_order_id'], $order->getPreOrderId());

                    // カートを削除する
                    $this->cartService->clear();

                    if (isset($ret['redirect_url']) && strlen($ret['redirect_url']) > 0) {
                        $response = $this->redirect($ret['redirect_url']);
                        return $response;
                    } else {
                        // 支払画面フォームをデコード
                        $redirectHtml = mb_convert_encoding($ret['redirect_html'], $this->eccubeConfig['paygent_payment']['char_code'], "Shift-JIS");
                    }
                } else {
                    // 受注ステータスを購入処理中へ変更
                    $orderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
                    $order->setOrderStatus($orderStatus);

                    // purchaseFlow::rollbackを呼び出し, 購入処理をロールバックする.
                    $this->purchaseFlow->rollback($order, new PurchaseContext());
                    $this->entityManager->flush();

                    $error = '決済に失敗しました。' . $ret['response'];
                    $this->errorLogOutput($ret, $error);
                }
            }
        }

        $response = $this->frontCareerService->setDefaultHeader(new Response());

        $arrReturn = [
            'form' => $form->createView(),
            'redirectHtml' => $redirectHtml,
            'error' => $error,
        ];

        return $this->render('@PaygentPayment/default/payment_career.twig', $arrReturn, $response);
    }

    /**
     * @Route("/paygent_payment_career_cancel", name="paygent_payment/career_cancel")
     * @Route("/paygent_payment_career_auth_ng", name="paygent_payment/career_auth_ng")
     */
    public function careerCancel(Request $request)
    {
        // セッションからpre_order_idを取得
        $preOrderId = $this->session->get($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // 不要なセッションを削除
        $this->session->remove($this->eccubeConfig['paygent_payment']['session_pre_order_id']);
        $this->session->remove($this->eccubeConfig['paygent_payment']['career_session']['career_type']);

        // 受注情報の取得
        if ($request->get('trading_id') && $request->get('payment_id')) {
            if ($request->get('trading_id') != $request->get('order_id')) {
                return $this->redirectToRoute('shopping_error');
            }

            $order = $this->orderRepository->findOneBy([
                'id' => $request->get('trading_id'),
                'paygent_payment_id' => $request->get('payment_id'),
                'OrderStatus' => OrderStatus::PENDING,
                'pre_order_id' => $preOrderId,
            ]);

        } else {
            $order = $this->orderRepository->findOneBy([
                'id' => $request->get('order_id'),
                'OrderStatus' => OrderStatus::PENDING,
                'pre_order_id' => $preOrderId,
            ]);
        }

        if ($order) {
            // 受注ステータスを購入処理中へ変更
            $orderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $order->setOrderStatus($orderStatus);

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

            return $this->redirectToRoute('cart');
        } else {
            return $this->redirectToRoute('shopping_error');
        }
    }

    /**
     * @Route("/paygent_payment_career_apply", name="paygent_payment/career_apply")
     */
    public function careerApply(Request $request)
    {
        // $inputParamsの設定
        $inputParams = $request->query->all();
        $careerType = $this->session->get($this->eccubeConfig['paygent_payment']['career_session']['career_type']);
        $inputParams['career_type'] = $careerType;

        // セッションからpre_order_idを取得
        $preOrderId = $this->session->get($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // 不要なセッションを削除
        $this->session->remove($this->eccubeConfig['paygent_payment']['career_session']['career_type']);

        // 受注情報の取得
        $order = $this->orderRepository->findOneBy([
            'id' => $request->get('order_id'),
            'OrderStatus' => OrderStatus::PENDING,
            'pre_order_id' => $preOrderId,
        ]);

        if (!$order || !$careerType) {
            return $this->redirectToRoute('shopping_error');
        }

        $telegramKind = $this->eccubeConfig['paygent_payment']['paygent_career'];

        // 申込電文の送信
        $ret = $this->frontCareerService->applyProcess($order, $inputParams, $telegramKind);

        if ($ret['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_success']) {
            if (isset($ret['redirect_url']) && strlen($ret['redirect_url']) > 0) {
                $response = $this->redirect($ret['redirect_url']);
                return $response;
            } else {
                // 支払画面フォームをデコード
                $redirectHtml = mb_convert_encoding($ret['redirect_html'], $this->eccubeConfig['paygent_payment']['char_code'], "Shift-JIS");
                $this->session->set($this->eccubeConfig['paygent_payment']['career_session']['redirect_html'], $redirectHtml);
                return $this->redirectToRoute('paygent_payment/payment_career');
            }
        } else {
            // セッションからpre_order_idを削除
            $this->session->remove($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

            // 受注ステータスを購入処理中へ変更
            $orderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $order->setOrderStatus($orderStatus);

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

            // エラー出力
            $error = '決済に失敗しました。' . $ret['response'];
            $this->errorLogOutput($ret, $error);

            // セッションにエラーをセット
            $this->session->set($this->eccubeConfig['paygent_payment']['career_session']['error'], $error);

            return $this->redirectToRoute('paygent_payment/payment_career');
        }
    }

    /**
     * @Route("/paygent_payment_career_complete", name="paygent_payment/career_complete")
     */
    public function careerComplete(Request $request)
    {
        // セッションからpre_order_idを取得
        $preOrderId = $this->session->get($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // セッションからpre_order_idを削除
        $this->session->remove($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // 受注情報の取得
        $order = $this->orderRepository->findOneBy([
            'id' => $request->get('trading_id'),
            'paygent_payment_id' => $request->get('payment_id'),
            'OrderStatus' => OrderStatus::PENDING,
            'pre_order_id' => $preOrderId,
        ]);

        if (!$order) {
            return $this->redirectToRoute('shopping_error');
        }

        // 完了画面を表示するため, 受注IDをセッションに保持する
        $this->session->set('eccube.front.shopping.order.id', $order->getId());

        // 注文完了画面にリダイレクト
        return $this->redirectToRoute('shopping_complete');
    }

    /**
     * @Route("/paygent_payment/payment_career/back", name="paygent_payment/payment_career_back")
     */
    public function back()
    {
        // ご注文手続き画面へ遷移
        return $this->redirectToRoute('shopping');
    }

    /**
     * エラーログを出力する
     */
    private function errorLogOutput($ret, $error)
    {
        logs('paygent_payment')->error("エラーが発生しました。");
        logs('paygent_payment')->error("ERROR_CODE => ". $ret['responseCode']);
        logs('paygent_payment')->error("ERROR_DETAIL => ".$ret['responseDetail']);
        logs('paygent_payment')->error("ERROR_MESSAGE => ".$error);
    }
}
