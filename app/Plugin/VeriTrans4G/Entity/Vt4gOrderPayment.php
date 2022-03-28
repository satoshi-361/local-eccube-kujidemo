<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * Vt4gOrderPayment
 *
 * @ORM\Table(name="plg_vt4g_order_payment")
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Vt4gOrderPaymentRepository")
 *
 */
class Vt4gOrderPayment
{

    /**
     * @var int
     *
     * @ORM\Column(name="order_id", type="integer", options={"unsigned":true})
     * @ORM\Id
     */
     private $order_id;

    /**
     *
     * @var string
     *
     * @ORM\Column(name="memo01", type="text", nullable=true)
     */
    private $memo01;

    /**
     *
     * @var string
     *
     * @ORM\Column(name="memo02", type="text", nullable=true)
     */
    private $memo02;

    /**
     *
     * @var string
     *
     * @ORM\Column(name="memo03", type="text", nullable=true)
     */
    private $memo03;

    /**
     *
     * @var string
     *
     * @ORM\Column(name="memo04", type="text", nullable=true)
     */
    private $memo04;

    /**
     *
     * @var string
     *
     * @ORM\Column(name="memo05", type="text", nullable=true)
     */
    private $memo05;

    /**
     *
     * @var string
     *
     * @ORM\Column(name="memo06", type="text", nullable=true)
     */
    private $memo06;

    /**
     *
     * @var string
     *
     * @ORM\Column(name="memo07", type="text", nullable=true)
     */
    private $memo07;

    /**
     *
     * @var string
     *
     * @ORM\Column(name="memo08", type="text", nullable=true)
     */
    private $memo08;

    /**
     *
     * @var string
     *
     * @ORM\Column(name="memo09", type="text", nullable=true)
     */
    private $memo09;

    /**
     *
     * @var string
     *
     * @ORM\Column(name="memo10", type="text", nullable=true)
     */
    private $memo10;


    /**
     * Set orderId.
     *
     * @param int $orderId
     *
     * @return Vt4gOrderPayment
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
     * Set memo01.
     *
     * @param string|null $memo01
     *
     * @return Vt4gOrderPayment
     */
    public function setMemo01($memo01 = null)
    {
        $this->memo01 = $memo01;

        return $this;
    }

    /**
     * Get memo01.
     *
     * @return string|null
     */
    public function getMemo01()
    {
        return $this->memo01;
    }

    /**
     * Set memo02.
     *
     * @param string|null $memo02
     *
     * @return Vt4gOrderPayment
     */
    public function setMemo02($memo02 = null)
    {
        $this->memo02 = $memo02;

        return $this;
    }

    /**
     * Get memo02.
     *
     * @return string|null
     */
    public function getMemo02()
    {
        return $this->memo02;
    }

    /**
     * Set memo03.
     *
     * @param string|null $memo03
     *
     * @return Vt4gOrderPayment
     */
    public function setMemo03($memo03 = null)
    {
        $this->memo03 = $memo03;

        return $this;
    }

    /**
     * Get memo03.
     *
     * @return string|null
     */
    public function getMemo03()
    {
        return $this->memo03;
    }

    /**
     * Set memo04.
     *
     * @param string|null $memo04
     *
     * @return Vt4gOrderPayment
     */
    public function setMemo04($memo04 = null)
    {
        $this->memo04 = $memo04;

        return $this;
    }

    /**
     * Get memo04.
     *
     * @return string|null
     */
    public function getMemo04()
    {
        return $this->memo04;
    }

    /**
     * Set memo05.
     *
     * @param string|null $memo05
     *
     * @return Vt4gOrderPayment
     */
    public function setMemo05($memo05 = null)
    {
        $this->memo05 = $memo05;

        return $this;
    }

    /**
     * Get memo05.
     *
     * @return string|null
     */
    public function getMemo05()
    {
        return $this->memo05;
    }

    /**
     * Set memo06.
     *
     * @param string|null $memo06
     *
     * @return Vt4gOrderPayment
     */
    public function setMemo06($memo06 = null)
    {
        $this->memo06 = $memo06;

        return $this;
    }

    /**
     * Get memo06.
     *
     * @return string|null
     */
    public function getMemo06()
    {
        return $this->memo06;
    }

    /**
     * Set memo07.
     *
     * @param string|null $memo07
     *
     * @return Vt4gOrderPayment
     */
    public function setMemo07($memo07 = null)
    {
        $this->memo07 = $memo07;

        return $this;
    }

    /**
     * Get memo07.
     *
     * @return string|null
     */
    public function getMemo07()
    {
        return $this->memo07;
    }

    /**
     * Set memo08.
     *
     * @param string|null $memo08
     *
     * @return Vt4gOrderPayment
     */
    public function setMemo08($memo08 = null)
    {
        $this->memo08 = $memo08;

        return $this;
    }

    /**
     * Get memo08.
     *
     * @return string|null
     */
    public function getMemo08()
    {
        return $this->memo08;
    }

    /**
     * Set memo09.
     *
     * @param string|null $memo09
     *
     * @return Vt4gOrderPayment
     */
    public function setMemo09($memo09 = null)
    {
        $this->memo09 = $memo09;

        return $this;
    }

    /**
     * Get memo09.
     *
     * @return string|null
     */
    public function getMemo09()
    {
        return $this->memo09;
    }

    /**
     * Set memo10.
     *
     * @param string|null $memo10
     *
     * @return Vt4gOrderPayment
     */
    public function setMemo10($memo10 = null)
    {
        $this->memo10 = $memo10;

        return $this;
    }

    /**
     * Get memo10.
     *
     * @return string|null
     */
    public function getMemo10()
    {
        return $this->memo10;
    }
}
