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

namespace Plugin\PaygentPayment\Service\Paidy;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PluginRepository;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system\PaygentB2BModule;
use Plugin\PaygentPayment\Service\CacheConfig;

/**
 * Paidy決済受注管理操作取消処理クラス
 */
class PaidyOperationCancelService extends PaidyOperationService {

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var PluginRepository
     */
    protected $pluginRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var PaidyAdminRequestService
     */
    protected $paidyAdminRequestService;

    /**
     * コンストラクタ
     * @param EccubeConfig $eccubeConfig
     * @param OrderRepository $orderRepository
     * @param CacheConfig $cacheConfig
     * @param PluginRepository $pluginRepository
     * @param EntityManagerInterface $entityManager
     * @param PaidyAdminRequestService $paidyAdminRequestService
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        OrderRepository $orderRepository,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EntityManagerInterface $entityManager,
        PaidyAdminRequestService $paidyAdminRequestService
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->orderRepository = $orderRepository;
        $this->cacheConfig = $cacheConfig;
        $this->pluginRepository = $pluginRepository;
        $this->entityManager = $entityManager;
        $this->paidyAdminRequestService = $paidyAdminRequestService;
    }

    /**
     * Paidy取消処理
     */
    public function process($paygentType, $orderId, $paymentId = '', $beforeStatus = '')
    {
        $arrPaygentKind = $this->getPaygentKindForOperationAction();

        $charCode = $this->eccubeConfig['paygent_payment']['char_code'];

        // 接続モジュールのインスタンス取得 (コンストラクタ)と初期化
        $objPaygent = new PaygentB2BModule($this->eccubeConfig);
        $objPaygent->init();

        // オーダーの取得
        /** @var Order $order */
        $order = $this->orderRepository->find(['id' => $orderId]);

        $status = $order->getPaygentKind();

        // 電文種別の設定
        if($status == $arrPaygentKind['paygent_paidy_authorized']) {
            $kind = $arrPaygentKind['paygent_paidy_auth_canceled'];
        } else if ($status == $arrPaygentKind['paygent_paidy_commit']) {
            $kind = $arrPaygentKind['paygent_paidy_refund'];
        }

        // 決済IDの取得
        $paymentId = $order->getPaygentPaymentId();

        // 電文パラメータ取得
        $arrSend = $this->paidyAdminRequestService->makeParam($kind, $orderId, $paymentId);

        // 電文送信
        $arrRes = $this->paidyAdminRequestService->sendRequest($objPaygent, $arrSend, $charCode);

        $arrReturn = [];
        if ($kind === $arrPaygentKind['paygent_paidy_refund']) {
            // 売上取消の場合ETC_4を設定
            $arrReturn['kind'] = $arrPaygentKind['paygent_paidy_commit_canceled'];
        } else {
            $arrReturn['kind'] = $kind;
        }
        $arrVal = [];

        // 正常終了
        if($arrRes[0]['result'] === $this->eccubeConfig['paygent_payment']['result_success']) {
            if ($kind === $arrPaygentKind['paygent_paidy_refund']) {
                // 売上取消の場合ETC_4を設定
                $arrVal['paygent_kind'] = $arrPaygentKind['paygent_paidy_commit_canceled'];
            } else {
                $arrVal['paygent_kind'] = $kind;
            }

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
