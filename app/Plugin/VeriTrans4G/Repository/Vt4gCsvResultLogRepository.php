<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Repository;

use Eccube\Repository\AbstractRepository;
use Plugin\VeriTrans4G\Entity\Vt4gCsvResultLog;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * plg_vt4g_csv_result_logリポジトリクラス
 */
class Vt4gCsvResultLogRepository extends AbstractRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Vt4gCsvResultLog::class);
    }

}
