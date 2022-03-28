<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Controller;

use Eccube\Controller\AbstractController;
use Eccube\Service\CartService;
use Eccube\Service\OrderHelper;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Plugin\VeriTrans4G\Entity\Vt4gOrderPayment;
use Plugin\VeriTrans4G\Entity\Master\Vt4gSubscSaleType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 決済関連コントローラー
 */
class PaymentController extends AbstractController
{
    /**
     * コンテナ
     */
    protected $container;

    /**
     * エンティティーマネージャー
     */
    protected $em;

    /**
     * VT用固定値配列
     */
    protected $vt4gConst;

    /**
     * 汎用処理用ユーティリティ
     */
    protected $util;

    /**
     * MDK Logger
     */
    protected $mdkLogger;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * コンストラクタ
     * @param ContainerInterface $container
     * @return void
     */
    public function __construct(
        ContainerInterface $container,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        CartService $cartService
    )
    {
        $this->container = $container;
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
        $this->util = $container->get('vt4g_plugin.service.util');
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->cartService = $cartService;
    }

    /**
     * 購入フロー決済画面
     *
     * @Route("/shopping/vt4g_payment", name="vt4g_shopping_payment")
     * @param  Request $request リクエストデータ
     * @return object           ビューレスポンス|レダイレクトレスポンス
     */
    public function index(Request $request, CartService $cartService, OrderRepository $orderRepository)
    {
        $mode = $request->get('mode');
        $preOrderId = $cartService->getPreOrderId();
        if (!empty($preOrderId)) {
            $order = $orderRepository->findOneBy(['pre_order_id' => $preOrderId]);
        } else {
            // 結果通知でカートをクリアした後に戻ってくる場合があるのでポストパラメータから注文を取得
            $memo01 = null;
            if (!empty($request->get('OrderId'))) {
                $memo01 = $request->get('OrderId');
            } elseif (!empty($request->get('orderId'))) {
                $memo01 = $request->get('orderId');
            }

            if (is_null($memo01)) {
                $order = null;
            } else {
                $orderPayment = $this->em->getRepository(Vt4gOrderPayment::class)->findOneBy(['memo01' => $memo01]);
                $order = $orderRepository->findOneBy(['id' => $orderPayment->getOrderId()]);
            }
        }

        // 決済の入力チェック
        $result = $this->container->get('vt4g_plugin.service.payment_base')->checkPaymentData($cartService, $order);
        if ($result !== true) {
            return $result;
        }

        $payment = $order->getPayment();
        $payId   = $this->util->getPayId($payment->getId());
        $payCode = $this->util->getPayCode($payId);
        $payload = [
            'paymentType' => $payCode,
            'mode'        => $mode,
            'order'       => $order,
            'paymentInfo' => $this->util->getPaymentMethodInfo($payment->getId()),
            'user'        => $this->getUser()
        ];
        $method = "exec{$payCode}Process";

        return $this->$method($request, $payload);
    }

    /**
     * 購入フロー決済完了画面
     *
     * @Route("/shopping/vt4g_payment/complete", name="vt4g_shopping_payment_complete")
     * @param  Request $request リクエストデータ
     * @return object           リダイレクトレスポンス
     */
    public function complete(Request $request)
    {
        $orderId = $request->get('no');
        $order = $this->getOrderById($orderId);

        // 注文が存在しない もしくは ログインユーザと注文者が一致しない場合
        if (!$order || $this->getUser() != $order->getCustomer()) {
            throw new NotFoundHttpException();
        }

        // 完了画面を表示するため、受注IDをセッションに保持する
        $this->session->set(OrderHelper::SESSION_ORDER_ID, $order->getId());

        //ネットバンク決済を使用している時のみ、注文/決済ステータスを変更する。
        $orderPayment = $this->util->getOrderPayment($order->getId());
        $payId = $orderPayment->getMemo03();
        if($payId == $this->vt4gConst['VT4G_PAYTYPEID_BANK']){
            $bank = $this->container->get('vt4g_plugin.service.payment_bank');
            $bank->changePayStatus($order, $orderPayment);
        }
        return $this->redirectToRoute('shopping_complete');
    }

    /**
     * 購入フロー決済 戻る処理
     *
     * @Route("/shopping/vt4g_payment/back", name="vt4g_shopping_payment_back")
     * @param  Request $request リクエストデータ
     * @return object           リダイレクトレスポンス
     */
    public function back(Request $request)
    {
        $orderId = $request->get('no');
        $order = $this->getOrderById($orderId);

        // 注文が存在しない もしくは ログインユーザと注文者が一致しない場合
        if (!$order || $this->getUser() != $order->getCustomer()) {
            throw new NotFoundHttpException();
        }

        // 注文ステータスを購入処理中へ変更 購入処理をロールバックする.
        $base = $this->container->get('vt4g_plugin.service.payment_base');
        $base->rollback($order);

        return $this->redirectToRoute('shopping');
    }

    /**
     * クレジットカード決済処理
     *
     * @param  Request $request リクエストデータ
     * @param  array   $payload 決済処理に使用するデータ
     * @return object           リダイレクトレスポンス|ビューレスポンス
     */
    private function execCreditProcess($request, $payload)
    {
        $credit = $this->container->get('vt4g_plugin.service.payment_credit');
        $error = [
            'payment' => '',
            'credit'  => ''
        ];

        // クレジットカード情報入力フォーム
        $form = $credit->createCreditForm($payload['paymentInfo']);
        // ベリトランス会員ID決済入力フォーム
        $accountForm = $credit->createAccountForm($payload['paymentInfo']);
        // 再取引入力フォーム
        $oneClickForm = $credit->createOneClickForm($payload['paymentInfo']);

        $form->handleRequest($request);
        $accountForm->handleRequest($request);
        $oneClickForm->handleRequest($request);

        // フォームのバリデーション結果
        $isValid = true;
        if ($form->isSubmitted()) {
            $isValid = $form->isValid();
        } else if ($accountForm->isSubmitted()) {
            $isValid = $accountForm->isValid();
        } else if ($oneClickForm->isSubmitted()) {
            $isValid = $oneClickForm->isValid();
        }

        // 入力フォーム送信時
        if ($isValid && $request->getMethod() === 'POST') {
            switch ($payload['mode']) {
                case 'token':   // MDKトークン利用
                case 'retrade': // 再取引
                case 'account': // ベリトランス会員ID決済
                    $result = $credit->commitNormalPayment($request->request, $payload, $error);
                    break;
                case 'comp': // 本人認証リダイレクト
                    $result = $credit->commitMpiPayment($request, $payload, $error);
                    break;
                default:
                    return $credit->makeErrorResponse();
            }

            if ($result) {
                return $this->redirectToRoute('vt4g_shopping_payment_complete', ['no' => $payload['order']->getId()]);
            }

            // 入力フォーム上にエラー表示の場合はロールバックを行わない
            if (empty($error['credit'])) {
                // ロールバック
                $credit->rollback($payload['order']);
            }

        } else if ($isValid && $request->getMethod() === 'GET' && $payload['mode'] == 'comp') {
            $result = $credit->commitMpiPayment($request, $payload, $error);

            if ($result) {
                return $this->redirectToRoute('vt4g_shopping_payment_complete', ['no' => $payload['order']->getId()]);
            }

            // 入力フォーム上にエラー表示の場合はロールバックを行わない
            if (empty($error['credit'])) {
                // ロールバック
                $credit->rollback($payload['order']);
            }
        }

        $pluginSetting = $this->util->getPluginSetting();

        // ベリトランス会員ID決済の利用可否
        $useAccountPayment = $payload['paymentInfo']['one_click_flg'] === $this->vt4gConst['VT4G_CREDIT_ONE_CLICK']['VERITRANS_ID'];

        // 登録済みカード情報を取得
        $accountCards = $useAccountPayment && $this->isGranted('IS_AUTHENTICATED_FULLY') && !empty($payload['user']->vt4g_account_id)
            ? $this->container->get('vt4g_plugin.service.vt4g_account_id')->getAccountCards($payload['user']->vt4g_account_id)
            : [];
        // 登録済みカード情報が存在しない場合
        if (empty($accountCards)) {
            $useAccountPayment = false;
        }

        $cardsMax = $payload['paymentInfo']['cardinfo_regist_max'] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['CARDINFO_REGIST_MAX'];

        // 再取引の利用可否
        $useReTradePayment = $payload['paymentInfo']['one_click_flg'] === $this->vt4gConst['VT4G_CREDIT_ONE_CLICK']['RETRADE'];

        // 再取引用の注文情報を取得
        $reTradeCards = $useReTradePayment
            ? $credit->getReTradeCards($payload['user'])
            : [];
        // 再取引に使用可能なカード情報が存在しない場合
        if (empty($reTradeCards)) {
            $useReTradePayment = false;
        }
        // 継続課金商品の注文か判定
        $subscOrderFlg = $this->em->getRepository(Vt4gSubscSaleType::class)->judgmentSubscSaleTypeByOrderId($payload['order']->getId());

        return $this->render('VeriTrans4G/Resource/template/default/Shopping/vt4g_payment.twig', [
            'paymentType'       => $payload['paymentType'],
            'form'              => $form->createView(),
            'accountForm'       => $accountForm->createView(),
            'oneClickForm'      => $oneClickForm->createView(),
            'error'             => $error,
            'orderNo'           => $payload['order']->getId(),
            'paymentInfo'       => $payload['paymentInfo'],
            'title'             => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_10'],
            'mode'              => $payload['mode'],
            'useAccountPayment' => $useAccountPayment,
            'accountCards'      => $accountCards,
            'useReTradePayment' => $useReTradePayment,
            'reTradeCards'      => $reTradeCards,
            'tokenApiUrl'       => $this->vt4gConst['VT4G_PLUGIN_TOKEN_API_ENDPOINT'],
            'tokenApiKey'       => $pluginSetting['token_api_key'],
            'tokenJsPath'       => $this->util->getTokenJsPath(),
            'cardRegistFlg'     => $payload['user'] != null && $payload['paymentInfo']['one_click_flg'] === $this->vt4gConst['VT4G_CREDIT_ONE_CLICK']['VERITRANS_ID'],
            'cardRetradeFlg'    => $payload['user'] != null && $payload['paymentInfo']['one_click_flg'] === $this->vt4gConst['VT4G_CREDIT_ONE_CLICK']['RETRADE'],
            'isCardMaxOver'     => count($accountCards) >= $cardsMax,
            'subscOrderFlg'     => $subscOrderFlg,
        ]);
    }

    /**
     * ATM決済処理
     *
     * @param  request $request リクエストデータ
     * @param  array   $payload 決済処理に使用するデータ
     * @return object           リダイレクトレスポンス|ビューレスポンス
     */
    private function execATMProcess($request, $payload)
    {
        $mode = $request->get('mode');
        $atm = $this->container->get('vt4g_plugin.service.payment_atm');
        $error = [
            'payment' => '',
        ];
        $form = $atm->createATMForm();

        if ('POST' === $request->getMethod()) {
            if ($mode == "next") {
                $formData = $form->getData();
                $result = $atm->atmCommit($payload['order'], $formData, $payload['paymentInfo'], $error);
                if ($result){
                    return $this->redirectToRoute('vt4g_shopping_payment_complete', ['no' => $payload['order']->getId()]);
                }
                // ロールバック
                $atm->rollback($payload['order']);
            }
        }

        return $this->render('VeriTrans4G/Resource/template/default/Shopping/vt4g_payment.twig', [
            'paymentType'  => $payload['paymentType'],
            'form'         => $form->createView(),
            'error'        => $error,
            'title'        => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_31'],
            'orderNo'      => $payload['order']->getId(),
            'tplIsLoading' => empty($error['payment']),
            'loadingImage' => $this->util->getLoadingImage()
        ]);
    }

    /**
     * ネットバンク決済処理
     *
     * @param  request $request リクエストデータ
     * @param  array   $payload 決済処理に使用するデータ
     * @return object           リダイレクトレスポンス|ビューレスポンス
     */
    public function execBankProcess($request, $payload)
    {
        $bank = $this->container->get('vt4g_plugin.service.payment_bank');
        $form = $bank->createBankForm();
        $error = [
            'payment' => '',
        ];

        $form->handleRequest($request);
        $formData = $form->getData();

        // POST用リクエストパラメータ
        $result = $bank->bankCommit($payload['order'], $formData, $payload['paymentInfo'], $error);
        if ($result == false){
            // ロールバック
            $bank->rollback($payload['order']);
        }
        return $this->render('VeriTrans4G/Resource/template/default/Shopping/vt4g_payment.twig', [
            'paymentType'  => $payload['paymentType'],
            'form'         => $form->createView(),
            'error'        => $error,
            'title'        => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_30'],
            'orderNo'      => $payload['order']->getId(),
            'paymentInfo'  => $payload['paymentInfo'],
            'tplIsLoading' => empty($error['payment']),
            'loadingImage' => $this->util->getLoadingImage(),
            'requestParam' => $result,
        ]);
    }

    /**
     * コンビニ決済処理
     *
     * @param  Request $request リクエストデータ
     * @param  array   $payload 決済処理に使用するデータ
     * @return object           リダイレクトレスポンス|ビューレスポンス
     */
    private function execCVSProcess($request, $payload)
    {
        $mode = $request->get('mode');
        $cvs = $this->container->get('vt4g_plugin.service.payment_cvs');
        $error = [
            'payment' => '',
        ];

        // コンビニ情報入力フォーム
        $form = $cvs->createCVSForm($payload['paymentInfo']);
        $form->handleRequest($request);

        // 入力フォーム送信時
        if ($request->getMethod() === 'POST') {
            if ($mode == "next" && $form->isSubmitted() && $form->isValid()) {
                // POST用リクエストパラメータ
                $result = $cvs->cvsCommit($payload['order'], $request->request, $payload['paymentInfo'], $error);
                if ($result !== false){
                    return $this->redirectToRoute('vt4g_shopping_payment_complete', ['no' => $payload['order']->getId()]);
                } else {
                    // ロールバック
                    $cvs->rollback($payload['order']);
                }
            }
        }

        return $this->render('VeriTrans4G/Resource/template/default/Shopping/vt4g_payment.twig', [
            'paymentType'  => $payload['paymentType'],
            'form'         => $form->createView(),
            'error'        => $error,
            'title'        => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_20'],
            'orderNo'      => $payload['order']->getId(),
            'paymentInfo'  => $payload['paymentInfo'],
            'tplIsLoading' => false,
        ]);
    }

    /**
     * Alipay決済処理
     *
     * @param  Request $request リクエストデータ
     * @param  array   $payload 決済処理に使用するデータ
     * @return object           リダイレクトレスポンス|ビューレスポンス
     */
    private function execAlipayProcess($request, $payload)
    {
        $alipay = $this->container->get('vt4g_plugin.service.payment_alipay');
        $error = [
            'payment' => '',
        ];
        $requestHtml ='';
        $form = $alipay->createAlipayForm($payload['paymentInfo']);
        $form->handleRequest($request);
        $formData = $form->getData();

        if ($request->getMethod() === 'POST') {
            if ($payload['mode'] == 'next') {
                // リダイレクト用HTML取得
                $result = $alipay->alipayCommit($payload['order'], $formData, $payload['paymentInfo'], $error);
                if ($result !== false) {
                    $requestHtml = $result;
                }else{
                    // ロールバック
                    $alipay->rollback($payload['order']);
                }
            } else {
                // 戻り完了処理
                $result = $alipay->alipayComplete($request, $payload['order'], $error);
                if ($payload['mode'] == 'success' && $result !== false) {
                    return $this->redirectToRoute('vt4g_shopping_payment_complete', ['no' => $payload['order']->getId()]);
                }
                // ロールバック
                $alipay->rollback($payload['order']);
            }
        }

        return $this->render('VeriTrans4G/Resource/template/default/Shopping/vt4g_payment.twig', [
            'paymentType'  => $payload['paymentType'],
            'form'         => $form->createView(),
            'error'        => $error,
            'title'        => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_50'],
            'orderNo'      => $payload['order']->getId(),
            'paymentInfo'  => $payload['paymentInfo'],
            'requestHtml'  => $requestHtml,
            'tplIsLoading' => empty($error['payment']),
            'loadingImage' => $this->util->getLoadingImage()
        ]);
    }

    /**
     * 銀聯ネット決済処理
     *
     * @param  Request $request リクエストデータ
     * @param  array   $payload 決済処理に使用するデータ
     * @return object           リダイレクトレスポンス|ビューレスポンス
     */
    private function execUPOPProcess($request, $payload)
    {
        $upop = $this->container->get('vt4g_plugin.service.payment_upop');
        $form = $upop->createUpopForm();
        $error = [
            'payment' => '',
        ];

        $request_html = '';

        $form->handleRequest($request);
        $formData = $form->getData();

        if ($request->getMethod() === 'POST') {

            if ($payload['mode'] == "next") {
                $result = $upop->upopCommit($payload['order'], $formData, $payload['paymentInfo'], $error);
                if ($result !== false) {
                    $request_html = $result;
                } else {
                    $upop->rollback($payload['order']);
                }
            }

            // 銀聯ネットから戻った場合
            if ($payload['mode'] == "complete") {
                $result = $upop->upopComplete($request, $payload['order'], $error);
                if ($result){
                    return $this->redirectToRoute('vt4g_shopping_payment_complete', ['no' => $payload['order']->getId()]);
                }else{
                    $upop->rollback($payload['order']);
                }
            }
        }

        // POST用リクエストパラメータ
        return $this->render('VeriTrans4G/Resource/template/default/Shopping/vt4g_payment.twig', [
            'paymentType'  => $payload['paymentType'],
            'form'         => $form->createView(),
            'error'        => $error,
            'title'        => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_40'],
            'orderNo'      => $payload['order']->getId(),
            'tplIsLoading' => empty($error['payment']),
            'loadingImage' => $this->util->getLoadingImage(),
            'request_html' => $request_html,
        ]);
    }

    /**
     * 楽天ペイ処理
     *
     * @param  Request $request リクエストデータ
     * @param  array   $payload 決済処理に使用するデータ
     * @return object           リダイレクトレスポンス|ビューレスポンス
     */
    private function execRakutenProcess($request, $payload)
    {
        $rakuten = $this->container->get('vt4g_plugin.service.payment_rakuten');
        $form    = $rakuten->createRakutenForm();
        $error   = [
            'payment' => '',
        ];

        $request_html = '';

        $form->handleRequest($request);
        $formData = $form->getData();

        if ($request->getMethod() === 'POST') {

            if ($payload['mode'] == "next") {
                $result = $rakuten->rakutenCommit($payload['order'], $formData, $payload['paymentInfo'], $error);
                if ($result !== false) {
                    $request_html = $result;
                } else {
                    $rakuten->rollback($payload['order']);
                }
            }

        }

        if ($request->getMethod() === 'GET') {
            // 楽天から成功で戻った場合
            if ($payload['mode'] == "success") {
                $result = $rakuten->rakutenComplete($request, $payload['order'], $error);
                if ($result){
                    return $this->redirectToRoute('vt4g_shopping_payment_complete', ['no' => $payload['order']->getId()]);
                }else{
                    $rakuten->rollback($payload['order']);
                }
            // 楽天からエラーで戻った場合
            }elseif ($payload['mode'] == "error") {
                // エラー画面用のメッセージを設定してロールバック
                $rakuten->getResponse($request, $payload['order'], $error);
                $rakuten->rollback($payload['order']);
            }
        }

        return $this->render('VeriTrans4G/Resource/template/default/Shopping/vt4g_payment.twig', [
            'paymentType'  => $payload['paymentType'],
            'form'         => $form->createView(),
            'error'        => $error,
            'title'        => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_60'],
            'orderNo'      => $payload['order']->getId(),
            'tplIsLoading' => empty($error['payment']),
            'loadingImage' => $this->util->getLoadingImage(),
            'request_html' => $request_html,
        ]);
    }

    /**
     * 楽天ペイ処理
     *
     * @param  Request $request リクエストデータ
     * @param  array   $payload 決済処理に使用するデータ
     * @return object           リダイレクトレスポンス|ビューレスポンス
     */
    private function execCarrierProcess($request, $payload)
    {

        $carrier = $this->container->get('vt4g_plugin.service.payment_carrier');
        $form    = $carrier->createCarrierForm();
        $error   = [
            'payment' => '',
        ];

        $request_html = '';

        $form->handleRequest($request);
        $formData = $form->getData();

        if ($request->getMethod() === 'POST') {

            if ($payload['mode'] == "next") {
                $result = $carrier->carrierCommit($payload['order'], $formData, $payload['paymentInfo'], $error);
                if ($result !== false) {
                    $request_html = $result;
                } else {
                    $carrier->rollback($payload['order']);
                }
            }

        }

        if ($request->getMethod() === 'GET') {
            // 楽天から成功で戻った場合
            if ($payload['mode'] == "success") {
                $result = $carrier->carrierComplete($request, $payload['order'], $error);
                if ($result){
                    return $this->redirectToRoute('vt4g_shopping_payment_complete', ['no' => $payload['order']->getId()]);
                }else{
                    $carrier->rollback($payload['order']);
                }
            // 楽天からエラーで戻った場合
            }elseif ($payload['mode'] == "error") {
                // エラー画面用のメッセージを設定してロールバック
                $carrier->getResponse($request, $payload['order'], $error);
                $carrier->rollback($payload['order']);
            }
        }

        return $this->render('VeriTrans4G/Resource/template/default/Shopping/vt4g_payment.twig', [
            'paymentType'  => $payload['paymentType'],
            'form'         => $form->createView(),
            'error'        => $error,
            'title'        => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_63'],
            'orderNo'      => $payload['order']->getId(),
            // 'tplIsLoading' => empty($error['payment']),
            // 'loadingImage' => $this->util->getLoadingImage(),
            'request_html' => $request_html,
        ]);
    }

    /**
     * リクルートかんたん支払い処理
     *
     * @param  Request $request リクエストデータ
     * @param  array   $payload 決済処理に使用するデータ
     * @return object           リダイレクトレスポンス|ビューレスポンス
     */
    private function execRecruitProcess($request, $payload)
    {
        $recruit = $this->container->get('vt4g_plugin.service.payment_recruit');
        $form    = $recruit->createRecruitForm();
        $error   = [
            'payment' => '',
        ];

        $form->handleRequest($request);
        $formData = $form->getData();

        if ($request->getMethod() === 'POST') {

            if ($payload['mode'] == "next") {
                $result = $recruit->recruitCommit($payload['order'], $formData, $payload['paymentInfo'], $error);
                if ($result !== false) {
                    // リクルートの画面にリダイレクト
                    return $this->redirect($result);
                } else {
                    $recruit->rollback($payload['order']);
                }
            }

        }

        if ($request->getMethod() === 'GET') {
            // リクルートから成功で戻った場合
            if ($payload['mode'] == "success") {
                $result = $recruit->recruitComplete($request, $payload['order'], $error);
                if ($result){
                    return $this->redirectToRoute('vt4g_shopping_payment_complete', ['no' => $payload['order']->getId()]);
                }else{
                    $recruit->rollback($payload['order']);
                }
            // リクルートからエラーで戻った場合
            }elseif ($payload['mode'] == "error") {
                // エラー画面用のメッセージを設定してロールバック
                $recruit->getResponse($request, $payload['order'], $error);
                $recruit->rollback($payload['order']);
            }
        }

        return $this->render('VeriTrans4G/Resource/template/default/Shopping/vt4g_payment.twig', [
            'paymentType'  => $payload['paymentType'],
            'form'         => $form->createView(),
            'error'        => $error,
            'title'        => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_61'],
            'orderNo'      => $payload['order']->getId(),
            'tplIsLoading' => empty($error['payment']),
            'loadingImage' => $this->util->getLoadingImage(),
        ]);
    }

    /**
     * LINE Pay処理
     *
     * @param  Request $request リクエストデータ
     * @param  array   $payload 決済処理に使用するデータ
     * @return object           リダイレクトレスポンス|ビューレスポンス
     */
    private function execLINEPayProcess($request, $payload)
    {
        $line = $this->container->get('vt4g_plugin.service.payment_linepay');
        $form    = $line->createLineForm();
        $error   = [
            'payment' => '',
        ];

        $form->handleRequest($request);
        $formData = $form->getData();

        if ($request->getMethod() === 'POST') {

            if ($payload['mode'] == "next") {
                $result = $line->linepayCommit($payload['order'], $formData, $payload['paymentInfo'], $error);
                if ($result !== false) {
                    // LINEの画面にリダイレクト
                    return $this->redirect($result);
                } else {
                    $line->rollback($payload['order']);
                }
            }

        }

        if ($request->getMethod() === 'GET') {
            // LINEから成功で戻った場合
            if ($payload['mode'] == "success") {
                $result = $line->linepayComplete($request, $payload['order'], $error);
                if ($result){
                    return $this->redirectToRoute('vt4g_shopping_payment_complete', ['no' => $payload['order']->getId()]);
                }else{
                    $line->rollback($payload['order']);
                }
            // LINEからエラーで戻った場合
            } elseif ($payload['mode'] == "error") {
                // エラー画面用のメッセージを設定してロールバック
                $line->getResponse($request, $payload['order'], $error);
                $line->rollback($payload['order']);
            // LINEからキャンセルで戻った場合
            } elseif ($payload['mode'] == "cancel") {
                // レスポンスをログに出力してご注文手続き画面へ
                $line->getResponse($request, $payload['order'], $error);
                return $this->redirectToRoute('vt4g_shopping_payment_back', ['no' => $payload['order']->getId()]);
            }
        }

        return $this->render('VeriTrans4G/Resource/template/default/Shopping/vt4g_payment.twig', [
            'paymentType'  => $payload['paymentType'],
            'form'         => $form->createView(),
            'error'        => $error,
            'title'        => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_62'],
            'orderNo'      => $payload['order']->getId(),
            'tplIsLoading' => empty($error['payment']),
            'loadingImage' => $this->util->getLoadingImage(),
        ]);
    }

    /**
     * PayPal決済処理
     *
     * @param  Request $request リクエストデータ
     * @param  array   $payload 決済処理に使用するデータ
     * @return object           リダイレクトレスポンス|ビューレスポンス
     */
    private function execPayPalProcess($request, $payload)
    {
        $paypal = $this->container->get('vt4g_plugin.service.payment_paypal');
        $error = [
            'payment' => '',
        ];
        $form = $paypal->createPayPalForm($payload['paymentInfo']);
        $form->handleRequest($request);
        $formData = $form->getData();
        if ($request->getMethod() === 'POST' && $payload['mode'] == 'next') {
            $result = $paypal->PayPalCommit($payload['order'], $formData, $payload['paymentInfo'], $error);
            if ($result !== false) {
                return $this->redirect($result);
            } else {
                // ロールバック
                $paypal->rollback($payload['order']);
            }
        }
        if ($request->getMethod() === 'GET') {
            // paypalから成功で戻った場合
            if($payload['mode'] == 'exec') {
                $result = $paypal->PayPalComplete($request, $payload['order'], $error);
                if ($result !== false) {
                    return $this->redirectToRoute('vt4g_shopping_payment_complete', ['no' => $payload['order']->getId()]);
                }else{
                    // ロールバック
                    $paypal->rollback($payload['order']);
                }
            // paypalからキャンセルで戻った場合
            } elseif($payload['mode'] == 'back') {
                return $this->redirectToRoute('vt4g_shopping_payment_back', ['no' => $payload['order']->getId()]);
            }
        }
        // POST用リクエストパラメータ
        return $this->render('VeriTrans4G/Resource/template/default/Shopping/vt4g_payment.twig', [
            'paymentType'  => $payload['paymentType'],
            'form'         => $form->createView(),
            'error'        => $error,
            'title'        => $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_70'],
            'orderNo'      => $payload['order']->getId(),
            'paymentInfo'  => $payload['paymentInfo'],
            'tplIsLoading' => empty($error['payment']),
            'loadingImage' => $this->util->getLoadingImage()
        ]);
    }
    /**
     * 注文IDから受注データを取得
     *
     * @param  integer $orderId 注文ID
     * @return \Eccube\Entity\Order            Orderクラスインスタンス
     */
    private function getOrderById($orderId)
    {
        return $this->orderRepository->findOneBy([
            'id' => $orderId
        ]);
    }
}
