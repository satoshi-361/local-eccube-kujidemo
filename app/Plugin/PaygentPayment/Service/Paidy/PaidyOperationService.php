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

use Eccube\Repository\OrderRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Common\EccubeConfig;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\PaygentPayment\Service\CacheConfig;
use Plugin\PaygentPayment\Service\PaygentBaseService;

/**
 * Paidy決済受注管理操作クラス
 */
class PaidyOperationService extends PaygentBaseService {

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
     * @var PaidyOperationFactory
     */
    protected $paidyOperationFactory;

    /**
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param EntityManagerInterface $entityManager
     * @param CacheConfig $cacheConfig
     * @param PluginRepository $pluginRepository
     * @param EccubeConfig $eccubeConfig
     * @param PaidyOperationFactory $paidyOperationFactory
     */
    public function __construct 
    (
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig,
        PaidyOperationFactory $paidyOperationFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->entityManager = $entityManager;
        $this->cacheConfig = $cacheConfig;
        $this->pluginRepository = $pluginRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->paidyOperationFactory = $paidyOperationFactory;
    }

    /**
     * 決済受注管理操作の処理で使用するpaygent_kindの値を配列化して取得
     */
    protected function getPaygentKindForOperationAction(){
        return [
            'paygent_paidy_auth_canceled' => $this->eccubeConfig['paygent_payment']['paygent_paidy_auth_canceled'],
            'paygent_paidy_commit' => $this->eccubeConfig['paygent_payment']['paygent_paidy_commit'],
            'paygent_paidy_refund' => $this->eccubeConfig['paygent_payment']['paygent_paidy_refund'],
            'paygent_paidy_authorized' => $this->eccubeConfig['paygent_payment']['paygent_paidy_authorized'],
            'paygent_paidy_auth_expired' => $this->eccubeConfig['paygent_payment']['paygent_paidy_auth_expired'],
            'paygent_paidy_commit_expired' => $this->eccubeConfig['paygent_payment']['paygent_paidy_commit_expired'],
            'paygent_paidy_commit_canceled' => $this->eccubeConfig['paygent_payment']['paygent_paidy_commit_canceled'],
            'paygent_paidy_commit_revice' => $this->eccubeConfig['paygent_payment']['paygent_paidy_commit_revice'],
        ];
    }

    public function getOperationName($paygentType)
    {
        switch ($paygentType) {
            case 'paidy_commit':
                return '売上';
                break;

            case 'change_paidy':
                return '売上変更';
                break;

            case 'paidy_cancel':
                return '取消';
                break;
        }
    }

    
    function process($paygentType, $orderId) {
        $paidyOperationInstance = $this->paidyOperationFactory->getInstance($paygentType);
        $arrReturn = $paidyOperationInstance->process($paygentType, $orderId);

        return $arrReturn;
    }
}
