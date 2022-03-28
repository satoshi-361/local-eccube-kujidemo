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

namespace Plugin\PaygentPayment\Service\Credit;

use Eccube\Common\EccubeConfig;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Plugin\PaygentPayment\Service\CacheConfig;
use Plugin\PaygentPayment\Service\PaygentBaseService;

/**
 * Paygent KS-システムとの連携・EC-CUBEの決済レコード作成を行う子クラス
 */
class FrontCreditService extends PaygentBaseService
{
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var PluginRepository
     */
    protected $pluginRepository;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param ConfigRepository $configRepository
     * @param EntityManagerInterface $entityManager
     * @param CacheConfig $cacheConfig
     * @param PluginRepository $pluginRepository
     * @param EccubeConfig $eccubeConfig
     * @param UrlGeneratorInterface $router
     */
    public function __construct(
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        ConfigRepository $configRepository,
        EntityManagerInterface $entityManager,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig,
        UrlGeneratorInterface $router
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->configRepository = $configRepository;
        $this->entityManager = $entityManager;
        $this->cacheConfig = $cacheConfig;
        $this->pluginRepository = $pluginRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->router = $router;
    }

    /**
     * 新規申込
     * @param array $order          受注情報
     * @param array $inputParams    入力パラメータ(FormまたはGET)
     * @param array $telegramKind  電文種別
     * @return array $arrRes        レスポンス情報
     */
    public function applyProcess($order, $inputParams, $telegramKind)
    {
        // 電文パラメータ設定
        $params = $this->makeParam($order, $inputParams, $telegramKind);
        // 電文送信
        $arrRes = $this->callApi($params);
        // 受注情報更新
        $this->saveOrder($order, $inputParams, $arrRes);

        return $arrRes;
    }

    /**
     * 電文パラメータの設定
     * @param array $inputOrder 受注情報
     * @param array $inputParams 入力パラメータ
     * @param array $telegramKind 電文種別
     * @return array $params 電文パラメータ
     */
    function makeParam($inputOrder, $inputParams, $telegramKind) {
        // プラグイン設定情報の取得
        $config = $this->cacheConfig->getConfig();
        // URL作成用のパラメーター
        $orderId = $inputOrder->getId();
        /** 共通電文パラメータ **/
        $params = $this->commonMakeParam($inputOrder->getId(),"");
        /** 個別電文パラメータ **/
        // 電文種別ID
        $params['telegram_kind'] = $telegramKind;

        // マーチャント取引ID
        $params['trading_id'] = $orderId;
        // 決済金額
        $params['payment_amount'] = (int)$inputOrder->getPaymentTotal();
        // 支払い区分
        $params['payment_class'] = $inputParams['payment_class'];
        if (isset($inputParams['split_count'])) {
            // 分割回数
            $params['split_count'] = $inputParams['split_count'];
        }
        // 3Dセキュア利用タイプ
        $params['3dsecure_use_type'] = '1';
        // 3Dセキュア
        if ($config->getCredit3d()) {
            // HttpAccept
            if ($inputParams['http_accept']) {
                $params['http_accept'] = $inputParams['http_accept'];
            } else {
                $params['http_accept'] = "*/*";
            }
            // HttpUserAgent
            $params['http_user_agent'] = $inputParams['http_user_agent'];
            // 3-Dセキュア戻りURL
            $sUrl = $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $params['term_url'] =  $sUrl . 'paygent_payment_credit_3d?order_id=' . $orderId;
        } else {
            // 3Dセキュア不要区分
            $params['3dsecure_ryaku'] = '1';
        }

        if (isset($inputParams['card_token'])) {
            // カード情報トークン
            $params['card_token'] = $inputParams['card_token'];
        }

        if ($config->getSecurityCode()) {
            // セキュリティーコード利用
            $params['security_code_use'] = '1';
        }

        // カード情報お預かり機能
        if ($config->getModuleStockCard() && $inputParams['stock']) {
            if ($config->getSecurityCode()) {
                // セキュリティーコードトークン利用
                $params['security_code_token'] = '1';
            }

            // カード情報お預りモード
            $params['stock_card_mode'] = '1';
            // 顧客ID
            $params['customer_id'] = $inputOrder->getCustomer()->getId();
            // 顧客カードID
            $params['customer_card_id'] = $inputParams['customer_card_id'];
        }

        return $params;
    }

    /**
     * 受注テーブル更新処理
     * @param array $inputOrder 受注情報
     * @param array $inputParams 入力パラメータ
     * @param array $arrRes 電文レスポンス情報
     */
    function saveOrder($inputOrder, $inputParams, $arrRes)
    {
        $order = $this->orderRepository->find($inputOrder->getId());
        $order->setPaygentCode($this->eccubeConfig['paygent_payment']['paygent_payment_code']);
        $order->setResponseResult($arrRes['resultStatus']);
        $order->setResponseCode($arrRes['responseCode']);
        if ($arrRes['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_error']) {
            $order->setPaygentError('エラー詳細 : '. $arrRes['responseDetail'] . 'エラーコード' . $arrRes['responseCode']);
        } else {
            $order->setPaygentError(null);
        }
        $order->setPaygentPaymentMethod($this->eccubeConfig['paygent_payment']['paygent_credit']);

        if (isset($arrRes['payment_id'])) {
            $order->setPaygentPaymentId($arrRes['payment_id']);
        }

        $creditSubdata = [];

        if (isset($inputParams['payment_class'])) {
            $creditSubdata['payment_class'] = $inputParams['payment_class'];
        }
        if (isset($inputParams['split_count'])) {
            $creditSubdata['split_count'] = $inputParams['split_count'];
        }
        if (isset($inputParams['customer_card_id'])) {
            $creditSubdata['customer_card_id'] = $inputParams['customer_card_id'];
        }
        if ($creditSubdata) {
            $order->setPaygentCreditSubdata(serialize($creditSubdata));
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush($order);
    }
}