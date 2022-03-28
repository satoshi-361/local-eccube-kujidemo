<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Repository;

use Eccube\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Plugin\VeriTrans4G\Entity\Vt4gOrderLog;


/**
 * plg_vt4g_order_logリポジトリクラス
 */
class Vt4gOrderLogRepository extends AbstractRepository
{
    /**
     * コンストラクタ
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Vt4gOrderLog::class);
    }

    /**
     * 指定されたorder_idのレコードを取得します。
     * @param int $order_id
     * @return null|Vt4gOrderLog
     */
    public function get($order_id = 1)
    {
        return $this->find($order_id);
    }

}