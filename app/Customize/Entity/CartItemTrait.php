<?php

namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
  * @EntityExtension("Eccube\Entity\CartItem")
 */
trait CartItemTrait
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
    private $ship_count;

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
}
