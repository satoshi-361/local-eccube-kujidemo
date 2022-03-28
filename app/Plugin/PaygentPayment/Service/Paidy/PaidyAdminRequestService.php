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

use Eccube\Repository\PluginRepository;
use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Plugin\PaygentPayment\Service\PaygentBaseService;

/**
 * Paidy管理画面のリクエストを行うクラス
 */
class PaidyAdminRequestService extends PaygentBaseService {

    /**
     * @var PluginRepository
     */
    protected $pluginRepository;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * コンストラクタ
     * @param PluginRepository $pluginRepository
     * @param EccubeConfig $eccubeConfig
     * @param ConfigRepository $configRepository
     */
    public function __construct (
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig,
        ConfigRepository $configRepository
    ) {
        $this->pluginRepository = $pluginRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->configRepository = $configRepository;
    }

    /**
     * 売上・取消・売上変更処理の電文パラメータ作成
     * @param string $orderId 注文ID
     * @param string $paymentId 決済ID
     * @return array
     */
    public function makeParam($telegramKind, $orderId, $paymentId) {
        /** 共通電文パラメータ **/
        $params = $this->commonMakeParam($orderId, $paymentId);

        /** 個別電文パラメータ **/
        // 電文種別ID
        $params['telegram_kind'] = $telegramKind;

        return $params;
    }

    /**
     * 電文のリクエストを行う
     */
    public function sendRequest($objPaygent, $arrSend, $charCode){
        // 電文の送付
        foreach($arrSend as $key => $val) {
            $objPaygent->reqPut($key, $val);
        }

        $objPaygent->post();
        // レスポンスの取得
        while($objPaygent->hasResNext()) {
            # データが存在する限り、取得
            $arrRes[] = $objPaygent->resNext(); # 要求結果取得
        }
        $arrRes[0]['result'] = $objPaygent->getResultStatus(); # 処理結果 0=正常終了, 1=異常終了

        foreach($arrRes[0] as $key => $val) {
            // Shift-JISで応答があるので、エンコードする。
            $arrRes[0][$key] = mb_convert_encoding($val, $charCode, "Shift-JIS");
        }

        return $arrRes;
    }
}
