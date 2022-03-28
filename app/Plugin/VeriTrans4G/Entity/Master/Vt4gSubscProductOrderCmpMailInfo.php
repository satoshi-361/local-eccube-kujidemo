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
 * @ORM\Table(name="plg_vt4g_subsc_product_ord_comp_mail_inf", options={"comment":"継続課金商品注文完了メール情報"})
 * @ORM\DiscriminatorColumn(name="discriminator_type", type="string", length=255)
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Master\Vt4gSubscProductOrderCmpMailInfoRepository")
 *
 */
class Vt4gSubscProductOrderCmpMailInfo extends \Eccube\Entity\AbstractEntity
{

    /**
     * @var int
     *
     * @ORM\Column(name="product_id", type="integer", length=10, options={"unsigned":true, "comment":"商品ID"})
     * @ORM\Id
     */
     private $product_id;

     /**
      * @var string
      *
      * @ORM\Column(name="order_cmp_mail_title", type="string", length=50, options={"comment":"注文完了メールタイトル"})
      */
     private $order_cmp_mail_title;

     /**
      * @var string
      *
      * @ORM\Column(name="order_cmp_mail_body", type="string", length=1000, options={"comment":"注文完了メール本文"})
      */
     private $order_cmp_mail_body;

    /**
     * Set product_id.
     *
     * @param int $productId
     *
     * @return Vt4gSubscProductOrderCmpMailInfo
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
     * Set order_cmp_mail_title.
     *
     * @param string $orderCmpMailTitle
     *
     * @return Vt4gSubscProductOrderCmpMailInfo
     */
    public function setOrderCmpMailTitle($orderCmpMailTitle)
    {
        $this->order_cmp_mail_title = $orderCmpMailTitle;

        return $this;
    }

    /**
     * Get order_cmp_mail_title.
     *
     * @return string
     */
    public function getOrderCmpMailTitle()
    {
        return $this->order_cmp_mail_title;
    }

    /**
     * Set order_cmp_mail_body.
     *
     * @param string $orderCmpMailBody
     *
     * @return Vt4gSubscProductOrderCmpMailInfo
     */
    public function setOrderCompMailBody($orderCmpMailBody)
    {
        $this->order_cmp_mail_body = $orderCmpMailBody;

        return $this;
    }

    /**
     * Get order_cmp_mail_body.
     *
     * @return string
     */
    public function getOrderCompMailBody()
    {
        return $this->order_cmp_mail_body;
    }

}
