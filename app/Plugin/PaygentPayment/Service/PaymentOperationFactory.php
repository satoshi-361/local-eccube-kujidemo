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

use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\Service\Career\CareerOperationService;
use Plugin\PaygentPayment\Service\Credit\CreditOperationService;
use Plugin\PaygentPayment\Service\Paidy\PaidyOperationService;

/**
 * ペイジェント決済管理画面用の決済のインスタンスを取得をするFactoryクラス
 */
class PaymentOperationFactory {

    /**
     * @var CreditOperationService
     */
    private $creditOperationService;

    /**
     * @var CareerOperationService
     */
    private $careerOperationService;

    /**
     * @var PaidyOperationService
     */
    private $paidyOperationService;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * コンストラクタ
     * @param CreditOperationService $creditOperationService
     * @param CareerOperationService $careerOperationService
     * @param PaidyOperationService $paidyOperationService
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        CreditOperationService $creditOperationService,
        CareerOperationService $careerOperationService,
        PaidyOperationService $paidyOperationService,
        EccubeConfig $eccubeConfig
    ) {
        $this->creditOperationService = $creditOperationService;
        $this->careerOperationService = $careerOperationService;
        $this->paidyOperationService = $paidyOperationService;
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * ペイジェント決済管理画面表示用の各決済インスタンス取得
     * @param string $paymentType 決済種別
     * @return ペイジェント各決済のインスタンス
     */
    public function getInstance($paymentType)
    {
        switch ($paymentType) {
            case $this->eccubeConfig['paygent_payment']['paygent_credit']:
                return $this->creditOperationService;
                break;
            case $this->eccubeConfig['paygent_payment']['paygent_career']:
            case $this->eccubeConfig['paygent_payment']['paygent_career_d']:
            case $this->eccubeConfig['paygent_payment']['paygent_career_a']:
            case $this->eccubeConfig['paygent_payment']['paygent_career_s']:
                return $this->careerOperationService;
                break;
            case $this->eccubeConfig['paygent_payment']['paygent_paidy']:
                return $this->paidyOperationService;
                break;
            default:
                return null;
                break;
        }
    }
}
