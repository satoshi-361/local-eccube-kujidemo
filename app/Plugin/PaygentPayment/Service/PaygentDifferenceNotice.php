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

namespace Plugin\PaygentPayment\Service;

use Eccube\Common\EccubeConfig;
use Eccube\Repository\OrderRepository;
use Eccube\Service\PurchaseFlow\Processor\PointProcessor;
use Eccube\Service\PurchaseFlow\Processor\StockReduceProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\PaygentPayment\Service\Paidy\PaidyService;

/**
 * Paygent KS-システムとの連携・差分通知を行うクラス。
 */
class PaygentDifferenceNotice {

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var PaymentFactory
     */
    protected $paymentFactory;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var PointProcessor
     */
    private $pointProcessor;

    /**
     * @var StockReduceProcessor
     */
    private $stockReduceProcessor;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var PaidyService
     */
    protected $paidyService;

    // 決済手段のインスタンス
    private $paymentInstance;

    /**
     * コンストラクタ
     * @param EccubeConfig $eccubeConfig
     * @param PaymentFactory $paymentFactory
     * @param CacheConfig $cacheConfig
     * @param OrderRepository $orderRepository
     * @param PointProcessor $pointProcessor
     * @param StockReduceProcessor $stockReduceProcessor
     * @param EntityManagerInterface $entityManager
     * @param PaidyService $paidyService
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        PaymentFactory $paymentFactory,
        CacheConfig $cacheConfig,
        OrderRepository $orderRepository,
        PointProcessor $pointProcessor,
        StockReduceProcessor $stockReduceProcessor,
        EntityManagerInterface $entityManager,
        PaidyService $paidyService
        ) {
            $this->eccubeConfig = $eccubeConfig;
            $this->paymentFactory = $paymentFactory;
            $this->cacheConfig = $cacheConfig;
            $this->orderRepository = $orderRepository;
            $this->pointProcessor = $pointProcessor;
            $this->stockReduceProcessor = $stockReduceProcessor;
            $this->entityManager = $entityManager;
            $this->paidyService = $paidyService;
    }

    /**
     * 差分通知 - メインメソッド
     * ペイジェントサーバーから送られてくるリクエストを処理して、ステータスの変更処理を行う。
     */
    public function mainProcess()
    {
        logs('paygent_payment')->info("BEGIN PAYGENT ACCEPTED THE REQUEST!");

        // ペイジェントから送られてくるリクエストパラメータを取得
        $arrParam = [];
        $arrParam['trading_id'] = filter_input( INPUT_POST, 'trading_id' );
        $arrParam['payment_id'] = filter_input( INPUT_POST, 'payment_id' );
        $arrParam['payment_status'] = filter_input( INPUT_POST, 'payment_status' );
        $arrParam['payment_type'] = filter_input( INPUT_POST, 'payment_type' );
        $arrParam['payment_notice_id'] = filter_input( INPUT_POST, 'payment_notice_id' );
        $arrParam['payment_date'] = filter_input( INPUT_POST, 'payment_date' );
        $arrParam['clear_detail'] = filter_input( INPUT_POST, 'clear_detail' );
        $arrParam['payment_amount'] = filter_input( INPUT_POST, 'payment_amount' );
        $arrParam['hc'] = filter_input( INPUT_POST, 'hc' );
        $arrParam['payment_class'] = filter_input( INPUT_POST, 'payment_class' );
        $arrParam['split_count'] = filter_input( INPUT_POST, 'split_count' );

        if (!$this->hashCheck($arrParam)) {
            return $this->eccubeConfig['paygent_payment']['result_error'];
        }

        if ($this->isValid($arrParam)) {
            // 取得したパラメータをログに出力する
            foreach ($arrParam as $key => $val) {
                $convertedKey = mb_convert_encoding($key, $this->eccubeConfig['paygent_payment']['char_code'], $this->eccubeConfig['paygent_payment']['char_code_ks']);
                $convertedVal = mb_convert_encoding($val, $this->eccubeConfig['paygent_payment']['char_code'], $this->eccubeConfig['paygent_payment']['char_code_ks']);

                logs('paygent_payment')->info("$convertedKey => $convertedVal");
            }

            $config = $this->cacheConfig->getConfig();
            $settlementDivision = $config->getSettlementDivision();
            $order = $this->orderRepository->find($arrParam['trading_id']);

            // 対象決済での初回差分通知時に対応状況が「決済処理中」以外の場合
            if ($this->paymentInstance->isAlertMail($settlementDivision, $arrParam['payment_status'], $order)) {
                $this->paymentInstance->sendStatusAlertMailToAdmin($arrParam);
                return $this->eccubeConfig['paygent_payment']['result_success'];
            }

            // 入金ステータスを更新する
            $this->paymentInstance->updatePaygentOrder($arrParam);

            if ($this->isRepairStatus($arrParam['payment_status'])) {
                $arrOrder = $this->orderRepository->find($arrParam['trading_id']);

                // 在庫戻し処理
                $this->stockReduceProcessor->rollback($arrOrder, new PurchaseContext());
                // ポイント戻し処理
                $this->pointProcessor->rollback($arrOrder, new PurchaseContext());

                $this->entityManager->persist($arrOrder);
                $this->entityManager->flush();
            }
        }

        logs('paygent_payment')->info("END PAYGENT ACCEPTED THE REQUEST!");

        return $this->eccubeConfig['paygent_payment']['result_success'];
    }

    /**
     * ペイジェントから送られてくるリクエストパラメータのバリデーションチェック
     * @return boolean $issetFlg
     */
    function isValid($arrParam) {
        if (!$this->isValidRequire($arrParam)) {
            return false;
        }

        if (!$this->isValidType($arrParam)) {
            return false;
        }

        if (!$this->paidyService->isValidPaidy($arrParam)) {
            return false;
        }

        if (!$this->isValidExistence($arrParam)) {
            return false;
        }

        if (!$this->isValidStatus($arrParam)) {
            return false;
        }

        return true;
    }

    /**
     * 必須チェックファンクション
     */
    function isValidRequire($arrParam) {
        if (!$arrParam['payment_notice_id']) {
            logs('paygent_payment')->error("決済通知IDのPOSTパラメータ値がありません。");
            return false;
        }
        if (!$arrParam['payment_id']) {
            logs('paygent_payment')->error("決済IDのPOSTパラメータ値がありません。");
            return false;
        }
        if (!$arrParam['trading_id']) {
            logs('paygent_payment')->error("マーチャント取引IDのPOSTパラメータ値がありません。");
            return false;
        }
        if (!$arrParam['payment_type']) {
            logs('paygent_payment')->error("決済種別CDのPOSTパラメータ値がありません。");
            return false;
        }
        if (!$arrParam['payment_status']) {
            logs('paygent_payment')->error("決済ステータスのPOSTパラメータ値がありません。");
            return false;
        }
        if (!$arrParam['payment_amount']) {
            logs('paygent_payment')->error("決済金額のPOSTパラメータ値がありません。");
            return false;
        }
        return true;
    }

    /**
     * 型チェックファンクション
     */
    function isValidType ($arrParam) {
        if ($arrParam['payment_date']) {
            if (! preg_match('/^\d{14}$/', $arrParam['payment_date']) || ! strtotime($arrParam['payment_date'])) {
                // 支払日時が null ではなく、yyyyMMddHHmmss の日付として不正な場合はログに出力する
                logs('paygent_payment')->error("支払日時 -> ". $arrParam['payment_date'] ."の値が不正です。");
                return false;
            }
        }
        return true;
    }

    /**
     * 存在チェックファンクション
     */
    function isValidExistence ($arrParam) {
        $arrOrder = $this->orderRepository->find($arrParam['trading_id']);
        if (empty($arrOrder)) {
            // 存在しなかった場合
            logs('paygent_payment')->error("マーチャント取引ID ->" . $arrParam['trading_id'] . "が一致するデータは受注情報に存在しません。");
            return false;
        } else {
            if ($arrOrder['payment_total'] != $arrParam['payment_amount'] || ($arrOrder['paygent_payment_id'] != "" && $arrOrder['paygent_payment_id'] != $arrParam['payment_id'])) {
                if (
                    !in_array($arrOrder['paygent_kind'], [
                        $this->eccubeConfig['paygent_payment']['paygent_credit'],
                        $this->eccubeConfig['paygent_payment']['paygent_card_commit_revice'],
                        $this->eccubeConfig['paygent_payment']['paygent_career_commit_revice'],
                    ], true)
                ) {
                    logs('paygent_payment')->error("決済ID -> " . $arrParam['payment_id'] . "マーチャント取引ID ->" . $arrParam['trading_id'] .
                        "決済金額 -> ". $arrParam['payment_amount'] . "が一致するデータは受注情報に存在しません。");
                    return false;
                }
                return false;
            }
        }
        return true;
    }

    /**
     * ステータスチェックファンクション
     */
    function isValidStatus ($arrParam) {
        // 決済種別CDに該当するインスタンスを取得。取得できなければ、エラーを返す。
        $this->paymentInstance = $this->paymentFactory->getInstance($arrParam['payment_type']);
        if(empty($this->paymentInstance)) {
            logs('paygent_payment')->error("invalid payment_type!");
            return false;
        }

        // ステータスチェック
        if (!$this->paymentInstance->isValidStatus($arrParam['payment_status'])) {
            logs('paygent_payment')->error("決済種別CD ->". $arrParam['payment_type'] ."で決済ステータス -> ". $arrParam['payment_status'] ."は存在しません。");
            return false;
        }
        return true;
    }

    /**
     * ハッシュチェック
     */
    private function hashCheck($arrParam) {
        // プラグイン設定情報の取得
        $config = $this->cacheConfig->getConfig();
        $eccubeHash = hash("sha256", $arrParam['payment_notice_id'] . $arrParam['payment_id'] . $arrParam['trading_id'] . $arrParam['payment_type'] . $arrParam['payment_amount'] . $config->getNoticeHashKey(), false);
        if ($arrParam['hc'] != $eccubeHash) {
            logs('paygent_payment')->error("マーチャント取引ID->" . $arrParam['trading_id'] . "のハッシュ値 ->". $arrParam['hc'] ."が一致しません。");
            return false;
        }
        return true;
    }

    /**
     * 在庫・ポイント戻し ステータスチェック
     * @param string paymentStatus ペイジェントから送られてくるステータスパラメータ
     * @return 正常:True 異常:False
     */
    function isRepairStatus($paymentStatus){
        // 在庫・ポイント戻し対象のステータス
        $arrIsRepairStatus = [
            $this->eccubeConfig['paygent_payment']['status_payment_expired'],
            $this->eccubeConfig['paygent_payment']['status_authority_expired']
        ];

        if(in_array($paymentStatus, $arrIsRepairStatus)) {
            return true;
        } else {
            return false;
        }
    }
}
