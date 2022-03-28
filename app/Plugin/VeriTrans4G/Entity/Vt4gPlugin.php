<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Vt4gPlugin
 *
 * @ORM\Table(name="plg_vt4g_plugin")
 * @ORM\Entity(repositoryClass="Plugin\VeriTrans4G\Repository\Vt4gPluginRepository")
 */
class Vt4gPlugin
{
    /**
     * @var int
     *
     * @ORM\Column(name="plugin_id", type="integer", length=11, options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $plugin_id;

    /**
     * @var string
     *
     * @ORM\Column(name="plugin_code", type="text")
     */
    private $plugin_code;

    /**
     * @var string
     *
     * @ORM\Column(name="plugin_name", type="text")
     */
    private $plugin_name;

    /**
     * @var string
     *
     * @ORM\Column(name="sub_data", type="text", nullable=true)
     */
    private $sub_data;

    /**
     * @var int
     *
     * @ORM\Column(name="auto_update_flg", type="smallint", length=6, options={"default" = 0})
     */
    private $auto_update_flg;


    /**
     * @var int
     *
     * @ORM\Column(name="del_flg", type="smallint", length=6, options={"default" = 0})
     */
    private $del_flg;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="create_date", type="datetime")
     */
    private $create_date;


    /**
     * @var \DateTime
     *
     * @ORM\Column(name="update_date", type="datetime")
     */
    private $update_date;



    /**
     * @return int
     */
    public function getId()
    {
        return $this->plugin_id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->plugin_code;
    }

    /**
     * @param string $plugin_code
     *
     * @return $this;
     */
    public function setName($plugin_code)
    {
        $this->plugin_code = $plugin_code;

        return $this;
    }

    /**
     * Get pluginId.
     *
     * @return int
     */
    public function getPluginId()
    {
        return $this->plugin_id;
    }

    /**
     * Set pluginCode.
     *
     * @param string $pluginCode
     *
     * @return $this
     */
    public function setPluginCode($pluginCode)
    {
        $this->plugin_code = $pluginCode;

        return $this;
    }

    /**
     * Get pluginCode.
     *
     * @return string
     */
    public function getPluginCode()
    {
        return $this->plugin_code;
    }

    /**
     * Set pluginName.
     *
     * @param string $pluginName
     *
     * @return $this
     */
    public function setPluginName($pluginName)
    {
        $this->plugin_name = $pluginName;

        return $this;
    }

    /**
     * Get pluginName.
     *
     * @return string
     */
    public function getPluginName()
    {
        return $this->plugin_name;
    }

    /**
     * Set subData.
     *
     * @param string|null $subData
     *
     * @return $this
     */
    public function setSubData($subData = null)
    {
        $this->sub_data = $subData;

        return $this;
    }

    /**
     * Get subData.
     *
     * @return string|null
     */
    public function getSubData()
    {
        return $this->sub_data;
    }

    /**
     * Set autoUpdateFlg.
     *
     * @param int $autoUpdateFlg
     *
     * @return $this
     */
    public function setAutoUpdateFlg($autoUpdateFlg)
    {
        $this->auto_update_flg = $autoUpdateFlg;

        return $this;
    }

    /**
     * Get autoUpdateFlg.
     *
     * @return int
     */
    public function getAutoUpdateFlg()
    {
        return $this->auto_update_flg;
    }

    /**
     * Set delFlg.
     *
     * @param int $delFlg
     *
     * @return $this
     */
    public function setDelFlg($delFlg)
    {
        $this->del_flg = $delFlg;

        return $this;
    }

    /**
     * Get delFlg.
     *
     * @return int
     */
    public function getDelFlg()
    {
        return $this->del_flg;
    }

    /**
     * Set createDate.
     *
     * @param \DateTime $createDate
     *
     * @return $this
     */
    public function setCreateDate($createDate)
    {
        $this->create_date = $createDate;

        return $this;
    }

    /**
     * Get createDate.
     *
     * @return \DateTime
     */
    public function getCreateDate()
    {
        return $this->create_date;
    }

    /**
     * Set updateDate.
     *
     * @param \DateTime $updateDate
     *
     * @return $this
     */
    public function setUpdateDate($updateDate)
    {
        $this->update_date = $updateDate;

        return $this;
    }

    /**
     * Get updateDate.
     *
     * @return \DateTime
     */
    public function getUpdateDate()
    {
        return $this->update_date;
    }
}
