<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * Vt4gSubscOrderItem
 *
 * @ORM\Table(name="plg_vt4g_subsc_order_item", options={"comment":"継続課金注文明細テーブル"})
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Vt4gSubscOrderItemRepository")
 *
 */
class Vt4gSubscOrderItem extends \Eccube\Entity\AbstractEntity
{

    /**
     * @var int
     *
     * @ORM\Column(name="order_id", type="integer", length=11, options={"unsigned":true, "comment":"注文ID"})
     * @ORM\Id
     */
    private $order_id;

    /**
     * @var int
     *
     * @ORM\Column(name="product_id", type="integer", length=11, options={"unsigned":true, "comment":"商品ID"})
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
     * @ORM\Column(name="shipping_id", type="integer", length=10, options={"unsigned":true, "comment":"出荷ID"})
     * @ORM\Id
     */
    private $shipping_id;

    /**
     * @var int
     *
     * @ORM\Column(name="subsc_status", type="smallint", length=5, options={"unsigned":true, "comment":"継続課金ステータス"})
     */
    private $subsc_status;

    /**
     * Set $orderId.
     *
     * @param int $orderId
     *
     * @return Vt4gSubscOrderItem
     */
    public function setOrderId($orderId)
    {
        $this->order_id = $orderId;

        return $this;
    }

    /**
     * Get $orderId.
     *
     * @return int
     */
    public function getOrderId()
    {
        return $this->order_id;
    }

    /**
     * Set $productId.
     *
     * @param int $productId
     *
     * @return Vt4gSubscOrderItem
     */
    public function setProductId($productId)
    {
        $this->product_id = $productId;

        return $this;
    }

    /**
     * Get $productId.
     *
     * @return int
     */
    public function getProductId()
    {
        return $this->product_id;
    }

    /**
     * Set $productClassId.
     *
     * @param int $product_class_id
     *
     * @return Vt4gSubscOrderItem
     */
    public function setProductClassId($productClassId)
    {
        $this->product_class_id = $productClassId;

        return $this;
    }

    /**
     * Get $productClassId.
     *
     * @return int
     */
    public function getProductClassId()
    {
        return $this->product_class_id;
    }

    /**
     * Set $shippingId.
     *
     * @param int $shipping_id
     *
     * @return Vt4gSubscOrderItem
     */
    public function setShippingId($shippingId)
    {
        $this->shipping_id = $shippingId;

        return $this;
    }

    /**
     * Get $shippingId.
     *
     * @return int
     */
    public function getShippingId()
    {
        return $this->shipping_id;
    }

    /**
     * Set $subscStatus.
     *
     * @param int $subscStatus
     *
     * @return Vt4gSubscOrderItem
     */
    public function setSubscStatus($subscStatus)
    {
        $this->subsc_status = $subscStatus;

        return $this;
    }

    /**
     * Get $subscStatus.
     *
     * @return int
     */
    public function getSubscStatus()
    {
        return $this->subsc_status;
    }
}
