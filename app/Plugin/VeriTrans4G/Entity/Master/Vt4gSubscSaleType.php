<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity\Master;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Master\SaleType;

/**
 *
 * Vt4gSubscSaleType
 *
 * @ORM\Table(name="plg_vt4g_subsc_sale_type", options={"comment":"継続課金販売種別マスタ"})
 * @ORM\DiscriminatorColumn(name="discriminator_type", type="string", length=255)
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Master\Vt4gSubscSaleTypeRepository")
 *
 */
class Vt4gSubscSaleType extends \Eccube\Entity\AbstractEntity
{

    /**
     * @var int
     *
     * @ORM\Column(name="sale_type_id", type="smallint", length=5, options={"unsigned":true, "comment":"販売種別ID"})
     * @ORM\Id
     */
     private $sale_type_id;

     /**
      * @var string
      *
      *
      */
     private $name;

    /**
     * @var int
     *
     * @ORM\Column(name="few_credit_flg", type="boolean", length=1, options={"comment":"少額与信利用フラグ"})
     */
    private $few_credit_flg;

    /**
     * @var int
     *
     * @ORM\Column(name="discriminator_type", type="string", length=255)
     */
    private $discriminator_type;

    /**
     * @var \Eccube\Entity\Master\SaleType
     *
     * @ORM\OneToOne(targetEntity="Eccube\Entity\Master\SaleType")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="sale_type_id", referencedColumnName="id")
     * })
     */
    private $SaleType;

    /**
     * Set id.
     *
     * @param int $saleTypeId
     *
     * @return Vt4gSubscSaleType
     */
    public function setSaleTypeId($saleTypeId)
    {
        $this->sale_type_id = $saleTypeId;

        return $this;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getSaleTypeId()
    {
        return $this->sale_type_id;
    }

    /**
     * Set fewCreditFlg.
     *
     * @param int $fewCreditFlg
     *
     * @return Vt4gSubscSaleType
     */
    public function setFewCreditFlg($fewCreditFlg)
    {
        $this->few_credit_flg = $fewCreditFlg;

        return $this;
    }

    /**
     * Get fewCreditFlg.
     *
     * @return int
     */
    public function getFewCreditFlg()
    {
        return $this->few_credit_flg;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set discriminator_type.
     *
     * @param string $discriminator_type
     *
     * @return $this
     */
    public function setDiscriminatorType($discriminatorType)
    {
        $this->discriminator_type = $discriminatorType;

        return $this;
    }

    /**
     * Get discriminator_type.
     *
     * @return string
     */
    public function getDiscriminatorType()
    {
        return $this->discriminator_type;
    }

    /**
     * Set saletype.
     *
     * @param \Eccube\Entity\Master\SaleType|null $saletype
     *
     * @return Vt4gSubscSaleType
     */
    public function setSaleType(\Eccube\Entity\Master\SaleType $saletype = null)
    {
        $this->SaleType = $saletype;

        return $this;
    }

    /**
     * Get payment.
     *
     * @return \Eccube\Entity\Master\SaleType|null
     */
    public function getSaleType()
    {
        return $this->SaleType;
    }
}
