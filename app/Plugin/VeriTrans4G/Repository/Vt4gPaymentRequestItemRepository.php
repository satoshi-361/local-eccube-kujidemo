<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Repository;

use Eccube\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentRequestItem;

/**
 * plg_vt4g_payment_request_itemリポジトリクラス
 */
class Vt4gPaymentRequestItemRepository extends AbstractRepository
{
    /**
     * コンストラクタ
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Vt4gPaymentRequestItem::class);
    }


    /**
     * CSV決済依頼明細情報を取得する
     * @param int $request_id
     * @return array
     */
    public function getItems($request_id)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->select('i.id, i.shipping_id, i.order_item_type_id, p.name, i.amount, i.quantity, i.point, i.payment_target')
           ->leftJoin('\Eccube\Entity\Product', 'p', 'WITH', 'i.product_id = p.id')
           ->where('i.request_id = :request_id')
           ->setParameter('request_id', $request_id);

        return $qb->getQuery()->getResult();
    }
}
