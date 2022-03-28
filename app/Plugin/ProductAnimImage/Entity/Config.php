<?php

namespace Plugin\ProductAnimImage\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Config
 *
 * @ORM\Table(name="plg_product_anim_image_config")
 * @ORM\Entity(repositoryClass="Plugin\ProductAnimImage\Repository\ConfigRepository")
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
     * @var string
     *
     * @ORM\Column(name="animImage", type="string", length=255)
     */
    private $animImage;

    /**
     * @var int
     * 
     * @ORM\Column(name="productId", type="integer", nullable=true)
     */
    public $productId;

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
    public function getAnimImage()
    {
        return $this->animImage;
    }

    /**
     * @param string $animImage
     *
     * @return $this;
     */
    public function setAnimImage($ai)
    {
        $this->animImage = $ai;

        return $this;
    }
}
