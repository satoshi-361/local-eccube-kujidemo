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

namespace Plugin\PaygentPayment\Service\Credit;

use Eccube\Repository\OrderRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Common\EccubeConfig;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Plugin\PaygentPayment\Service\CacheConfig;
use Plugin\PaygentPayment\Service\PaygentBaseService;

/**
 * クレジット決済受注管理操作クラス
 */
class CreditOperationService extends PaygentBaseService {

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var PluginRepository
     */
    protected $pluginRepository;
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var CreditOperationFactory
     */
    protected $creditOperationFactory;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param EntityManagerInterface $entityManager
     * @param CacheConfig $cacheConfig
     * @param PluginRepository $pluginRepository
     * @param EccubeConfig $eccubeConfig
     * @param CreditOperationFactory $creditOperationFactory
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig,
        CreditOperationFactory $creditOperationFactory,
        ConfigRepository $configRepository
        ) {
            $this->orderRepository = $orderRepository;
            $this->entityManager = $entityManager;
            $this->cacheConfig = $cacheConfig;
            $this->pluginRepository = $pluginRepository;
            $this->eccubeConfig = $eccubeConfig;
            $this->creditOperationFactory = $creditOperationFactory;
            $this->configRepository = $configRepository;
    }

    /**
     * 売上・取消・オーソリ変更・売上変更処理の電文パラメータ作成
     * @param string $orderId 注文ID
     * @param string $paymentId 決済ID
     * @return array
     */
    protected function makeParam($telegramKind, $orderId, $paymentId) {
        /** 共通電文パラメータ **/
        $params = $this->commonMakeParam($orderId, $paymentId);

        /** 個別電文パラメータ **/
        // 電文種別ID
        $params['telegram_kind'] = $telegramKind;

        return $params;
    }

    /**
     * 決済受注管理操作の処理で使用するpaygent_kindの値を配列化して取得
     */
    protected function getPaygentKindForOperationAction(){
        return [
            'paygent_card_commit' => $this->eccubeConfig['paygent_payment']['paygent_card_commit'],
            'paygent_auth_cancel' => $this->eccubeConfig['paygent_payment']['paygent_auth_cancel'],
            'paygent_card_commit_cancel' => $this->eccubeConfig['paygent_payment']['paygent_card_commit_cancel'],
            'paygent_card_commit_revice_processing' => $this->eccubeConfig['paygent_payment']['paygent_card_commit_revice_processing'],
            'paygent_credit_processing' => $this->eccubeConfig['paygent_payment']['paygent_credit_processing'],
            'paygent_card_commit_revice' => $this->eccubeConfig['paygent_payment']['paygent_card_commit_revice'],
            'paygent_credit' => $this->eccubeConfig['paygent_payment']['paygent_credit'],
        ];
    }

    /**
     * セットするpaygent_kindを判断する
     */
    protected function judgePaygentKindSet($paygentType, $arrPaygentKind){
        // 処理分岐
        switch($paygentType) {
            case 'change_auth':
            case 'change_commit_auth':
                $kind = $arrPaygentKind['paygent_credit'];
                break;
            case 'change_auth_cancel':
                $kind = $arrPaygentKind['paygent_auth_cancel'];
                break;
            case 'change_commit':
                $kind = $arrPaygentKind['paygent_card_commit'];
                break;
            case 'change_commit_cancel':
                $kind = $arrPaygentKind['paygent_card_commit_cancel'];
                break;
            case 'auth_cancel':
                $kind = $arrPaygentKind['paygent_auth_cancel'];
                break;
            case 'card_commit_cancel':
                $kind = $arrPaygentKind['paygent_card_commit_cancel'];
                break;
        }
        return $kind;
    }

    /**
     * ステータス矛盾エラーか判断する
     */
    protected function judgeStatusContradiction($paygentType, $arrPaygentKind, $status, $kind, $beforeStatus = ''){
        $arrCommitPaygentKind = [$arrPaygentKind['paygent_card_commit'],
                                $arrPaygentKind['paygent_card_commit_revice']];
        $arrCancelPaygentKind = [$arrPaygentKind['paygent_auth_cancel'],
                                $arrPaygentKind['paygent_card_commit_cancel']];
        $arrProcessingPaygentKind = [$arrPaygentKind['paygent_card_commit_revice_processing'],
                                    $arrPaygentKind['paygent_credit_processing']];

        if (((in_array($status, $arrCancelPaygentKind) || in_array($status, $arrProcessingPaygentKind)) && ($beforeStatus === '' && $beforeStatus == null) )
                || (in_array($status, $arrCommitPaygentKind) && $paygentType == 'change_auth')) {
                    // ステータスが取消または売上変更処理中になっている、
                    // またはステータスが売上or売上変更になっているときのオーソリ変更は処理を中断
                    if ($paygentType == 'change_commit') {
                        $arrReturn['kind'] = $arrPaygentKind['paygent_card_commit_revice'];
                    } else {
                        $arrReturn['kind'] = $kind;
                    }
                    $arrReturn['return'] = false;
                    $arrReturn['response'] = "ステータス矛盾エラー";
                    return $arrReturn;
        }
        return [];
    }

    /**
     * 電文のリクエストを行う
     */
    protected function sendRequest($objPaygent, $arrSend, $charCode){
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

    public function getOperationName($paygentType) {
        switch ($paygentType) {
            case 'card_commit':
                return '売上';
                break;

            case 'change_auth':
                return 'オーソリ変更';
                break;

            case 'change_commit':
                return '売上変更';
                break;

            case 'auth_cancel':
            case 'card_commit_cancel':
                return '取消';
                break;
        }
    }

    function process($paygentType, $orderId) {
        $creditOperationInstance = $this->creditOperationFactory->getInstance($paygentType);
        $arrReturn = $creditOperationInstance->process($paygentType, $orderId);

        return $arrReturn;
    }
}
