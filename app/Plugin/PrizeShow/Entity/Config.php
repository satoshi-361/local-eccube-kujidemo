<?php

namespace Plugin\PrizeShow\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\AbstractEntity;
use \Plugin\PrizeShow\Entity\PrizeList;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Symfony\Component\Security\Core\User\UserInterface;

if (!class_exists(Config::class)) {
/**
 * Class Config
 * @package Plugin\PrizeShow\Entity
 *
 * @ORM\Table(name="plg_prize_show_config")
 * @ORM\Entity(repositoryClass="Plugin\PrizeShow\Repository\ConfigRepository")
 */
class Config extends AbstractEntity
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="image", type="string", options={"unsigned":true})
     */
    private $image;    

    /**
     * @var int
     * 
     * @ORM\Column(name="remain", type="integer", options={"unsigned":true})
     */
    private $remain;

    /**
     * @ORM\ManyToOne(
     *      targetEntity="\Plugin\PrizeShow\Entity\PrizeList",
     *      inversedBy="settings"
     * )
     * @ORM\JoinColumn(
     *      name="prizeGroup",
     *      referencedColumnName="id",
     *      onDelete="CASCADE",
     *      nullable=true
     * )
     */
    private $prizeGroup;

    /**
     * @ORM\ManyToMany(
     *      targetEntity="Eccube\Entity\Order",
     *      inversedBy="prizes",
     * )
     */
    private $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return $this;
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     *
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param string $image
     * 
     * @return $this
     */
    public function setImage($image)
    {
        $this->image = $image;

        return $this;
    }

    /**
    * @return int
    */
    public function getRemain()
    {
        return $this->remain;
    }

    /**
     * @param int $remain
     *
     * @return $this;
     */
    public function setRemain($remain)
    {
        $this->remain = $remain;

        return $this;
    }

    public function setPrizeGroup(?PrizeList $productList)
    {
        $this->prizeGroup = $productList;

        return $this;
    }

    public function getPrizeGroup(): ?PrizeList
    {
        return $this->prizeGroup;
    }
    
    public function getOrders() : Collection
    {
        return $this->orders;
    }

    public function addOrder($order)
    {
        $this->orders->add($order);
    }
}
}