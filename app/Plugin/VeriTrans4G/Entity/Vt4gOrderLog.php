<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * Vt4gOrderLog
 *
 * @ORM\Table(name="plg_vt4g_order_log", indexes={ 
 * @ORM\Index(name="plg_vt4g_order_log_order_id_idx", columns={"order_id"}) 
 * })
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Vt4gOrderLogRepository")
 *
 */
class Vt4gOrderLog
{

    /**
     * @var int
     *
     * @ORM\Column(name="log_id", type="integer", length=11, options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
     private $log_id;

    /**
     * @var int
     *
     * @ORM\Column(name="order_id", type="integer", length=11, options={"unsigned":true})
     */
    private $order_id;

    /**
     *
     * @var string
     *
     * @ORM\Column(name="vt4g_log", type="text", nullable=true)
     */
    private $vt4g_log;



    /**
     * Get logId.
     *
     * @return int
     */
    public function getLogId()
    {
        return $this->log_id;
    }

    /**
     * Set orderId.
     *
     * @param int $orderId
     *
     * @return Vt4gOrderLog
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
     * Set vt4gLog.
     *
     * @param string|null $vt4gLog
     *
     * @return Vt4gOrderLog
     */
    public function setVt4gLog($vt4gLog = null)
    {
        $this->vt4g_log = $vt4gLog;

        return $this;
    }

    /**
     * Get vt4gLog.
     *
     * @return string|null
     */
    public function getVt4gLog()
    {
        return $this->vt4g_log;
    }
}
