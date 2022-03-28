<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * Vt4gPaymentRequestItem
 *
 * @ORM\Table(name="plg_vt4g_payment_request_item", options={"comment":"決済依頼明細テーブル"})
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Vt4gPaymentRequestItemRepository")
 *
 */
class Vt4gPaymentRequestItem extends \Eccube\Entity\AbstractEntity
{

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", length=11, options={"unsigned":true, "comment":"決済依頼明細ID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="request_id", type="integer", length=11, options={"unsigned":true, "comment":"決済依頼ID"})
     */
    private $request_id;

    /**
     * @var int
     *
     * @ORM\Column(name="subsc_sale_type_id", type="integer", length=11, options={"unsigned":true, "comment":"継続課金販売種別ID"})
     */
    private $subsc_sale_type_id;

    /**
     * @var int
     *
     * @ORM\Column(name="shipping_id", type="integer", length=11, options={"unsigned":true, "comment":"出荷ID"})
     */
    private $shipping_id;

    /**
     * @var int
     *
     * @ORM\Column(name="order_item_type_id", type="integer", length=11, options={"unsigned":true, "comment":"明細区分"})
     */
    private $order_item_type_id;

    /**
     * @var int
     *
     * @ORM\Column(name="product_id", type="integer", length=11, nullable=true, options={"unsigned":true, "default":null, "comment":"商品ID"})
     */
    private $product_id = null;

    /**
     * @var int
     *
     * @ORM\Column(name="product_class_id", type="integer", length=11, nullable=true, options={"unsigned":true, "default":null, "comment":"商品規格ID"})
     */
    private $product_class_id = null;

    /**
     * @var string
     *
     * @ORM\Column(name="amount", type="decimal", precision=12, scale=2, options={"default":0.00, "comment":"金額"})
     */
    private $amount = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="quantity", type="decimal", precision=10, scale=0, options={"default":0, "comment":"数量"})
     */
    private $quantity = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="point", type="decimal", precision=12, scale=0, options={"default":0, "comment":"付与ポイント"})
     */
    private $point = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="payment_target", type="smallint", length=5, options={"unsigned":true, "default":1, "comment":"決済対象フラグ"})
     */
    private $payment_target = 1;

    /**
     * Set $id.
     *
     * @param int $Id
     *
     * @return Vt4gPaymentRequestItem
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get $id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set $request_id.
     *
     * @param int $requestId
     *
     * @return Vt4gPaymentRequestItem
     */
    public function setRequestId($requestId)
    {
        $this->request_id = $requestId;

        return $this;
    }

    /**
     * Get $request_id.
     *
     * @return int
     */
    public function getRequestId()
    {
        return $this->request_id;
    }

    /**
     * Set $subsc_sale_type_id.
     *
     * @param int $subscSaleTypeId
     *
     * @return Vt4gPaymentRequestItem
     */
    public function setSubscSaleTypeId($subscSaleTypeId)
    {
        $this->subsc_sale_type_id = $subscSaleTypeId;

        return $this;
    }

    /**
     * Get $subsc_sale_type_id.
     *
     * @return int
     */
    public function getSubscSaleTypeId()
    {
        return $this->subsc_sale_type_id;
    }

    /**
     * Set $shipping_id.
     *
     * @param int $shippingId
     *
     * @return Vt4gPaymentRequestItem
     */
    public function setShippingId($shippingId)
    {
        $this->shipping_id = $shippingId;

        return $this;
    }

    /**
     * Get $shipping_id.
     *
     * @return int
     */
    public function getShippingId()
    {
        return $this->shipping_id;
    }

    /**
     * Set $order_item_type_id.
     *
     * @param int $orderItemTypeId
     *
     * @return Vt4gPaymentRequestItem
     */
    public function setOrderItemTypeId($orderItemTypeId)
    {
        $this->order_item_type_id = $orderItemTypeId;

        return $this;
    }

    /**
     * Get $order_item_type_id.
     *
     * @return int
     */
    public function getOrderItemTypeId()
    {
        return $this->order_item_type_id;
    }

    /**
     * Set $product_id.
     *
     * @param int $productId
     *
     * @return Vt4gPaymentRequestItem
     */
    public function setProductId($productId)
    {
        $this->product_id = $productId;

        return $this;
    }

    /**
     * Get $product_id.
     *
     * @return int
     */
    public function getProductId()
    {
        return $this->product_id;
    }

    /**
     * Set $product_class_id.
     *
     * @param int $productClassId
     *
     * @return Vt4gPaymentRequestItem
     */
    public function setProductClassId($productClassId)
    {
        $this->product_class_id = $productClassId;

        return $this;
    }

    /**
     * Get $product_class_id.
     *
     * @return int
     */
    public function getProductClassId()
    {
        return $this->product_class_id;
    }

    /**
     * Set $amount.
     *
     * @param int $amount
     *
     * @return Vt4gPaymentRequestItem
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get $amount.
     *
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set $quantity.
     *
     * @param int $quantity
     *
     * @return Vt4gPaymentRequestItem
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Get $quantity.
     *
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Set $point.
     *
     * @param int $point
     *
     * @return Vt4gPaymentRequestItem
     */
    public function setPoint($point)
    {
        $this->point = $point;

        return $this;
    }

    /**
     * Get $point.
     *
     * @return int
     */
    public function getPoint()
    {
        return $this->point;
    }

    /**
     * Set $payment_target.
     *
     * @param int $paymentTarget
     *
     * @return Vt4gPaymentRequestItem
     */
    public function setPaymentTarget($paymentTarget)
    {
        $this->payment_target = $paymentTarget;

        return $this;
    }

    /**
     * Get $payment_target.
     *
     * @return int
     */
    public function getPaymentTarget()
    {
        return $this->payment_target;
    }
}
