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

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\PurchaseFlow\Processor\PointProcessor;
use Eccube\Service\PurchaseFlow\Processor\StockReduceProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;

/**
 * Paygent決済受注のロールバック処理を行うクラス
 */
class PaygentOrderRollbackService {

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var PointProcessor
     */
    private $pointProcessor;

    /**
     * @var StockReduceProcessor
     */
    private $stockReduceProcessor;

    /**
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param EntityManagerInterface $entityManager
     * @param CacheConfig $cacheConfig
     * @param EccubeConfig $eccubeConfig
     * @param PointProcessor $pointProcessor
     * @param StockReduceProcessor $stockReduceProcessor
     */
    public function __construct(
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        EntityManagerInterface $entityManager,
        CacheConfig $cacheConfig,
        EccubeConfig $eccubeConfig,
        PointProcessor $pointProcessor,
        StockReduceProcessor $stockReduceProcessor
        ) {
            $this->orderRepository = $orderRepository;
            $this->orderStatusRepository = $orderStatusRepository;
            $this->entityManager = $entityManager;
            $this->cacheConfig = $cacheConfig;
            $this->eccubeConfig = $eccubeConfig;
            $this->pointProcessor = $pointProcessor;
            $this->stockReduceProcessor = $stockReduceProcessor;
    }

    /**
     * 決済処理中ステータス受注の取得
     * 
     * @return Order $arrOrder 決済処理中ステータスの受注情報配列
     */
    function getPendingOrder() {
        // プラグイン設定情報の取得
        $config = $this->cacheConfig->getConfig();

        $limitTerm = $config->getRollbackTargetTerm(); // 決済処理中の注文の取消期間の取得

        // 支払い期限の取得
        $limitDate = new \DateTime();
        $limitDate->setTime(0,0,0)->modify('-'.$limitTerm.' days')->format('Y/m/d H:i:s');

        // ペイジェント決済での決済処理中ステータス受注で取消期限設定値を過ぎている受注の取得
        $queryBuilder = $this->orderRepository->createQueryBuilder('o')
            ->andWhere('o.OrderStatus = :pending')
            ->andWhere('o.paygent_code = :paygentCode')
            ->andWhere('o.create_date < :limitDate')
            ->setParameter(':pending', OrderStatus::PENDING)
            ->setParameter(':paygentCode', $this->eccubeConfig['paygent_payment']['paygent_payment_code'])
            ->setParameter(':limitDate', $limitDate)
            ->orderBy('o.create_date');

        $arrOrder = $queryBuilder->getQuery()->getResult();

        logs('paygent_payment')->info("Get ".count($arrOrder)." pending orders.");

        return $arrOrder;
    }

    /**
     * 決済処理中ステータス受注のロールバック処理
     * 
     * @param Order $order 受注情報
     */
    function rollbackPaygentOrder($order) {
        logs('paygent_payment')->info("Start rollback. order_id[".$order->getId()."]");

        $this->entityManager->beginTransaction();

        // 受注ステータスをキャンセルに変更
        $orderStatus = $this->orderStatusRepository->find(OrderStatus::CANCEL);
        $order->setOrderStatus($orderStatus);

        // 在庫戻し処理
        $this->stockReduceProcessor->rollback($order, new PurchaseContext());
        // ポイント戻し処理
        $this->pointProcessor->rollback($order, new PurchaseContext());

        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->entityManager->commit();
    }

}
