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

namespace Plugin\PaygentPayment\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\CustomerAddress;
use Eccube\Entity\Master\OrderItemType;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\OrderItem;
use Eccube\Repository\AbstractRepository;

/**
 * Class Credit3dSecure2OrderRepository
 */
class Credit3dSecure2OrderRepository extends AbstractRepository
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    /**
     * 配送先の初回利用受注を取得する
     *
     * @param Customer $customer
     * @param Shipping $shipping
     * @return Order|null
     */
    public function getShipAddressUseFirstDateOrder($customer, $shipping)
    {
        // ステータスが配送済み、支払い済みのものを対象とする
        $queryBuilder = $this->entityManager->createQueryBuilder('o');
        $queryBuilder
            ->select('o')
            ->from(Order::class, 'o')
            ->join('o.Shippings', 's')
            ->andWhere('o.Customer = :Customer')
            ->andWhere('s.Pref = :Pref')
            ->andWhere('s.addr01 = :addr01')
            ->andWhere('s.addr02 = :addr02')
            ->andWhere($queryBuilder->expr()->in('o.OrderStatus', [OrderStatus::DELIVERED, OrderStatus::PAID]))
            ->andWhere('o.order_date is NOT NULL')
            ->setParameters([
                'Customer' => $customer,
                'Pref' => $shipping->getPref(),
                'addr01' => $shipping->getAddr01(),
                'addr02' => $shipping->getAddr02(),
            ])
            ->orderBy('o.order_date')
            ->setMaxResults(1);

        $result = $queryBuilder->getQuery()->getResult();

        return isset($result[0]) ? $result[0] : null;
    }

    /**
     * 同じ商品を購入した回数を取得する
     *
     * @param Customer $customer
     * @param Order $order
     * @return string
     */
    public function getOrderCountSameProduct($customer, $order)
    {
        // 商品明細のみを取得
        // 規格違いは同じ商品とみなす
        $subQueryBuilder = $this->entityManager->createQueryBuilder('oi2')
            ->from(OrderItem::class, 'oi2')
            ->select('p.id')
            ->leftJoin('oi2.Product', 'p')
            ->andWhere('oi2.Order = :Order')
            ->andWhere('oi2.OrderItemType = '. OrderItemType::PRODUCT);

        // ステータスが発送済み、支払い済みのものを対象とする
        $queryBuilder = $this->entityManager->createQueryBuilder('o');
        $queryBuilder->select('count(o.id)')
            ->from(Order::class, 'o')
            ->join('o.OrderItems', 'oi')
            ->andWhere('o.Customer = :Customer')
            ->andWhere('oi.Product IN ('. $subQueryBuilder->getDQL(). ')')
            ->andWhere('o.id != :order_id')
            ->andWhere($queryBuilder->expr()->in('o.OrderStatus', [OrderStatus::DELIVERED, OrderStatus::PAID]))
            ->setParameters([
                'Order' => $order,
                'Customer' => $customer,
                'order_id' => $order->getId()
            ]);

        $result = $queryBuilder->getQuery()->getSingleScalarResult();

        return $result;
    }

    /**
     * 指定した期間内の注文回数を取得する
     *
     * @param Customer $customer
     * @param Payment $payment
     * @param DateTime $periodDate
     * @return string
     */
    public function getActiveCount($customer, $payment, $periodDate)
    {
        // 決済処理中、購入処理中のものは除く
        $queryBuilder = $this->entityManager->createQueryBuilder('o');
        $queryBuilder->select('count(o.id)')
            ->from(Order::class, 'o')
            ->andWhere('o.Customer = :Customer')
            ->andWhere('o.OrderStatus <> '. OrderStatus::PENDING)
            ->andWhere('o.OrderStatus <> '. OrderStatus::PROCESSING)
            ->andWhere('o.Payment = :Payment')
            ->andWhere('o.order_date > :order_date')
            ->setParameters([
                'Customer' => $customer,
                'Payment' => $payment,
                'order_date' => $periodDate
            ]);

        $result = $queryBuilder->getQuery()->getSingleScalarResult();

        return $result;
    }

    /**
     * 購入回数(全決済手段)を取得する
     *
     * @param Customer $customer
     * @param DateTime $periodDate
     * @return string
     */
    public function getPurchaseCount($customer, $periodDate)
    {
        // 指定した期間内で注文ステータスが発送済みまたは入金済みの受注の個数を取得する
        $queryBuilder = $this->entityManager->createQueryBuilder('o');
        $queryBuilder->select('count(o.id)')
            ->from(Order::class, 'o')
            ->andWhere('o.Customer = :Customer')
            ->andWhere($queryBuilder->expr()->in(
                'o.OrderStatus', [OrderStatus::DELIVERED, OrderStatus::PAID]
            ))
            ->andWhere('o.order_date > :order_date')
            ->setParameters([
                'Customer' => $customer,
                'order_date' => $periodDate,
            ]);

        $result = $queryBuilder->getQuery()->getSingleScalarResult();

        return $result;
    }

    /**
     * 最新のアカウント更新日を取得する
     *
     * @param Customer $customer
     * @return DateTime
     */
    public function getCustomerLatestUpdateDate($customer)
    {
        $customerUpdateDate = $customer->getUpdateDate();

        $queryBuilder = $this->entityManager->createQueryBuilder('ca');
        $queryBuilder->select('ca.update_date')
            ->from(CustomerAddress::class, 'ca')
            ->andWhere('ca.Customer = :Customer')
            ->andWhere('ca.update_date > :update_date')
            ->setParameters([
                'Customer' => $customer,
                'update_date' => $customerUpdateDate,
            ])
            ->orderBy('ca.update_date', 'desc')
            ->setMaxResults(1);

        $arrCustomerAddressUpdateDate = $queryBuilder->getQuery()->getResult();

        // 会員の更新日よりお届け先の更新日が最新の場合お届け先の更新日を返却する
        return isset($arrCustomerAddressUpdateDate[0]) ? $arrCustomerAddressUpdateDate[0]['update_date'] : $customerUpdateDate;
    }
}
