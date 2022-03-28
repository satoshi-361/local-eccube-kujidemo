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

use Customize\Controller\AbstractController;

use Eccube\Entity\BaseInfo;
use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Eccube\Entity\Customer;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\ProductClassRepository;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Service\PurchaseFlow\PurchaseFlowResult;
use Eccube\Service\OrderHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends AbstractController
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
     * @var ProductClassRepository
     */
    protected $productClassRepository;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var BaseInfo
     */
    protected $baseInfo;

    /**
     * CartController constructor.
     *
     * @param ProductClassRepository $productClassRepository
     * @param CartService $cartService
     * @param PurchaseFlow $cartPurchaseFlow
     * @param BaseInfoRepository $baseInfoRepository
     */
    public function __construct(
        ProductClassRepository $productClassRepository,
        CartService $cartService,
        PurchaseFlow $cartPurchaseFlow,
        BaseInfoRepository $baseInfoRepository
    ) {
        $this->productClassRepository = $productClassRepository;
        $this->cartService = $cartService;
        $this->purchaseFlow = $cartPurchaseFlow;
        $this->baseInfo = $baseInfoRepository->get();

        $this->client_id = '2W28ATt7K2QwWmcW';// ニコニコから発行されたクライアントID
		$this->client_secret = 'KAQWLo0EUjrFPiAxjbPXjojSQMPEO2U1';// ニコニコデベロッパーで発行したクライアントシークレット
		$this->baseurl = 'https://oauth.nicovideo.jp';// 認証URL
		$this->response_type = 'code%20id_token';// 要求する応答タイプ
		$this->redirect_uri = $this->homepage_url() . 'shopping/';// リダイレクトURI
		$this->nonce = hash('ripemd160','oauth_login_niconico');// 任意の文字列の発行
		$this->scope = 'email%20offline_access%20openid%20profile%20user%20user.premium%20user.authorities.lives.ticket.get';
    }

    protected function getNicoInfo(){
        $this->scope = 'email%20offline_access%20openid%20profile%20user%20user.premium%20user.authorities.lives.ticket.get'.$this->getScopeChannelID();
        $user = $this->getUserInfo();
        if( $this->is_error( $user ) ) {
            $this->redirect( $this->redirect_uri );
            exit;
        }
        $this->user = json_decode( $user );

        $point = $this->getUserPoint();
        if( $this->is_error( $point ) ) {
            $this->redirect( $this->redirect_uri );
            exit;
        }
        $this->point = json_decode( $point )->data->allRemainPoint;

		$premium = $this->getUserPremium();
        if( $this->is_error( $premium ) ) {
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

        $this->checkMember();
    }

    /**
     * カート画面.
     *
     * @Route("/cart", name="cart")
     * @Template("Cart/index.twig")
     */
    public function index(Request $request)
    {
        if ($request->getSession()->get('is_niconico_signup') == true) {
            $this->nico_code = $request->getSession()->get('nico_code');
            $this->nico_id = $request->getSession()->get('nico_id');
            $this->access_token = $request->getSession()->get('access_token');
            $this->refresh_token = $request->getSession()->get('refresh_token');

            $this->getNicoInfo();
            $Customer = $this->getUser();

            foreach($this->cartService->getCarts() as $Cart)
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
                    break;
                }
            }
        }
        // カートを取得して明細の正規化を実行
        $Carts = $this->cartService->getCarts();
        $this->execPurchaseFlow($Carts);

        // TODO itemHolderから取得できるように
        $least = [];
        $quantity = [];
        $isDeliveryFree = [];
        $totalPrice = 0;
        $totalQuantity = 0;

        foreach ($Carts as $Cart) {
            $quantity[$Cart->getCartKey()] = 0;
            $isDeliveryFree[$Cart->getCartKey()] = false;

            if ($this->baseInfo->getDeliveryFreeQuantity()) {
                if ($this->baseInfo->getDeliveryFreeQuantity() > $Cart->getQuantity()) {
                    $quantity[$Cart->getCartKey()] = $this->baseInfo->getDeliveryFreeQuantity() - $Cart->getQuantity();
                } else {
                    $isDeliveryFree[$Cart->getCartKey()] = true;
                }
            }

            if ($this->baseInfo->getDeliveryFreeAmount()) {
                if (!$isDeliveryFree[$Cart->getCartKey()] && $this->baseInfo->getDeliveryFreeAmount() <= $Cart->getTotalPrice()) {
                    $isDeliveryFree[$Cart->getCartKey()] = true;
                } else {
                    $least[$Cart->getCartKey()] = $this->baseInfo->getDeliveryFreeAmount() - $Cart->getTotalPrice();
                }
            }

            $totalPrice += $Cart->getTotalPrice();
            $totalQuantity += $Cart->getQuantity();
        }

        // カートが分割された時のセッション情報を削除
        $request->getSession()->remove(OrderHelper::SESSION_CART_DIVIDE_FLAG);

        return [
            'totalPrice' => $totalPrice,
            'totalQuantity' => $totalQuantity,
            // 空のカートを削除し取得し直す
            'Carts' => $this->cartService->getCarts(true),
            'least' => $least,
            'quantity' => $quantity,
            'is_delivery_free' => $isDeliveryFree,
        ];
    }

    /**
     * @param $Carts
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function execPurchaseFlow($Carts)
    {
        /** @var PurchaseFlowResult[] $flowResults */
        $flowResults = array_map(function ($Cart) {
            $purchaseContext = new PurchaseContext($Cart, $this->getUser());

            return $this->purchaseFlow->validate($Cart, $purchaseContext);
        }, $Carts);

        // 復旧不可のエラーが発生した場合はカートをクリアして再描画
        $hasError = false;
        foreach ($flowResults as $result) {
            if ($result->hasError()) {
                $hasError = true;
                foreach ($result->getErrors() as $error) {
                    $this->addRequestError($error->getMessage());
                }
            }
        }
        if ($hasError) {
            $this->cartService->clear();

            return $this->redirectToRoute('cart');
        }

        $this->cartService->save();

        foreach ($flowResults as $index => $result) {
            foreach ($result->getWarning() as $warning) {
                if ($Carts[$index]->getItems()->count() > 0) {
                    $cart_key = $Carts[$index]->getCartKey();
                    $this->addRequestError($warning->getMessage(), "front.cart.${cart_key}");
                } else {
                    // キーが存在しない場合はグローバルにエラーを表示する
                    $this->addRequestError($warning->getMessage());
                }
            }
        }
    }

    /**
     * カート明細の加算/減算/削除を行う.
     *
     * - 加算
     *      - 明細の個数を1増やす
     * - 減算
     *      - 明細の個数を1減らす
     *      - 個数が0になる場合は、明細を削除する
     * - 削除
     *      - 明細を削除する
     *
     * @Route(
     *     path="/cart/{operation}/{productClassId}",
     *     name="cart_handle_item",
     *     methods={"PUT"},
     *     requirements={
     *          "operation": "up|down|remove",
     *          "productClassId": "\d+"
     *     }
     * )
     */
    public function handleCartItem($operation, $productClassId)
    {
        log_info('カート明細操作開始', ['operation' => $operation, 'product_class_id' => $productClassId]);

        $this->isTokenValid();

        /** @var ProductClass $ProductClass */
        $ProductClass = $this->productClassRepository->find($productClassId);

        if (is_null($ProductClass)) {
            log_info('商品が存在しないため、カート画面へredirect', ['operation' => $operation, 'product_class_id' => $productClassId]);

            return $this->redirectToRoute('cart');
        }

        // 明細の増減・削除
        switch ($operation) {
            case 'up':
                $this->cartService->addProduct($ProductClass, 1);
                break;
            case 'down':
                $this->cartService->addProduct($ProductClass, -1);
                break;
            case 'remove':
                $this->cartService->removeProduct($ProductClass);
                break;
        }

        // カートを取得して明細の正規化を実行
        $Carts = $this->cartService->getCarts();
        $this->execPurchaseFlow($Carts);

        log_info('カート演算処理終了', ['operation' => $operation, 'product_class_id' => $productClassId]);

        return $this->redirectToRoute('cart');
    }

    /**
     * カートをロック状態に設定し、購入確認画面へ遷移する.
     *
     * @Route("/cart/buystep/{cart_key}", name="cart_buystep", requirements={"cart_key" = "[a-zA-Z0-9]+[_][\x20-\x7E]+"})
     */
    public function buystep(Request $request, $cart_key)
    {
        $Carts = $this->cartService->getCart();
        if (!is_object($Carts)) {
            return $this->redirectToRoute('cart');
        }
        // FRONT_CART_BUYSTEP_INITIALIZE
        $event = new EventArgs(
            [],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_CART_BUYSTEP_INITIALIZE, $event);

        $this->cartService->setPrimary($cart_key);
        $this->cartService->save();

        // FRONT_CART_BUYSTEP_COMPLETE
        $event = new EventArgs(
            [],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_CART_BUYSTEP_COMPLETE, $event);

        if ($event->hasResponse()) {
            return $event->getResponse();
        }
        
        return $this->redirectToRoute('shopping');  
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
        if (empty($this->nico_code)) return $this->error();

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
            if( $this->is_error( $nonce ) ) {
                return $nonce;
            }

            return $response;
        } else{
            return $this->error();
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
                return $this->error();
            }

            $this->nico_id = $nonce->data->sub;
            $this->access_token = $code->access_token;
            $this->refresh_token = $code->refresh_token;

        }else{
            return $this->error();
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
        } else{
            return $this->error();
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
            return $this->error();
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
            return $this->error();
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
            return $this->error();
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
			$context = array(
				'http' => array(
						'method'  => 'GET',
						'header'  => implode("\r\n", $header),
						'protocol_version' => 1.1
				)
			);

			$response = @file_get_contents($url.'?'.http_build_query($data, '', '&'), false, stream_context_create($context));

			if( $response ){
				return $response;
			}else{
                return $this->error();
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
            return $this->error();
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
            return $this->error();
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
        if( $this->is_error( $response ) ){
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
        if( $this->is_error( $response ) ){
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
        if( $this->is_error( $response ) ){
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

    function error(){
        return 'error happened';
    }
    
    function is_error($error){
        return $error == 'error happened';
    }
}

