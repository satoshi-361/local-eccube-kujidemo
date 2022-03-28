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

namespace Plugin\PaygentPayment;

use Eccube\Common\EccubeNav;

class PaygentPaymentNav implements EccubeNav
{
    /**
     * @return array
     */
    public static function getNav()
    {
        return [
            'order' => [
                'children' => [
                    'paygent_payment_admin_payment_status' => [
                        'name' => 'paygent_payment.admin.nav.payment_list',
                        'url' => 'paygent_payment_admin_payment_status',
                    ],
                ],
            ],
            'setting' => [
                'children' => [
                    'system' => [
                        'children' => [
                            'paygent_payment_admin_paygent_file' => [
                                'name' => 'paygent_payment.admin.nav.paygent_file',
                                'url' => 'paygent_payment_admin_paygent_file',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
