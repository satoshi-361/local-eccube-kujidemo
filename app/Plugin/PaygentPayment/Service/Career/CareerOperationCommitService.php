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

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PluginRepository;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system\PaygentB2BModule;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Plugin\PaygentPayment\Service\CacheConfig;

/**
 * 携帯キャリア決済受注管理操作売上処理クラス
 */
class CareerOperationCommitService extends CareerOperationService {

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

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
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param EntityManagerInterface $entityManager
     * @param CacheConfig $cacheConfig
     * @param PluginRepository $pluginRepository
     * @param EccubeConfig $eccubeConfig
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig,
        ConfigRepository $configRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->entityManager = $entityManager;
        $this->cacheConfig = $cacheConfig;
        $this->pluginRepository = $pluginRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->configRepository = $configRepository;
    }

    /**
     * 携帯キャリア売上処理
     */
    public function process($paygentType, $orderId = '', $paymentId = '', $beforeStatus = '')
    {
        // 売上計上
        $arrPaygentKind = $this->getPaygentKindForOperationAction();

        $charCode = $this->eccubeConfig['paygent_payment']['char_code'];

        // 接続モジュールのインスタンス取得 (コンストラクタ)と初期化
        $objPaygent = new PaygentB2BModule($this->eccubeConfig);
        $objPaygent->init();

        $kind = $arrPaygentKind['paygent_career_commit'];

        // ペイジェントステータスのチェック
        /** @var Order $order */
        $order = $this->orderRepository->findBy(['id' => $orderId]);

        $status = $order[0]->getPaygentKind();

        // 決済IDの取得
        $paymentId = $order[0]->getPaygentPaymentId();

        // 電文パラメータ取得
        $arrSend = $this->makeParam($kind, $orderId, $paymentId);

        // 電文送信
        $arrRes = $this->sendRequest($objPaygent, $arrSend, $charCode);

        $arrReturn = [];
        $arrReturn['kind'] = $kind;
        $arrVal = [];

        // 正常終了
        if($arrRes[0]['result'] === $this->eccubeConfig['paygent_payment']['result_success']) {
            $arrVal['paygent_kind'] = $kind;
            $arrVal['paygent_payment_id'] = $arrRes[0]['payment_id'];
            $arrVal['paygent_error'] = '';
            $arrReturn['return'] = true;
        } else {
            $arrReturn['return'] = false;
            $arrVal['paygent_kind'] = $status;

            $arrErrorMessage = $this->getArrErrorMessage($objPaygent, $charCode);
            $arrVal['paygent_error'] = $arrErrorMessage['paygent_error'];
            $arrReturn['response'] = $arrErrorMessage['response'];
        }

        $this->updateOrderPayment($orderId, $arrVal);
        return $arrReturn;
    }
}
