<?php

namespace Plugin\ProductAssistConfig\Entity;

use Doctrine\ORM\Mapping as ORM;
use Plugin\ProductAssist\Entity\Config as ProductAssist;

/**
 * Config
 *
 * @ORM\Table(name="plg_product_assist_config_config")
 * @ORM\Entity(repositoryClass="Plugin\ProductAssistConfig\Repository\ConfigRepository")
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
     * @ORM\Column(name="groupId", type="integer", nullable=true)
     */
    private $groupId;

    /**
     * @var string
     *
     * @ORM\Column(name="grade", type="string", length=255, nullable=true)
     */
    private $grade;

    /**
     * @var string
     *
     * @ORM\Column(name="className", type="string", length=255, nullable=true)
     */
    private $className;

    /**
     * @var string
     *
     * @ORM\Column(name="descriptionText", type="string", length=255, nullable=true)
     */
    private $descriptionText;

    /**
     * @var string
     *
     * @ORM\Column(name="showText", type="string", length=255, nullable=true)
     */
    private $showText;

    /**
     * @var string
     *
     * @ORM\Column(name="setOption", type="string", nullable=true)
     */
    private $setOption;

    /**
     * @var int
     *
     * @ORM\Column(name="setCount", type="integer", nullable=true)
     */
    private $setCount;

    /**
     * @var string
     *
     * @ORM\Column(name="colorName", type="string", nullable=true)
     */
    private $colorName;


    /**
     * @ORM\ManyToOne(
     *      targetEntity="\Plugin\ProductAssist\Entity\Config",
     *      inversedBy="settings"
     * )
     * @ORM\JoinColumn(
     *      name="product_assist_id",
     *      referencedColumnName="id",
     *      onDelete="CASCADE",
     *      nullable=true
     * )
     */
    protected $product_assist;

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
    public function getGroupId()
    {
        return $this->groupId;
    }

    /**
     * @param int $groupId
     *
     * @return $this;
     */
    public function setGroupId($groupId)
    {
        $this->groupId = $groupId;

        return $this;
    }


    /**
     * @return string
     */
    public function getGrade()
    {
        return $this->grade;
    }

    /**
     * @param string $grade
     *
     * @return $this;
     */
    public function setGrade($grade)
    {
        $this->grade = $grade;

        return $this;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param string $className
     *
     * @return $this;
     */
    public function setClassName($className)
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescriptionText()
    {
        return $this->descriptionText;
    }

    /**
     * @param string $descriptionText
     *
     * @return $this;
     */
    public function setDescriptionText($descriptionText)
    {
        $this->descriptionText = $descriptionText;

        return $this;
    }

    /**
     * @return string
     */
    public function getShowText()
    {
        return $this->showText;
    }

    /**
     * @param string $show
     *
     * @return $this;
     */
    public function setShowText($showText)
    {
        $this->showText = $showText;

        return $this;
    }

    /**
     * @return string
     */
    public function getSetOption()
    {
        return $this->setOption;
    }

    /**
     * @param string $setOption
     *
     * @return $this;
     */
    public function setSetOption($setOption)
    {
        $this->setOption = $setOption;

        return $this;
    }

    /**
     * @return int
     */
    public function getSetCount()
    {
        return $this->setCount;
    }

    /**
     * @param int $setCount
     *
     * @return $this;
     */
    public function setSetCount($setCount)
    {
        $this->setCount = $setCount;

        return $this;
    }

    /**
     * @return string
     */
    public function getColorName()
    {
        return $this->colorName;
    }

    /**
     * @param string $colorName
     *
     * @return $this;
     */
    public function setColorName($colorName)
    {
        $this->colorName = $colorName;

        return $this;
    }

    public function setProductAssist(?ProductAssist $productAssist)
    {
        $this->product_assist = $productAssist;

        return $this;
    }

    public function getProductAssist(): ?ProductAssist
    {
        return $this->product_assist;
    }
}
