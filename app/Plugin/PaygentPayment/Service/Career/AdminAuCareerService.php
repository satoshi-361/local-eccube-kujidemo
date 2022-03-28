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
use Plugin\PaygentPayment\Service\PaygentBaseService;

class AdminAuCareerService extends PaygentBaseService {

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var CareerOperationService
     */
    protected $careerOperationService;

    /**
     * @var CareerOperationFactory
     */
    protected $careerOperationFactory;

    /**
     * コンストラクタ
     * @param EccubeConfig $eccubeConfig
     * @param CareerOperationService $careerOperationService
     * @param CareerOperationFactory $careerOperationFactory
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        CareerOperationService $careerOperationService,
        CareerOperationFactory $careerOperationFactory
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->careerOperationService = $careerOperationService;
        $this->careerOperationFactory = $careerOperationFactory;
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
        $careerOperationInstance = $this->careerOperationFactory->getInstance($paygentType);
        $arrReturn = $careerOperationInstance->process($paygentType, $orderId);
        $arrReturn['type'] = $careerOperationInstance->getOperationName($paygentType);

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

        $paygentFlags['isCareer'] = true;

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
        $paygentPaymentStatus = $order->getPaygentPaymentStatus();
        $paygentPaymentKind = $order->getPaygentKind();
        $paymentMethod = $order->getPaygentPaymentMethod();

        $arrPaygentStatus = $this->getPaygentStatusForEdit();

        $paygentFlags['commit'] = false;

        if ($paygentPaymentKind != null) {
            return $paygentFlags;
        }

        if ($paymentMethod == $this->eccubeConfig['paygent_payment']['paygent_career_a']) {
            if ($paygentPaymentStatus == $arrPaygentStatus['status_authority_ok']) {
                $paygentFlags['commit'] = 'career_commit';
            }
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
        $paygentPaymentStatus = $order->getPaygentPaymentStatus();
        $paygentPaymentKind = $order->getPaygentKind();
        $paymentMethod = $order->getPaygentPaymentMethod();

        $arrPaygentKind = $this->getPaygentKindForEdit();
        $arrPaygentStatus = $this->getPaygentStatusForEdit();

        $paygentFlags['cancel'] = false;
        if ($paymentMethod == $this->eccubeConfig['paygent_payment']['paygent_career_a']) {
            if (($paygentPaymentStatus == $arrPaygentStatus['status_authority_ok'] and in_array($paygentPaymentKind, [$arrPaygentKind['paygent_career_commit'], $arrPaygentKind['paygent_career_commit_revice'], null], true))
                or ($paygentPaymentStatus == $arrPaygentStatus['status_pre_cleared'] and in_array($paygentPaymentKind, [$arrPaygentKind['paygent_career_commit'], $arrPaygentKind['paygent_career_commit_revice']], true))) {
                $paygentFlags['cancel'] = 'career_commit_cancel';
            }
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
        $paygentPaymentStatus = $order->getPaygentPaymentStatus();
        $paygentPaymentKind = $order->getPaygentKind();
        $paygentPaymentId = $order->getPaygentPaymentId();
        $paymentMethod = $order->getPaygentPaymentMethod();

        $arrPaygentStatus = $this->getPaygentStatusForEdit();

        $paygentFlags['change'] = false;

        if (is_null($paygentPaymentId)) {
            return $paygentFlags;
        }

        if ($paymentMethod == $this->eccubeConfig['paygent_payment']['paygent_career_a']) {
            if ($paygentPaymentKind == null and $paygentPaymentStatus == $arrPaygentStatus['status_authority_ok']) {
                $paygentFlags['change'] = 'change_career_auth';
            }
        }

        return $paygentFlags;
    }
}