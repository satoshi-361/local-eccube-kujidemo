<?php

namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
  * @EntityExtension("Eccube\Entity\OrderItem")
 */
trait OrderItemTrait
{
    /**
     * @ORM\OneToMany(
     *      targetEntity="\Plugin\PrizesPerProduct\Entity\Config",
     *      mappedBy="order_item",
     *      cascade={"persist", "remove"}
     * )
     */
    protected $winning_prizes;

    public function __construct()
    {
      $this->winning_prizes = new ArrayCollection();
    }

    public function getWinningPrizes() : Collection
    {
        return $this->winning_prizes;
    }

    public function addWinningPrize($winning_prize)
    {
        $this->winning_prizes->add($winning_prize);
    }
}