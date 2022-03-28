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
 * Paidy決済受注管理操作売上変更処理クラス
 */
class PaidyOperationChangeService extends PaidyOperationService {

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
     * Paidy売上変更処理
     */
    public function process($paygentType, $orderId, $paymentId = '', $beforeStatus = '')
    {
        $arrPaygentKind = $this->getPaygentKindForOperationAction();

        $charCode = $this->eccubeConfig['paygent_payment']['char_code'];

        // 接続モジュールのインスタンス取得 (コンストラクタ)と初期化
        $objPaygent = new PaygentB2BModule($this->eccubeConfig);
        $objPaygent->init();

        // 電文種別の設定
        $kind = $arrPaygentKind['paygent_paidy_refund'];

        // オーダーの取得
        /** @var Order $order */
        $order = $this->orderRepository->find(['id' => $orderId]);

        $status = $order->getPaygentKind();

        $arrReturn = [];

        $arrResponseDetail = unserialize($order->getResponseDetail());
        if ($arrResponseDetail['ecOrderData']['payment_total'] <= $order->getPaymentTotal()) {
            // 決済金額が増加する変更は、Paidy決済不可
            $arrReturn['kind'] = $arrPaygentKind['paygent_paidy_commit_revice'];
            $arrReturn['return'] = false;
            $arrReturn['response'] = "エラー：決済金額が変更されていないか増加しています。";
            return $arrReturn;
        }

        // 決済IDの取得
        $paymentId = $order->getPaygentPaymentId();

        // 電文パラメータ取得
        $arrSend = $this->paidyAdminRequestService->makeParam($kind, $orderId, $paymentId);

        // 返金額
        $arrSend['amount'] = (int)$arrResponseDetail['ecOrderData']['payment_total'] - (int)$order->getPaymentTotal();

        // 電文送信
        $arrRes = $this->paidyAdminRequestService->sendRequest($objPaygent, $arrSend, $charCode);

        $arrReturn['kind'] = $kind;
        $arrVal = [];

        // 正常終了
        if($arrRes[0]['result'] === $this->eccubeConfig['paygent_payment']['result_success']) {
            $arrVal['paygent_payment_id'] = $arrRes[0]['payment_id'];
            $arrVal['paygent_error'] = '';
            $arrReturn['return'] = true;
            // ['ecOrderData']['payment_total']の金額を更新する
            $this->updateOrderChangePaygent($orderId);

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

    /**
     * 受注情報内のPaidy決済金額の更新
     * @param  integer      $orderId     注文番号
     * @return void
     */
    private function updateOrderChangePaygent($orderId)
    {
        $arrOrder = $this->orderRepository->findBy(['id' => $orderId]);
        $order = $arrOrder[0];

        $arrResponseDetail = unserialize($order->getResponseDetail());

        $arrResponseDetail['ecOrderData']['payment_total'] = $order->getPaymentTotal();

        $order->setResponseDetail(serialize($arrResponseDetail));

        $this->entityManager->persist($order);
        $this->entityManager->flush($order);
    }
}
