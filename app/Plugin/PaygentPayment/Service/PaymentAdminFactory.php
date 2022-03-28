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
use Plugin\PaygentPayment\Service\Career\AdminAuCareerService;
use Plugin\PaygentPayment\Service\Career\AdminDocomoCareerService;
use Plugin\PaygentPayment\Service\Career\AdminSoftbankCareerService;
use Plugin\PaygentPayment\Service\Credit\AdminCreditService;
use Plugin\PaygentPayment\Service\Paidy\AdminPaidyService;

/**
 * ペイジェント受注編集画面用の決済のインスタンスを取得をするFactoryクラス
 */
class PaymentAdminFactory {

    /**
     * @var AdminCreditService
     */
    private $adminCreditService;
    /**
     * @var AdminDocomoCareerService
     */
    private $adminDocomoCareerService;
    /**
     * @var AdminSoftbankCareerService
     */
    private $adminSoftbankCareerService;
    /**
     * @var AdminAuCareerService
     */
    private $adminAuCareerService;
    /**
     * @var AdminPaidyService
     */
    private $adminPaidyService;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * コンストラクタ
     * @param AdminCreditService $adminCreditService
     * @param AdminDocomoCareerService $adminDocomoCareerService
     * @param AdminSoftbankCareerService $adminSoftbankCareerService
     * @param AdminAuCareerService $adminAuCareerService
     * @param AdminPaidyService $adminPaidyService
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        AdminCreditService $adminCreditService,
        AdminDocomoCareerService $adminDocomoCareerService,
        AdminSoftbankCareerService $adminSoftbankCareerService,
        AdminAuCareerService $adminAuCareerService,
        AdminPaidyService $adminPaidyService,
        EccubeConfig $eccubeConfig
        ) {
            $this->adminCreditService = $adminCreditService;
            $this->adminDocomoCareerService = $adminDocomoCareerService;
            $this->adminSoftbankCareerService = $adminSoftbankCareerService;
            $this->adminAuCareerService = $adminAuCareerService;
            $this->adminPaidyService = $adminPaidyService;
            $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * ペイジェント受注編集画面表示用の各決済インスタンス取得
     * @param string $paymentType 決済種別
     * @return ペイジェント各決済のインスタンス
     */
    public function getInstance($paymentType)
    {
        switch ($paymentType) {
            case $this->eccubeConfig['paygent_payment']['paygent_credit']:
                return $this->adminCreditService;
                break;
            case $this->eccubeConfig['paygent_payment']['paygent_career_d']:
                return $this->adminDocomoCareerService;
                break;
            case $this->eccubeConfig['paygent_payment']['paygent_career_s']:
                return $this->adminSoftbankCareerService;
                break;
            case $this->eccubeConfig['paygent_payment']['paygent_career_a']:
                return $this->adminAuCareerService;
                break;
            case $this->eccubeConfig['paygent_payment']['paygent_paidy']:
                return $this->adminPaidyService;
                break;
            default:
                return null;
                break;
        }
    }
}
