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
use Plugin\PaygentPayment\Form\Type\CreditPaymentType;
use Plugin\PaygentPayment\Service\Credit\FrontCreditService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Plugin\PaygentPayment\Service\CacheConfig;
use Plugin\PaygentPayment\Service\Credit\FrontCredit3dSecure2Service;
use Plugin\PaygentPayment\Service\Credit\FrontCreditCardStockService;

/**
 * モジュール型クレジット決済のコントローラー
 */
class PaymentCreditController extends AbstractController
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
     * @var FrontCreditService
     */
    protected $frontCreditService;

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
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var FrontCredit3dSecure2Service
     */
    protected $frontCredit3dSecure2Service;

    /**
     * @var FrontCreditCardStockService
     */
    protected $frontCreditCardStockService;

    /**
     * コンストラクタ
     * @param CartService $cartService
     * @param MailService $mailService
     * @param FrontCreditService $frontCreditService
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param CacheConfig $cacheConfig
     * @param FrontCredit3dSecure2Service $frontCredit3dSecure2Service
     * @param FrontCreditCardStockService $frontCreditCardStockService
     */
    public function __construct(
        CartService $cartService,
        MailService $mailService,
        FrontCreditService $frontCreditService,
        PurchaseFlow $shoppingPurchaseFlow,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        CacheConfig $cacheConfig,
        FrontCredit3dSecure2Service $frontCredit3dSecure2Service,
        FrontCreditCardStockService $frontCreditCardStockService
    ) {
        $this->cartService = $cartService;
        $this->mailService = $mailService;
        $this->frontCreditService = $frontCreditService;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->cacheConfig = $cacheConfig;
        $this->frontCredit3dSecure2Service = $frontCredit3dSecure2Service;
        $this->frontCreditCardStockService = $frontCreditCardStockService;
    }

    /**
     * @Route("/paygent_payment/payment_credit", name="paygent_payment/payment_credit")
     * @Template("@PaygentPayment/default/payment_credit.twig")
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

        $cardErrorCount = null;

        $form = $this->createForm(CreditPaymentType::class);
        $form->handleRequest($request);

        // 3Dセキュアのエラーを取得する
        $error = $this->session->get($this->eccubeConfig['paygent_payment']['credit_session']['credit_3dError']);
        // エラー存在時
        if ($error) {
            // オーソリ失敗回数のチェック
            $cardErrorCount = $this->session->get($this->eccubeConfig['paygent_payment']['credit_session']['card_error_count']);
            // 上限を超えない場合
            if ($cardErrorCount < $this->eccubeConfig['paygent_payment']['credit_authority_retry_limit']) {
                if (isset($cardErrorCount)) {
                    $cardErrorCount++;
                } else {
                    $cardErrorCount = 1;
                }
                $this->session->set($this->eccubeConfig['paygent_payment']['credit_session']['card_error_count'], $cardErrorCount);
            }
        }
        $this->session->remove($this->eccubeConfig['paygent_payment']['credit_session']['credit_3dError']);

        $errorCard = null;
        $listData = [];
        $ret = null;
        $config = $this->cacheConfig->getConfig();
        $isStockCard = $config->getModuleStockCard();
        $cardStockCount = 0;
        $isCustomer = false;

        $inputParams = $form->getData();

        // カード情報の取得
        $customer = $order->getCustomer();
        if ($customer) {
            if ($isStockCard && $customer->getPaygentCardStock()) {
                $listData = $this->getCardStock($order);
                $cardStockCount = count($listData);
            }
            $isCustomer = true;
        }

        if ($form->isSubmitted() && $form->isValid()) {
            logs('paygent_payment')->info("クレジット決済開始");

            // 入力項目 値取得
            $inputParams = $form->getData();
            $inputParams['customer_card_id'] = $request->request->get('customer_card_id');

            if ($config->getCredit3d()) {
                $inputParams['http_accept'] = $request->headers->get('Accept');
                $inputParams['http_user_agent'] = $request->headers->get('User-Agent');
            }

            // カード情報削除判定
            if ($inputParams['delete_card']) {
                if (isset($inputParams['customer_card_id'])) {
                    $telegramKind = $this->eccubeConfig['paygent_payment']['paygent_card_stock_del'];
                    // カード情報削除電文の送信
                    $ret = $this->frontCreditCardStockService->applyProcess($order, $inputParams, $telegramKind);
                } else {
                    $errorCard = '※ 削除カードが入力されていません。';
                }
            } else {
                // オーソリ失敗回数のチェック
                $cardErrorCount = $this->session->get($this->eccubeConfig['paygent_payment']['credit_session']['card_error_count']);
                if ($cardErrorCount < $this->eccubeConfig['paygent_payment']['credit_authority_retry_limit']) {
                    // 分割回数チェック
                    if (strpos($inputParams['payment_class'], '-')) {
                        list ( $paymentClass, $splitCount ) = explode("-", $inputParams['payment_class'] );
                        // 支払い区分
                        $inputParams['payment_class'] = $paymentClass;
                        // 分割回数
                        $inputParams['split_count'] = $splitCount;
                    }

                    try {
                        // 注文仮確定
                        $this->purchaseFlow->prepare($order, new PurchaseContext());
                        $this->entityManager->flush();
        
                        // 受注ステータスを決済処理中へ変更
                        $orderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
                        $order->setOrderStatus($orderStatus);
                        $this->entityManager->flush();
                    } catch (ShoppingException $e) {
                        // 在庫切れの場合
                        $this->addError($e->getMessage());
                        return $this->redirectToRoute('shopping_error');
                    }

                    if ($config->getCredit3d() === $this->eccubeConfig['paygent_payment']['credit_3d']['3dSecure_2']) {
                        $telegramKind = $this->eccubeConfig['paygent_payment']['payment_card_3d2'];
                        // 3Dセキュア2.0の場合
                        $ret = $this->frontCredit3dSecure2Service->send3dSecure2Auth($order, $inputParams, $telegramKind);
                    } else {
                        $telegramKind = $this->eccubeConfig['paygent_payment']['paygent_credit'];
                        // 電文の送信
                        $ret = $this->frontCreditService->applyProcess($order, $inputParams, $telegramKind);
                    }
                }
            }
        }

        if ($ret) {
            if ($ret['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_success']) {
                switch ($telegramKind) {
                    case $this->eccubeConfig['paygent_payment']['paygent_card_stock_del']:
                        $response = $this->redirectToRoute('paygent_payment/payment_credit');
                        return $this->frontCreditService->setDefaultHeader($response);
                        break;
                    case $this->eccubeConfig['paygent_payment']['paygent_credit']:
                        // カード情報登録
                        $this->setCardStock($order, $inputParams, $cardStockCount);

                        // オーソリ失敗回数のリセット
                        $this->session->remove($this->eccubeConfig['paygent_payment']['credit_session']['card_error_count']);

                        // 受注ステータスを新規受付へ変更
                        $orderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
                        $order->setOrderStatus($orderStatus);

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
                        break;
                    case $this->eccubeConfig['paygent_payment']['payment_card_3d2']:
                        // pre_order_idをセッションに保持
                        $this->session->set($this->eccubeConfig['paygent_payment']['session_pre_order_id'], $order->getPreOrderId());
                        // 認証結果応答電文受信後のオーソリで使用するために入力値を保存する。
                        $this->session->set($this->eccubeConfig['paygent_payment']['credit_session']['credit_3d2_input'], $inputParams);

                        // カード会社画面へ遷移（ACS支払人認証要求HTMLを表示）
                        $response = new Response(
                            $ret['out_acs_html'],
                            Response::HTTP_OK,
                            ['content-type' => 'text/html']
                        );
                        return $this->frontCreditService->setDefaultHeader($response);
                        break;
                }
            } elseif ($ret['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_3dsecure']) {
                // カード情報登録
                $this->setCardStock($order, $inputParams, $cardStockCount);

                // pre_order_idをセッションに保持
                $this->session->set($this->eccubeConfig['paygent_payment']['session_pre_order_id'], $order->getPreOrderId());

                $charCode = $this->eccubeConfig['paygent_payment']['char_code'];
                // カード会社画面へ遷移（ACS支払人認証要求HTMLを表示）
                $acsHtml = mb_convert_encoding($ret['out_acs_html'], $charCode, "Shift-JIS");
                $response = new Response(
                    $acsHtml,
                    Response::HTTP_OK,
                    ['content-type' => 'text/html']
                );
                logs('paygent_payment')->info("3Dセキュア入力画面へ遷移します");
                return $this->frontCreditService->setDefaultHeader($response);

            } else {
                // 受注ステータスを購入処理中へ変更
                $orderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
                $order->setOrderStatus($orderStatus);
                $this->entityManager->flush();

                // purchaseFlow::rollbackを呼び出し, 購入処理をロールバックする.
                $this->purchaseFlow->rollback($order, new PurchaseContext());
                $this->entityManager->flush();

                // オーソリ失敗回数の管理
                $cardErrorCount = $this->session->get($this->eccubeConfig['paygent_payment']['credit_session']['card_error_count']);
                if (isset($cardErrorCount)) {
                    $cardErrorCount++;
                } else {
                    $cardErrorCount = 1;
                }
                $this->session->set($this->eccubeConfig['paygent_payment']['credit_session']['card_error_count'], $cardErrorCount);

                $errorMsg = null;
                switch ($telegramKind) {
                    case $this->eccubeConfig['paygent_payment']['paygent_card_stock_del']:
                        $errorMsg = '登録カード情報の削除に失敗しました。';
                        break;
                    case $this->eccubeConfig['paygent_payment']['paygent_credit']:
                    case $this->eccubeConfig['paygent_payment']['payment_card_3d2']:
                        $errorMsg = '決済に失敗しました。';
                        break;
                }
                $error = $errorMsg . $ret['response'];
                $this->errorLogOutput($ret, $error);
            }
        }

        if ($cardErrorCount >= $this->eccubeConfig['paygent_payment']['credit_authority_retry_limit']) {
            if ($error) {
                $error .= "<br>";
            }
            $error .= $this->eccubeConfig['paygent_payment']['credit_authority_lock_message'];
        }

        $response = $this->frontCreditService->setDefaultHeader(new Response());

        $arrReturn = [
            'form' => $form->createView(),
            'error' => $error,
            'error_card' => $errorCard,
            'isSecurityCode' => $config->getSecurityCode(),
            'isStockCard' => $isStockCard,
            'list_data' => $listData,
            'isCustomer' => $isCustomer,
            'javascriptUrl' => $this->getJavascriptUrl(),
            'merchant_id' => $config->getMerchantId(),
            'token_key' => $config->getTokenKey(),
            'card_stock_max' => $this->eccubeConfig['paygent_payment']['card_stock_max'],
            'card_stock_count' => $cardStockCount,
            'paygent_token_connect_url' => $this->eccubeConfig['paygent_payment']['paygent_token_connect_url'],
        ];

        return $this->render('@PaygentPayment/default/payment_credit.twig', $arrReturn, $response);

    }

    /**
     * @Route("/paygent_payment/payment_credit/back", name="paygent_payment/payment_credit_back")
     */
    public function back()
    {
        // ご注文手続き画面へ遷移
        return $this->redirectToRoute('shopping');
    }

    /**
     * @Route("/paygent_payment_credit_3d", name="paygent_payment/credit_3d")
     */
    public function credit3dSecure(Request $request)
    {
        // 処理結果 0=正常終了, 1=異常終了
        $resultStatus = $request->get('result');

        if ($request->get('order_id') != $request->get('trading_id')) {
            return $this->redirectToRoute('shopping_error');
        }

        // ハッシュ値のチェック
        $config = $this->cacheConfig->getConfig();
        $hash = hash('sha256', $resultStatus . $request->get('payment_id') . $request->get('attempt_kbn') . $config->getCredit3dHashKey());

        if ($hash != $request->get('hc')){
            return $this->redirectToRoute('shopping_error');
        }

        // セッションからpre_order_idを取得
        $preOrderId = $this->session->get($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // セッションからpre_order_idを削除
        $this->session->remove($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // 受注情報の取得
        $order = $this->orderRepository->findOneBy([
            'id' => $request->get('order_id'),
            'paygent_payment_id' => $request->get('payment_id'),
            'OrderStatus' => OrderStatus::PENDING,
            'pre_order_id' => $preOrderId,
        ]);

        if (!$order) {
            return $this->redirectToRoute('shopping_error');
        }

        if ($resultStatus === $this->eccubeConfig['paygent_payment']['result_success']) {
            // オーソリ失敗回数のリセット
            $this->session->remove($this->eccubeConfig['paygent_payment']['credit_session']['card_error_count']);

            // 受注ステータスを新規受付へ変更
            $orderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
            $order->setOrderStatus($orderStatus);

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
            // 異常終了時、レスポンスコードが取得できる
            $responseCode = $request->get('response_code');

            // 異常終了時、レスポンス詳細が取得できる
            $responseDetail = mb_convert_encoding($request->get('response_detail'), $this->eccubeConfig['paygent_payment']['char_code'], $this->eccubeConfig['paygent_payment']['char_code_ks']);

            // 画面表示用の値をセット
            if ($responseCode) {
                if (preg_match('/^[P|E]/', $responseCode)) {
                    $error = '決済に失敗しました。' . "（". $responseCode. "）";
                } else {
                    $error = '決済に失敗しました。' . $responseDetail. "（". $responseCode. "）";
                }
            } else {
                $error = "";
            }

            $this->session->set($this->eccubeConfig['paygent_payment']['credit_session']['credit_3dError'], $error);

            $res = null;
            $res['resultStatus'] = $resultStatus;
            $res['responseCode'] = $responseCode;
            $res['responseDetail'] = $responseDetail;

            $this->errorLogOutput($res, $error);

            $this->frontCreditService->saveOrder($order, null, $res);

            // 受注ステータスを購入処理中へ変更
            $orderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $order->setOrderStatus($orderStatus);

            // purchaseFlow::rollbackを呼び出し, 購入処理をロールバックする.
            $this->purchaseFlow->rollback($order, new PurchaseContext());
            $this->entityManager->flush();

            return $this->redirectToRoute('paygent_payment/payment_credit');
        }
    }

    /**
     * @Route("/paygent_payment_credit_3d2", name="paygent_payment/credit_3d2")
     */
    public function credit3d2Secure(Request $request)
    {
        $resultStatus = null;
        $responseCode = null;
        $responseDetail = null;
        $creditAuthRet = null;

        // 3Dセキュア2.0認証結果応答電文の処理結果 0=正常終了, 1=異常終了
        $resultStatus = $request->get('result');

        // セッションから入力情報を取得し削除
        $inputParams = $this->session->get($this->eccubeConfig['paygent_payment']['credit_session']['credit_3d2_input']);
        $this->session->remove($this->eccubeConfig['paygent_payment']['credit_session']['credit_3d2_input']);

        // セッションからpre_order_idを取得し削除
        $preOrderId = $this->session->get($this->eccubeConfig['paygent_payment']['session_pre_order_id']);
        $this->session->remove($this->eccubeConfig['paygent_payment']['session_pre_order_id']);

        // ハッシュ値のチェック
        $config = $this->cacheConfig->getConfig();
        $hash = hash('sha256', $resultStatus . $request->get('3ds_auth_id') . $request->get('attempt_kbn') . $config->getCredit3dHashKey());

        if ($hash != $request->get('hc')){
            return $this->redirectToRoute('shopping_error');
        }

        // 受注情報の取得
        $order = $this->orderRepository->findOneBy([
            'OrderStatus' => OrderStatus::PENDING,
            'pre_order_id' => $preOrderId,
        ]);

        if (!$order) {
            return $this->redirectToRoute('shopping_error');
        }

        // 3Dセキュア2.0認証結果応答電文の処理結果が正常の場合
        if ($resultStatus === $this->eccubeConfig['paygent_payment']['result_success']) {

            // オーソリ電文を送信する。(３Dセキュアのオーソリ電文の処理とほぼ一緒)
            $creditAuthRet = $this->frontCredit3dSecure2Service->sendCreditAuth($order, $inputParams, $request->get('3ds_auth_id'));
        // 2.3Dセキュア2.0認証結果応答電文の処理結果が異常の場合
        } else {
            // 異常終了時、レスポンスコードが取得できる
            $responseCode = $request->get('response_code');

            // 異常終了時、レスポンス詳細が取得できる
            $responseDetail = $request->get('response_detail');

            $credit3D2Res = [
                'resultStatus' => $resultStatus,
                'responseCode' => $responseCode,
                'responseDetail' => $responseDetail,
            ];

            $this->errorLogOutput($credit3D2Res, "3Dセキュア2.0認証結果応答電文エラー：".$responseDetail);
        }

        if ($creditAuthRet) {
            // オーソリ電文のレスポンスが正常の場合
            if ($creditAuthRet['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_success']) {

                // カード情報の取得
                $cardStockCount = 0;
                $customer = $order->getCustomer();
                if ($customer && $config->getModuleStockCard() && $customer->getPaygentCardStock()) {
                    $listData = $this->getCardStock($order);
                    $cardStockCount = count($listData);
                }

                // カード登録
                $this->setCardStock($order, $inputParams, $cardStockCount);
                // オーソリ失敗回数のリセット
                $this->session->remove($this->eccubeConfig['paygent_payment']['credit_session']['card_error_count']);
    
                // 受注ステータスを新規受付へ変更
                $orderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
                $order->setOrderStatus($orderStatus);
    
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
                $resultStatus = $creditAuthRet['resultStatus'];
                // 異常終了時、レスポンス詳細が取得できる
                $responseDetail = $creditAuthRet['responseDetail'];
                $responseCode = $creditAuthRet['responseCode'];
            }
        }

        // 画面表示用の値をセット
        if ($responseCode) {
            if (preg_match('/^[P|E]/', $responseCode)) {
                $error = '決済に失敗しました。' . "（". $responseCode. "）";
            } else {
                $error = '決済に失敗しました。' . $responseDetail. "（". $responseCode. "）";
            }
        } else {
            $error = "決済に失敗しました。";
        }

        $this->session->set($this->eccubeConfig['paygent_payment']['credit_session']['credit_3dError'], $error);

        $res = null;
        $res['resultStatus'] = $resultStatus;
        $res['responseCode'] = $responseCode;
        $res['responseDetail'] = $responseDetail;

        $this->errorLogOutput($res, $error);

        $this->frontCreditService->saveOrder($order, null, $res);

        // 受注ステータスを購入処理中へ変更
        $orderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
        $order->setOrderStatus($orderStatus);

        // purchaseFlow::rollbackを呼び出し, 購入処理をロールバックする.
        $this->purchaseFlow->rollback($order, new PurchaseContext());
        $this->entityManager->flush();

        return $this->redirectToRoute('paygent_payment/payment_credit');
    }

    /**
     * トークン決済で利用するjavascriptのurlを取得する
     *
     * @return string $url
     */
    private function getJavascriptUrl()
    {
        $config = $this->cacheConfig->getConfig();
        $selectedId = $config->getTokenEnv();
        $url = null;
        foreach ($this->eccubeConfig['paygent_payment']['credit_token_env_id'] as $tokenEnvName => $tokenEnvId) {
            if ($tokenEnvId == $selectedId) {
                $url = $this->eccubeConfig['paygent_payment']['credit_token_env_js_url'][$tokenEnvName];
            }
        }
        return $url;
    }

    /**
     * 登録済みカード情報を取得する
     *
     * @return array $listData
     */
    private function getCardStock($order)
    {
        $listData = [];

        $telegramKind = $this->eccubeConfig['paygent_payment']['paygent_card_stock_get'];
        // カード情報取得電文の送信
        $cardStockRet = $this->frontCreditCardStockService->applyProcessCreditStock($order, $telegramKind);

        if ($cardStockRet[0]['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_success']) {
            foreach ($cardStockRet as $key => $cardData) {
                $listData[$key]['customer_card_id'] = $cardData['customer_card_id'];
                $listData[$key]['card_number'] = $cardData['card_number'];
                $listData[$key]['card_valid_term'] = $cardData['card_valid_term'];
                $listData[$key]['cardholder_name'] = $cardData['cardholder_name'];
            }
        }
        return $listData;
    }

    /**
     * カード情報を登録する
     */
    private function setCardStock($order, $inputParams, $cardStockCount)
    {
        $isStockNew = isset($inputParams['stock_new']) && $inputParams['stock_new'];
        $isUseStock = isset($inputParams['stock']) && $inputParams['stock'];
        if ($isStockNew && $isUseStock == false && $cardStockCount < $this->eccubeConfig['paygent_payment']['card_stock_max']) {
            $telegramKind = $this->eccubeConfig['paygent_payment']['paygent_card_stock_set'];
            // カード情報登録電文の送信
            $this->frontCreditCardStockService->applyProcess($order, $inputParams, $telegramKind);
        }
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
