<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Repository\Master;

use Plugin\VeriTrans4G\Entity\Master\Vt4gSubscProductOrderCmpMailInfo;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * plg_vt4g_subsc_product_ord_comp_mail_infリポジトリクラス
 */
class Vt4gSubscProductOrderCmpMailInfoRepository extends \Eccube\Repository\AbstractRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Vt4gSubscProductOrderCmpMailInfo::class);
    }

    /**
     * 継続課金商品注文完了メール情報を商品IDをキーに削除する.
     *
     * @param  string $productId 削除対象の商品ID
     */
    public function deleteWithProductId($productId)
    {
      $em = $this->getEntityManager();

      $qb = $em->createQueryBuilder()
        ->delete(Vt4gSubscProductOrderCmpMailInfo::class, 'sm')
        ->where('sm.product_id = :product_id')
        ->setParameter('product_id', $productId);

      $qb->getQuery()->execute();
    }

}
