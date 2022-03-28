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

namespace Plugin\PaygentPayment\Service\Error;

use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\Repository\ConfigRepository;

/**
 * 申し込み電文異常時のレスポンスコードとレスポンス詳細を元に詳細エラーメッセージを生成する
 */
class ErrorDetailMsg
{
	/**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    public function __construct(
    	EccubeConfig $eccubeConfig,
    	ConfigRepository $configRepository
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->configRepository = $configRepository;
    }

    public function getErrorDetailMsg($responseCode, $responseDetail, $telegramKind = null) {

        $const = $this->eccubeConfig['paygent_payment'];

        // エラーが起こりえる項目について、物理名/論理名/文字数のマッピングテーブルを定義する。
        // 「customer_name」等は部分一致で「customer_family_name_kana」に引っかからないように最長のものから定義すること。
        $mappingTable = [
            $const['paygent_atm'] => [
            	'customer_family_name_kana'=>   ['name'=>'ご注文者お名前(フリガナ)姓','length'=>12],
                'customer_name_kana'=>          ['name'=>'ご注文者お名前(フリガナ)名','length'=>12],
                'customer_family_name'=>        ['name'=>'ご注文者お名前(姓)','length'=>6],
                'customer_name'=>               ['name'=>'ご注文者お名前(名)','length'=>6],
            ],
            $const['paygent_bank'] => [
            	'customer_family_name_kana'=>   ['name'=>'ご注文者お名前(フリガナ)姓','length'=>12],
                'customer_name_kana'=>          ['name'=>'ご注文者お名前(フリガナ)名','length'=>12],
                'customer_family_name'=>        ['name'=>'ご注文者お名前(姓)','length'=>6],
                'customer_name'=>               ['name'=>'ご注文者お名前(名)','length'=>6],
            ],
            $const['paygent_conveni_num'] => [
                'customer_family_name_kana'=>   ['name'=>'ご注文者お名前(フリガナ)姓','length'=>14],
                'customer_name_kana'=>          ['name'=>'ご注文者お名前(フリガナ)名','length'=>14],
                'customer_family_name'=>        ['name'=>'ご注文者お名前(姓)','length'=>10],
                'customer_name'=>               ['name'=>'ご注文者お名前(名)','length'=>10],
                'customer_tel'=>                ['name'=>'ご注文者電話番号','length'=>11],
            ],
            'link' => [
                'customer_family_name_kana'=>   ['name'=>'ご注文者お名前(フリガナ)姓','length'=>12],
                'customer_name_kana'=>          ['name'=>'ご注文者お名前(フリガナ)名','length'=>12],
                'customer_family_name'=>        ['name'=>'ご注文者お名前(姓)','length'=>6],
                'customer_name'=>               ['name'=>'ご注文者お名前(名)','length'=>6],
                'customer_tel'=>                ['name'=>'ご注文者電話番号','length'=>11]
            ],
        ];

        $msgTemplates = [
            'P008'=>'%name%は形式が正しくないか使用できない文字が含まれています。',
            'P009'=>'%name%は%length%文字以内を設定してください。',
        ];

        if (!array_key_exists($responseCode, $msgTemplates)) {
            return $this->eccubeConfig['paygent_payment']['no_mapping_message'];
        }

        // プラグイン設定情報の取得
        $config = $this->configRepository->get();

        if ($config->getSettlementDivision() == $this->eccubeConfig['paygent_payment']['settlement_id']['module']) {
        	$tagetTable = $mappingTable[$telegramKind];
        } elseif ($config->getSettlementDivision() == $this->eccubeConfig['paygent_payment']['settlement_id']['link']) {
        	$tagetTable = $mappingTable['link'];
        }
        
        foreach ($tagetTable AS $keyInputValues => $inputValue) {

            $pos = strpos($responseDetail, $keyInputValues);

            if ($pos !== false) {

                $mapping = [
                    '%name%' => $inputValue['name'],
                    '%length%' => $inputValue['length'],
                ];

                $search = array_keys($mapping);
                $replace = array_values($mapping);

                return str_replace($search, $replace, $msgTemplates[$responseCode]);
            }
        }

        return $this->eccubeConfig['paygent_payment']['no_mapping_message'];
    }
}
