<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright (c) 2006 PAYGENT Co.,Ltd. All rights reserved.
 *
 * https://www.paygent.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Plugin\PaygentPayment\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Order")
 */
trait OrderTrait
{
    /**
     * @var string
     * @ORM\Column(type="text", length=36777215, nullable=true)
     */
    private $paygent_code;

    /**
     * @var string
     * @ORM\Column(type="text", length=36777215, nullable=true)
     */
    private $response_detail;

    /**
     * @var string
     * @ORM\Column(type="text", length=36777215, nullable=true)
     */
    private $response_result;

    /**
     * @var string
     * @ORM\Column(type="text", length=36777215, nullable=true)
     */
    private $response_code;

    /**
     * @var string
     * @ORM\Column(type="text", length=36777215, nullable=true)
     */
    private $paygent_error;

    /**
     * @var string
     * @ORM\Column(type="text", length=36777215, nullable=true)
     */
    private $paygent_payment_id;

    /**
     * @var string
     * @ORM\Column(type="text", length=36777215, nullable=true)
     */
    private $paygent_payment_status;

    /**
     * @var string
     * @ORM\Column(type="text", length=36777215, nullable=true)
     */
    private $paygent_payment_method;

    /**
     * @var string
     * @ORM\Column(type="text", length=36777215, nullable=true)
     */
    private $paygent_kind;

    /**
     * @var string
     * @ORM\Column(type="text", length=36777215, nullable=true)
     */
    private $payment_notice_id;

    /**
     * @var string
     * @ORM\Column(type="text", length=36777215, nullable=true)
     */
    private $paygent_credit_subdata;

    /**
     * @return string
     */
    public function getPaygentCode()
    {
        return $this->paygent_code;
    }

    /**
     * @param string $paygent_code
     * @return $this
     */
    public function setPaygentCode($paygent_code)
    {
        $this->paygent_code = $paygent_code;

        return $this;
    }

    /**
     * @return string
     */
    public function getResponseDetail()
    {
        return $this->response_detail;
    }

    /**
     * @param string $response_detail
     * @return $this
     */
    public function setResponseDetail($response_detail)
    {
        $this->response_detail = $response_detail;

        return $this;
    }

    /**
     * @return string
     */
    public function getResponseResult()
    {
        return $this->response_result;
    }

    /**
     * @param string $response_result
     * @return $this
     */
    public function setResponseResult($response_result)
    {
        $this->response_result = $response_result;

        return $this;
    }

    /**
     * @return string
     */
    public function getResponseCode()
    {
        return $this->response_code;
    }

    /**
     * @param string $response_code
     * @return $this
     */
    public function setResponseCode($response_code)
    {
        $this->response_code = $response_code;

        return $this;
    }

    /**
     * @return string
     */
    public function getPaygentError()
    {
        return $this->paygent_error;
    }

    /**
     * @param string $paygent_error
     * @return $this
     */
    public function setPaygentError($paygent_error)
    {
        $this->paygent_error = $paygent_error;

        return $this;
    }

    /**
     * @return string
     */
    public function getPaygentPaymentId()
    {
        return $this->paygent_payment_id;
    }

    /**
     * @param string $paygent_payment_id
     * @return $this
     */
    public function setPaygentPaymentId($paygent_payment_id)
    {
        $this->paygent_payment_id = $paygent_payment_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getPaygentPaymentStatus()
    {
        return $this->paygent_payment_status;
    }

    /**
     * @param string $paygent_payment_status
     * @return $this
     */
    public function setPaygentPaymentStatus($paygent_payment_status)
    {
        $this->paygent_payment_status = $paygent_payment_status;

        return $this;
    }

    /**
     * @return string
     */
    public function getPaygentPaymentMethod()
    {
        return $this->paygent_payment_method;
    }

    /**
     * @param string $paygent_payment_method
     * @return $this
     */
    public function setPaygentPaymentMethod($paygent_payment_method)
    {
        $this->paygent_payment_method = $paygent_payment_method;

        return $this;
    }

    /**
     * @return string
     */
    public function getPaygentKind()
    {
        return $this->paygent_kind;
    }

    /**
     * @param string $paygent_kind
     * @return $this
     */
    public function setPaygentKind($paygent_kind)
    {
        $this->paygent_kind = $paygent_kind;

        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentNoticeId()
    {
        return $this->payment_notice_id;
    }

    /**
     * @param string $payment_notice_id
     * @return $this
     */
    public function setPaymentNoticeId($payment_notice_id)
    {
        $this->payment_notice_id = $payment_notice_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getPaygentCreditSubdata()
    {
        return $this->paygent_credit_subdata;
    }

    /**
     * @param string $paygent_credit_subdata
     * @return $this
     */
    public function setPaygentCreditSubdata($paygent_credit_subdata)
    {
        $this->paygent_credit_subdata = $paygent_credit_subdata;

        return $this;
    }

}
