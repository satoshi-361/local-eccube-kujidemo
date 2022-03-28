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

namespace Plugin\PaygentPayment\Service;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Common\Constant;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Service\CartService;
use Plugin\PaygentPayment\Service\Error\ErrorDetailMsg;
use GuzzleHttp\Client;

/**
 * フロントの決済処理を行うクラス.
 */
class PaygentRequestService
{
    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var PluginRepository
     */
    protected $pluginRepository;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var ErrorDetailMsg
     */
    private $errorDetailMsg;

    private $error = [];
    private $errorMsg;

    /**
     * コンストラクタ
     * @param OrderStatusRepository $orderStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param UrlGeneratorInterface $router
     * @param SessionInterface $session
     * @param OrderRepository $orderRepository
     * @param CartService $cartService
     * @param EntityManagerInterface $entityManager
     * @param PluginRepository $pluginRepository
     * @param EccubeConfig $eccubeConfig
     * @param ErrorDetailMsg $errorDetailMsg
     */
    public function __construct(
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        UrlGeneratorInterface $router,
        SessionInterface $session,
        OrderRepository $orderRepository,
        CartService $cartService,
        EntityManagerInterface $entityManager,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig,
        ErrorDetailMsg $errorDetailMsg
        ) {
            $this->orderStatusRepository = $orderStatusRepository;
            $this->purchaseFlow = $shoppingPurchaseFlow;
            $this->router = $router;
            $this->session = $session;
            $this->orderRepository = $orderRepository;
            $this->cartService = $cartService;
            $this->entityManager = $entityManager;
            $this->pluginRepository = $pluginRepository;
            $this->eccubeConfig = $eccubeConfig;
            $this->errorDetailMsg = $errorDetailMsg;
        }

    /**
     * リクエストパラメータを設定
     *
     * @param \Eccube\Entity\Order $order
     * @param $config プラグイン設定値
     * @param $paymentMethod 決済方法
     * @return array
     */
    function setParameter($order, $config, $paymentMethod)
    {
        // マーチャント取引ID
        $params['trading_id'] = $order->getId();
        // 決済種別
        $params['payment_type'] = $paymentMethod;
        // 固定
        $params['fix_params'] = "customer_family_name,customer_name,customer_family_name_kana,customer_name_kana,customer_tel";
        // 決済金額
        $params['id'] = $order->getPaymentTotal();
        // マーチャントID
        $params['seq_merchant_id'] = $config->getMerchantId();
        // 支払期間
        $params['payment_term_day'] = $config->getLinkPaymentTerm();
        // 支払区分
        $params['payment_class'] = $config->getCardClass();
        // カード確認番号利用フラグ
        $params['use_card_conf_number'] = $config->getCardConf();

        // 会員の場合
        if($order->getCustomer()){
            // カード情報お預かりモード
            $params['stock_card_mode'] = $config->getStockCard();
            // 顧客ID
            $params['customer_id'] = $order->getCustomer()->getId();
        }
        // ハッシュ値
        if (strlen($config->getHashKey()) > 0) {
            $params['hc'] = $this->setPaygentHash($params, $config->getHashKey());
        }
        // マーチャント名
        $params['merchant_name'] = $config->getMerchantName();
        // コピーライト
        $params['copy_right'] = $config->getLinkCopyRight();
        // 自由メモ欄
        $params['free_memo'] = $config->getLinkFreeMemo();

        // 利用者姓
        $params['customer_family_name'] = mb_convert_kana($order->getName01(), 'KVA');
        // 利用者名
        $params['customer_name'] = mb_convert_kana($order->getName02(), 'KVA');
        // 利用者姓半角カナ
        $params['customer_family_name_kana'] = mb_convert_kana($order->getKana01(),'k');
        $params['customer_family_name_kana'] = preg_replace("/ｰ/", "-", $params['customer_family_name_kana']);
        // 利用者名半角カナ
        $params['customer_name_kana'] = mb_convert_kana($order->getKana02(),'k');
        $params['customer_name_kana'] = preg_replace("/ｰ/", "-", $params['customer_name_kana']);
        // 利用者電話番号
        $params['customer_tel'] = $order->getPhoneNumber();

        // 戻りURL
        $orderId = $order->getId();
        if ($config->getReturnUrl()) {
            $params['return_url'] = $config->getReturnUrl();
        } else {
            $params['return_url'] = $this->router->generate('paygent_payment_shopping_complete', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?order_id=' . $orderId;
        }
        // 処理中断時戻りURL
        $params['stop_return_url'] = $this->router->generate('paygent_payment_recovery_cart', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?order_id=' . $orderId;

        // カード決済エラー時の再決済区分
        $params['re_payment_type'] = 1;

        // 連携モード
        $params['isbtob'] = 1;

        // 決済モジュール識別
        $params['partner'] = 'lockon';
        // EC-CUBE本体のバージョン
        $params['eccube_version'] = Constant::VERSION;
        // 決済プラグインのバージョン
        $plugin = $this->pluginRepository->findOneBy(["code" => $this->eccubeConfig['paygent_payment']['paygent_payment_code']]);
        $params['eccube_plugin_version'] = $plugin->getVersion();

        return $params;
    }

    function setPaygentHash($arrSend, $hashKey) {
        // create hash hex string
        $default = [
                'payment_class'=>'',
                'hash_key'=>$hashKey,
                'paygent_mark'=>'paygent2006',
                'trading_id'=>'',
                'id'=>'',
                'payment_type'=>'',
                'seq_merchant_id'=>'',
                'payment_term_day'=>'',
                'use_card_conf_number'=>'',
                'fix_params'=>'',
                'inform_url'=>'',
                'payment_term_min'=>'',
                'customer_id'=>'',
                'threedsecure_ryaku'=>'',
        ];
        $orgStr = '';
        foreach ($default as $key=>$value) {
            $orgStr .= isset($arrSend[$key]) ? $arrSend[$key]:$value;
        }
        if (function_exists("hash")) {
            $hashStr = hash("sha256", $orgStr);
        } elseif (function_exists("mhash")) {
            $hashStr = bin2hex(mhash(MHASH_SHA256, $orgStr));
        } else {
            return;
        }

        // create random string
        $randStr="";
        $randChar = ['a','b','c','d','e','f','A','B','C','D','E','F','0','1','2','3','4','5','6','7','8','9'];
        for ($i = 0; ($i < 20 && rand(1,10) != 10); $i++) {
            $randStr .= $randChar[rand(0, count($randChar)-1)];
        }

        return $hashStr. $randStr;
    }

    function sendRequest($url, $dataSend)
    {
        $listData = [];
        foreach ($dataSend as $key => $value) {
            $listData[$key] = $value;
        }

        $config = ['curl' => [
                CURLOPT_SSLVERSION => 6
            ]
        ];
        $client = new Client($config);

        $response = $client->post($url, [
                        'form_params' => $listData
                    ]);

        $rCode = $response->getStatusCode();

        switch ($rCode) {
            case 200:
                break;
            case 404:
                $msg = 'レスポンスエラー:RCODE:' . $rCode;
                $this->setError($msg);
                return false;
                break;
            case 500:
            default:
                $msg = '決済サーバーエラー:RCODE:' . $rCode;
                $this->setError($msg);
                return false;
                break;
        }

        $responseBody = $response->getBody(true);

        if (is_null($responseBody)) {
            $msg = 'レスポンスデータエラー: レスポンスがありません。';
            $this->setError($msg);
            return false;
        }


        $arrRet = $this->parseResponse($responseBody);
        if ($this->error) {
            return $this->getError();
        }
        return $arrRet;
    }

    /**
     * レスポンスを解析する
     *
     * @param string $string レスポンス
     * @return array 解析結果
     */
    function parseResponse($string)
    {
        $str = str_replace(["\r\n","\r","\n"], "\n", $string);
        $string = explode("\n", $str);
        // $logtext = "\n************ Response start ************";
        foreach ($string as $line) {
            $item = explode("=", $line, 2);
            if (strlen($item[0]) > 0) {
                if(isset($item[1])){
                    $res[$item[0]] = $item[1];
                }
            }
        }
        return $res;
    }

    function setError($msg)
    {
        $this->error[] = $msg;
    }

    function getError()
    {

        return $this->error;
    }

    /**
     * 受注＆ページ遷移
     */
    function linkPaygentPage($dispatcher, $arrRet, $paymentMethod, $order) {
        // 成功
        if ($arrRet['result'] === $this->eccubeConfig['paygent_payment']['result_success']) {
            // 受注ステータスを決済処理中へ変更
            $orderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
            $order->setOrderStatus($orderStatus);

            // 受注登録
            $arrMemo['title'] = $this->sfSetConvMSG("お支払", true);
            $arrMemo['payment_url'] = $this->sfSetConvMSG("お支払画面URL", $arrRet['url']);
            $year = substr($arrRet['limit_date'], 0, 4);
            $month = substr($arrRet['limit_date'], 4, 2);
            $day = substr($arrRet['limit_date'], 6, 2);
            $hour = substr($arrRet['limit_date'], 8, 2);
            $minute = substr($arrRet['limit_date'], 10, 2);
            $second = substr($arrRet['limit_date'], 12);
            $arrMemo['limit_date'] = $this->sfSetConvMSG("お支払期限", "$year/$month/$day $hour:$minute:$second");

            $order->setPaygentCode($this->eccubeConfig['paygent_payment']['paygent_payment_code']);
            $order->setResponseDetail(serialize($arrMemo));
            $order->setResponseResult($arrRet['result']);
            $order->setPaygentPaymentMethod($paymentMethod);

            $this->purchaseFlow->prepare($order, new PurchaseContext());

            // カートを削除する
            $this->cartService->clear();

            $this->entityManager->flush();

            $url = $arrRet['url'];

            logs('paygent_payment')->info("PAYGENT決済画面へ遷移します");

            $response = new RedirectResponse($url);
            $dispatcher->setResponse($response);
            // 失敗
        } elseif ($arrRet['result'] === $this->eccubeConfig['paygent_payment']['result_error']) {
            logs('paygent_payment')->error("エラーが発生しました。");
            $this->errorMsg .= "決済に失敗しました。";

            if (preg_match('/^[P|E]/', $arrRet['response_code']) <= 0) {
                $this->errorMsg .= "<br />". $arrRet['response_detail']. "（". $arrRet['response_code']. "）";
            } else {
                $this->errorMsg .= "（". $arrRet['response_code']. "）";

                $strErrorDetailMsg = $this->errorDetailMsg->getErrorDetailMsg($arrRet['response_code'], $arrRet['response_detail']);
                $this->errorMsg .= "<br>" . $strErrorDetailMsg;

                if ($strErrorDetailMsg != $this->eccubeConfig['paygent_payment']['no_mapping_message']) {
                    if($order->getCustomer()){
                        $this->errorMsg .= "<br>" . $this->eccubeConfig['paygent_payment']['telegram_error_member'];
                    } else {
                        $this->errorMsg .= "<br>" . $this->eccubeConfig['paygent_payment']['telegram_error_guest'];
                    }
                }
            }
            logs('paygent_payment')->error("ERROR_CODE => ". $arrRet['response_code']);
            logs('paygent_payment')->error("ERROR_DETAIL => ".$arrRet['response_detail']);
            logs('paygent_payment')->error("ERROR_MESSAGE => ".$this->errorMsg);

            $this->session->set('paygent_payment.shopping_error', $this->errorMsg);
            $url = $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL)."shopping/paygent_error";

            $response = new RedirectResponse($url);
            $dispatcher->setResponse($response);
            // 通信エラー
        } else {
            logs('paygent_payment')->error("エラーが発生しました。");
            $this->errorMsg = "決済に失敗しました。<br />". $arrRet;

            logs('paygent_payment')->error("ERROR_MESSAGE => ".$this->errorMsg);

            $this->session->set('paygent_payment.shopping_error', $this->errorMsg);
            $url = $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL)."shopping/paygent_error";

            $response = new RedirectResponse($url);
            $dispatcher->setResponse($response);
        }
        return $dispatcher;
    }

    /**
     * 関数名：sfSetConvMSG
     * 処理内容：コンビニ情報表示用
     * 戻り値：取得結果
     */
    function sfSetConvMSG($name, $value){
        return ["name" => $name, "value" => $value];
    }
}
