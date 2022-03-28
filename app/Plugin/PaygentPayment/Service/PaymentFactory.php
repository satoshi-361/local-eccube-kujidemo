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
use Plugin\PaygentPayment\Service\Atm\AtmService;
use Plugin\PaygentPayment\Service\Bank\BankService;
use Plugin\PaygentPayment\Service\Career\CareerService;
use Plugin\PaygentPayment\Service\Conveni\ConveniService;
use Plugin\PaygentPayment\Service\Credit\CreditService;
use Plugin\PaygentPayment\Service\Paidy\PaidyService;

/**
 * ペイジェントの各決済のインスタンスを取得をするFactoryクラス
 */
class PaymentFactory {

    /**
     * @var AtmService
     */
    private $atmService;
    /**
     * @var CreditService
     */
    private $creditService;
    /**
     * @var BankService
     */
    private $bankService;
    /**
     * @var ConveniService
     */
    private $conveniService;
    /**
     * @var CareerService
     */
    private $careerService;
    /**
     * @var PaidyService
     */
    private $paidyService;
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * コンストラクタ
     * @param AtmService $atmService
     * @param CreditService $creditService
     * @param BankService $bankService
     * @param ConveniService $conveniService
     * @param CareerService $careerService
     * @param PaidyService $paidyService
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        AtmService $atmService,
        CreditService $creditService,
        BankService $bankService,
        ConveniService $conveniService,
        CareerService $careerService,
        PaidyService $paidyService,
        EccubeConfig $eccubeConfig
        ) {
            $this->atmService = $atmService;
            $this->creditService = $creditService;
            $this->bankService = $bankService;
            $this->conveniService = $conveniService;
            $this->careerService = $careerService;
            $this->paidyService = $paidyService;
            $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * ペイジェント各決済のインスタンス取得
     * @param string $paymentType 決済種別
     * @return ペイジェント各決済のインスタンス
     */
    public function getInstance($paymentType) {
        switch($paymentType) {
            case $this->eccubeConfig['paygent_payment']['payment_type_atm']:
            case $this->eccubeConfig['paygent_payment']['paygent_atm']:
                return $this->atmService;
                break;
            case $this->eccubeConfig['paygent_payment']['payment_type_credit']:
            case $this->eccubeConfig['paygent_payment']['paygent_credit']:
                return $this->creditService;
                break;
            case $this->eccubeConfig['paygent_payment']['payment_type_bank']:
            case $this->eccubeConfig['paygent_payment']['paygent_bank']:
                return $this->bankService;
                break;
            case $this->eccubeConfig['paygent_payment']['payment_type_conveni_num']:
            case $this->eccubeConfig['paygent_payment']['paygent_conveni_num']:
                return $this->conveniService;
                break;
            case $this->eccubeConfig['paygent_payment']['payment_type_career']:
            case $this->eccubeConfig['paygent_payment']['paygent_career']:
            case $this->eccubeConfig['paygent_payment']['paygent_career_d']:
            case $this->eccubeConfig['paygent_payment']['paygent_career_a']:
            case $this->eccubeConfig['paygent_payment']['paygent_career_s']:
            case $this->eccubeConfig['paygent_payment']['paygent_career_auth_d']:
            case $this->eccubeConfig['paygent_payment']['paygent_career_auth_a']:
                return $this->careerService;
                break;
            case $this->eccubeConfig['paygent_payment']['payment_type_paidy']:
            case $this->eccubeConfig['paygent_payment']['paygent_paidy']:
                return $this->paidyService;
                break;
            default:
                return null;
                break;
        }
    }
}
