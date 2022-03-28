<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * Vt4gPaymentRequest
 *
 * @ORM\Table(name="plg_vt4g_payment_request", options={"comment":"決済依頼テーブル"})
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Vt4gPaymentRequestRepository")
 *
 */
class Vt4gPaymentRequest extends \Eccube\Entity\AbstractEntity
{

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", length=11, options={"unsigned":true, "comment":"決済依頼ID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="req_event_id", type="integer", length=11, options={"unsigned":true, "comment":"決済依頼イベントID"})
     */
    private $req_event_id;

    /**
     * @var int
     *
     * @ORM\Column(name="customer_id", type="integer", length=11, options={"unsigned":true, "comment":"会員ID"})
     */
    private $customer_id;

    /**
     * @var int
     *
     * @ORM\Column(name="first_order_id", type="integer", length=11, options={"unsigned":true, "comment":"初回注文番号"})
     */
    private $first_order_id;

    /**
     * @var string
     *
     * @ORM\Column(name="transaction_id", type="text", length=65532, nullable=true, options={"default":null, "comment":"取引ID"})
     */
    private $transaction_id = null;

    /**
     * @var string
     *
     * @ORM\Column(name="order_total", type="decimal", precision=12, scale=2, options={"default":0, "comment":"合計商品金額"})
     */
    private $order_total = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="delivery_fee_total", type="decimal", precision=12, scale=2, options={"default":0, "comment":"合計送料"})
     */
    private $delivery_fee_total = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="settlement_fee", type="decimal", precision=12, scale=2, options={"default":0, "comment":"決済手数料"})
     */
    private $settlement_fee = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="point_total", type="decimal", precision=12, scale=2, options={"default":0, "comment":"合計付与ポイント"})
     */
    private $point_total = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="request_status", type="smallint", length=5, options={"unsigned":true, "default":0, "comment":"決済依頼ステータス"})
     */
    private $request_status=0;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="reflect_date", type="datetime", nullable=true, options={"default":null, "comment":"決済反映日"})
     */
    private $reflect_date = null;


    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     */
    private $pay_req_items;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->pay_req_items = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add pay_req_item.
     *
     * @param \Plugin\VeriTrans4G\Entity\Vt4gPaymentRequestItem $pay_req_item
     *
     * @return Vt4gPaymentRequest
     */
    public function addPayReqItem(\Plugin\VeriTrans4G\Entity\Vt4gPaymentRequestItem $pay_req_item)
    {
        $this->pay_req_items[] = $pay_req_item;

        return $this;
    }

    /**
     * Remove pay_req_item.
     *
     * @param \Plugin\VeriTrans4G\Entity\Vt4gPaymentRequestItem $pay_req_item
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removePayReqItem(\Plugin\VeriTrans4G\Entity\Vt4gPaymentRequestItem $pay_req_item)
    {
        return $this->pay_req_items->removeElement($pay_req_item);
    }

    /**
     * Get pay_req_items.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPayReqItems()
    {
        return $this->pay_req_items;
    }


    /**
     * Set $id.
     *
     * @param int $id
     *
     * @return Vt4gPaymentRequest
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
     * Set $req_event_id.
     *
     * @param int $reqEventId
     *
     * @return Vt4gPaymentRequest
     */
    public function setReqEventId($reqEventId)
    {
        $this->req_event_id = $reqEventId;

        return $this;
    }

    /**
     * Get $req_event_id.
     *
     * @return int
     */
    public function getReqEventId()
    {
        return $this->req_event_id;
    }

    /**
     * Set $customer_id.
     *
     * @param int $customerId
     *
     * @return Vt4gPaymentRequest
     */
    public function setCustomerId($customerId)
    {
        $this->customer_id = $customerId;

        return $this;
    }

    /**
     * Get $customer_id.
     *
     * @return int
     */
    public function getCustomerId()
    {
        return $this->customer_id;
    }

    /**
     * Set $first_order_id.
     *
     * @param int $firstOrderId
     *
     * @return Vt4gPaymentRequest
     */
    public function setFirstOrderId($firstOrderId)
    {
        $this->first_order_id = $firstOrderId;

        return $this;
    }

    /**
     * Get $first_order_id.
     *
     * @return int
     */
    public function getFirstOrderId()
    {
        return $this->first_order_id;
    }

    /**
     * Set $transaction_id.
     *
     * @param int $transactionId
     *
     * @return Vt4gPaymentRequest
     */
    public function setTransactionId($transactionId)
    {
        $this->transaction_id = $transactionId;

        return $this;
    }

    /**
     * Get $transaction_id.
     *
     * @return int
     */
    public function getTransactionId()
    {
        return $this->transaction_id;
    }

    /**
     * Set $order_total.
     *
     * @param int $orderTotal
     *
     * @return Vt4gPaymentRequest
     */
    public function setOrderTotal($orderTotal)
    {
        $this->order_total = $orderTotal;

        return $this;
    }

    /**
     * Get $order_total.
     *
     * @return int
     */
    public function getOrderTotal()
    {
        return $this->order_total;
    }

    /**
     * Set $delivery_fee_total.
     *
     * @param int $deliveryFeeTotal
     *
     * @return Vt4gPaymentRequest
     */
    public function setDeliveryFeeTotal($deliveryFeeTotal)
    {
        $this->delivery_fee_total = $deliveryFeeTotal;

        return $this;
    }

    /**
     * Get $delivery_fee_total.
     *
     * @return int
     */
    public function getDeliveryFeeTotal()
    {
        return $this->delivery_fee_total;
    }

    /**
     * Set $settlement_fee.
     *
     * @param int $settlementFee
     *
     * @return Vt4gPaymentRequest
     */
    public function setSettlementFee($settlementFee)
    {
        $this->settlement_fee = $settlementFee;

        return $this;
    }

    /**
     * Get $settlement_fee.
     *
     * @return int
     */
    public function getSettlementFee()
    {
        return $this->settlement_fee;
    }

    /**
     * Set $point_total.
     *
     * @param int $pointTotal
     *
     * @return Vt4gPaymentRequest
     */
    public function setPointTotal($pointTotal)
    {
        $this->point_total = $pointTotal;

        return $this;
    }

    /**
     * Get $point_total.
     *
     * @return int
     */
    public function getPointTotal()
    {
        return $this->point_total;
    }

    /**
     * Set $request_status.
     *
     * @param int $requestStatus
     *
     * @return Vt4gPaymentRequest
     */
    public function setRequestStatus($requestStatus)
    {
        $this->request_status = $requestStatus;

        return $this;
    }

    /**
     * Get $request_status.
     *
     * @return int
     */
    public function getRequestStatus()
    {
        return $this->request_status;
    }

    /**
     * Set $reflect_date.
     *
     * @param int $reflectDate
     *
     * @return Vt4gPaymentRequest
     */
    public function setReflectDate($reflectDate)
    {
        $this->reflect_date = $reflectDate;

        return $this;
    }

    /**
     * Get $reflect_date.
     *
     * @return \DateTime $reflect_date
     */
    public function getReflectDate()
    {
        return $this->reflect_date;
    }
}
