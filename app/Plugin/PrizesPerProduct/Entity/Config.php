<?php

namespace Plugin\PrizesPerProduct\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\OrderItem;

/**
 * Config
 *
 * @ORM\Table(name="plg_prizes_per_product_config")
 * @ORM\Entity(repositoryClass="Plugin\PrizesPerProduct\Repository\ConfigRepository")
 */
class Config extends \Eccube\Entity\AbstractEntity
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
     * @ORM\Column(name="orderId", type="integer", nullable=false)
     */
    private $orderId;

    /**
     * @var int
     * 
     * @ORM\Column(name="productId", type="integer", nullable=true)
     */
    private $productId;

    /**
     * @var string
     * 
     * @ORM\Column(name="prizeName", type="text", nullable=false)
     */
    private $prizeName;

    /**
     * @var string
     * 
     * @ORM\Column(name="prizeImage", type="string", nullable=true)
     */
    private $prizeImage;

    /**
     * @var string
     * 
     * @ORM\Column(name="prizeGrade", type="string", nullable=true)
     */
    private $prizeGrade;

    /**
     * @var string
     * 
     * @ORM\Column(name="prizeClassName", type="string", nullable=true)
     */
    private $prizeClassName;

    /**
     * @var int
     * 
     * @ORM\Column(name="prizeOpen", type="integer", nullable=true)
     */
    private $prizeOpen;

    /**
     * @var int
     * 
     * @ORM\Column(name="prizeListId", type="integer", nullable=true)
     */
    private $prizeListId;

    /**
     * @var string
     * 
     * @ORM\Column(name="prizeColor", type="string", nullable=true)
     */
    private $prizeColor;

    /**
     * @ORM\ManyToOne(
     *      targetEntity="Eccube\Entity\OrderItem",
     *      inversedBy="winning_prizes"
     * )
     * @ORM\JoinColumn(
     *      name="order_item_id",
     *      referencedColumnName="id",
     *      onDelete="CASCADE",
     *      nullable=true
     * )
     */
    protected $order_item;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @param int $orderId
     * 
     * @return $this
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * @return int
     */
    public function getProductId()
    {
        return $this->productId;
    }

    /**
     * @param int $productId
     * 
     * @return $this
     */
    public function setproductId($productId)
    {
        $this->productId = $productId;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrizeName()
    {
        return $this->prizeName;
    }

    /**
     * @param string $prizeName
     * 
     * @return $this
     */
    public function setPrizeName($prizeName)
    {
        $this->prizeName = $prizeName;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrizeImage()
    {
        return $this->prizeImage;
    }

    /**
     * @param string $prizeImage
     * 
     * @return $this
     */
    public function setPrizeImage($prizeImage)
    {
        $this->prizeImage = $prizeImage;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrizeGrade()
    {
        return $this->prizeGrade;
    }

    /**
     * @param string $prizeGrade
     * 
     * @return $this
     */
    public function setPrizeGrade($prizeGrade)
    {
        $this->prizeGrade = $prizeGrade;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrizeClassName()
    {
        return $this->prizeClassName;
    }

    /**
     * @param string $prizeClassName
     * 
     * @return $this
     */
    public function setPrizeClassName($prizeClassName)
    {
        $this->prizeClassName = $prizeClassName;

        return $this;
    }

    /**
     * @return int
     */
    public function getPrizeOpen()
    {
        return $this->prizeOpen;
    }

    /**
     * @param int $prizeOpen
     * 
     * @return $this
     */
    public function setPrizeOpen($prizeOpen)
    {
        $this->prizeOpen = $prizeOpen;

        return $this;
    }

    /**
     * @return int
     */
    public function getPrizeListId()
    {
        return $this->prizeListId;
    }

    /**
     * @param int $prizeListId
     * 
     * @return $this
     */
    public function setPrizeListId($prizeListId)
    {
        $this->prizeListId = $prizeListId;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrizeColor()
    {
        return $this->prizeColor;
    }

    /**
     * @param string $prizeColor
     * 
     * @return $this
     */
    public function setPrizeColor($prizeColor)
    {
        $this->prizeColor = $prizeColor;

        return $this;
    }
    
    public function setOrderItem(?OrderItem $order_item)
    {
        $this->order_item = $order_item;

        return $this;
    }

    public function getOrderItem(): ?OrderItem
    {
        return $this->order_item;
    }
}
