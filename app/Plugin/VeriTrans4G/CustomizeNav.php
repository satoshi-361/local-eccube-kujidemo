<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G;

use Eccube\Common\EccubeNav;

/**
 * 管理画面のメニューを追加
 */
class CustomizeNav implements EccubeNav
{
    /**
     * @return array
     */
    public static function getNav()
    {
        return [
            'product' => [
                'children' => [
                    'vt4g_subsc_salestype' => [
                        'name' => 'vt4g_plugin.admin.menu.product.subsc_salestype',
                        'url'  => 'vt4g_admin_subsc_salestype',
                    ],
                ],
            ],
            'order' => [
                'children' => [
                    'vt4g_order_csv_upload' => [
                        'name' => 'vt4g_plugin.admin.menu.order.csv_upload',
                        'url'  => 'vt4g_admin_order_csv_upload',
                    ],
                    'vt4g_order_csv_registration' => [
                        'name' => 'vt4g_plugin.admin.menu.order.csv_registration',
                        'url'  => 'vt4g_admin_order_csv_registration',
                    ],
                    'vt4g_order_csv_request' => [
                        'name' => 'vt4g_plugin.admin.menu.order.csv_request',
                        'url'  => 'vt4g_admin_order_csv_request',
                    ],
                    'vt4g_order_csv_result' => [
                        'name' => 'vt4g_plugin.admin.menu.order.csv_result',
                        'url'  => 'vt4g_admin_order_csv_result',
                    ],
                ],
            ],
            'customer' => [
                'children' => [
                    'vt4g_customer_subsc_history' => [
                        'name' => 'vt4g_plugin.admin.menu.customer.subsc_history',
                        'url'  => 'vt4g_admin_subsc_customer',
                    ],
                ]
            ],
            'setting' => [
                'children' => [
                    'system' => [
                        'children' => [
                            'vt4g_log_download' => [
                                'name' => 'vt4g_plugin.admin.menu.setting.system.log_download',
                                'url'  => 'vt4g_admin_log_download',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
