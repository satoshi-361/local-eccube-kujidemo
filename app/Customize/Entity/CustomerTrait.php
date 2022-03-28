<?php

namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
  * @EntityExtension("Eccube\Entity\Customer")
 */
trait CustomerTrait
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
    public $premium;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=500, nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\TextType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    public $channel;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=500, nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\TextType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    public $ticket;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=500, nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\TextType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    public $customer_rank;

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
    private $wrong_count = 0;

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
    private $old_mem_id;

    /**
     * Get wrong_count.
     *
     * @return int
     */
    public function getWrongCount()
    {
        return $this->wrong_count;
    }

    /**
     * Set wrong_count
     * 
     * @return this
     */
    public function setWrongCount($wrong_count)
    {
        $this->wrong_count = $wrong_count;

        return $this;
    }

    /**
     * Get old_mem_id.
     *
     * @return old_mem_id
     */
    public function getOldMemId()
    {
        return $this->old_mem_id;
    }

    /**
     * Set old_mem_id.
     *
     * @param string $old_mem_id
     *
     * @return this
     */
    public function setOldMemId($old_mem_id)
    {
        $this->old_mem_id = $old_mem_id;
        
        return $this;
    }
    
}