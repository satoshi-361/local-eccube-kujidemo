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

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Routing\Annotation\Route;
use Plugin\ProductAssist\Entity\Config as ProductAssist;
use Eccube\Entity\Product;
use Eccube\Entity\Customer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcher,
    Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken,
    Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Plugin\PrizeShow\Entity\PrizeList as PrizeList;

function error(){
    return 'error happened';
}

function is_error($error){
    return $error == 'error happened';
}

class TopController extends AbstractController
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

	public function __construct(){
        $this->client_id = '2W28ATt7K2QwWmcW';// ニコニコから発行されたクライアントID
		$this->client_secret = 'KAQWLo0EUjrFPiAxjbPXjojSQMPEO2U1';// ニコニコデベロッパーで発行したクライアントシークレット
		$this->baseurl = 'https://oauth.nicovideo.jp';// 認証URL
		$this->response_type = 'code%20id_token';// 要求する応答タイプ
		$this->redirect_uri = $this->homepage_url();// リダイレクトURI
		$this->nonce = hash('ripemd160','oauth_login_niconico');// 任意の文字列の発行
		$this->scope = 'email%20offline_access%20openid%20profile%20user%20user.premium%20user.authorities.lives.ticket.get'/*.$this->getScopeChannelID()*/;
	}

	protected function oauthLogin($request){
        $token = $this->getToken();
        if( is_error( $token ) ) {
            return $this->redirect($this->redirect_uri);
            exit;
        }
            
        $request->getSession()->set('nico_id', $this->nico_id);
        $request->getSession()->set('access_token', $this->access_token);
        $request->getSession()->set('refresh_token', $this->refresh_token);

        $user = $this->getUserInfo();
        if( is_error( $user ) ) {
            $this->redirect( $this->redirect_uri );
            exit;
        }
        $this->user = json_decode( $user );

        $point = $this->getUserPoint();
        if( is_error( $point ) ) {
            $this->redirect( $this->redirect_uri );
            exit;
        }
        $this->point = json_decode( $point )->data->allRemainPoint;

		$premium = $this->getUserPremium();
        if( is_error( $premium ) ) {
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
        // print_r(";u->".$user.";p->".$point.";pr->".$premium.";c->".json_encode($channels));
        // print_r("nid->".$this->nico_id.";act->".$this->access_token.";ret->".$this->refresh_token);exit;
        return $this->checkMember();
    }

    /**
     * @Route("/", name="homepage")
     * @Template("index.twig")
     */
    public function index(Request $request)
    {
		if( isset($_GET['code']) && isset($_GET['id_token']) ){
            $request->getSession()->set('is_niconico_signup', true);
            $request->getSession()->set('where_to_login', 'mypage');

            $request->getSession()->set('nico_code', $_GET['code']);
            $request->getSession()->set('nico_id_token', $_GET['id_token']);

            return $this->oauthLogin($request);
        }

		$Products = $this->getDoctrine()->getRepository(Product::class)->findBy([], ['position' => 'asc']);
		$assists = $this->getDoctrine()->getRepository(ProductAssist::class)->findAll();
        $lastProductId = 0;
        if (count($this->getDoctrine()->getRepository(Product::class)->findAll()))
		    $lastProductId= $this->getDoctrine()->getRepository(Product::class)->findOneBy([],['id'=>'DESC'])->getId();
		$res = array();
		foreach($assists as $item)
		{
			$res[$item->product_id] = $item->getSaleEndText();			
		}

		$count_sold_out = 0;
		foreach($Products as $key => $Product)
		{
            if($Product->getStatus() != "公開") {
                unset($Products[$key]);
                continue;
            }

            $has_class = $Product->hasProductClass();
            if (!$has_class) {
                $ProductClasses = $Product->getProductClasses();
                foreach ($ProductClasses as $pc) {
                    if (!is_null($pc->getClassCategory1())) {
                        continue;
                    }
                    if ($pc->isVisible()) {
                        $ProductClass = $pc;
                        break;
                    }
                }
            }
			if($ProductClass->getRemainStatus() == 3) {
                $count_sold_out++;
                unset($Products[$key]);
                continue;
            }
            if( ($ProductClass->getStock() != null && $ProductClass->getStock() == 0) ){
                unset($Products[$key]);
                $count_sold_out++;

                $ProductClass->setRemainStatus(3);
                $this->entityManager->persist($ProductClass);
                $this->entityManager->flush();
                continue;
            }

            $productAssist = $this->getDoctrine()->getRepository(ProductAssist::class)->findOneBy(['id'=>$Product->product_assist_id]);
            foreach($productAssist->getSettings() as $item) {
                $old = false;
                $prizeList = $this->getDoctrine()->getRepository(PrizeList::class)->findOneBy(['id' => $item->getSetOption()]);
                if($prizeList == null) continue;
                $Prizes = $prizeList->getSettings();
                foreach($Prizes as $Prize) {
                    if($Prize->getRemain() == 0) {
                        $count_sold_out++;

                        $ProductClass->setRemainStatus(4);
                        $this->entityManager->persist($ProductClass);
                        $this->entityManager->flush();

                        $old = true;

                        break;
                    }
                }
                if($old) {
                    unset($Products[$key]);
                    break;
                }
                else {
                    $ProductClass->setRemainStatus(1);
                    $this->entityManager->persist($ProductClass);
                    $this->entityManager->flush();
                }

            }
		}
        $nico_url = $this->getAuthorize();
        return [
		'assists' => $res,
		'Products' => $Products,
		'count_sold_out' => $count_sold_out,
        'nico_url' => $nico_url
		];
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
                // $endpoint = $this->baseurl.'/v1/user/nicopoints.json';
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
        // $link = '<a href="%s" class="btn" rel="nofollow">ニコニコアカウントでログイン</a>';
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
    public function getToken(){

        $url = $this->getEndPoint('token');
        $data = array(
            'grant_type' => 'authorization_code',
            'code' => $_GET['code'],
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
		);
        $header = array(
            "Content-Type: application/x-www-form-urlencoded",
        );

        if( $response = $this->send($url, 'POST', $header, $data) ){
            $nonce = $this->checkNonce($response);
            if( is_error( $nonce ) ) {
                return $nonce;
            }

            return $response;
        }else{
            return error();
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
                return error();
            }

            $this->nico_id = $nonce->data->sub;
            $this->access_token = $code->access_token;
            $this->refresh_token = $code->refresh_token;

        }else{
            return error();
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
            return error();
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
            return error();
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
            return error();
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
            return error();
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
                return error();
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
            return error();
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
            return error();
        }
	}
    
    public function checkMember(){
        $email = $this->user->email;

        $customer = $this->getDoctrine()->getRepository(Customer::class)->findOneBy(['email' => $email]);
        
        if ($customer == null)
            return $this->registMember();
        else{
            return $this->refreshMember($customer);
        }

        // return $customer;
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
        
        $token = new UsernamePasswordToken($customer, null, 'customer', ['ROLE_USER']);
        $this->get("security.token_storage")->setToken($token);

        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            log_info('認証済のためログイン処理をスキップ');

            $last_uri = $this->get('session')->getFlashBag()->get('last_uri')[0];
            
            if($last_uri == 'mypage_login')
                return $this->redirectToRoute('mypage_change');
                
            else if($last_uri == 'shopping_login') {
                $cart_key = $this->get('session')->getFlashBag()->get('cart_key');

                return $this->redirectToRoute('shopping');
            }
        }
        return $this->redirectToRoute('homepage');    
    }

    public function registMember(){
        $customer_status = 0;
        $premium = null;
        $channel = null;
        $ticket = null;

        if( $this->premium == 'premium' ){
            $customer_status = 4;
            $premium = 1;
        }
        if( !empty($this->channel) ){
            $customer_status = 5;
            $channel = implode("|", $this->channel);
        }
        $tickets = $this->ticketIDs();
        foreach($tickets as $item) {
            if ( $this->ticket($item) ){
                $ticket = $item;
                break;
            }             
        }

        $this->addFlash('customer_status', $customer_status);
        $this->addFlash('customer_point', $this->point);
        $this->addFlash('customer_email', $this->user->email);
        $this->addFlash('customer_premium', $premium);
        $this->addFlash('customer_channel', $channel);
        $this->addFlash('customer_ticket', $ticket);

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
        if( is_error( $response ) ){
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
        if( is_error( $response ) ){
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
        if( is_error( $response ) ){
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
}
