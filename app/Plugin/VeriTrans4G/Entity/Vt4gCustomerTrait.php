<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Entity;

use Eccube\Annotation\EntityExtension;
use Doctrine\ORM\Mapping as ORM;

/**
 * @EntityExtension("Eccube\Entity\Customer")
 */
trait Vt4gCustomerTrait
{
    /**
     * ベリトランス会員ID用カラム
     *
     * @var string
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    public $vt4g_account_id;

    /**
     * サービスオプションタイプ<br>
     * 半角英数字
     * - "docomo":ドコモケータイ払い
     * - "au":auかんたん決済
     * - "sb_ktai":ソフトバンクまとめて支払い（B）
     * - "sb_matomete":ソフトバンクまとめて支払い（A）
     * - "s_bikkuri":S!まとめて支払い
     * - "flets":フレッツまとめて支払い
     * 
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\ChoiceType",
     *     options={
     *          "required": false,
     *          "label": "キャリア選択",
     *          "expanded": false,
     *          "multiple": false,
     *          "choices": {
     *              "ドコモ": "docomo",
     *              "au": "au",
     *              "ソフトバンクまとめて支払い（B）": "sb_ktai",
     *              "ソフトバンクまとめて支払い（A）": "sb_matomete",
     *              "S!まとめて支払い": "s_bikkuri",
     *              "フレッツまとめて支払い": "flets",
     *          },
     *     })
     */
    private $service_option_type;

    /**
     * 課金種別
     * 半角数字
     * 最大桁数：1
     * - 0:都度
     * - 1:継続
     * - 2:バーコード
     * - 3:スキャンコード
     * - 4:随時
     * 
     * @var integer
     * @ORM\Column(type="integer", nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\ChoiceType",
     *     options={
     *          "required": false,
     *          "label": "課金種別",
     *          "expanded": false,
     *          "multiple": false,
     *          "choices": {
     *              "都度": 0,
     *              "継続": 1,
     *              "随時": 4,
     *          },
     *     })
     */
    private $accounting_type;
    
    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\TextType",
     *     options={
     *          "required": false,
     *          "label": "初回課金年月日",
     *     })
     */
    private $mp_first_date;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\TextType",
     *     options={
     *          "required": false,
     *          "label": "継続課金日",
     *     })
     */
    private $mp_day;

    /**
     * @return string
     */
    public function getServiceOptionType()
    {
        return $this->service_option_type;
    }

    /**
     * @param string $service_option_type
     *
     * @return $this;
     */
    public function setServiceOptionType($service_option_type)
    {
        $this->service_option_type = $service_option_type;

        return $this;
    }

    /**
     * @return int
     */
    public function getAccountingType()
    {
        return $this->accounting_type;
    }

    /**
     * @param int $accounting_type
     *
     * @return $this;
     */
    public function setAccountingType($accounting_type)
    {
        $this->accounting_type = $accounting_type;

        return $this;
    }

    /**
     * @return string
     */
    public function getMpFirstDate()
    {
        return $this->mp_first_date;
    }

    /**
     * @param string $mp_first_date
     *
     * @return $this;
     */
    public function setMpFirstDate($mp_first_date)
    {
        $this->mp_first_date = $mp_first_date;

        return $this;
    }

    /**
     * @return string
     */
    public function getMpDay()
    {
        return $this->mp_day;
    }

    /**
     * @param string $mp_day
     *
     * @return $this;
     */
    public function setMpDay($mp_day)
    {
        $this->mp_day = $mp_day;

        return $this;
    }
}
