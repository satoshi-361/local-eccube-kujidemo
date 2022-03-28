<?php

namespace Plugin\PrizeShow;

use Eccube\Common\EccubeNav;

class Nav implements EccubeNav
{
    /**
     * @return array
     */
    public static function getNav()
    {
        return [
            // 'prize' => [
            //     'name' => '当選商品',
            //     'icon' => 'fa fa-tag',
            //     'children' => [
            //         'prize_list' => [
            //             'name' => '当選商品一覧',
            //             'url'  => 'prize_show_admin'
            //         ],
            //         'prize_show' => [
            //             'name' => '当選商品登録',
            //             'url'  => 'prize_show_admin_new'
            //         ]
            //     ]
            // ]
        ];
    }
}
