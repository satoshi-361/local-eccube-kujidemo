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

use Eccube\Common\EccubeConfig;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system\PaygentB2BModule;
use Plugin\PaygentPayment\Service\CacheConfig;
use Plugin\PaygentPayment\Service\PaygentBaseService;

/**
 * カードお預かり機能のクラス
 */
class FrontCreditCardStockService extends PaygentBaseService
{
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

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
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param CustomerRepository $customerRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param ConfigRepository $configRepository
     * @param EntityManagerInterface $entityManager
     * @param CacheConfig $cacheConfig
     * @param PluginRepository $pluginRepository
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        OrderStatusRepository $orderStatusRepository,
        ConfigRepository $configRepository,
        EntityManagerInterface $entityManager,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig
    ) {
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->configRepository = $configRepository;
        $this->entityManager = $entityManager;
        $this->cacheConfig = $cacheConfig;
        $this->pluginRepository = $pluginRepository;
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * クレジットカード情報の登録と削除
     * @param array $order          受注情報
     * @param array $inputParams    入力パラメータ(FormまたはGET)
     * @param array $telegramKind  電文種別
     * @return array $arrRes        レスポンス情報
     */
    public function applyProcess($order, $inputParams, $telegramKind)
    {
        // 電文パラメータ設定
        $params = $this->makeParam($order, $inputParams, $telegramKind);
        // 電文送信
        $arrRes = $this->callApi($params);
        // 顧客情報更新
        $this->saveCustomer($order, $arrRes);
        return $arrRes;
    }

    /**
     * クレジットカード情報の取得
     * @param array $order          受注情報
     * @param array $telegramKind  電文種別
     * @return array $arrRes        レスポンス情報
     */
    public function applyProcessCreditStock($order, $telegramKind)
    {
        $inputParams = [];

        // 電文パラメータ設定
        $params = $this->makeParam($order, $inputParams, $telegramKind);
        // 電文送信
        $arrRes = $this->callApiCreditStock($params);
        // 顧客情報更新
        $this->saveCustomer($order, $arrRes[0]);
        return $arrRes;
    }

    /**
     * 電文パラメータの設定
     * @param array $inputOrder 受注情報
     * @param array $inputParams 入力パラメータ
     * @param array $telegramKind 電文種別
     * @return array $params 電文パラメータ
     */
    private function makeParam($inputOrder, $inputParams, $telegramKind) {
        /** 共通電文パラメータ **/
        $params = $this->commonMakeParam($inputOrder->getId(),"");
        /** 個別電文パラメータ **/
        // 電文種別ID
        $params['telegram_kind'] = $telegramKind;

        // マーチャント取引ID
        // カード情報お預かり機能
        $params['trading_id'] = '0';
        // 顧客ID
        $params['customer_id'] = $inputOrder->getCustomer()->getId();

        /** カード情報登録電文 **/
        if ($telegramKind == $this->eccubeConfig['paygent_payment']['paygent_card_stock_set']) {
            // カード情報トークン
            $params['card_token'] = $inputParams['card_token_stock'];
            // プラグイン設定情報の取得
            $config = $this->cacheConfig->getConfig();
            // 有効性チェックフラグ
            $params['valid_check_flg'] = $config->getCardValidCheck();
            /** カード情報削除電文 **/
        } elseif ($telegramKind == $this->eccubeConfig['paygent_payment']['paygent_card_stock_del']) {
            // 顧客カードID
            $params['customer_card_id'] = $inputParams['customer_card_id'];
        }

        return $params;
    }

    /**
     * 電文呼出ファンクション
     * @param  array $params 電文パラメータ配列
     * @return array $arrRes レスポンス結果
     */
    function callApiCreditStock($params) {
        // 接続モジュールのインスタンス取得 (コンストラクタ)と初期化
        $objPaygent = new PaygentB2BModule($this->eccubeConfig);
        $objPaygent->init();

        // 電文の送付
        foreach($params as $key => $val) {
            // Shift-JISにエンコードする必要あり
            $encVal = mb_convert_encoding($val, $this->eccubeConfig['paygent_payment']['char_code_ks'], $this->eccubeConfig['paygent_payment']['char_code']);
            $objPaygent->reqPut($key, $encVal);
            log_info("$key => $val");
        }

        $objPaygent->post();

        // レスポンスの取得
        $arrRes = null;
        while($objPaygent->hasResNext()) {
            # データが存在する限り、取得
            $arrRes[] = $objPaygent->resNext(); # 要求結果取得
        }

        // 処理結果 0=正常終了, 1=異常終了
        $resultStatus = $objPaygent->getResultStatus();

        // 異常終了時、レスポンスコードが取得できる
        $responseCode = $objPaygent->getResponseCode();

        // 異常終了時、レスポンス詳細が取得できる
        $responseDetail = mb_convert_encoding($objPaygent->getResponseDetail(), $this->eccubeConfig['paygent_payment']['char_code'], $this->eccubeConfig['paygent_payment']['char_code_ks']);

        // 画面表示用の値をセット
        if ($responseCode) {
            if (preg_match('/^[P|E]/', $responseCode)) {
                $response = "（". $responseCode. "）";
            } else {
                $response = $responseDetail. "（". $responseCode. "）";
            }
        } else {
            $response = "";
        }

        // 取得した値をログに保存する。
        if($resultStatus === $this->eccubeConfig['paygent_payment']['result_error']) {
            $arrResOther = [];
            $arrResOther['result'] = $resultStatus;
            $arrResOther['code'] = $responseCode;
            $arrResOther['detail'] = $responseDetail;
            foreach($arrResOther as $key => $val) {
                log_info($key."->".$val);
            }
        }

        $arrRes[0]['resultStatus'] = $resultStatus;
        $arrRes[0]['responseCode'] = $responseCode;
        $arrRes[0]['responseDetail'] = $responseDetail;
        $arrRes[0]['response'] = $response;

        return $arrRes;
    }

    /**
     * 顧客テーブル更新処理
     * @param array $inputOrder 受注情報
     * @param array $arrRes 電文レスポンス情報
     */
    private function saveCustomer($inputOrder, $arrRes)
    {
        $customer = $inputOrder->getCustomer();

        if ($customer) {
            $customer = $this->customerRepository->find($customer->getId());
        }

        if (isset($customer) && $customer && $arrRes['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_success'] && isset($arrRes['num_of_cards'])) {
            if ($arrRes['num_of_cards'] == '0') {
                $customer->setPaygentCardStock(false);
            } else {
                $customer->setPaygentCardStock(true);
            }
            $this->entityManager->persist($customer);
            $this->entityManager->flush($customer);
        }
    }
}