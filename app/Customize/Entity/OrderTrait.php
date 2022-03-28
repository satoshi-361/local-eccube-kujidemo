<?php

namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
  * @EntityExtension("Eccube\Entity\Order")
 */
trait OrderTrait
{
    /**
     * @var integer
     * @ORM\Column(type="integer", nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\NumberType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    protected $lotteryCount;

    /**
     * @var integer
     * @ORM\Column(type="integer", nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\NumberType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    private $old_id;


    /**
     * @return int
     */
    public function getLotteryCount()
    {
        return $this->lotteryCount;
    }

    /**
     * @param int $lotteryCount
     *
     * @return $this;
     */
    public function setLotteryCount($lotteryCount)
    {
        $this->lotteryCount = $lotteryCount;

        return $this;
    }

    /**
     * @return int
     */
    public function getOldId()
    {
        return $this->old_id;
    }

    /**
     * @param int $old_id
     *
     * @return $this;
     */
    public function setOldId($old_id)
    {
        $this->old_id = $old_id;

        return $this;
    }

    /**
     * @ORM\ManyToMany(
     *      targetEntity="\Plugin\PrizeShow\Entity\Config",
     *      mappedBy="orders",
     *      cascade={"persist", "remove"}
     * )
     */
    public $prizes;

    public function __construct()
    {
        $this->prizes = new ArrayCollection();
    }

    public function getPrizes() : Collection
    {
        return $this->prizes;
    }

    public function addPrize($prize)
    {
        $this->prizes->add($prize);
    }

    /**
     * @var string|null
     * @ORM\Column(type="string", length=10, nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\TextType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    private $add_nico_point = '0';

    /**
     * @var string|null
     * @ORM\Column(type="string", length=10, nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\TextType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    private $use_nico_point = '0';

    /**
     * Set addNicoPoint
     *
     * @param string $addNicoPoint
     *
     * @return Order
     */
    public function setAddNicoPoint($addNicoPoint)
    {
        $this->add_nico_point = $addNicoPoint;

        return $this;
    }

    /**
     * Get addNicoPoint
     *
     * @return string
     */
    public function getAddNicoPoint()
    {
        return $this->add_nico_point;
    }

    /**
     * Set useNicoPoint
     *
     * @param string $useNicoPoint
     *
     * @return Order
     */
    public function setUseNicoPoint($useNicoPoint)
    {
        $this->use_nico_point = $useNicoPoint;

        return $this;
    }

    /**
     * Get useNicoPoint
     *
     * @return string
     */
    public function getUseNicoPoint()
    {
        return $this->use_nico_point;
    }
}