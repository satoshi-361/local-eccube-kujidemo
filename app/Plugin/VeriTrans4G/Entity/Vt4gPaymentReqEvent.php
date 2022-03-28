<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * Vt4gPaymentReqEvent
 *
 * @ORM\Table(name="plg_vt4g_payment_req_event", options={"comment":"決済依頼イベントテーブル"})
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Vt4gPaymentReqEventRepository")
 *
 */
class Vt4gPaymentReqEvent extends \Eccube\Entity\AbstractEntity
{

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", length=11, options={"unsigned":true, "comment":"決済依頼イベントID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="sale_type_id", type="integer", length=11, options={"unsigned":true, "comment":"継続課金販売種別ID"})
     */
    private $sale_type_id;

    /**
     * @var string
     *
     * @ORM\Column(name="event_name", type="text", length=65532, options={"comment":"継続課金ステータス"})
     */
    private $event_name;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="csv_create_date", type="datetime", nullable=true, options={"default":null, "comment":"CSV作成日"})
     */
    private $csv_create_date = null;

    /**
     * @var string
     *
     * @ORM\Column(name="file_name", type="text", length=65532, options={"comment":"CSVファイル名"})
     */
    private $file_name;

    /**
     * Set $id.
     *
     * @param int $Id
     *
     * @return Vt4gPaymentReqEvent
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
     * Set $saleTypeId.
     *
     * @param int $saleTypeId
     *
     * @return Vt4gPaymentReqEvent
     */
    public function setSaleTypeId($saleTypeId)
    {
        $this->sale_type_id = $saleTypeId;

        return $this;
    }

    /**
     * Get $saleTypeId.
     *
     * @return int
     */
    public function getSaleTypeId()
    {
        return $this->sale_type_id;
    }

    /**
     * Set $eventName.
     *
     * @param string $eventName
     *
     * @return Vt4gPaymentReqEvent
     */
    public function setEventName($eventName)
    {
        $this->event_name = $eventName;

        return $this;
    }

    /**
     * Get $event_name.
     *
     * @return string
     */
    public function getEventName()
    {
        return $this->event_name;
    }

    /**
     * Set $csv_create_date.
     *
     * @param int $csvCreateDate
     *
     * @return Vt4gPaymentReqEvent
     */
    public function setCsvCreateDate($csvCreateDate)
    {
        $this->csv_create_date = $csvCreateDate;

        return $this;
    }

    /**
     * Get $csv_create_date.
     *
     * @return \DateTime $csv_create_date
     */
    public function getCsvCreateDate()
    {
        return $this->csv_create_date;
    }

    /**
     * Set $fileName.
     *
     * @param string $fileName
     *
     * @return Vt4gPaymentReqEvent
     */
    public function setFileName($fileName)
    {
        $this->file_name = $fileName;

        return $this;
    }

    /**
     * Get $fileName.
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->file_name;
    }
}
