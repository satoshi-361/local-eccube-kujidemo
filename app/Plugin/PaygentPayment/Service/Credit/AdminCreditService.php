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
use Eccube\Entity\Master\OrderStatus;
use Plugin\PaygentPayment\Service\PaygentBaseService;

class AdminCreditService extends PaygentBaseService {

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var CreditOperationService
     */
    protected $creditOperationService;

    /**
     * @var CreditOperationFactory
     */
    protected $creditOperationFactory;

    /**
     * コンストラクタ
     * @param EccubeConfig $eccubeConfig
     * @param CreditOperationService $creditOperationService
     * @param CreditOperationFactory $creditOperationFactory
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        CreditOperationService $creditOperationService,
        CreditOperationFactory $creditOperationFactory
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->creditOperationService = $creditOperationService;
        $this->creditOperationFactory = $creditOperationFactory;
    }

    /**
     * 受注編集画面内での売上、取消、オーソリ、売上変更、分岐処理
     * @param $paygentType
     * @param $orderId
     * @return array
     */
    function process($paygentType, $orderId)
    {
        $arrReturn = null;
        $creditOperationInstance = $this->creditOperationFactory->getInstance($paygentType);
        $arrReturn = $creditOperationInstance->process($paygentType, $orderId);
        $arrReturn['type'] = $creditOperationInstance->getOperationName($paygentType);

        return $arrReturn;
    }

    /**
     * 受注編集画面内でのフラグの管理
     *
     * @param $order
     * @return array
     */
    function getPaygentFlags($order)
    {
        $paygentFlags = [];

        $paygentFlags['isCredit'] = true;

        $paygentFlags = $this->setCommitFlags($paygentFlags, $order);
        $paygentFlags = $this->setCancelFlags($paygentFlags, $order);
        $paygentFlags = $this->setChangeFlags($paygentFlags, $order);

        return $paygentFlags;
    }

    /**
     * 受注編集画面内でのcommitフラグのセット
     *
     * @param $paygentFlags
     * @param $order
     * @return array
     */
    private function setCommitFlags($paygentFlags, $order)
    {
        $paygentFlags['commit'] = false;

        $paygentPaymentStatus = $order->getPaygentPaymentStatus();
        $paygentPaymentKind = $order->getPaygentKind();
        $paygentPaymentId = $order->getPaygentPaymentId();
        $paygentResponseResult = $order->getResponseResult();

        $arrPaygentKind = $this->getPaygentKindForEdit();
        $arrPaygentStatus = $this->getPaygentStatusForEdit();

        // 決済前ステータスでOrderに各パラメータがセットされたパターンは弾く
        if (!$this->isSetOperationFlag($order, $paygentResponseResult)) {
            return $paygentFlags;
        }

        if ($paygentPaymentId != null
            and in_array($paygentPaymentStatus, [$arrPaygentStatus['status_authority_ok'], null], true)
            and in_array($paygentPaymentKind, [$arrPaygentKind['paygent_credit'], null], true)) {
            $paygentFlags['commit'] = 'card_commit';
        }

        return $paygentFlags;
    }

    /**
     * 受注編集画面内でのcancelフラグのセット
     *
     * @param $paygentFlags
     * @param $order
     * @return array
     */
    private function setCancelFlags($paygentFlags, $order)
    {
        $paygentFlags['cancel'] = false;

        $paygentPaymentStatus = $order->getPaygentPaymentStatus();
        $paygentPaymentKind = $order->getPaygentKind();
        $paygentPaymentId = $order->getPaygentPaymentId();
        $paygentResponseResult = $order->getResponseResult();

        $arrPaygentKind = $this->getPaygentKindForEdit();
        $arrPaygentStatus = $this->getPaygentStatusForEdit();

        if (is_null($paygentPaymentId)) {
            return $paygentFlags;
        }

        // 決済前ステータスでOrderに各パラメータがセットされたパターンは弾く
        if (!$this->isSetOperationFlag($order, $paygentResponseResult)) {
            return $paygentFlags;
        }

        if ($paygentPaymentStatus != $arrPaygentStatus['status_authority_expired']
            and in_array($paygentPaymentKind, [$arrPaygentKind['paygent_credit'], null], true)) {
            $paygentFlags['cancel'] = 'auth_cancel';

        } elseif ($paygentPaymentStatus != $arrPaygentStatus['status_pre_cleared_expiration_cancellation_sales']
                and in_array($paygentPaymentKind, [$arrPaygentKind['paygent_card_commit'], $arrPaygentKind['paygent_card_commit_revice']], true)) {
            $paygentFlags['cancel'] = 'card_commit_cancel';
        }

        return $paygentFlags;
    }

    /**
     * 受注編集画面内でのchangeフラグのセット
     *
     * @param $paygentFlags
     * @param $order
     * @return array
     */
    private function setChangeFlags($paygentFlags, $order)
    {
        $paygentFlags['change'] = false;
        $paygentFlags['change_auth'] = false;

        $paygentPaymentStatus = $order->getPaygentPaymentStatus();
        $paygentPaymentKind = $order->getPaygentKind();
        $paygentPaymentId = $order->getPaygentPaymentId();
        $paygentResponseResult = $order->getResponseResult();

        $arrPaygentKind = $this->getPaygentKindForEdit();
        $arrPaygentStatus = $this->getPaygentStatusForEdit();

        if (is_null($paygentPaymentId)) {
            return $paygentFlags;
        }

        // 決済前ステータスでOrderに各パラメータがセットされたパターンは弾く
        if (!$this->isSetOperationFlag($order, $paygentResponseResult)) {
            return $paygentFlags;
        }

        if (in_array($paygentPaymentStatus, [$arrPaygentStatus['status_authority_ok'], null], true)
            and in_array($paygentPaymentKind, [$arrPaygentKind['paygent_credit'], null], true)) {
            $paygentFlags['change_auth'] = 'change_auth';

        } elseif (in_array($paygentPaymentStatus, [$arrPaygentStatus['status_pre_cleared'], $arrPaygentStatus['status_authority_ok'], null], true)
            and in_array($paygentPaymentKind, [$arrPaygentKind['paygent_card_commit'], $arrPaygentKind['paygent_card_commit_revice']], true)) {
            $paygentFlags['change'] = 'change_commit';
        }

        return $paygentFlags;
    }

    /**
     * 受注編集画面内で操作を可能とするフラグを設定するか判定
     *
     * @param Order $order
     * @param string $paygentResponseResult
     * @return boolean
     */
    private function isSetOperationFlag($order, $paygentResponseResult)
    {
        // 対応状況が決済処理中かつ受注情報のレスポンスが7(3Dセキュア)か
        // 3Dセキュア決済で決済ベンダの画面を表示している時はボタンを非活性
        if ($order->getOrderStatus()->getId() == OrderStatus::PENDING && $paygentResponseResult == $this->eccubeConfig['paygent_payment']['result_3dsecure']) {
            return false;
        }

        // 対応状況が購入処理中かつ受注情報のレスポンス結果が0ではないか
        // 3Dセキュア認証エラーで購入処理中に戻ってきたときもボタンを非活性にする
        if ($order->getOrderStatus()->getId() == OrderStatus::PROCESSING && $paygentResponseResult != $this->eccubeConfig['paygent_payment']['result_success']) {
            return false;
        }

        return true;
    }
}
