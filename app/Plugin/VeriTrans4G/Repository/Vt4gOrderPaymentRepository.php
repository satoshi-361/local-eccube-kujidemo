<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Repository;

use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Plugin\VeriTrans4G\Entity\Vt4gOrderPayment;


/**
 * plg_vt4g_order_paymentリポジトリクラス
 */
class Vt4gOrderPaymentRepository extends AbstractRepository
{
    /**
     * コンストラクタ
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Vt4gOrderPayment::class);
    }

    /**
     * 指定されたorder_idのレコードを取得します。
     * @param int $order_id
     * @return null|Vt4gOrderPayment
     */
    public function get($order_id = 1)
    {
        return $this->find($order_id);
    }

    /**
     * 注文履歴からクレジットカード番号などの情報を取得
     *
     * @param  integer $customerId  会員ID
     * @param  integer $paymentType 決済方法コード
     * @param  string  $limitDate   使用可能とする日時
     * @param  integer $limit       最大取得件数
     * @return array                カード番号などの情報
     */
    public function getReTradeCards($customerId, $paymentType, $limitDate, $limit)
    {
        $query = $this->createQueryBuilder('op');

        $query
            ->select(
                'op.order_id AS orderId',
                'op.memo01 AS paymentOrderId',
                'op.memo04 AS payStatus',
                'op.memo07 AS cardNumber',
                'op.memo10 AS memo10',
                'o.order_date AS orderDate'
            )
            ->innerJoin(Order::class, 'o', 'WITH', 'o.id = op.order_id')
            ->where('op.memo02 = :customerId')
            ->andWhere('o.order_date > :limitDate')
            ->andWhere(
                $query->expr()->notIn('o.OrderStatus', [
                    OrderStatus::CANCEL,
                    OrderStatus::PENDING
                ])
            )
            ->andWhere('op.memo03 = :paymentType')
            ->andWhere('op.memo07 IS NOT NULL')
            ->setParameters(compact('customerId', 'paymentType', 'limitDate'))
            ->orderBy('op.order_id', 'DESC');

        $orderPayments = $query->getQuery()->setMaxResults($limit)->getResult();

        return array_map(function ($record) {
            // カード番号に含まれる「*」を3つに統一
            $record['cardNumber'] = preg_replace('/\*+/', '***', $record['cardNumber']);
            // 日付のフォーマット
            $record['orderDate'] = $record['orderDate']->format('Y/m/d H:i:s');

            $memo10 = unserialize($record['memo10']);
            $record['registeredCardName'] = $memo10['cardName'] ?? '';

            return $record;
        }, $orderPayments);
    }

    /**
     * 取引IDから元取引が存在するか確認
     *
     * @param  integer $customerId     会員ID
     * @param  integer $paymentType    決済方法コード
     * @param  string  $limitDate      使用可能とする日時
     * @param  string  $paymentOrderId 取引ID
     * @return boolean                 取引が存在するかどうか
     */
    public function existsReTradeOrder($customerId, $paymentType, $limitDate, $paymentOrderId)
    {
        $query = $this->createQueryBuilder('op');

        $query
            ->select('COUNT(op.order_id)')
            ->innerJoin(Order::class, 'o', 'WITH', 'o.id = op.order_id')
            ->where('op.memo01 = :paymentOrderId')
            ->andWhere('op.memo02 = :customerId')
            ->andWhere('op.memo03 = :paymentType')
            ->andWhere('op.memo07 IS NOT NULL')
            ->andWhere('o.create_date > :limitDate')
            ->andWhere(
                $query->expr()->notIn('o.OrderStatus', [
                    OrderStatus::CANCEL,
                    OrderStatus::PENDING
                ])
            )
            ->setParameters(compact('customerId', 'paymentType', 'limitDate', 'paymentOrderId'));

        $count = $query->getQuery()->getOneOrNullResult();

        return intval($count) > 0;
    }
}
