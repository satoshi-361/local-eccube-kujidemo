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

use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\Service\PaygentBaseService;

class AdminPaidyService extends PaygentBaseService {

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var PaidyOperationService
     */
    protected $paidyOperationService;

    /**
     * @var PaidyOperationFactory
     */
    protected $paidyOperationFactory;

    /**
     * コンストラクタ
     * @param EccubeConfig $eccubeConfig
     * @param PaidyOperationService $paidyOperationService
     * @param PaidyOperationFactory $paidyOperationFactory
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        PaidyOperationService $paidyOperationService,
        PaidyOperationFactory $paidyOperationFactory
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->paidyOperationService = $paidyOperationService;
        $this->paidyOperationFactory = $paidyOperationFactory;
    }

    /**
     * 受注編集画面内での売上、取消、売上変更、分岐処理
     * @param $paygentType
     * @param $orderId
     * @return array
     */
    function process($paygentType, $orderId)
    {
        $arrReturn = null;
        $paidyOperationInstance = $this->paidyOperationFactory->getInstance($paygentType);
        $arrReturn = $paidyOperationInstance->process($paygentType, $orderId);
        $arrReturn['type'] = $paidyOperationInstance->getOperationName($paygentType);

        return $arrReturn;
    }

    /**
     * 受注編集画面内でのフラグの管理
     *
     * @param $order
     * @return array
     */
    function getPaygentFlags($order)
    {
        $paygentFlags = [];

        $paygentFlags['isPaidy'] = true;

        $paygentFlags = $this->setCommitFlags($paygentFlags, $order);
        $paygentFlags = $this->setCancelFlags($paygentFlags, $order);
        $paygentFlags = $this->setChangeFlags($paygentFlags, $order);

        return $paygentFlags;
    }

    /**
     * 受注編集画面内でのcommitフラグのセット
     *
     * @param $paygentFlags
     * @param $order
     * @return array
     */
    private function setCommitFlags($paygentFlags, $order)
    {
        $paygentFlags['commit'] = false;

        $paygentPaymentKind = $order->getPaygentKind();

        if ($paygentPaymentKind == $this->eccubeConfig['paygent_payment']['paygent_paidy_authorized']) {
            $paygentFlags['commit'] = 'paidy_commit';
        }

        return $paygentFlags;
    }

    /**
     * 受注編集画面内でのcancelフラグのセット
     *
     * @param $paygentFlags
     * @param $order
     * @return array
     */
    private function setCancelFlags($paygentFlags, $order)
    {
        $paygentFlags['cancel'] = false;

        $paygentPaymentKind = $order->getPaygentKind();

        if (in_array($paygentPaymentKind, [$this->eccubeConfig['paygent_payment']['paygent_paidy_authorized'], $this->eccubeConfig['paygent_payment']['paygent_paidy_commit']], true)) {
            $paygentFlags['cancel'] = 'paidy_cancel';
        }

        return $paygentFlags;
    }

    /**
     * 受注編集画面内でのchangeフラグのセット
     *
     * @param $paygentFlags
     * @param $order
     * @return array
     */
    private function setChangeFlags($paygentFlags, $order)
    {
        $paygentFlags['change'] = false;

        $paygentPaymentKind = $order->getPaygentKind();

        if ($paygentPaymentKind == $this->eccubeConfig['paygent_payment']['paygent_paidy_commit']) {
            $paygentFlags['change'] = 'change_paidy';
        }

        return $paygentFlags;
    }
}