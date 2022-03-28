<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Customize\Controller;

use Eccube\Controller\AbstractShoppingController;

use Eccube\Entity\CustomerAddress;
use Eccube\Entity\Order;
use Eccube\Entity\Shipping;
use Eccube\Entity\Product;
use Eccube\Entity\Customer;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Exception\ShoppingException;
use Eccube\Form\Type\Front\CustomerLoginType;
use Eccube\Form\Type\Front\ShoppingShippingType;
use Eccube\Form\Type\Shopping\CustomerAddressType;
use Eccube\Form\Type\Shopping\OrderType;
use Eccube\Repository\OrderRepository;
use Eccube\Service\CartService;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Entity\Payment;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

use Plugin\PrizesPerProduct\Entity\Config as PrizesPerProduct;
use Plugin\PrizeShow\Entity\PrizeList as PrizeList;
use Plugin\ProductAssist\Entity\Config as ProductAssist;
use Plugin\ProductAssistConfig\Entity\Config as ProductAssistConfig; 

if (!defined('PRIZE_NAME_SIZE')) {
    define("PRIZE_NAME_SIZE", 400000);
}

function homepage_url(){
    return sprintf(
        "%s://%s%s",
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
        $_SERVER['SERVER_NAME'],
        $_SERVER['REQUEST_URI']
    );
}

class ShoppingController extends AbstractShoppingController
{

    protected $client_id;
    protected $baseurl;
    protected $response_type;
    protected $redirect_uri;
    protected $nonce;
    protected $scope;

    public $nico_id;
    public $access_token;
    public $refresh_token;
    public $nico_code;

    protected $premium;
    protected $channel;
    protected $ticket;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    public function __construct(
        CartService $cartService,
        MailService $mailService,
        OrderRepository $orderRepository,
        OrderHelper $orderHelper
    ) {
        $this->cartService = $cartService;
        $this->mailService = $mailService;
        $this->orderRepository = $orderRepository;
        $this->orderHelper = $orderHelper;

        $this->client_id = '2W28ATt7K2QwWmcW';// ニコニコから発行されたクライアントID
		$this->client_secret = 'KAQWLo0EUjrFPiAxjbPXjojSQMPEO2U1';// ニコニコデベロッパーで発行したクライアントシークレット
		$this->baseurl = 'https://oauth.nicovideo.jp';// 認証URL
		$this->response_type = 'code%20id_token';// 要求する応答タイプ
		$this->redirect_uri = homepage_url() . 'shopping/';// リダイレクトURI
		$this->nonce = hash('ripemd160','oauth_login_niconico');// 任意の文字列の発行
		$this->scope = 'email%20offline_access%20openid%20profile%20user%20user.premium%20user.authorities.lives.ticket.get';
    }

    protected function getNicoInfo(){
        $this->scope = 'email%20offline_access%20openid%20profile%20user%20user.premium%20user.authorities.lives.ticket.get'.$this->getScopeChannelID();
        $user = $this->getUserInfo();
        if(  $this->is_niko_error( $user ) ) {
            $this->redirect( $this->redirect_uri );
            exit;
        }
        $this->user = json_decode( $user );

        $point = $this->getUserPoint();
        if(  $this->is_niko_error( $point ) ) {
            $this->redirect( $this->redirect_uri );
            exit;
        }
        $this->point = json_decode( $point )->data->allRemainPoint;

		$premium = $this->getUserPremium();
        if(  $this->is_niko_error( $premium ) ) {
            $this->redirect( $this->redirect_uri );
            exit;
        }
        $this->premium = json_decode( $premium )->data->type;
        
        $channels = $this->getUserChannel();
        $this->channel = array();

        if( is_array($channels) ){
            foreach( $channels as $channel ){
                if( json_decode( $channel )->meta->status == 200 ){
                    $this->channel[] = json_decode( $channel )->data->channel->id;
                }
            }
        }

//         print_r(";u->".$user.";p->".$point.";pr->".$premium.";c->".json_encode($channels));
// print_r("nid->".$this->nico_id.";act->".$this->access_token.";ret->".$this->refresh_token);exit;
        return $this->checkMember();
    }    

    /**
     * 注文手続き画面を表示する
     *
     * 未ログインまたはRememberMeログインの場合はログイン画面に遷移させる.
     * ただし、非会員でお客様情報を入力済の場合は遷移させない.
     *
     * カート情報から受注データを生成し, `pre_order_id`でカートと受注の紐付けを行う.
     * 既に受注が生成されている場合(pre_order_idで取得できる場合)は, 受注の生成を行わずに画面を表示する.
     *
     * purchaseFlowの集計処理実行後, warningがある場合はカートど同期をとるため, カートのPurchaseFlowを実行する.
     *
     * @Route("/shopping", name="shopping")
     * @Template("Shopping/index.twig")
     */
    public function index(PurchaseFlow $cartPurchaseFlow, Request $request)
    {
        if( isset($_GET['code']) && isset($_GET['id_token']) ){
            $request->getSession()->set('is_niconico_signup', true);
            $request->getSession()->set('where_to_login', 'shopping');

            $request->getSession()->set('nico_code', $_GET['code']);
            $request->getSession()->set('nico_id_token', $_GET['id_token']);

            return $this->getNicoInfo();
        }

        // ログイン状態のチェック.
        if ($this->orderHelper->isLoginRequired()) {
            log_info('[注文手続] 未ログインもしくはRememberMeログインのため, ログイン画面に遷移します.');

            return $this->redirectToRoute('shopping_login');
        }

        $Cart = $this->cartService->getCart();
        if (!($Cart && $this->orderHelper->verifyCart($Cart))) {
            log_info('[注文手続] カートが購入フローへ遷移できない状態のため, カート画面に遷移します.');

            return $this->redirectToRoute('cart');
        }

        $Customer = $this->getUser() ? $this->getUser() : $this->orderHelper->getNonMember();

        // if ($request->getSession()->get('is_niconico_signup') == true) {
        //     $this->nico_code = $request->getSession()->get('nico_code');
        //     $this->nico_id = $request->getSession()->get('nico_id');
        //     $this->access_token = $request->getSession()->get('access_token');
        //     $this->refresh_token = $request->getSession()->get('refresh_token');
    
        //     $this->getNicoInfo();

            foreach($Cart->getCartItems() as $CartItem) {
                $Product = $CartItem->getProductClass()->getProduct();
                $compare_premium = false;
                $compare_channel = false;
                $compare_ticket = false;
    
                if ($Product->premium == 0 || $Product->premium == '0' || $Customer->premium == $Product->premium) $compare_premium = true;
                if ($Product->niconico == 0 || $Product->niconico == '0' || $Customer->channel == $Product->niconico) $compare_channel = true;
                if ($Product->specifics == 0 || $Product->specifics == '0' || $Customer->ticket == $Product->specifics) $compare_ticket = true;
    
                if ($compare_premium && $compare_channel && $compare_ticket);
                else {
                    $this->cartService->clear();
                    $this->cartService->save();
                    return $this->redirectToRoute('homepage');
                } 
            }
        // }
        // 受注の初期化.
        log_info('[注文手続] 受注の初期化処理を開始します.');

        $Order = $this->orderHelper->initializeOrder($Cart, $Customer);

        // 集計処理.
        log_info('[注文手続] 集計処理を開始します.', [$Order->getId()]);
        $flowResult = $this->executePurchaseFlow($Order, false);

        $this->entityManager->flush();

        if ($flowResult->hasError()) {
            log_info('[注文手続] Errorが発生したため購入エラー画面へ遷移します.', [$flowResult->getErrors()]);

            return $this->redirectToRoute('shopping_error');
        }

        if ($flowResult->hasWarning()) {
            log_info('[注文手続] Warningが発生しました.', [$flowResult->getWarning()]);

            // 受注明細と同期をとるため, CartPurchaseFlowを実行する
            $cartPurchaseFlow->validate($Cart, new PurchaseContext());
            $this->cartService->save();
        }

        // マイページで会員情報が更新されていれば, Orderの注文者情報も更新する.
        if ($Customer->getId()) {
            $this->orderHelper->updateCustomerInfo($Order, $Customer);
            $this->entityManager->flush();
        }

        $form = $this->createForm(OrderType::class, $Order);

        // foreach($this->getDoctrine()->getRepository(Payment::class)->findAll() as $item) 
        //     if ($item->isVisible())    
        //         print_r($item->getMethod());
        // exit;

        return [
            'form' => $form->createView(),
            'Order' => $Order,
        ];
    }

    /**
     * 他画面への遷移を行う.
     *
     * お届け先編集画面など, 他画面へ遷移する際に, フォームの値をDBに保存してからリダイレクトさせる.
     * フォームの`redirect_to`パラメータの値にリダイレクトを行う.
     * `redirect_to`パラメータはpath('遷移先のルーティング')が渡される必要がある.
     *
     * 外部のURLやPathを渡された場合($router->matchで展開出来ない場合)は, 購入エラーとする.
     *
     * プラグインやカスタマイズでこの機能を使う場合は, twig側で以下のように記述してください.
     *
     * <button data-trigger="click" data-path="path('ルーティング')">更新する</button>
     *
     * data-triggerは, click/change/blur等のイベント名を指定してください。
     * data-pathは任意のパラメータです. 指定しない場合, 注文手続き画面へリダイレクトします.
     *
     * @Route("/shopping/redirect_to", name="shopping_redirect_to", methods={"POST"})
     * @Template("Shopping/index.twig")
     */
    public function redirectTo(Request $request, RouterInterface $router)
    {
        // ログイン状態のチェック.
        if ($this->orderHelper->isLoginRequired()) {
            log_info('[リダイレクト] 未ログインもしくはRememberMeログインのため, ログイン画面に遷移します.');

            return $this->redirectToRoute('shopping_login');
        }

        // 受注の存在チェック.
        $preOrderId = $this->cartService->getPreOrderId();
        $Order = $this->orderHelper->getPurchaseProcessingOrder($preOrderId);
        if (!$Order) {
            log_info('[リダイレクト] 購入処理中の受注が存在しません.');

            return $this->redirectToRoute('shopping_error');
        }

        $form = $this->createForm(OrderType::class, $Order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            log_info('[リダイレクト] 集計処理を開始します.', [$Order->getId()]);
            $response = $this->executePurchaseFlow($Order);
            $this->entityManager->flush();

            if ($response) {
                return $response;
            }

            $redirectTo = $form['redirect_to']->getData();
            if (empty($redirectTo)) {
                log_info('[リダイレクト] リダイレクト先未指定のため注文手続き画面へ遷移します.');

                return $this->redirectToRoute('shopping');
            }

            try {
                // リダイレクト先のチェック.
                $pattern = '/^'.preg_quote($request->getBasePath(), '/').'/';
                $redirectTo = preg_replace($pattern, '', $redirectTo);
                $result = $router->match($redirectTo);
                // パラメータのみ抽出
                $params = array_filter($result, function ($key) {
                    return 0 !== \strpos($key, '_');
                }, ARRAY_FILTER_USE_KEY);

                log_info('[リダイレクト] リダイレクトを実行します.', [$result['_route'], $params]);

                // pathからurlを再構築してリダイレクト.
                return $this->redirectToRoute($result['_route'], $params);
            } catch (\Exception $e) {
                log_info('[リダイレクト] URLの形式が不正です', [$redirectTo, $e->getMessage()]);

                return $this->redirectToRoute('shopping_error');
            }
        }

        log_info('[リダイレクト] フォームエラーのため, 注文手続き画面を表示します.', [$Order->getId()]);

        return [
            'form' => $form->createView(),
            'Order' => $Order,
        ];
    }

    /**
     * 注文確認画面を表示する.
     *
     * ここではPaymentMethod::verifyがコールされます.
     * PaymentMethod::verifyではクレジットカードの有効性チェック等, 注文手続きを進められるかどうかのチェック処理を行う事を想定しています.
     * PaymentMethod::verifyでエラーが発生した場合は, 注文手続き画面へリダイレクトします.
     *
     * @Route("/shopping/confirm", name="shopping_confirm", methods={"POST"})
     * @Template("Shopping/confirm.twig")
     */
    public function confirm(Request $request)
    {
        // ログイン状態のチェック.
        if ($this->orderHelper->isLoginRequired()) {
            log_info('[注文確認] 未ログインもしくはRememberMeログインのため, ログイン画面に遷移します.');

            return $this->redirectToRoute('shopping_login');
        }

        // 受注の存在チェック
        $preOrderId = $this->cartService->getPreOrderId();
        $Order = $this->orderHelper->getPurchaseProcessingOrder($preOrderId);
        if (!$Order) {
            log_info('[注文確認] 購入処理中の受注が存在しません.', [$preOrderId]);

            return $this->redirectToRoute('shopping_error');
        }

        $form = $this->createForm(OrderType::class, $Order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            log_info('[注文確認] 集計処理を開始します.', [$Order->getId()]);
            $response = $this->executePurchaseFlow($Order);
            $this->entityManager->flush();

            if ($response) {
                return $response;
            }

            log_info('[注文確認] PaymentMethod::verifyを実行します.', [$Order->getPayment()->getMethodClass()]);
            $paymentMethod = $this->createPaymentMethod($Order, $form);
            $PaymentResult = $paymentMethod->verify();

            if ($PaymentResult) {
                if (!$PaymentResult->isSuccess()) {
                    $this->entityManager->rollback();
                    foreach ($PaymentResult->getErrors() as $error) {
                        $this->addError($error);
                    }

                    log_info('[注文確認] PaymentMethod::verifyのエラーのため, 注文手続き画面へ遷移します.', [$PaymentResult->getErrors()]);

                    return $this->redirectToRoute('shopping');
                }

                $response = $PaymentResult->getResponse();
                if ($response instanceof Response && ($response->isRedirection() || $response->isSuccessful())) {
                    $this->entityManager->flush();

                    log_info('[注文確認] PaymentMethod::verifyが指定したレスポンスを表示します.');

                    return $response;
                }
            }

            $this->entityManager->flush();

            log_info('[注文確認] 注文確認画面を表示します.');

            return [
                'form' => $form->createView(),
                'Order' => $Order,
            ];
        }

        log_info('[注文確認] フォームエラーのため, 注文手続画面を表示します.', [$Order->getId()]);

        // FIXME @Templateの差し替え.
        $request->attributes->set('_template', new Template(['template' => 'Shopping/index.twig']));

        return [
            'form' => $form->createView(),
            'Order' => $Order,
        ];
    }

    /**
     * 注文処理を行う.
     *
     * 決済プラグインによる決済処理および注文の確定処理を行います.
     *
     * @Route("/shopping/checkout", name="shopping_checkout", methods={"POST"})
     * @Template("Shopping/confirm.twig")
     */
    public function checkout(Request $request)
    {
        // ログイン状態のチェック.
        if ($this->orderHelper->isLoginRequired()) {
            log_info('[注文処理] 未ログインもしくはRememberMeログインのため, ログイン画面に遷移します.');

            return $this->redirectToRoute('shopping_login');
        }

        // 受注の存在チェック
        $preOrderId = $this->cartService->getPreOrderId();
        $Order = $this->orderHelper->getPurchaseProcessingOrder($preOrderId);
        if (!$Order) {
            log_info('[注文処理] 購入処理中の受注が存在しません.', [$preOrderId]);

            return $this->redirectToRoute('shopping_error');
        }

        // フォームの生成.
        $form = $this->createForm(OrderType::class, $Order, [
            // 確認画面から注文処理へ遷移する場合は, Orderエンティティで値を引き回すためフォーム項目の定義をスキップする.
            'skip_add_form' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            log_info('[注文処理] 注文処理を開始します.', [$Order->getId()]);

            try {
                /*
                 * 集計処理
                 */
                log_info('[注文処理] 集計処理を開始します.', [$Order->getId()]);
                $response = $this->executePurchaseFlow($Order);
                $this->entityManager->flush();

                if ($response) {
                    return $response;
                }

                log_info('[注文処理] PaymentMethodを取得します.', [$Order->getPayment()->getMethodClass()]);
                $paymentMethod = $this->createPaymentMethod($Order, $form);

                /*
                 * 決済実行(前処理)
                 */
                log_info('[注文処理] PaymentMethod::applyを実行します.');
                if ($response = $this->executeApply($paymentMethod)) {
                    return $response;
                }

                /*
                 * 決済実行
                 *
                 * PaymentMethod::checkoutでは決済処理が行われ, 正常に処理出来た場合はPurchaseFlow::commitがコールされます.
                 */
                log_info('[注文処理] PaymentMethod::checkoutを実行します.');
                if ($response = $this->executeCheckout($paymentMethod)) {
                    return $response;
                }

                $this->entityManager->flush();

                log_info('[注文処理] 注文処理が完了しました.', [$Order->getId()]);
            } catch (ShoppingException $e) {
                log_error('[注文処理] 購入エラーが発生しました.', [$e->getMessage()]);

                $this->entityManager->rollback();

                $this->addError($e->getMessage());

                return $this->redirectToRoute('shopping_error');
            } catch (\Exception $e) {
                log_error('[注文処理] 予期しないエラーが発生しました.', [$e->getMessage()]);

                $this->entityManager->rollback();

                $this->addError('front.shopping.system_error');

                return $this->redirectToRoute('shopping_error');
            }

            // カート削除
            log_info('[注文処理] カートをクリアします.', [$Order->getId()]);
            $this->cartService->clear();

            // 受注IDをセッションにセット
            $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

            // メール送信
            log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
            $this->mailService->sendOrderMail($Order);
            $this->entityManager->flush();

            log_info('[注文処理] 注文処理が完了しました. 購入完了画面へ遷移します.', [$Order->getId()]);

            return $this->redirectToRoute('shopping_complete');
        }

        log_info('[注文処理] フォームエラーのため, 購入エラー画面へ遷移します.', [$Order->getId()]);

        return $this->redirectToRoute('shopping_error');
    }

    public function generatePrize(Product $Product, $quant, $orderId)
    {
        $PrizeListRepo = $this->getDoctrine()->getRepository(PrizeList::class);
        $ProductAssist = $this->getDoctrine()->getRepository(ProductAssist::class)->findOneBy(['id' => $Product->product_assist_id]);
		$PrizesPerProductRepo = $this->getDoctrine()->getRepository(PrizesPerProduct::class);
        $ProductAssistConfs = $ProductAssist->getSettings();
        $Categories = $Product->getProductCategories();

        $lotteryConfs = array();
        $confirmedConfs = array();
        $bulkConfs = array();
        foreach($ProductAssistConfs as $conf)
		{
			if ($conf->getGroupId() == 1)
				array_push($bulkConfs, $conf);
            if ($conf->getGroupId() == 2)
                array_push($confirmedConfs, $conf);
            if ($conf->getGroupId() == 3)
                array_push($lotteryConfs, $conf);
		}
        $quantity = $quant;

        foreach($Categories as $Category)
        {	
            if ($Category->getCategory()->getName() == "通常くじ")
            {
                foreach($lotteryConfs as $conf)
                {
                    $prizeList = $PrizeListRepo->find($conf->getSetOption());
                    $Prizes = $prizeList->getSettings()->getValues();
                    foreach($Prizes as $Prize)
                    {
                        $PrizePerProduct = new PrizesPerProduct();
                        $PrizePerProduct->setPrizeOpen(true);
        
                        $PrizePerProduct->setOrderId($orderId);
                        $PrizePerProduct->setProductId($Product->getId());
                        $PrizePerProduct->setPrizeImage($Prize->getImage());
                        $PrizePerProduct->setPrizeName($Prize->getName());
                        $PrizePerProduct->setPrizeGrade($conf->getGrade());
                        $PrizePerProduct->setPrizeClassName($conf->getClassName());
                        $PrizePerProduct->setPrizeColor($conf->getColorName());
                        $PrizePerProduct->setPrizeListId($prizeList->getId()); 
                        
                        $this->entityManager->persist($PrizePerProduct);
                    }
                }
                $this->entityManager->flush();
                $this->entityManager->clear();

                $countSum = 0;
                foreach($lotteryConfs as $conf)
                {
                    $countSum += $conf->getSetCount();
                }

                $PrizePerProductList = $PrizesPerProductRepo->findBy(['orderId' => $orderId, 'productId' => $Product->getId()]);
                $text = '';

                while($quantity > 0)
                {
                    $rand = rand(1, $countSum);
                    $i = 0;

                    foreach($lotteryConfs as $conf)
                    {
                        $j = $i + $conf->getSetCount();
                        if ($rand > $i && $rand <= $j)
                        {
                            break;
                        }
                        $i = $j;
                    }
                    $prizeList = $PrizeListRepo->find($conf->getSetOption());
                    $Prizes = $prizeList->getSettings()->getValues();
                    $rand = rand(0, count($Prizes) - 1);
                    $Prize = $Prizes[$rand];
                    
                    $remain = $Prize->getRemain();
                    $Prize->setRemain($remain - 1);

                    if($remain == 0)
                        $Prize->setRemain(0);

                    foreach($PrizePerProductList as $item)
                    {
                        if($item->getPrizeName() == $Prize->getName() && $item->getPrizeListId() == $prizeList->getId())
                        {
                            $text .= $item->getId();
                            $text .= ',1;';
                            break;
                        }
                    }

                    $quantity--;
                }
                for($i = 0; $i < ceil(strlen($text) / PRIZE_NAME_SIZE); $i++)
                {
                    $PrizePerProductObject = new PrizesPerProduct();
                    $PrizePerProductObject->setPrizeOpen(true);
                    $PrizePerProductObject->setOrderId($orderId);
                    $PrizePerProductObject->setProductId($Product->getId());
                    $PrizePerProduct->setPrizeGrade(0);
    
                    $PrizePerProductObject->setPrizeName(substr($text, $i * PRIZE_NAME_SIZE, PRIZE_NAME_SIZE));
    
                    $this->entityManager->persist($PrizePerProductObject);
                }
                $this->entityManager->flush();
                $this->entityManager->clear();
            } else if($Category->getCategory()->getName() == "確定くじ")
            {
                foreach($confirmedConfs as $conf)
                {
                    $prizeList = $PrizeListRepo->find($conf->getSetOption());
                    $Prizes = $prizeList->getSettings()->getValues();
                    foreach($Prizes as $Prize)
                    {
                        $PrizePerProduct = new PrizesPerProduct();
                        $PrizePerProduct->setPrizeOpen(true);
        
                        $PrizePerProduct->setOrderId($orderId);
                        $PrizePerProduct->setProductId($Product->getId());
                        $PrizePerProduct->setPrizeImage($Prize->getImage());
                        $PrizePerProduct->setPrizeName($Prize->getName());
                        $PrizePerProduct->setPrizeGrade($conf->getGrade());
                        $PrizePerProduct->setPrizeClassName($conf->getClassName());
                        $PrizePerProduct->setPrizeColor($conf->getColorName());
                        $PrizePerProduct->setPrizeListId($prizeList->getId()); 
                        
                        $this->entityManager->persist($PrizePerProduct);
                    }
                }
                $this->entityManager->flush();
                $this->entityManager->clear();

                foreach($lotteryConfs as $conf)
                {
                    $prizeList = $PrizeListRepo->find($conf->getSetOption());
                    $Prizes = $prizeList->getSettings()->getValues();
                    foreach($Prizes as $Prize)
                    {
                        $PrizePerProduct = new PrizesPerProduct();
                        $PrizePerProduct->setPrizeOpen(true);
        
                        $PrizePerProduct->setOrderId($orderId);
                        $PrizePerProduct->setProductId($Product->getId());
                        $PrizePerProduct->setPrizeImage($Prize->getImage());
                        $PrizePerProduct->setPrizeName($Prize->getName());
                        $PrizePerProduct->setPrizeGrade($conf->getGrade());
                        $PrizePerProduct->setPrizeClassName($conf->getClassName());
                        $PrizePerProduct->setPrizeColor($conf->getColorName());
                        $PrizePerProduct->setPrizeListId($prizeList->getId()); 
                        
                        $this->entityManager->persist($PrizePerProduct);
                    }
                }
                $this->entityManager->flush();
                $this->entityManager->clear();    

                $PrizePerProductList = $PrizesPerProductRepo->findBy(['orderId' => $orderId, 'productId' => $Product->getId()]);

                $text = '';
                while($quantity > 0)
                {
                    foreach($confirmedConfs as $conf)
                    {
                        $prizeList = $PrizeListRepo->find($conf->getSetOption());
                        $Prizes = $prizeList->getSettings()->getValues();
                        foreach($Prizes as $Prize)
                        {
                            $count = $conf->getSetCount();
                            while($count > 0)
                            {
                                foreach($PrizePerProductList as $item) {
                                    if($item->getPrizeName() == $Prize->getName() && $item->getPrizeListId() == $prizeList->getId())
                                    {
                                        $text .= $item->getId();
                                        $text .= ',1;';

                                        $remain = $Prize->getRemain();
                                        $Prize->setRemain($remain - 1);
                    
                                        if($remain == 0)
                                            $Prize->setRemain(0);
                                        break;
                                    }
                                }
                                $count--;
                            }
                        }
                    }
                        
                    $count = $ProductAssist->getWinningCount();
                    $countSum = 0;
                    foreach($lotteryConfs as $conf)
                    {
                        $countSum += $conf->getSetCount();
                    }
                    while($count > 0)
                    {
                        $rand = rand(1, $countSum);
                        $i = 0;
    
                        foreach($lotteryConfs as $conf)
                        {
                            $j = $i + $conf->getSetCount();
                            if ($rand > $i && $rand <= $j)
                            {
                                break;
                            }
                            $i = $j;
                        }
                        $prizeList = $PrizeListRepo->find($conf->getSetOption());
                        $Prizes = $prizeList->getSettings()->getValues();
                        $rand = rand(0, count($Prizes) - 1);
                        $Prize = $Prizes[$rand];
                        
                        $remain = $Prize->getRemain();
                        $Prize->setRemain($remain - 1);
    
                        if($remain == 0)
                            $Prize->setRemain(0);
    
                        foreach($PrizePerProductList as $item)
                        {
                            if($item->getPrizeName() == $Prize->getName() && $item->getPrizeListId() == $prizeList->getId())
                            {
                                $text .= $item->getId();
                                $text .= ',1;';
                                break;
                            }
                        }
                        $count--;
                    }
                    $quantity--;
                }
                for($i = 0; $i < ceil(strlen($text) / PRIZE_NAME_SIZE); $i++)
                {
                    $PrizePerProductObject = new PrizesPerProduct();
                    $PrizePerProductObject->setPrizeOpen(true);
                    $PrizePerProductObject->setOrderId($orderId);
                    $PrizePerProductObject->setProductId($Product->getId());
                    $PrizePerProduct->setPrizeGrade(0);
    
                    $PrizePerProductObject->setPrizeName(substr($text, $i * PRIZE_NAME_SIZE, PRIZE_NAME_SIZE));
                    $this->entityManager->persist($PrizePerProductObject);
                }
                $this->entityManager->flush();
                $this->entityManager->clear();

            } else if($Category->getCategory()->getName() == "大人買いくじ")
            {
                foreach($lotteryConfs as $conf)
                {
                    $text = "";
                    $prizeList = $PrizeListRepo->find($conf->getSetOption());
                    $Prizes = $prizeList->getSettings()->getValues();

                    foreach($Prizes as $Prize)
                    {
                        $remain = $Prize->getRemain();
                        $Prize->setRemain($remain - 1);

                        if($remain == 0)
                            $Prize->setRemain(0);

                        $text .= $Prize->getName()."###";  
                    }                    
                    $PrizePerProduct = new PrizesPerProduct();
    
                    $PrizePerProduct->setOrderId($orderId);
                    $PrizePerProduct->setProductId($Product->getId());
                    $PrizePerProduct->setPrizeName($prizeList->getName());
                    $PrizePerProduct->setPrizeImage($text);
                    $PrizePerProduct->setPrizeGrade($conf->getGrade());
                    $PrizePerProduct->setPrizeClassName($conf->getClassName());
                    $PrizePerProduct->setPrizeOpen(true);
                    $PrizePerProduct->setPrizeColor($conf->getColorName());
                    $PrizePerProduct->setPrizeListId($prizeList->getId());
                    
                    $this->entityManager->persist($PrizePerProduct);
                }
                $this->entityManager->flush();
                $this->entityManager->clear();

                $PrizePerProductList = $PrizesPerProductRepo->findBy(['orderId' => $orderId, 'productId' => $Product->getId()]);
                
                $text = "";

                $countSum = 0;
                foreach($lotteryConfs as $conf)
                {
                    $countSum += $conf->getSetCount();
                }

                while($quantity > 0)
                {
                    $rand = rand(1, $countSum);
                    $i = 0;
                    foreach($lotteryConfs as $conf)
                    {
                        $j = $i + $conf->getSetCount();
                        if ($rand > $i && $rand <= $j)
                        {
                            break;
                        }
                        $i = $j;
                    }
                    $prizeList = $PrizeListRepo->find($conf->getSetOption());
                    
                    foreach($PrizePerProductList as $item)
                    {
                        if($item->getPrizeListId() == $prizeList->getId())
                        {
                            $text .= $item->getId();
                            $text .= ',1;';
                            break;
                        }
                    }
                    $quantity--;
                }
                for($i = 0; $i < ceil(strlen($text) / PRIZE_NAME_SIZE); $i++)
                {
                    $PrizePerProductObject = new PrizesPerProduct();
                    $PrizePerProductObject->setPrizeOpen(true);
                    $PrizePerProductObject->setOrderId($orderId);
                    $PrizePerProductObject->setProductId($Product->getId());
                    $PrizePerProduct->setPrizeGrade(0);
    
                    $PrizePerProductObject->setPrizeName(substr($text, $i * PRIZE_NAME_SIZE, PRIZE_NAME_SIZE));
    
                    $this->entityManager->persist($PrizePerProductObject);
                }
                $this->entityManager->flush();
                $this->entityManager->clear();
            } else if($Category->getCategory()->getName() == "まとめ買いくじ")
            {
                $PrizePerProduct = new PrizesPerProduct();
                $PrizePerProduct->setOrderId($orderId);
                $PrizePerProduct->setProductId($Product->getId());

                $text = "";
                $text_grade = "";

                foreach($bulkConfs as $conf)
                {
                    $prizeList = $PrizeListRepo->find(['id' => $conf->getSetOption()]);
                    $text .= $prizeList->getName().": ".$conf->getSetCount()."個###";

                    // $Prizes = $prizeList->getSettings()->getValues();
                    // foreach($Prizes as $Prize)
                    // {
                    //     if ($Prize->getRemain() < $conf->getSetCount()) $Prize->setRemain(0);
                    //     else $Prize->setRemain($Prize->getRemain() - $conf->getSetCount());
                    // }
                    $text_grade .= $conf->getGrade().$conf->getClassName().';';
                }
                $PrizePerProduct->setPrizeName($text);
                $PrizePerProduct->setPrizeImage($Product->getProductImage()[2]->getFileName());
                $PrizePerProduct->setPrizeOpen(true);
                $PrizePerProduct->setPrizeGrade($text_grade);
                $PrizePerProduct->setPrizeColor($quantity);

                $this->entityManager->persist($PrizePerProduct);
                $this->entityManager->flush();
                $this->entityManager->clear();

                $text = '';
                while($quantity > 0)
                {
                    $text .= $PrizePerProduct->getId();
                    $text .= ',1;';

                    $quantity--;
                }
                for($i = 0; $i < ceil(strlen($text) / PRIZE_NAME_SIZE); $i++)
                {
                    $PrizePerProductObject = new PrizesPerProduct();
                    $PrizePerProductObject->setPrizeOpen(true);
                    $PrizePerProductObject->setOrderId($orderId);
                    $PrizePerProductObject->setProductId($Product->getId());
                    $PrizePerProduct->setPrizeGrade(0);
    
                    $PrizePerProductObject->setPrizeName(substr($text, $i * PRIZE_NAME_SIZE, PRIZE_NAME_SIZE));
    
                    $this->entityManager->persist($PrizePerProductObject);
                }
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }
    }

    /**
     * 購入完了画面を表示する.
     *
     * @Route("/shopping/complete", name="shopping_complete")
     * @Template("Shopping/complete.twig")
     */
    public function complete(Request $request)
    {
        log_info('[注文完了] 注文完了画面を表示します.');

        // 受注IDを取得
        $orderId = $this->session->get(OrderHelper::SESSION_ORDER_ID);

        if (empty($orderId)) {
            log_info('[注文完了] 受注IDを取得できないため, トップページへ遷移します.');

            return $this->redirectToRoute('homepage');
        }

        $Order = $this->orderRepository->find($orderId);
        $OrderItems = $Order->getMergedProductOrderItems();
        
        foreach($OrderItems as $OrderItem)
        {
            $this->generatePrize($OrderItem->getProduct(), $OrderItem->getQuantity(), $orderId);
        }

        $event = new EventArgs(
            [
                'Order' => $Order,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_SHOPPING_COMPLETE_INITIALIZE, $event);

        if ($event->getResponse() !== null) {
            return $event->getResponse();
        }

        log_info('[注文完了] 購入フローのセッションをクリアします. ');
        $this->orderHelper->removeSession();

        $hasNextCart = !empty($this->cartService->getCarts());

        log_info('[注文完了] 注文完了画面を表示しました. ', [$hasNextCart]);

        return [
            'Order' => $Order,
            'hasNextCart' => $hasNextCart,
        ];
    }

    /**
     * お届け先選択画面.
     *
     * 会員ログイン時, お届け先を選択する画面を表示する
     * 非会員の場合はこの画面は使用しない。
     *
     * @Route("/shopping/shipping/{id}", name="shopping_shipping", requirements={"id" = "\d+"})
     * @Template("Shopping/shipping.twig")
     */
    public function shipping(Request $request, Shipping $Shipping)
    {
        // ログイン状態のチェック.
        if ($this->orderHelper->isLoginRequired()) {
            return $this->redirectToRoute('shopping_login');
        }

        // 受注の存在チェック
        $preOrderId = $this->cartService->getPreOrderId();
        $Order = $this->orderHelper->getPurchaseProcessingOrder($preOrderId);
        if (!$Order) {
            return $this->redirectToRoute('shopping_error');
        }

        // 受注に紐づくShippingかどうかのチェック.
        if (!$Order->findShipping($Shipping->getId())) {
            return $this->redirectToRoute('shopping_error');
        }

        $builder = $this->formFactory->createBuilder(CustomerAddressType::class, null, [
            'customer' => $this->getUser(),
            'shipping' => $Shipping,
        ]);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            log_info('お届先情報更新開始', [$Shipping->getId()]);

            /** @var CustomerAddress $CustomerAddress */
            $CustomerAddress = $form['addresses']->getData();

            // お届け先情報を更新
            $Shipping->setFromCustomerAddress($CustomerAddress);

            // 合計金額の再計算
            $response = $this->executePurchaseFlow($Order);
            $this->entityManager->flush();

            if ($response) {
                return $response;
            }

            $event = new EventArgs(
                [
                    'Order' => $Order,
                    'Shipping' => $Shipping,
                ],
                $request
            );
            $this->eventDispatcher->dispatch(EccubeEvents::FRONT_SHOPPING_SHIPPING_COMPLETE, $event);

            log_info('お届先情報更新完了', [$Shipping->getId()]);

            return $this->redirectToRoute('shopping');
        }

        return [
            'form' => $form->createView(),
            'Customer' => $this->getUser(),
            'shippingId' => $Shipping->getId(),
        ];
    }

    /**
     * お届け先の新規作成または編集画面.
     *
     * 会員時は新しいお届け先を作成し, 作成したお届け先を選択状態にして注文手続き画面へ遷移する.
     * 非会員時は選択されたお届け先の編集を行う.
     *
     * @Route("/shopping/shipping_edit/{id}", name="shopping_shipping_edit", requirements={"id" = "\d+"})
     * @Template("Shopping/shipping_edit.twig")
     */
    public function shippingEdit(Request $request, Shipping $Shipping)
    {
        // ログイン状態のチェック.
        if ($this->orderHelper->isLoginRequired()) {
            return $this->redirectToRoute('shopping_login');
        }

        // 受注の存在チェック
        $preOrderId = $this->cartService->getPreOrderId();
        $Order = $this->orderHelper->getPurchaseProcessingOrder($preOrderId);
        if (!$Order) {
            return $this->redirectToRoute('shopping_error');
        }

        // 受注に紐づくShippingかどうかのチェック.
        if (!$Order->findShipping($Shipping->getId())) {
            return $this->redirectToRoute('shopping_error');
        }

        $CustomerAddress = new CustomerAddress();
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // ログイン時は会員と紐付け
            $CustomerAddress->setCustomer($this->getUser());
        } else {
            // 非会員時はお届け先をセット
            $CustomerAddress->setFromShipping($Shipping);
        }
        $builder = $this->formFactory->createBuilder(ShoppingShippingType::class, $CustomerAddress);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Order' => $Order,
                'Shipping' => $Shipping,
                'CustomerAddress' => $CustomerAddress,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_SHOPPING_SHIPPING_EDIT_INITIALIZE, $event);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            log_info('お届け先追加処理開始', ['order_id' => $Order->getId(), 'shipping_id' => $Shipping->getId()]);

            $Shipping->setFromCustomerAddress($CustomerAddress);

            if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
                $this->entityManager->persist($CustomerAddress);
            }

            // 合計金額の再計算
            $response = $this->executePurchaseFlow($Order);
            $this->entityManager->flush();

            if ($response) {
                return $response;
            }

            $event = new EventArgs(
                [
                    'form' => $form,
                    'Shipping' => $Shipping,
                    'CustomerAddress' => $CustomerAddress,
                ],
                $request
            );
            $this->eventDispatcher->dispatch(EccubeEvents::FRONT_SHOPPING_SHIPPING_EDIT_COMPLETE, $event);

            log_info('お届け先追加処理完了', ['order_id' => $Order->getId(), 'shipping_id' => $Shipping->getId()]);

            return $this->redirectToRoute('shopping');
        }

        return [
            'form' => $form->createView(),
            'shippingId' => $Shipping->getId(),
        ];
    }

    /**
     * ログイン画面.
     *
     * @Route("/shopping/login", name="shopping_login")
     * @Template("Shopping/login.twig")
     */
    public function login(Request $request, AuthenticationUtils $authenticationUtils)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('shopping');
        }

        /* @var $form \Symfony\Component\Form\FormInterface */
        $builder = $this->formFactory->createNamedBuilder('', CustomerLoginType::class);

        if ($this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $Customer = $this->getUser();
            if ($Customer) {
                $builder->get('login_email')->setData($Customer->getEmail());
            }
        }

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_SHOPPING_LOGIN_INITIALIZE, $event);

        $form = $builder->getForm();

        $nico_url = $this->getAuthorize();

        $this->addFlash('last_uri', 'shopping_login');
        return [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'form' => $form->createView(),
            'nico_url' => $nico_url
        ];
    }

    /**
     * 購入エラー画面.
     *
     * @Route("/shopping/error", name="shopping_error")
     * @Template("Shopping/shopping_error.twig")
     */
    public function error(Request $request, PurchaseFlow $cartPurchaseFlow)
    {
        // 受注とカートのずれを合わせるため, カートのPurchaseFlowをコールする.
        $Cart = $this->cartService->getCart();
        if (null !== $Cart) {
            $cartPurchaseFlow->validate($Cart, new PurchaseContext());
            $this->cartService->setPreOrderId(null);
            $this->cartService->save();
        }

        $event = new EventArgs(
            [],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_SHOPPING_SHIPPING_ERROR_COMPLETE, $event);

        if ($event->getResponse() !== null) {
            return $event->getResponse();
        }

        return [];
    }

    /**
     * PaymentMethodをコンテナから取得する.
     *
     * @param Order $Order
     * @param FormInterface $form
     *
     * @return PaymentMethodInterface
     */
    private function createPaymentMethod(Order $Order, FormInterface $form)
    {
        $PaymentMethod = $this->container->get($Order->getPayment()->getMethodClass());
        $PaymentMethod->setOrder($Order);
        $PaymentMethod->setFormType($form);

        return $PaymentMethod;
    }

    /**
     * PaymentMethod::applyを実行する.
     *
     * @param PaymentMethodInterface $paymentMethod
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    protected function executeApply(PaymentMethodInterface $paymentMethod)
    {
        $dispatcher = $paymentMethod->apply(); // 決済処理中.

        // リンク式決済のように他のサイトへ遷移する場合などは, dispatcherに処理を移譲する.
        if ($dispatcher instanceof PaymentDispatcher) {
            $response = $dispatcher->getResponse();
            $this->entityManager->flush();

            // dispatcherがresponseを保持している場合はresponseを返す
            if ($response instanceof Response && ($response->isRedirection() || $response->isSuccessful())) {
                log_info('[注文処理] PaymentMethod::applyが指定したレスポンスを表示します.');

                return $response;
            }

            // forwardすることも可能.
            if ($dispatcher->isForward()) {
                log_info('[注文処理] PaymentMethod::applyによりForwardします.',
                    [$dispatcher->getRoute(), $dispatcher->getPathParameters(), $dispatcher->getQueryParameters()]);

                return $this->forwardToRoute($dispatcher->getRoute(), $dispatcher->getPathParameters(),
                    $dispatcher->getQueryParameters());
            } else {
                log_info('[注文処理] PaymentMethod::applyによりリダイレクトします.',
                    [$dispatcher->getRoute(), $dispatcher->getPathParameters(), $dispatcher->getQueryParameters()]);

                return $this->redirectToRoute($dispatcher->getRoute(),
                    array_merge($dispatcher->getPathParameters(), $dispatcher->getQueryParameters()));
            }
        }
    }

    /**
     * PaymentMethod::checkoutを実行する.
     *
     * @param PaymentMethodInterface $paymentMethod
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    protected function executeCheckout(PaymentMethodInterface $paymentMethod)
    {
        $PaymentResult = $paymentMethod->checkout();
        $response = $PaymentResult->getResponse();
        // PaymentResultがresponseを保持している場合はresponseを返す
        if ($response instanceof Response && ($response->isRedirection() || $response->isSuccessful())) {
            $this->entityManager->flush();
            log_info('[注文処理] PaymentMethod::checkoutが指定したレスポンスを表示します.');

            return $response;
        }

        // エラー時はロールバックして購入エラーとする.
        if (!$PaymentResult->isSuccess()) {
            $this->entityManager->rollback();
            foreach ($PaymentResult->getErrors() as $error) {
                $this->addError($error);
            }

            log_info('[注文処理] PaymentMethod::checkoutのエラーのため, 購入エラー画面へ遷移します.', [$PaymentResult->getErrors()]);

            return $this->redirectToRoute('shopping_error');
        }
    }
	
	/**
	 * @Route("/mypage/", name="winning_page")
	 */
	public function winningPage(Request $request)
	{
		return $this->redirectToRoute('mypage');
	}

    protected function getEndPoint($str){
        switch( $str ){
            case 'authorize':
                $endpoint = $this->baseurl.'/oauth2/authorize';
                break;
            case 'token':
                $endpoint = $this->baseurl.'/oauth2/token';
                break;
            case 'user':
                $endpoint = $this->baseurl.'/open_id/userinfo';
                break;
            case 'point':
                $endpoint = 'https://bapi.nicobus.nicovideo.jp/v1/user/nicopoints.json';
                break;
            case 'use':
                $endpoint = 'https://bapi.nicobus.nicovideo.jp/v1/user/nicopoints/use.json';
                break;
            case 'premium':
                $endpoint = $this->baseurl.'/v1/user/premium.json';
                break;
            case 'ticket':
                $endpoint = 'https://api.live2.nicovideo.jp/api/v1/ticket';
                break;
            case 'channel':
				$ids = $this->getItemChannelID();
				if( !empty($ids) ){
					$endpoint = array_map( function($value){ return $this->baseurl.'/v1/user/memberships/channels/'.$value.'.json'; }, $ids );
				}else{
					$endpoint = '';
				}
            break;
        }
        return $endpoint;
    }

    protected function createLink($url, $args){
        $url .= '?';
        $last = end($args);
        foreach( array_filter($args) as $key => $value ){
            $url .= $key.'='.$value;
            if( $value != $last ){
                $url .= '&';
            }
		}
        
        return $url;
    }

    protected function send($url, $method, $header, $data=array()){

        if( !empty($data) ){
            $data = http_build_query($data, '', '&');
            $header[] = 'Content-Length: '.strlen($data);
        }

        $context = array(
            'http' => array(
                'method'  => $method,
				'header'  => implode("\r\n", $header),
            )
        );

        if( !empty($data) ){
            $context['http']['content'] = $data;
        }

        return @file_get_contents($url, false, stream_context_create($context));
    }

    public function getAuthorize(){
        $url = $this->getEndPoint('authorize');
        $args = array(
            'client_id' => $this->client_id,
            'response_type' => $this->response_type,
            'redirect_uri' => $this->redirect_uri,
            'nonce' => $this->nonce,
            'scope' => $this->scope,
            'prompt' => 'login consent',
        );
        return $this->createLink($url, $args);
    }

    public function getToken(){
        if (empty($this->nico_code)) return $this->niko_error();

        $url = $this->getEndPoint('token');
        $data = array(
            'grant_type' => 'authorization_code',
            'code' => $this->nico_code,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
		);
        $header = array(
            "Content-Type: application/x-www-form-urlencoded",
        );
        if( $response = $this->send($url, 'POST', $header, $data) ){
            $nonce = $this->checkNonce($response);
            if(  $this->is_niko_error( $nonce ) ) {
                return $nonce;
            }

            return $response;
        } else{
            return $this->niko_error();
        }
    }

    protected function checkNonce($token){
        $code = json_decode( $token );
        $url = $this->baseurl.'/v1/id_tokens/'.$code->id_token.'.json';
        $header = array(
            "Content-type: application/json; charset=utf-8",
            "Authorization: Bearer ".$code->access_token
        );

        if( $response = $this->send($url, 'GET', $header) ){
            $nonce = json_decode( $response );
            if( $nonce->meta->status !== 200 || $nonce->data->nonce !== $this->nonce ){
                return $this->niko_error();
            }

            $this->nico_id = $nonce->data->sub;
            $this->access_token = $code->access_token;
            $this->refresh_token = $code->refresh_token;

        }else{
            return $this->niko_error();
        }
        return $response;
    }

    public function getUserInfo( $access_token='' ){
        if( empty($access_token) ){
            $access_token = $this->access_token;
        }
        $url = $this->getEndPoint('user');
        $header = array(
            "Content-type: application/json; charset=utf-8",
            "Authorization: Bearer ".$access_token
        );

        if( $response = $this->send($url, 'GET', $header) ){
            return $response;
        }else{
            return $this->niko_error();
        }

    }

    /*
    ** ユーザーのポイントを取得する
    */
    public function getUserPoint( $access_token='' ){
        if( empty($access_token) ){
            $access_token = $this->access_token;
        }
        $url = $this->getEndPoint('point');
        $header = array(
            "Content-type: application/json; charset=utf-8",
            "Authorization: Bearer ".$access_token
        );
        return json_encode( [ 'meta' => [ 'status' => 200 ], 'data' => [ 'allRemainPoint' => 0 ] ] );
        if( $response = $this->send($url, 'GET', $header) ){
            return $response;
        }else{
            return $this->niko_error();
        }

    }

    /*
    ** プレミアム会員の判定
    */
    public function getUserPremium( $access_token='' ){
        if( empty($access_token) ){
            $access_token = $this->access_token;
        }
        $url = $this->getEndPoint('premium');
        $header = array(
            "Content-type: application/json; charset=utf-8",
            "Authorization: Bearer ".$access_token
        );

        if( $response = $this->send($url, 'GET', $header) ){
            return $response;
        }else{
            return $this->niko_error();
        }

    }

    /*
    ** すべてのチャンネル登録の判定
    */
    public function getUserChannel( $access_token='' ){
        if( empty($access_token) ){
            $access_token = $this->access_token;
        }
		$urls = $this->getEndPoint('channel');
		if( empty($urls) ){
			return [json_encode( [ 'meta' => [ 'status' => 404 ] ] )];
		}

        $header = array(
            "Content-type: application/json; charset=utf-8",
            "Authorization: Bearer ".$access_token
		);
		$ids = array();
		foreach( $urls as $url ){
			if( $response = $this->send($url, 'GET', $header) ){
				$ids[] = $response;
			}else{
				$ids[] = json_encode( [ 'meta' => [ 'status' => 404 ] ] );
			}
		return $ids;
	    }
    }

    /*
    ** 個別チャンネル登録の判定
    */
    public function getUserChannelIsID( $channel_id ){
		$access_token = $this->access_token;
		$url = $this->baseurl.'/v1/user/memberships/channels/'.$channel_id.'.json';

        $header = array(
            "Content-type: application/json; charset=utf-8",
            "Authorization: Bearer ".$access_token
		);

        // //return json_encode( ['data'=>array('channel'=>array('id'=>2598414))] );
        if( $response = $this->send($url, 'GET', $header) ){
            return $response;
        }else{
            return $this->niko_error();
        }

	}

		/*
    ** 購入済みチケットの情報
    */
    public function getUserTicket( $ticket_id, $user_id, $access_token='' ){
			if( empty($access_token) ){
					$access_token = $this->access_token;
			}
			$url = $this->getEndPoint('ticket');
			$data = array(
				'live_id' => $ticket_id,
				'user_id' => $user_id,
			);
			$header = array(
					"Content-type: application/json; charset=utf-8",
					"Authorization: Bearer ".$access_token
			);

			// if( !empty($data) ){
			// 	$data = http_build_query($data, '', '&');
			// 	$header[] = 'Content-Length: '.strlen($data);
			// }

			$context = array(
				'http' => array(
						'method'  => 'GET',
						'header'  => implode("\r\n", $header),
						'protocol_version' => 1.1
				)
			);

			// if( !empty($data) ){
			// 		$context['http']['content'] = $data;
			// }
			$response = @file_get_contents($url.'?'.http_build_query($data, '', '&'), false, stream_context_create($context));

			if( $response ){
				return $response;
			}else{
                return $this->niko_error();
			}

	}

    /*
    ** トークンのリフレッシュ
    */
    public function refreshToken($refresh_token){

        $url = $this->getEndPoint('token');
        $data = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
        );
        $header = array(
            "Content-Type: application/x-www-form-urlencoded",
        );

        if( $response = $this->send($url, 'POST', $header, $data) ){
            return $response;
        }else{
            return $this->niko_error();
        }

    }

    /*
    ** ユーザーのポイントを消費する
    ** $totalPoint : integer 利用全ポイント数
    ** $items : array 商品情報
    **          itemCode = 商品コード
    **          itemName = 商品名
    **          itemCount = 商品数量
    **          itemPoint = 商品ポイント数
    */
    public function useUserPoint( $totalPoint, $items, $access_token='' ){
        if( empty($access_token) ){
            $access_token = $this->access_token;
        }
        $url = $this->getEndPoint('use');
        $data = array(
            "totalPoint" => $totalPoint,
            "totalCount" => count($items),
            "transactionUseId" => hash('ripemd160','use_point_niconico'),
            "items" => $items,
        );
        $data = json_encode( $data, JSON_UNESCAPED_UNICODE );
        $header = array(
            "Content-Type: application/json; charset=utf-8",
            "Authorization: Bearer ".$access_token
        );
        $context = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => implode("\r\n", $header),
                'content' => $data,
                "ignore_errors" => true
            )
        );
        $response = @file_get_contents($url, false, stream_context_create($context));
        if( $response ){
            return $response;
        }else{
            return $this->niko_error();
        }
	}
    
    public function checkMember(){
        $email = $this->user->email;

        $customer = $this->getDoctrine()->getRepository(Customer::class)->findOneBy(['email' => $email]);
        
        if ($customer == null)
            return $this->registMember();
        else
            return $this->refreshMember($customer);
    }

    public function refreshMember($customer){        
        $member_status = 0;
        if( $this->premium == 'premium' ){
            $member_status = 4;
            $customer->premium = 1;
        } else {
            $customer->premium = 0;
        }
        if( !empty($this->channel) ){
            $member_status = 5;
            $customer->channel = implode("|", $this->channel);
        } else {
            $customer->channel = null;
        }
        $tickets = $this->ticketIDs();
        foreach($tickets as $ticket) {
            if ( $this->ticket($ticket) ){
                $customer->ticket = $ticket;
                break;
            }             
        }

        $customer->customer_rank = $member_status;
        $customer->setPoint($this->point);

        $this->entityManager->persist($customer);
        $this->entityManager->flush();
        
        $this->nico_premium = $customer->premium;
        $this->nico_channel = $customer->channel;
        $this->nico_ticket = $customer->ticket;

        $token = new UsernamePasswordToken($customer, null, 'customer', ['ROLE_USER']);
        $this->get("security.token_storage")->setToken($token);
print_r('eee');exit;
        return $this->redirectToRoute('shopping');
    }

    public function registMember(){
        $customer_status = 0;

        if( $this->premium == 'premium' ){
            $customer_status = 4;
            $this->premium = 1;
        }
        if( !empty($this->channel) ){
            $customer_status = 5;
            $this->channel = implode("|", $this->channel);
        }
        $tickets = $this->ticketIDs();
        foreach($tickets as $item) {
            if ( $this->ticket($item) ){
                $this->ticket = $item;
                break;
            }             
        }

        $this->addFlash('customer_status', $customer_status);
        $this->addFlash('customer_point', $this->point);
        $this->addFlash('customer_email', $this->user->email);
        $this->addFlash('customer_premium', $this->premium);
        $this->addFlash('customer_channel', $this->channel);
        $this->addFlash('customer_ticket', $this->ticket);

        print_r('bbb');exit;
        return $this->redirectToRoute('entry');
    }

    public function getItemChannelID()
    {
        $result = array();
        $Products = $this->getDoctrine()->getRepository(Product::class)->findAll();

        foreach($Products as $Product)
        {
            if(strlen($Product->niconico) > 1 && is_numeric($Product->niconico))
                array_push($result, $Product->niconico);
        }
        return array_unique($result, SORT_REGULAR);
    }

    public function ticketIDs()
    {
        $result = array();
        $Products = $this->getDoctrine()->getRepository(Product::class)->findAll();

        foreach($Products as $Product)
        {
            if(strlen($Product->specifics) > 1)
                array_push($result, $Product->specifics);
        }
        return array_unique($result, SORT_REGULAR);
    }

    /*
    ** チャンネルIDをスコープ文字列に変換
    */
    public function getScopeChannelID(){
		$results = $this->getItemChannelID();
		$channel = array_map( function($value){ return 'user.memberships.channels:'.$value; }, $results );
		return '%20'.implode('%20', $channel);
	}

    /*
    ** チケット購入の判定
    */
    public function ticket($ticket_id){
        if( empty($this->nico_id) ){
            return false;
        }
        $response = $this->getUserTicket($ticket_id, $this->nico_id);
        if(  $this->is_niko_error( $response ) ){
                $this->refresh();
                $this->reticket($ticket_id);
        }else{
            if( empty(json_decode( $response )->data) ){
                return false;
            }
            return true;
        }
    }
    public function reticket($ticket_id){
        $response = $this->getUserTicket($ticket_id, $this->nico_id);
        if(  $this->is_niko_error( $response ) ){
            return false;
        }else{
            if( empty(json_decode( $response )->data) ){
                return false;
            }
            return true;
        }
    }
    public function refresh(){
        $response = $this->refreshToken($this->refresh_token);
        if(  $this->is_niko_error( $response ) ){
            $this->addFlash('oauth_error', 'トークンの有効期限が切れました。もう一度ログインしてください。');
            
            $Customer = $this->getUser();
            if(!isset($Customer)) {
                $request->getSession()->invalidate();
                return $this->redirectToRoute('mypage_login');
            }
        }
        $token = json_decode( $response );
        $this->access_token = $token->access_token;
        $this->refresh_token = $token->refresh_token;
    }
    
    function niko_error(){
        return 'error happened';
    }
    
    function is_niko_error($error){
        return $error == 'error happened';
    }

}