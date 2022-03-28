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

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PluginRepository;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system\PaygentB2BModule;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Plugin\PaygentPayment\Service\CacheConfig;

/**
 * クレジット決済受注管理操作オーソリ変更・売上変更処理クラス
 */
class CreditOperationChangeService extends CreditOperationService {

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var PluginRepository
     */
    protected $pluginRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * コンストラクタ
     * @param EccubeConfig $eccubeConfig
     * @param OrderRepository $orderRepository
     * @param CacheConfig $cacheConfig
     * @param PluginRepository $pluginRepository
     * @param EntityManagerInterface $entityManager
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        OrderRepository $orderRepository,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EntityManagerInterface $entityManager,
        ConfigRepository $configRepository
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->orderRepository = $orderRepository;
        $this->cacheConfig = $cacheConfig;
        $this->pluginRepository = $pluginRepository;
        $this->entityManager = $entityManager;
        $this->configRepository = $configRepository;
    }

    /**
     * クレジットオーソリ・売上変更処理
     */
    public function process($paygentType, $orderId, $paymentId = '', $beforeStatus = '')
    {
        $arrPaygentKind = $this->getPaygentKindForOperationAction();

        $charCode = $this->eccubeConfig['paygent_payment']['char_code'];

        // 接続モジュールのインスタンス取得 (コンストラクタ)と初期化
        $objPaygent = new PaygentB2BModule($this->eccubeConfig);
        $objPaygent->init();

        $kind = $this->judgePaygentKindSet($paygentType, $arrPaygentKind);

        // ペイジェントステータスのチェック
        /** @var Order $order */
        $order = $this->orderRepository->findBy(['id' => $orderId]);

        $status = $order[0]->getPaygentKind();

        // ステータス矛盾判定
        $arrReturn = $this->judgeStatusContradiction($paygentType, $arrPaygentKind, $status, $kind, $beforeStatus);
        if($arrReturn){
            return $arrReturn;
        }

        // 決済IDの取得
        if(strlen($paymentId) === 0) {
            $paymentId = $order[0]->getPaygentPaymentId();
        }
        // 電文パラメータ取得
        $arrSendParam = $this->makeParam($kind, $orderId, $paymentId);
        // $arrSendに個別詳細情報を付け加える
        $arrSend = $this->addMakeParamForChange($arrSendParam, $paygentType, $arrPaygentKind, $orderId, $order, $status, $paymentId);

        if(isset($arrSend['return'])){
            return $arrSend;
        }

        // 電文送信
        $arrRes = $this->sendRequest($objPaygent, $arrSend, $charCode);

        // 正常終了
        if($arrRes[0]['result'] === $this->eccubeConfig['paygent_payment']['result_success']) {
            // 成功時の更新処理
            $arrReturn = $this->updateOrderPaymentForChangeSuccess($arrRes, $orderId, $paymentId, $kind, $status, $paygentType, $arrPaygentKind);
        } else {
            // 失敗時の更新処理
            $arrReturn = $this->updateOrderPaymentForChangeFailed($objPaygent, $arrSend, $orderId, $paymentId, $kind, $status, $beforeStatus, $paygentType);
        }
        return $arrReturn;
    }

    /**
     * 追加電文パラメータ
     * @param array $arrSend 電文パラメータ
     * @param string $paygentType 一括処理タイプ
     * @param array $arrPaygentKind 応答電文一覧配列
     * @param string $orderId 注文ID
     * @param Order $order 受注情報
     * @param string $status 応答電文
     * @param string $paymentId 決済ID
     * @return array
     */
    private function addMakeParamForChange($arrSend, $paygentType, $arrPaygentKind, $orderId, $order, $status, $paymentId){

        switch($paygentType) {
            case 'change_auth':
            case 'change_commit_auth':
                if ($paygentType == 'change_auth') {
                    // ステータスをオーソリ変更処理中に変更
                    $arrVal['paygent_kind'] = $arrPaygentKind['paygent_credit_processing'];
                    $this->updateOrderPayment($orderId, $arrVal);
                }
                $arrOrder = $order[0];
                $arrSend['payment_amount'] = (int)$arrOrder['payment_total'];
                $arrSend['ref_trading_id'] = $orderId;
                $arrPaymentParam = unserialize($arrOrder['paygent_credit_subdata']);
                $arrSend['payment_class'] = isset($arrPaymentParam['payment_class']) ? $arrPaymentParam['payment_class'] : '';
                $arrSend['split_count'] = isset($arrPaymentParam['split_count']) ? $arrPaymentParam['split_count'] : '';
                $arrSend['3dsecure_ryaku'] = '1';
                unset($arrSend['payment_id']);
                break;
            case 'change_commit':
                // ステータスを売上変更処理中に変更
                $arrVal['paygent_kind'] = $arrPaygentKind['paygent_card_commit_revice_processing'];
                $this->updateOrderPayment($orderId, $arrVal);
                // 新規オーソリ処理
                $arrRetAuth = $this->process('change_commit_auth', $orderId, $paymentId, $status);
                // オーソリ失敗
                if($arrRetAuth['return'] == false) {
                    $arrRetAuth['kind'] = $arrPaygentKind['paygent_card_commit_revice'];
                    return $arrRetAuth;
                } else {
                    // 決済IDを更新
                    $arrSend['payment_id'] = $arrRetAuth['payment_id'];
                }
                break;
        }

        return $arrSend;
    }

    
    /**
     * 変更処理成功時のアップデート処理
     */
    private function updateOrderPaymentForChangeSuccess($arrRes, $orderId, $paymentId, $kind, $status, $paygentType, $arrPaygentKind){

        $arrReturn['kind'] = $kind;

        $arrVal = [];

        // オーソリ変更
        switch($paygentType) {
            case 'change_commit_auth':
                $arrReturn['payment_id'] = $arrRes[0]['payment_id'];
                break;
            case 'change_auth_cancel':
            case 'change_commit_cancel':
                break;
            case 'change_auth':
                // オーソリ変更前の決済に対してオーソリキャンセル電文を送信
                $arrRetCancel = $this->process('change_auth_cancel', $orderId, $paymentId, $status);
                // オーソリキャンセル失敗
                if($arrRetCancel['return'] == false) {
                    $arrVal['paygent_kind'] = $kind;
                    $arrVal['paygent_payment_id'] = $arrRes[0]['payment_id'];
                    $this->updateOrderPayment($orderId, $arrVal);
                    return $arrRetCancel;
                } else {
                    $arrVal['paygent_kind'] = $kind;
                    $arrVal['paygent_payment_id'] = $arrRes[0]['payment_id'];
                    $arrVal['paygent_error'] = '';
                }
                break;
            // 売上変更
            case 'change_commit':
                // 売上変更前の決済に対して売上キャンセル電文を送信
                $arrRetCancel = $this->process('change_commit_cancel', $orderId, $paymentId, $status);
                // 売上キャンセル失敗
                if($arrRetCancel['return'] == false) {
                    $arrRetCancel['kind'] = $arrPaygentKind['paygent_card_commit_revice'];
                    $arrVal['paygent_kind'] = $arrPaygentKind['paygent_card_commit_revice'];
                    $arrVal['paygent_payment_id'] = $arrRes[0]['payment_id'];
                    $this->updateOrderPayment($orderId, $arrVal);
                    return $arrRetCancel;
                } else {
                    $arrReturn['kind'] = $arrPaygentKind['paygent_card_commit_revice'];
                    $arrVal['paygent_kind'] = $arrPaygentKind['paygent_card_commit_revice'];
                    $arrVal['paygent_payment_id'] = $arrRes[0]['payment_id'];
                    $arrVal['paygent_error'] = '';
                }
                break;
        }
        $arrReturn['return'] = true;

        if(0 < count($arrVal)) {
            $this->updateOrderPayment($orderId, $arrVal);
        }
        return $arrReturn;
    }

    /**
     * 変更処理失敗時のアップデート処理
     */
    private function updateOrderPaymentForChangeFailed($objPaygent, $arrSend, $orderId, $paymentId, $kind, $status, $beforeStatus, $paygentType){

        $charCode = $this->eccubeConfig['paygent_payment']['char_code'];
        $arrReturn['kind'] = $kind;
        $arrVal = [];

        $arrReturn['return'] = false;
        $responseCode = $objPaygent->getResponseCode(); # 異常終了時、レスポンスコードが取得できる

        if ($beforeStatus != '' && $paygentType != 'change_auth_cancel') {
            $arrVal['paygent_kind'] = $beforeStatus;
        } else {
            $arrVal['paygent_kind'] = $status;
        }

        switch($paygentType) {
            case 'change_commit':
                // 売上変更に失敗
                $arrVal['paygent_error'] = "変更後の金額での売上に失敗しました。（" . $responseCode . "） 取引ID:" . $orderId . ", 決済ID:" . $arrSend['payment_id'];
                $arrReturn['response'] = $arrVal['paygent_error'];
                break;
            // 売上変更時の売上キャンセルに失敗
            case 'change_commit_cancel':
                $arrVal['paygent_error'] = "変更後の金額による売上が成功しましたが、変更前の売上取消に失敗しました。（" . $responseCode . "） 同一取引IDで複数の売上が発生しているため、取引ID:" . $orderId . ", 決済ID:" . $paymentId . "の売上をペイジェントオンラインから取り消してください。";
                $arrReturn['response'] = $arrVal['paygent_error'];
                break;
            // オーソリ変更時のオーソリキャンセルに失敗
            case 'change_auth_cancel':
                $arrVal['paygent_error'] = "変更後の金額によるオーソリが成功しましたが、変更前のオーソリ取消に失敗しました。（" . $responseCode . "） 同一取引IDで複数のオーソリが発生しているため、取引ID:" . $orderId . ", 決済ID:" . $paymentId . "のオーソリをペイジェントオンラインから取り消してください。";
                $arrReturn['response'] = $arrVal['paygent_error'];
                break;
            default:
                $arrErrorMessage = $this->getArrErrorMessage($objPaygent, $charCode);
                $arrVal['paygent_error'] = $arrErrorMessage['paygent_error'];
                $arrReturn['response'] = $arrErrorMessage['response'];
                break;
        }
        $this->updateOrderPayment($orderId, $arrVal);

        return $arrReturn;
    }

}
