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
use Eccube\Doctrine\Query\Queries;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\Shipping;
use Eccube\Repository\PaymentRepository;
use Eccube\Repository\QueryKey;
use Eccube\Util\StringUtil;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ペイジェント決済画面での検索用サービスクラス
 */
class PaymentStatusSearchService {

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var Queries
     */
    protected $queries;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * コンストラクタ
     * @param PaymentRepository $paymentRepository
     * @param Queries $queries
     * @param EccubeConfig $eccubeConfig
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        PaymentRepository $paymentRepository,
        Queries $queries,
        EccubeConfig $eccubeConfig,
        EntityManagerInterface $entityManager
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->queries = $queries;
        $this->eccubeConfig = $eccubeConfig;
        $this->entityManager = $entityManager;
    }

    public function createQueryBuilder(array $searchData)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder('o')
            ->select('o, s')
            ->from(Order::class, 'o')
            ->addSelect('oi', 'pref')
            ->leftJoin('o.OrderItems', 'oi')
            ->leftJoin('o.Pref', 'pref')
            ->innerJoin('o.Shippings', 's');

        // multi
        if (isset($searchData['multi']) && StringUtil::isNotBlank($searchData['multi'])) {
            $multi = preg_match('/^\d{0,10}$/', $searchData['multi']) ? $searchData['multi'] : null;
            $queryBuilder
                ->andWhere('o.id = :multi OR o.name01 LIKE :likemulti OR o.name02 LIKE :likemulti OR '.
                            'o.kana01 LIKE :likemulti OR o.kana02 LIKE :likemulti OR o.company_name LIKE :likemulti OR '.
                            'o.order_no LIKE :likemulti OR '.
                            'o.email LIKE :likemulti OR o.phone_number LIKE :likemulti')
                ->setParameter('multi', $multi)
                ->setParameter('likemulti', '%'.$searchData['multi'].'%');
        }

        // status
        $filterStatus = false;
        if (!empty($searchData['status']) && count($searchData['status'])) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->in('o.OrderStatus', ':status'))
                ->setParameter('status', $searchData['status']);
            $filterStatus = true;
        }

        if (!$filterStatus) {
            // 購入処理中, 決済処理中は検索対象から除外
            $queryBuilder->andWhere($queryBuilder->expr()->notIn('o.OrderStatus', ':status'))
                ->setParameter('status', [OrderStatus::PROCESSING, OrderStatus::PENDING]);
        }

        // 詳細検索項目
        $queryBuilder = $this->detailSearchItems($queryBuilder, $searchData);
        $queryBuilder = $this->detailSearchPeriodItems($queryBuilder, $searchData);

        // Paygentのみ
        foreach($this->eccubeConfig['paygent_payment']['create_payment_param'] as $createParam) {
            $payment[] = $this->paymentRepository->findOneBy(['method_class' => $createParam["method_class"]]);
        }
        $queryBuilder->andWhere($queryBuilder->expr()->in('o.Payment', ':Payment'))
            ->setParameter('Payment', $payment);

        // Order By
        $queryBuilder->orderBy('o.update_date', 'DESC');
        $queryBuilder->addorderBy('o.id', 'DESC');

        return $this->queries->customize(QueryKey::ORDER_SEARCH_ADMIN, $queryBuilder, $searchData);
    }

    // 詳細検索項目
     private function detailSearchItems($queryBuilder, array $searchData)
    {
        // name
        if (isset($searchData['name']) && StringUtil::isNotBlank($searchData['name'])) {
            $queryBuilder
                ->andWhere('CONCAT(o.name01, o.name02) LIKE :name')
                ->setParameter('name', '%'.$searchData['name'].'%');
        }

        // kana
        if (isset($searchData['kana']) && StringUtil::isNotBlank($searchData['kana'])) {
            $queryBuilder
                ->andWhere('CONCAT(o.kana01, o.kana02) LIKE :kana')
                ->setParameter('kana', '%'.$searchData['kana'].'%');
        }

        // company_name
        if (isset($searchData['company_name']) && StringUtil::isNotBlank($searchData['company_name'])) {
            $queryBuilder
                ->andWhere('o.company_name LIKE :company_name')
                ->setParameter('company_name', '%'.$searchData['company_name'].'%');
        }

        // email
        if (isset($searchData['email']) && StringUtil::isNotBlank($searchData['email'])) {
            $queryBuilder
                ->andWhere('o.email like :email')
                ->setParameter('email', '%'.$searchData['email'].'%');
        }

        // tel
        if (isset($searchData['phone_number']) && StringUtil::isNotBlank($searchData['phone_number'])) {
            $tel = preg_replace('/[^0-9]/ ', '', $searchData['phone_number']);
            $queryBuilder
                ->andWhere('o.phone_number LIKE :phone_number')
                ->setParameter('phone_number', '%'.$tel.'%');
        }

        // order_no
        if (isset($searchData['order_no']) && StringUtil::isNotBlank($searchData['order_no'])) {
            $queryBuilder
                ->andWhere('o.order_no = :order_no')
                ->setParameter('order_no', $searchData['order_no']);
        }

        // payment
        if (!empty($searchData['payment']) && count($searchData['payment'])) {
            $queryBuilder
                ->leftJoin('o.Payment', 'p')
                ->andWhere($queryBuilder->expr()->in('p.id', ':payments'))
                ->setParameter('payments', $searchData['payment']);
        }

        // payment_total
        if (isset($searchData['payment_total_start']) && StringUtil::isNotBlank($searchData['payment_total_start'])) {
            $queryBuilder
                ->andWhere('o.payment_total >= :payment_total_start')
                ->setParameter('payment_total_start', $searchData['payment_total_start']);
        }
        if (isset($searchData['payment_total_end']) && StringUtil::isNotBlank($searchData['payment_total_end'])) {
            $queryBuilder
                ->andWhere('o.payment_total <= :payment_total_end')
                ->setParameter('payment_total_end', $searchData['payment_total_end']);
        }

        // buy_product_name
        if (isset($searchData['buy_product_name']) && StringUtil::isNotBlank($searchData['buy_product_name'])) {
            $queryBuilder
                ->andWhere('oi.product_name LIKE :buy_product_name')
                ->setParameter('buy_product_name', '%'.$searchData['buy_product_name'].'%');
        }

        // 発送メール送信/未送信.
        if (isset($searchData['shipping_mail']) && $count = count($searchData['shipping_mail'])) {
            // 送信済/未送信両方にチェックされている場合は検索条件に追加しない
            if ($count < 2) {
                $checked = current($searchData['shipping_mail']);
                if ($checked == Shipping::SHIPPING_MAIL_UNSENT) {
                    // 未送信
                    $queryBuilder
                        ->andWhere('s.mail_send_date IS NULL');
                } elseif ($checked == Shipping::SHIPPING_MAIL_SENT) {
                    // 送信
                    $queryBuilder
                        ->andWhere('s.mail_send_date IS NOT NULL');
                }
            }
        }

        // 送り状番号.
        if (!empty($searchData['tracking_number'])) {
            $queryBuilder
                ->andWhere('s.tracking_number = :tracking_number')
                ->setParameter('tracking_number', $searchData['tracking_number']);
        }

        return $queryBuilder;
    }

    // 詳細検索項目(期間指定する項目)
    private function detailSearchPeriodItems($queryBuilder, array $searchData)
    {
        // oreder_date
        if (!empty($searchData['order_date_start']) && $searchData['order_date_start']) {
            $date = $searchData['order_date_start'];
            $queryBuilder
                ->andWhere('o.order_date >= :order_date_start')
                ->setParameter('order_date_start', $date);
        }
        if (!empty($searchData['order_date_end']) && $searchData['order_date_end']) {
            $date = clone $searchData['order_date_end'];
            $date = $date
                ->modify('+1 days');
            $queryBuilder
                ->andWhere('o.order_date < :order_date_end')
                ->setParameter('order_date_end', $date);
        }

        // payment_date
        if (!empty($searchData['payment_date_start']) && $searchData['payment_date_start']) {
            $date = $searchData['payment_date_start'];
            $queryBuilder
                ->andWhere('o.payment_date >= :payment_date_start')
                ->setParameter('payment_date_start', $date);
        }
        if (!empty($searchData['payment_date_end']) && $searchData['payment_date_end']) {
            $date = clone $searchData['payment_date_end'];
            $date = $date
                ->modify('+1 days');
            $queryBuilder
                ->andWhere('o.payment_date < :payment_date_end')
                ->setParameter('payment_date_end', $date);
        }

        // update_date
        if (!empty($searchData['update_date_start']) && $searchData['update_date_start']) {
            $date = $searchData['update_date_start'];
            $queryBuilder
                ->andWhere('o.update_date >= :update_date_start')
                ->setParameter('update_date_start', $date);
        }
        if (!empty($searchData['update_date_end']) && $searchData['update_date_end']) {
            $date = clone $searchData['update_date_end'];
            $date = $date
                ->modify('+1 days');
            $queryBuilder
                ->andWhere('o.update_date < :update_date_end')
                ->setParameter('update_date_end', $date);
        }

        // お届け予定日(Shipping.delivery_date)
        if (!empty($searchData['shipping_delivery_date_start']) && $searchData['shipping_delivery_date_start']) {
            $date = $searchData['shipping_delivery_date_start'];
            $queryBuilder
                ->andWhere('s.shipping_delivery_date >= :shipping_delivery_date_start')
                ->setParameter('shipping_delivery_date_start', $date);
        }
        if (!empty($searchData['shipping_delivery_date_end']) && $searchData['shipping_delivery_date_end']) {
            $date = clone $searchData['shipping_delivery_date_end'];
            $date = $date
                ->modify('+1 days');
            $queryBuilder
                ->andWhere('s.shipping_delivery_date < :shipping_delivery_date_end')
                ->setParameter('shipping_delivery_date_end', $date);
        }

        return $queryBuilder;
    }
}
