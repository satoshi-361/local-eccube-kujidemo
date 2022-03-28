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
use Plugin\PaygentPayment\Form\Type\PaidyPaymentType;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Plugin\PaygentPayment\Service\Paidy\FrontPaidyService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * モジュール型Paidy決済のコントローラー
 */
class PaymentPaidyController extends AbstractController
{
    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var FrontPaidyService
     */
    protected $frontPaidyService;

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
     * コンストラクタ
     * @param CartService $cartService
     * @param ConfigRepository $configRepository
     * @param MailService $mailService
     * @param FrontPaidyService $frontPaidyService
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     */
    public function __construct(
        CartService $cartService,
        ConfigRepository $configRepository,
        MailService $mailService,
        frontPaidyService $frontPaidyService,
        PurchaseFlow $shoppingPurchaseFlow,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository
    ) {
        $this->cartService = $cartService;
        $this->configRepository = $configRepository;
        $this->mailService = $mailService;
        $this->frontPaidyService = $frontPaidyService;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
    }

    /**
     * @Route("/paygent_payment/payment_paidy", name="paygent_payment/payment_paidy")
     * @Template("@PaygentPayment/default/payment_paidy.twig")
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

        $form = $this->createForm(PaidyPaymentType::class);
        $form->handleRequest($request);

        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {

            // 複数配送時は中断
            $shippings = $order->getShippings();
            if (count($shippings) > 1) {
                $error = 'Paidy決済は複数配送先をご指定いただいた場合はご利用いただけません。<br>別の決済手段をご検討ください。';

                $response = $this->frontPaidyService->setDefaultHeader(new Response());

                $arrReturn = [
                    'form' => $form->createView(),
                    'error' => $error,
                ];

                return $this->render('@PaygentPayment/default/payment_paidy.twig', $arrReturn, $response);
            }

            try {
                // purchaseFlow::prepareを呼び出し, 購入処理を進める.
                $this->purchaseFlow->prepare($order, new PurchaseContext());

                // 受注ステータスを決済処理中へ変更
                $orderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
                $order->setOrderStatus($orderStatus);

                $order->setPaygentCode($this->eccubeConfig['paygent_payment']['paygent_payment_code']);
                $order->setPaygentPaymentMethod($this->eccubeConfig['paygent_payment']['paygent_paidy']);

                $arrResponseDetail = [];
                $arrResponseDetail['ecOrderData'] = [
                    'payment_total' => $order->getPaymentTotal(),
                    'payment_total_check_status' => ''
                ];

                // 商品明細をセット
                $order->setResponseDetail(serialize($arrResponseDetail));

                $this->entityManager->flush();
            } catch (ShoppingException $e) {
                // 在庫切れの場合
                $this->addError($e->getMessage());
                return $this->redirectToRoute('shopping_error');
            }

            logs('paygent_payment')->info("Paidy決済画面へ遷移します");

            // pre_order_idをセッションに保持
            $this->session->set($this->eccubeConfig['paygent_payment']['session_pre_order_id'], $order->getPreOrderId());

            $checkoutForm = $this->createForm(PaidyPaymentType::class, null ,[
                'action' => $this->generateUrl('paygent_payment_paidy_checkout')
            ]);
            $checkoutForm->handleRequest($request);

            // プラグイン設定情報の取得
            $config = $this->configRepository->get();

            $response = $this->frontPaidyService->setDefaultHeader(new Response());

            $arrReturn = [
                'order_id' => $order->getId(),
                'form' => $checkoutForm->createView(),
                'api_key' => $config->getApiKey(),
                'logo_url' => $config->getLogoUrl(),
                'json_paidy' => $this->frontPaidyService->buildPaidyCheckout($order),
            ];

            return $this->render('@PaygentPayment/default/payment_paidy_checkout.twig', $arrReturn, $response);
        }

        $response = $this->frontPaidyService->setDefaultHeader(new Response());

        $arrReturn = [
            'form' => $form->createView(),
            'error' => $error,
        ];

        return $this->render('@PaygentPayment/default/payment_paidy.twig', $arrReturn, $response);
    }

    /**
     * Paidy決済 受注完了処理
     * @Route("/paygent_payment_paidy_checkout", name="paygent_payment_paidy_checkout", methods={"POST"})
     */
    public function checkout(Request $request)
    {
        // セッションからpre_order_idを取得
        $preOrderId = $this->session->get($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        $order = $this->orderRepository->findOneBy([
           'id' => $request->request->get('order_id'),
           'OrderStatus' => OrderStatus::PENDING,
           'pre_order_id' => $preOrderId,
        ]);

        // 受注が存在しない場合エラー
        if (!$order) {
            return $this->redirectToRoute('shopping_error');
        }

        $form = $this->createForm(PaidyPaymentType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            //正常なPOSTか確認

            if ($request->request->get('mode') === 'paidy_commit_cancel') {

                // 受注ステータスを購入処理中へ変更
                $orderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
                $order->setOrderStatus($orderStatus);

                // purchaseFlow::rollbackを呼び出し, 購入処理をロールバックする.
                $this->purchaseFlow->rollback($order, new PurchaseContext());

                $this->entityManager->flush();

                // ご注文手続き画面へ遷移
                return $this->redirectToRoute('shopping');
            } else if ($request->request->get('mode') === 'paidy_commit') {

                logs('paygent_payment')->info("Paidy決済開始");

                // 受注情報.レスポンス詳細のシリアライズ配列をアンシリアライズ
                $arrResponseDetail = unserialize($order->getResponseDetail());

                if ($arrResponseDetail === false) {
                    $arrResponseDetail = [];
                }

                // PaidyCheckoutからコールバックデータを取得
                $arrResponseDetail['callbackData'] = [
                    'amount' => $request->request->get('amount'),
                    'currency' => $request->request->get('currency'),
                    'created_at' => $request->request->get('created_at'),
                    'id' => $request->request->get('id'),
                    'status' => $request->request->get('status')
                ];

                // 注文完了画面メッセージ追加
                $order->appendCompleteMessage($this->frontPaidyService->getCompleteMessage($order));

                // 受注情報更新
                $order->setResponseDetail(serialize($arrResponseDetail));
                // クレジット決済でエラーかつレスポンスコードが2003になった場合(1G65等)、受注に決済IDが登録された状態になる
                // その後Paidyの差分通知を受信しても決済IDが異なり新規受付に変更できないのでnullを設定する
                $order->setPaygentPaymentId(null);
                $this->entityManager->flush($order);

                // カート削除
                $this->cartService->clear();

                // 完了画面を表示するため, 受注IDをセッションに保持する
                $this->session->set('eccube.front.shopping.order.id', $order->getId());

                // 注文完了画面に遷移
                return $this->redirectToRoute('shopping_complete');
            }
        }
    }

    /**
     * @Route("/paygent_payment/payment_paidy/back", name="paygent_payment/payment_paidy_back")
     */
    public function back()
    {
        // ご注文手続き画面へ遷移
        return $this->redirectToRoute('shopping');
    }
}
