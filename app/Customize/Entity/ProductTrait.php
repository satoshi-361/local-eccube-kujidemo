<?php

namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;
use Eccube\Entity\Product;

/**
  * @EntityExtension("Eccube\Entity\Product")
 */
trait ProductTrait
{
    /**
     * @var string|null
     * @ORM\Column(type="string", length=200, nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\HiddenType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    public $premium;
	
    /**
     * @var string|null
     * @ORM\Column(type="string", length=200, nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\HiddenType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    public $niconico;
	
    /**
     * @var string|null
     * @ORM\Column(type="string", length=200, nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\HiddenType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    public $specifics;
    
    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    public $limit_count;
	
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
    public $product_assist_id;

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
    public $position;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetimetz", nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\DateTimeType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    public $sales_start;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetimetz", nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\DateTimeType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    public $sales_end;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=200, nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\TextType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    private $animate_image;    

    /**
     * @var string|null
     * @ORM\Column(type="string", length=200, nullable=true)
     * @Eccube\Annotation\FormAppend(
     *     auto_render=false,
     *     type="\Symfony\Component\Form\Extension\Core\Type\TextType",
     *     options={
     *          "required": false,
     *          "label": false
     *     })
     */
    private $twitter_tags;    


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

    public function __construct()
    {
        $this->sales_start = new \DateTime('now');
        $this->sales_end = new \DateTime('now');
    }

    /**
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param int $position
     *
     * @return $this;
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getSalesStart()
    {
        return $this->sales_start;
    }

    /**
     * @param \DateTime $sales_start
     *
     * @return $this;
     */
    public function setSalesStart($sales_start)
    {
        $this->sales_start = $sales_start;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getSalesEnd()
    {
        return $this->sales_end;
    }

    /**
     * @param \DateTime $sales_end
     *
     * @return $this;
     */
    public function setSalesEnd($sales_end)
    {
        $this->sales_end = $sales_end;

        return $this;
    }

    public function getAnimateImage()
    {
        return $this->animate_image;
    }

    public function setAnimateImage($animate_image)
    {
        $this->animate_image = $animate_image;

        return $this;
    }

    public function getTwitterTags()
    {
        return $this->twitter_tags;
    }

    public function setTwitterTags($twitter_tags)
    {
        $this->twitter_tags = $twitter_tags;
        return $this;
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

}