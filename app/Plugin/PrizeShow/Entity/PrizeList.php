<?php

namespace Plugin\PrizeShow\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\AbstractEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Symfony\Component\Security\Core\User\UserInterface;

if (!class_exists(PrizeList::class)) {
    /**
     * Class PrizeList
     * @package Plugin\PrizeShow\Entity
     *
     * @ORM\Table(name="plg_prize_show_list")
     * @ORM\Entity(repositoryClass="Plugin\PrizeShow\Repository\PrizeListRepository")
     */
    class PrizeList extends AbstractEntity
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
         * @var integer
         *
         * @ORM\Column(name="old_id", type="integer", nullable=true)
         */
        private $old_id;

        /**
         * @var string
         *
         * @ORM\Column(name="name", type="string", length=255)
         */
        private $name;

        /**
         * @ORM\OneToMany(
         *      targetEntity="\Plugin\PrizeShow\Entity\Config",
         *      mappedBy="prizeGroup",
         *      cascade={"persist", "remove"}
         * )
         */
        protected $settings;

        public function __construct()
        {
            $this->settings = new ArrayCollection();
        }

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

        public function getSettings() : Collection
        {
            return $this->settings;
        }
    
        public function addSetting($setting)
        {
            $this->settings->add($setting);
        }

        public function emptySetting() {
            $this->settings->clear();
        }
    }
}