<?php

namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
  * @EntityExtension("Eccube\Entity\ProductClass")
 */
trait ProductClassTrait
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
    protected $remainStatus;

    /**
     * @return int
     */
    public function getRemainStatus()
    {
        return $this->remainStatus;
    }

    /**
     * @param int $remainStatus
     *
     * @return $this;
     */
    public function setRemainStatus($remainStatus)
    {
        $this->remainStatus = $remainStatus;

        return $this;
    }
}