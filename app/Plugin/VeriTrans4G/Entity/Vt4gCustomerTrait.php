<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity;

use Eccube\Annotation\EntityExtension;
use Doctrine\ORM\Mapping as ORM;

/**
 * @EntityExtension("Eccube\Entity\Customer")
 */
trait Vt4gCustomerTrait
{
    /**
     * ベリトランス会員ID用カラム
     *
     * @var string
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    public $vt4g_account_id;
}
