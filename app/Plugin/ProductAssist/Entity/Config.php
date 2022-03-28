<?php

namespace Plugin\ProductAssist\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Eccube\Entity\Product;

/**
 * Config
 *
 * @ORM\Table(name="plg_product_assist_config")
 * @ORM\Entity(repositoryClass="Plugin\ProductAssist\Repository\ConfigRepository")
 */
class Config
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="bulk_config_lottery", type="integer", nullable=true)
     */
    private $bulk_config_lottery;

    /**
     * @var int
     * 
     * @ORM\Column(name="product_link_lottery", type="integer", nullable=true)
     */
    private $product_link_lottery;

    /**
     * @var string
     * 
     * @ORM\Column(name="cart_button_text", type="string", nullable=true)
     */
    private $cart_button_text;

    /**
     * @var int
     * 
     * @ORM\Column(name="ship_count", type="integer", nullable=true)
     */
    private $ship_count;

    /**
     * @var bool
     * 
     * @ORM\Column(name="is_animate", type="boolean", nullable=true)
     */
    private $is_animate;

    /**
     * @var int
     * 
     * @ORM\Column(name="winning_count", type="integer", nullable=true)
     */
    private $winning_count;

    /**
     * @var string
     * 
     * @ORM\Column(name="free_text", type="string", nullable=true)
     */
    private $free_text;

    /**
     * @var string
     * 
     * @ORM\Column(name="delivery_day_text", type="string", nullable=true)
     */
    private $delivery_day_text;

    /**
     * @var string
     * 
     * @ORM\Column(name="sale_end_text", type="string", nullable=true)
     */
    private $sale_end_text;

    /**
     * @ORM\OneToMany(
     *      targetEntity="\Plugin\ProductAssistConfig\Entity\Config",
     *      mappedBy="product_assist",
     *      cascade={"persist", "remove"}
     * )
     */
    protected $settings;
	
	/**
     * @var int
     *
     * @ORM\Column(name="product_id", type="integer")
     */
    public $product_id;

    public function __construct()
    {
        $this->settings = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function setProduct(?Product $product)
    {
        $this->product = $product;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    /**
     * @return int
     */
    public function getBulkConfigLottery()
    {
        return $this->bulk_config_lottery;
    }

    /**
     * @param int $bcl
     *
     * @return $this;
     */
    public function setBulkConfigLottery($bcl)
    {
        $this->bulk_config_lottery = $bcl;

        return $this;
    }

    /**
     * @return int
     */
    public function getProductLinkLottery()
    {
        return $this->product_link_lottery;
    }

    /**
     * @param int $bcl
     *
     * @return $this;
     */
    public function setProductLinkLottery($pll)
    {
        $this->product_link_lottery = $pll;

        return $this;
    }

    /**
     * @return string
     */
    public function getCartButtonText()
    {
        return $this->cart_button_text;
    }

    /**
     * @param string $cbt
     *
     * @return $this;
     */
    public function setCartButtonText($cbt)
    {
        $this->cart_button_text = $cbt;

        return $this;
    }

    public function getSettings() : Collection
    {
        return $this->settings;
    }

    public function addSetting($setting)
    {
        $this->settings->add($setting);
    }

    /**
     * @return int
     */
    public function getShipCount()
    {
        return $this->ship_count;
    }

    /**
     * @param int $sc
     *
     * @return $this;
     */
    public function setShipCount($sc)
    {
        $this->ship_count = $sc;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIsAnimate()
    {
        return $this->is_animate;
    }

    /**
     * @param bool $ia
     *
     * @return $this;
     */
    public function setIsAnimate($ia)
    {
        $this->is_animate = $ia;

        return $this;
    }

    /**
     * @return int
     */
    public function getWinningCount()
    {
        return $this->winning_count;
    }

    /**
     * @param int $wc
     *
     * @return $this;
     */
    public function setWinningCount($wc)
    {
        $this->winning_count = $wc;

        return $this;
    }

    /**
     * @return string
     */
    public function getFreeText()
    {
        return $this->free_text;
    }

    /**
     * @param string $ft
     *
     * @return $this;
     */
    public function setFreeText($ft)
    {
        $this->free_text = $ft;

        return $this;
    }

    /**
     * @return string
     */
    public function getDeliveryDayText()
    {
        return $this->delivery_day_text;
    }

    /**
     * @param string $ddt
     *
     * @return $this;
     */
    public function setDeliveryDayText($ddt)
    {
        $this->delivery_day_text = $ddt;

        return $this;
    }

    /**
     * @return string
     */
    public function getSaleEndText()
    {
        return $this->sale_end_text;
    }

    /**
     * @param string $set
     *
     * @return $this;
     */
    public function setSaleEndText($set)
    {
        $this->sale_end_text = $set;

        return $this;
    }
}
