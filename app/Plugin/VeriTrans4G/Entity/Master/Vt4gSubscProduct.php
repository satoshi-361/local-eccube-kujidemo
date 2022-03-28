<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity\Master;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * Vt4gSubscProduct
 *
 * @ORM\Table(name="plg_vt4g_subsc_product", options={"comment":"継続課金商品マスタ"})
 * @ORM\DiscriminatorColumn(name="discriminator_type", type="string", length=255)
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Master\Vt4gSubscProductRepository")
 *
 */
class Vt4gSubscProduct extends \Eccube\Entity\AbstractEntity
{

    /**
     * @var int
     *
     * @ORM\Column(name="product_id", type="integer", length=10, options={"unsigned":true, "comment":"商品ID"})
     * @ORM\Id
     */
     private $product_id;

     /**
      * @var int
      *
      * @ORM\Column(name="product_class_id", type="integer", length=10, options={"unsigned":true, "comment":"商品規格ID"})
      * @ORM\Id
      */
     private $product_class_id;

     /**
      * @var int
      *
      * @ORM\Column(name="subsc_sale_type_id", type="smallint", length=5, options={"unsigned":true, "comment":"継続課金販売種別ID"})
      */
      private $subsc_sale_type_id;

    /**
     * @var int
     *
     * @ORM\Column(name="my_page_disp_flg", type="boolean", length=1, options={"comment":"マイページ表示フラグ"})
     */
    private $my_page_disp_flg;

    /**
     * Set product_id.
     *
     * @param int $productId
     *
     * @return Vt4gSubscProduct
     */
    public function setProductId($productId)
    {
        $this->product_id = $productId;

        return $this;
    }

    /**
     * Get product_id.
     *
     * @return int
     */
    public function getProductId()
    {
        return $this->product_id;
    }

    /**
     * Set product_class_id.
     *
     * @param int $productClassId
     *
     * @return Vt4gSubscProduct
     */
    public function setProductClassId($productClassId)
    {
        $this->product_class_id = $productClassId;

        return $this;
    }

    /**
     * Get product_class_id.
     *
     * @return int
     */
    public function getProductClassId()
    {
        return $this->product_class_id;
    }

    /**
     * Set subsc_sale_type_id.
     *
     * @param int $subscSaleTypeId
     *
     * @return Vt4gSubscProduct
     */
    public function setSubscSaleTypeId($subscSaleTypeId)
    {
        $this->subsc_sale_type_id = $subscSaleTypeId;

        return $this;
    }

    /**
     * Get subsc_sale_type_id.
     *
     * @return int
     */
    public function getSubscSaleTypeId()
    {
        return $this->subsc_sale_type_id;
    }

    /**
     * Set my_page_disp_flg.
     *
     * @param int $myPageDispFlg
     *
     * @return Vt4gSubscProduct
     */
    public function setMyPageDispFlg($myPageDispFlg)
    {
        $this->my_page_disp_flg = $myPageDispFlg;

        return $this;
    }

    /**
     * Get my_page_disp_flg.
     *
     * @return int
     */
    public function getMyPageDispFlg()
    {
        return $this->my_page_disp_flg;
    }
}
