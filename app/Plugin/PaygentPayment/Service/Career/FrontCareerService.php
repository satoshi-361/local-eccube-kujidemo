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

namespace Plugin\PaygentPayment\Service\Career;

use Eccube\Common\EccubeConfig;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\PaygentPayment\Service\CacheConfig;
use Plugin\PaygentPayment\Service\PaygentBaseService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Paygent KS-システムとの連携・EC-CUBEの決済レコード作成を行う子クラス
 */
class FrontCareerService extends PaygentBaseService {
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
     * @var CareerTypeService
     */
    private $careerTypeService;

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
     * @param CareerTypeService $careerTypeService
     */
    public function __construct(
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        ConfigRepository $configRepository,
        EntityManagerInterface $entityManager,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig,
        UrlGeneratorInterface $router,
        CareerTypeService $careerTypeService
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->configRepository = $configRepository;
        $this->entityManager = $entityManager;
        $this->cacheConfig = $cacheConfig;
        $this->pluginRepository = $pluginRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->router = $router;
        $this->careerTypeService = $careerTypeService;
    }

    /**
     * 新規申込
     * @param array $order          受注情報
     * @param array $inputParams    入力パラメータ(FormまたはGET)
     * @param array $telegramKind  電文種別
     * @return array $arreRes       レスポンス情報
     */
    public function applyProcess($order, $inputParams, $telegramKind)
    {
        // 電文パラメータ設定
        $params = $this->makeParam($order, $inputParams, $telegramKind);
        // 電文送信
        $arrRes = $this->callApi($params);
        // 受注情報更新
        $this->saveOrder($order, $inputParams, $telegramKind, $arrRes);
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
        // URL作成用のパラメーター
        $orderId = $inputOrder->getId();
        $sUrl = $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);

        /** 共通電文パラメータ **/
        $params = $this->commonMakeParam($inputOrder->getId(), "");
        /** 個別電文パラメータ **/
        // 電文種別ID
        $params['telegram_kind'] = $telegramKind;
        // PC/Mobile区分
        $params['pc_mobile_type'] = $this->eccubeConfig['paygent_payment']['career_pc_mobile_type']['pc'];
        // キャリア種別
        $params['career_type'] = $inputParams['career_type'];

        /** 認証電文 **/
        if ($telegramKind == $this->eccubeConfig['paygent_payment']['paygent_career_commit_auth']) {
            // 認証OKURL
            $params['redirect_url'] = $sUrl . 'paygent_payment_career_apply' . '?order_id=' . $orderId;
            // 認証NGURL
            $params['cancel_url'] = $sUrl . 'paygent_payment_career_auth_ng' . '?order_id=' . $orderId;

        /** 申込電文 **/
        } elseif ($telegramKind == $this->eccubeConfig['paygent_payment']['paygent_career']) {
            // 決済金額
            $params['amount'] = (int)$inputOrder->getPaymentTotal();
            // オーソリ通知URL
            $params['return_url'] = $sUrl . 'paygent_payment_career_complete';
            // キャンセルURL
            $params['cancel_url'] = $sUrl . 'paygent_payment_career_cancel' . '?order_id=' . $orderId;
            if ($inputParams['career_type'] == $this->eccubeConfig['paygent_payment']['career_division_id']['career_type_docomo']) {
                // 他決済用URL
                $params['other_url'] = $params['cancel_url'];
            }
            // OpenID
            if (isset($inputParams['open_id'])) {
                $params['open_id'] = $inputParams['open_id'];
            }
        }

        return $params;
    }

    /**
     * 受注テーブル更新処理
     * @param array $inputOrder 受注情報
     * @param array $inputParams 入力パラメータ
     * @param array $arrRes 電文レスポンス情報
     */
    function saveOrder($inputOrder, $inputParams, $telegramKind, $arrRes)
    {
        $order = $this->orderRepository->find($inputOrder->getId());
        $order->setPaygentCode($this->eccubeConfig['paygent_payment']['paygent_payment_code']);
        $order->setResponseResult($arrRes['resultStatus']);
        $order->setResponseCode($arrRes['responseCode']);
        if ($arrRes['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_error']) {
            $order->setPaygentError('エラー詳細 : '. $arrRes['responseDetail'] . 'エラーコード' . $arrRes['responseCode']);
        }

        // paygent_methodの設定
        if ($telegramKind == $this->eccubeConfig['paygent_payment']['paygent_career_commit_auth']) {
            switch ($inputParams['career_type']) {
                case $this->eccubeConfig['paygent_payment']['career_division_id']['career_type_docomo']:
                    $paygentMethod = $this->eccubeConfig['paygent_payment']['paygent_career_auth_d'];
                    break;
                case $this->eccubeConfig['paygent_payment']['career_division_id']['career_type_au']:
                    $paygentMethod = $this->eccubeConfig['paygent_payment']['paygent_career_auth_a'];
                    break;
            }
            $order->setPaygentPaymentMethod($paygentMethod);

        // 申込電文
        } elseif ($telegramKind == $this->eccubeConfig['paygent_payment']['paygent_career']) {
            $order->setResponseDetail($arrRes['responseDetail']);
            $order->setPaygentPaymentId($arrRes['payment_id']);

            // paygent_methodの設定
            $paygentMethod = $this->careerTypeService->getPaygentMethodByCareerType($inputParams['career_type']);
            $order->setPaygentPaymentMethod($paygentMethod);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush($order);
    }
}
