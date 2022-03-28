<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * Vt4gSubscOrder
 *
 * @ORM\Table(name="plg_vt4g_subsc_order", options={"comment":"継続課金注文テーブル"})
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Vt4gSubscOrderRepository")
 *
 */
class Vt4gSubscOrder extends \Eccube\Entity\AbstractEntity
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
     * @ORM\Column(name="customer_id", type="integer", length=11, options={"unsigned":true, "comment":"会員ID"})
     */
    private $customer_id;

    /**
     * @var int
     *
     * @ORM\Column(name="subsc_sale_type_id", type="integer", length=11, options={"unsigned":true, "comment":"継続課金販売種別ID"})
     */
    private $subsc_sale_type_id;

    /**
     * @var int|null
     *
     * @ORM\Column(name="latest_payment_req_no", type="integer", length=11, nullable=true, options={"unsigned":true, "comment":"最新決済依頼番号"})
     */
    private $latest_payment_req_no = null;

    /**
     * Set orderId.
     *
     * @param int $orderId
     *
     * @return Vt4gSubscOrder
     */
    public function setOrderId($orderId)
    {
        $this->order_id = $orderId;

        return $this;
    }

    /**
     * Get orderId.
     *
     * @return int
     */
    public function getOrderId()
    {
        return $this->order_id;
    }

    /**
     * Set customerId.
     *
     * @param int $customerId
     *
     * @return Vt4gSubscOrder
     */
    public function setCustomerId($customerId)
    {
        $this->customer_id = $customerId;

        return $this;
    }

    /**
     * Get customerId.
     *
     * @return int
     */
    public function getCustomerId()
    {
        return $this->customer_id;
    }

    /**
     * Set subscSaleTypeId.
     *
     * @param int $subscSaleTypeId
     *
     * @return Vt4gSubscOrder
     */
    public function setSubscSaleTypeId($subscSaleTypeId)
    {
        $this->subsc_sale_type_id = $subscSaleTypeId;

        return $this;
    }

    /**
     * Get subscSaleTypeId.
     *
     * @return int
     */
    public function getSubscSaleTypeId()
    {
        return $this->subsc_sale_type_id;
    }

    /**
     * Set $latest_payment_req_no.
     *
     * @param int $latestPaymentReqNo
     *
     * @return Vt4gSubscOrder
     */
    public function setLatestPaymentReqNo($latestPaymentReqNo)
    {
        $this->latest_payment_req_no = $latestPaymentReqNo;

        return $this;
    }

    /**
     * Get latestPaymentReqNo.
     *
     * @return int
     */
    public function getLatestPaymentReqNo()
    {
        return $this->latest_payment_req_no;
    }

}
