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
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\AbstractRepository;

/**
 * Class PaidyOrderRepository
 */
class PaidyOrderRepository extends AbstractRepository
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(
        EccubeConfig $eccubeConfig,
        EntityManagerInterface $entityManager
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->entityManager = $entityManager;
    }

    /**
     * @param $cusotmer
     */
    public function getNoPaidyOrderCountFromCustomer($cusotmer)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder('o');
        $queryBuilder
            ->select('count(o.id) as orderCount, sum(o.payment_total) as orderSumPaymentTotal')
            ->from(Order::class, 'o')
            ->andWhere('o.Customer = :customer')
            ->andWhere($queryBuilder->expr()->In('o.OrderStatus', ':status'))
            ->andWhere($queryBuilder->expr()->notIn('o.paygent_payment_method', ':paygent_payment_method'))
            ->setParameter('customer', $cusotmer)
            ->setParameter('status', [OrderStatus::DELIVERED, OrderStatus::PAID])
            ->setParameter('paygent_payment_method', $this->eccubeConfig['paygent_payment']['paygent_paidy']);

        $result = $queryBuilder->getQuery()->getResult();

        return $result;
    }

    /**
     * @param $cusotmer
     */
    public function getNoPaidyLastOrderPaymentTotalFromCustomer($cusotmer)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder('o');
        $queryBuilder
            ->select('o.payment_total as payment_total, o.create_date as create_date')
            ->from(Order::class, 'o')
            ->andWhere('o.Customer = :customer')
            ->andWhere($queryBuilder->expr()->notIn('o.OrderStatus', ':status'))
            ->andWhere($queryBuilder->expr()->notIn('o.paygent_payment_method', ':paygent_payment_method'))
            ->setParameter('customer', $cusotmer)
            ->setParameter('status', [OrderStatus::PENDING, OrderStatus::PROCESSING])
            ->setParameter('paygent_payment_method', $this->eccubeConfig['paygent_payment']['paygent_paidy'])
            ->orderBy('o.create_date', 'DESC')
            ->setMaxResults(1);

        $result = $queryBuilder->getQuery()->getResult();

        return $result;
    }
}
