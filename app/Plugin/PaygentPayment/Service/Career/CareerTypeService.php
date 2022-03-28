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

/**
 * 携帯キャリア決済の利用決済に関するクラス
 */
class CareerTypeService {

    /**
     * @var EccubeConfig
     */
    private $eccubeConfig;

    /**
     * コンストラクタ
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        EccubeConfig $eccubeConfig
    ) {
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * careerTypeからPaymentPaygentMethodを取得
     * @param $careerType
     * @return $paymentPaygentMethod
     */
    public function getPaygentMethodByCareerType($careerType) {
        $paygentMethod = null;

        switch ($careerType) {
            case $this->eccubeConfig['paygent_payment']['career_division_id']['career_type_docomo']:
                $paygentMethod = $this->eccubeConfig['paygent_payment']['paygent_career_d'];
                break;
            case $this->eccubeConfig['paygent_payment']['career_division_id']['career_type_au']:
                $paygentMethod = $this->eccubeConfig['paygent_payment']['paygent_career_a'];
                break;
            case $this->eccubeConfig['paygent_payment']['career_division_id']['career_type_softbank']:
                $paygentMethod = $this->eccubeConfig['paygent_payment']['paygent_career_s'];
                break;
        }

        return $paygentMethod;
    }
}
