<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Repository\Master;

use Plugin\VeriTrans4G\Entity\Master\Vt4gSubscProduct;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * plg_vt4g_subsc_productリポジトリクラス
 */
class Vt4gSubscProductRepository extends \Eccube\Repository\AbstractRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Vt4gSubscProduct::class);
    }

}
