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
 * @ORM\Table(name="plg_vt4g_csv_result_log", options={"comment":"CSV決済結果ログテーブル"})
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Vt4gCsvResultLogRepository")
 *
 */
class Vt4gCsvResultLog extends \Eccube\Entity\AbstractEntity
{

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", length=11, options={"unsigned":true, "comment":"ログID"})
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
     * @var string
     *
     * @ORM\Column(name="result_code", type="text", length=65532, nullable=true, options={"default":null, "comment":"処理結果コード"})
     */
    private $result_code = null;

    /**
     * @var string
     *
     * @ORM\Column(name="err_message", type="text", length=65532, nullable=true, options={"default":null, "comment":"エラーメッセージ"})
     */
    private $err_message = null;


    /**
     * Set $id.
     *
     * @param int $id
     *
     * @return Vt4gCsvResultLog
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
     * @return Vt4gCsvResultLog
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
     * Set $result_code.
     *
     * @param string $resultCode
     *
     * @return Vt4gCsvResultLog
     */
    public function setResultCode($resultCode)
    {
        $this->result_code = $resultCode;

        return $this;
    }

    /**
     * Get $result_code.
     *
     * @return string
     */
    public function getResultCode()
    {
        return $this->result_code;
    }

    /**
     * Set $err_message.
     *
     * @param string $errMessage
     *
     * @return Vt4gCsvResultLog
     */
    public function setErrMessage($errMessage)
    {
        $this->err_message = $errMessage;

        return $this;
    }

    /**
     * Get $err_message.
     *
     * @return string
     */
    public function getErrMessage()
    {
        return $this->err_message;
    }
}
