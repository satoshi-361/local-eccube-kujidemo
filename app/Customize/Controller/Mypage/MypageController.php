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

namespace Customize\Controller\Mypage;

use Customize\Controller\AbstractController;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Customer;
use Eccube\Entity\Product;
use Eccube\Entity\Order;
use Eccube\Entity\Category;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Exception\CartException;
use Eccube\Form\Type\Front\CustomerLoginType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CustomerFavoriteProductRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\ProductRepository;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Knp\Component\Pager\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

use Plugin\ProductAssist\Entity\Config as ProductAssist;
use Plugin\ProductAssistConfig\Entity\Config as ProductAssistConfig;
use Plugin\PrizeShow\Entity\Config as Prize;
use Plugin\PrizeShow\Entity\PrizeList as PrizeList;
use Plugin\PrizesPerProduct\Entity\Config as PrizesPerProduct;

use Plugin\ProductAnimImage\Entity\Config as ProductAnimImage;
use Eccube\Entity\Master\CustomerStatus;

if (!defined('PRIZE_NAME_SIZE')) {
    define("PRIZE_NAME_SIZE", 400000);
}

class MypageController extends AbstractController
{
    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var CustomerFavoriteProductRepository
     */
    protected $customerFavoriteProductRepository;

    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    protected $client_id;
    protected $baseurl;
    protected $response_type;
    protected $redirect_uri;
    protected $nonce;
    protected $scope;

    /**
     * MypageController constructor.
     *
     * @param OrderRepository $orderRepository
     * @param CustomerFavoriteProductRepository $customerFavoriteProductRepository
     * @param CartService $cartService
     * @param BaseInfoRepository $baseInfoRepository
     * @param PurchaseFlow $purchaseFlow
     */
    public function __construct(
        OrderRepository $orderRepository,
        CustomerFavoriteProductRepository $customerFavoriteProductRepository,
        CartService $cartService,
        BaseInfoRepository $baseInfoRepository,
        PurchaseFlow $purchaseFlow
    ) {
        $this->orderRepository = $orderRepository;
        $this->customerFavoriteProductRepository = $customerFavoriteProductRepository;
        $this->BaseInfo = $baseInfoRepository->get();
        $this->cartService = $cartService;
        $this->purchaseFlow = $purchaseFlow;

        $this->client_id = '2W28ATt7K2QwWmcW';// ニコニコから発行されたクライアントID
		$this->client_secret = 'KAQWLo0EUjrFPiAxjbPXjojSQMPEO2U1';// ニコニコデベロッパーで発行したクライアントシークレット
		$this->baseurl = 'https://oauth.nicovideo.jp';// 認証URL
		$this->response_type = 'code%20id_token';// 要求する応答タイプ
		$this->redirect_uri = $this->homepage_url();// リダイレクトURI
		$this->nonce = hash('ripemd160','oauth_login_niconico');// 任意の文字列の発行
		$this->scope = 'email%20offline_access%20openid%20profile%20user%20user.premium%20user.authorities.lives.ticket.get'/*.$this->getScopeChannelID()*/;
    }

    /**
     * ログイン画面.
     *
     * @Route("/mypage/login", name="mypage_login")
     * @Template("Mypage/login.twig")
     */
    public function login(Request $request, AuthenticationUtils $utils)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            log_info('認証済のためログイン処理をスキップ');
            $request->getSession()->set('is_niconico_signup', false);
            
            return $this->redirectToRoute('mypage');
        }

        if ( $request->get('mode') == 'login' || !is_null($request->get('login_email'))) {
            $Customer = $this->getDoctrine()->getRepository(Customer::class)->findOneBy(['email' => $request->get('login_email')]);
            print_r($request->get('login_email'));
            if ($Customer->getWrongCount() == 2) {
                $Customer->setWrongCount(0);

                $CustomerStatus = $this->getDoctrine()->getRepository(CustomerStatus::class)->find(CustomerStatus::PROVISIONAL);
                $Customer->setStatus($CustomerStatus);
            } else {
                $wrong_count = $Customer->getWrongCount();
                print_r($wrong_count);exit;
                $Customer->setWrongCount($wrong_count++);
            }
            $this->entityManager->persist($Customer);
            $this->entityManager->flush();
        }

        /* @var $form \Symfony\Component\Form\FormInterface */
        $builder = $this->formFactory
            ->createNamedBuilder('', CustomerLoginType::class);

        $builder->get('login_memory')->setData((bool) $request->getSession()->get('_security.login_memory'));

        if ($this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $Customer = $this->getUser();
            if ($Customer instanceof Customer) {
                $builder->get('login_email')
                    ->setData($Customer->getEmail());
            }
        }

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_MYPAGE_LOGIN_INITIALIZE, $event);

        $form = $builder->getForm();

        $nico_url = $this->getAuthorize();

        $this->addFlash('last_uri', 'mypage_login');

        return [
            'error' => $utils->getLastAuthenticationError(),	
            'form' => $form->createView(),
            'nico_url' => $nico_url
        ];
    }

    /**
     * マイページ.
     *
     * @Route("/mypage/", name="mypage")
     * @Template("Mypage/index.twig")
     */
    public function index(Request $request, Paginator $paginator)
    {
        $Customer = $this->getUser();
        if(!isset($Customer)) {
            $request->getSession()->invalidate();
            return $this->redirectToRoute('mypage_login');
        }

        // 購入処理中/決済処理中ステータスの受注を非表示にする.
        $this->entityManager
            ->getFilters()
            ->enable('incomplete_order_status_hidden');

        // paginator
        $qb = $this->orderRepository->getQueryBuilderByCustomer($Customer);

        $event = new EventArgs(
            [
                'qb' => $qb,
                'Customer' => $Customer,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_MYPAGE_INDEX_SEARCH, $event);

        $pagination = $paginator->paginate(
            $qb,
            $request->get('pageno', 1),
            $this->eccubeConfig['eccube_search_pmax']
        );

        $Prizes = array();
        $counts = array();
        $Category_types = array(); // for Prize Result Button Text
        $PrizesPerProductRepo = $this->getDoctrine()->getRepository(PrizesPerProduct::class);
        foreach($pagination as $Order)
        {
            $is_bulk = false;
            $sub_prizes = array();
            $sub_count = array();
            $sub_category_type = array();
            foreach($Order->getMergedProductOrderItems() as $OrderItem){
                $Product = $OrderItem->getProduct();
                $Categories = $Product->getProductCategories();
                foreach($Categories as $Category)
                {
                    if ($Category->getCategory()->getName() == "通常くじ"){
                        array_push($sub_category_type, 1);
                        $is_bulk = false;
                    }
                    if ($Category->getCategory()->getName() == "確定くじ"){
                        array_push($sub_category_type, 1);
                        $is_bulk = false;
                    }
                    if ($Category->getCategory()->getName() == "大人買いくじ"){
                        array_push($sub_category_type, 2);
                        $is_bulk = false;
                    }
                    if ($Category->getCategory()->getName() == "まとめ買いくじ"){
                        array_push($sub_category_type, 0);
                        $is_bulk = true;
                    }
                }
                $text = '';
                $text_prizes = $PrizesPerProductRepo->findBy(['orderId' => $Order->getId(), 'productId' => $Product->getId(), 'prizeGrade' => NULL]);
                foreach($text_prizes as $item)
                    $text .= $item->getPrizeName();
                $pos = strrpos($text, ',0');
                $count = count(explode(';', substr($text, $pos + 3, strlen($text) - $pos - 3))) - 1;
                array_push($sub_count, $count);
            }
            array_push($counts, $sub_count);
            array_push($Category_types, $sub_category_type);
        }
        return [
            'pagination' => $pagination,
            'counts' => $counts,
            'Categories' => $Category_types
        ];
    }

    /**
     * 購入履歴詳細を表示する.
     *
     * @Route("/mypage/history/{order_no}", name="mypage_history")
     * @Template("Mypage/history.twig")
     */
    public function history(Request $request, $order_no, Paginator $paginator)
    {
        $this->entityManager->getFilters()
            ->enable('incomplete_order_status_hidden');
        $Order = $this->orderRepository->findOneBy(
            [
                'order_no' => $order_no,
                'Customer' => $this->getUser(),
            ]
        );

        $event = new EventArgs(
            [
                'Order' => $Order,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_MYPAGE_HISTORY_INITIALIZE, $event);

        /** @var Order $Order */
        $Order = $event->getArgument('Order');

        if (!$Order) {
            throw new NotFoundHttpException();
        }

        $stockOrder = true;
        foreach ($Order->getOrderItems() as $orderItem) {
            if ($orderItem->isProduct() && $orderItem->getQuantity() < 0) {
                $stockOrder = false;
                break;
            }
        }

        $displayed_products = array();
        foreach($Order->getMergedProductOrderItems() as $OrderItem){
            $Product = $OrderItem->getProduct();
            $PrizesPerProductRepo = $this->getDoctrine()->getRepository(PrizesPerProduct::class);

            $text = '';
            $items = $PrizesPerProductRepo->findBy(['orderId' => $Order->getId(), 'productId' => $Product->getId(), 'prizeGrade' => NULL]);
            foreach($items as $item) {
                $text .= $item->getPrizeName();
            }
            
            $pos = strrpos($text, ',0');
            if ($pos == false) continue;
            $text = substr($text, 0, $pos + 3);
            $temp = explode(';', $text);

            foreach($temp as $item) {
                $Prize = $PrizesPerProductRepo->find(explode(',', $item)[0]);
                array_push($displayed_products, $Prize);
            }
        }

        $pagination = $paginator->paginate(
            $displayed_products,
            $request->get('pageno', 1),
            6
        );
        return [
            'Order' => $Order,
            'stockOrder' => $stockOrder,
            // 'displayed_products' => $displayed_products
            'pagination' => $pagination,
            'order_no' => $order_no
        ];
    }

    /**
     * 再購入を行う.
     *
     * @Route("/mypage/order/{order_no}", name="mypage_order", methods={"PUT"})
     */
    public function order(Request $request, $order_no)
    {
        $this->isTokenValid();

        log_info('再注文開始', [$order_no]);

        $Customer = $this->getUser();

        /* @var $Order \Eccube\Entity\Order */
        $Order = $this->orderRepository->findOneBy(
            [
                'order_no' => $order_no,
                'Customer' => $Customer,
            ]
        );

        $event = new EventArgs(
            [
                'Order' => $Order,
                'Customer' => $Customer,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_MYPAGE_ORDER_INITIALIZE, $event);

        if (!$Order) {
            log_info('対象の注文が見つかりません', [$order_no]);
            throw new NotFoundHttpException();
        }

        // エラーメッセージの配列
        $errorMessages = [];

        foreach ($Order->getOrderItems() as $OrderItem) {
            try {
                if ($OrderItem->getProduct() && $OrderItem->getProductClass()) {
                    $this->cartService->addProduct($OrderItem->getProductClass(), $OrderItem->getQuantity());

                    // 明細の正規化
                    $Carts = $this->cartService->getCarts();
                    foreach ($Carts as $Cart) {
                        $result = $this->purchaseFlow->validate($Cart, new PurchaseContext($Cart, $this->getUser()));
                        // 復旧不可のエラーが発生した場合は追加した明細を削除.
                        if ($result->hasError()) {
                            $this->cartService->removeProduct($OrderItem->getProductClass());
                            foreach ($result->getErrors() as $error) {
                                $errorMessages[] = $error->getMessage();
                            }
                        }
                        foreach ($result->getWarning() as $warning) {
                            $errorMessages[] = $warning->getMessage();
                        }
                    }

                    $this->cartService->save();
                }
            } catch (CartException $e) {
                log_info($e->getMessage(), [$order_no]);
                $this->addRequestError($e->getMessage());
            }
        }

        foreach ($errorMessages as $errorMessage) {
            $this->addRequestError($errorMessage);
        }

        $event = new EventArgs(
            [
                'Order' => $Order,
                'Customer' => $Customer,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_MYPAGE_ORDER_COMPLETE, $event);

        if ($event->getResponse() !== null) {
            return $event->getResponse();
        }

        log_info('再注文完了', [$order_no]);

        return $this->redirect($this->generateUrl('cart'));
    }

    /**
     * お気に入り商品を表示する.
     *
     * @Route("/mypage/favorite", name="mypage_favorite")
     * @Template("Mypage/favorite.twig")
     */
    public function favorite(Request $request, Paginator $paginator)
    {
        if (!$this->BaseInfo->isOptionFavoriteProduct()) {
            throw new NotFoundHttpException();
        }
        $Customer = $this->getUser();

        // paginator
        $qb = $this->customerFavoriteProductRepository->getQueryBuilderByCustomer($Customer);

        $event = new EventArgs(
            [
                'qb' => $qb,
                'Customer' => $Customer,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_MYPAGE_FAVORITE_SEARCH, $event);

        $pagination = $paginator->paginate(
            $qb,
            $request->get('pageno', 1),
            $this->eccubeConfig['eccube_search_pmax'],
            ['wrap-queries' => true]
        );

        return [
            'pagination' => $pagination,
        ];
    }

    /**
     * お気に入り商品を削除する.
     *
     * @Route("/mypage/favorite/{id}/delete", name="mypage_favorite_delete", methods={"DELETE"}, requirements={"id" = "\d+"})
     */
    public function delete(Request $request, Product $Product)
    {
        $this->isTokenValid();

        $Customer = $this->getUser();

        log_info('お気に入り商品削除開始', [$Customer->getId(), $Product->getId()]);

        $CustomerFavoriteProduct = $this->customerFavoriteProductRepository->findOneBy(['Customer' => $Customer, 'Product' => $Product]);

        if ($CustomerFavoriteProduct) {
            $this->customerFavoriteProductRepository->delete($CustomerFavoriteProduct);
        } else {
            throw new BadRequestHttpException();
        }

        $event = new EventArgs(
            [
                'Customer' => $Customer,
                'CustomerFavoriteProduct' => $CustomerFavoriteProduct,
            ], $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_MYPAGE_DELETE_COMPLETE, $event);

        log_info('お気に入り商品削除完了', [$Customer->getId(), $CustomerFavoriteProduct->getId()]);

        return $this->redirect($this->generateUrl('mypage_favorite'));
    }
	
    /**
     *
     * @Route("/mypage/winning/{order_no}/{product_id}", name="winning_start")
     * @Template("Mypage/winning_start.twig")
     */
	public function winningStart(Request $request, $order_no = null, $product_id = null)
	{
        $sponser_image = $this->getDoctrine()->getRepository(Product::class)->find($product_id)->getAnimateImage();

		return ['order_no' => $order_no, 'product_id' => $product_id, 'sponser_image' => $sponser_image];
	}

    /**
     *
     * @Route("/mypage/winning/complete/{order_no}/{product_id}/{anim}", name="winning_complete")
     * @Template("Mypage/winning_complete_animation.twig")
     */
	public function winningComplete(Request $request, $order_no = null, $product_id = null, $anim = false)
	{
		$PrizesPerProductRepo = $this->getDoctrine()->getRepository(PrizesPerProduct::class);
        $text_prizes = $PrizesPerProductRepo->findBy(['orderId' => $order_no, 'productId' => $product_id, 'prizeGrade' => NULL]);
        
        for($i = 0; $i < count($text_prizes); $i++)
        {
            $pos = strpos($text_prizes[$i]->getPrizeName(), ',1');
            if($pos) break;
        }
        if($i == count($text_prizes)) $i--;

        $text = '';
        if($i > 0) $text .= $text_prizes[$i - 1]->getPrizeName();
        $text .= $text_prizes[$i]->getPrizeName();
        if($i < count($text_prizes) - 1) $text .= $text_prizes[$i + 1]->getPrizeName();
        
        $pos = strrpos($text, ',0');

        if($pos == null) {
            $item = explode(',', explode(';', substr($text, 0, 10))[0]);
            $result = $PrizesPerProductRepo->find(intval($item[0]));
            $text = $text_prizes[0]->getPrizeName();
            $text = substr_replace($text, $item[0].',0', 0, strlen($item[0].',0'));
            $text_prizes[0]->setPrizeName($text);
            $this->entityManager->persist($text_prizes[0]);
        }
        else {
            $item = explode(',', explode(';', substr($text, $pos + 3, 10))[0]);
            $result = $PrizesPerProductRepo->find(intval($item[0]));
            if($result == null) {
                $temp = substr($text, 0, 20);
                $ele_length = strlen(explode(';', $temp)[0]);
                $result = $PrizesPerProductRepo->find(substr($text, $pos - $ele_length + 2, $ele_length - 2));
            } else {
                $text = substr_replace($text, $item[0].',0', $pos + 3, strlen($item[0].',0'));
                try {
                    for($j = 0; $j < ceil(strlen($text) / PRIZE_NAME_SIZE); $j++)
                    {
                        $key = $i + $j;
                        $text_prizes[$key]->setPrizeName(substr($text, $key * PRIZE_NAME_SIZE, PRIZE_NAME_SIZE));
                        $this->entityManager->persist($text_prizes[$key]);
                    }
                } catch (\Exception $e) {
                    echo $e->getMessage();
                }
            }
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $text = '';
        foreach($text_prizes as $item)
            $text .= $item->getPrizeName();
        $pos = strrpos($text, ',0');
        $count = count(explode(';', substr($text, $pos + 3, strlen($text) - $pos - 3))) - 1;
        
        $Product = $this->getDoctrine()->getRepository(Product::class)->find($product_id);
        $Assist = $this->getDoctrine()->getRepository(ProductAssist::class)->find($Product->product_assist_id);

        if($anim) $animate = false;
        else $animate = $Assist->getIsAnimate();

        $is_bulk = false;
        $Categories = $Product->getProductCategories();
        
        foreach($Categories as $Category)
        {
            if ($Category->getCategory()->getName() == "大人買いくじ"){
                $prizeList = $this->getDoctrine()->getRepository(PrizeList::class)->findOneBy(['name' => $result->getPrizeName()]);
                $Prizes = $prizeList->getSettings()->getValues();
                return $this->render('Mypage/winning_complete.twig', ['quantity' => $count, 'util' => $result, 'Prizes' => $Prizes, 'Product' => $Product]);
            }            
            else if ($Category->getCategory()->getName() == "まとめ買いくじ"){
                $is_bulk = true;
                break;
            }
        }
        if($count > 0)
        {
            return ['quantity' => $count , 'prize' => $result, 'animate' => $animate, 'is_bulk' => $is_bulk, 'Product' => $Product];
        }
        return ['quantity' => 0, 'prize' => $result, 'animate' => $animate, 'is_bulk' => $is_bulk, 'Product' => $Product];
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

    public function getAuthorize(){
        $url = $this->getEndPoint('authorize');
		$this->scope = 'email%20offline_access%20openid%20profile%20user%20user.premium%20user.authorities.lives.ticket.get'.$this->getScopeChannelID();
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

    /*
    ** チャンネルIDをスコープ文字列に変換
    */
    public function getScopeChannelID(){
		$results = $this->getItemChannelID();
		$channel = array_map( function($value){ return 'user.memberships.channels:'.$value; }, $results );
		return '%20'.implode('%20', $channel);
	}
}