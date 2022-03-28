<?php

namespace Plugin\LotteryProbability\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\AbstractEntity;


/**
 * Config
 *
 * @ORM\Table(name="plg_lottery_probability_config")
 * @ORM\Entity(repositoryClass="Plugin\LotteryProbability\Repository\ConfigRepository")
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
         * @var int
         *
         * @ORM\Column(name="product_id", type="integer", options={"unsigned":true})
         */
        private $product_id;
        /**
         * @var int
         *
         * @ORM\Column(name="winning", type="string", options={"unsigned":true})
         */
        private $winning;
        /**
         * @var string
         *
         * @ORM\Column(name="rank_name", type="string", options={"unsigned":true})
         */
        private $rank_name;    
        /**
         * @var string
         * 
         * @ORM\Column(name="explain_text", type="string", options={"unsigned":true})
         */
        private $explain_text;
        /**
         * @var int
         * 
         * @ORM\Column(name="winning_probability", type="integer", options={"unsigned":true})
         */
        private $winning_probability;
        /**
         * @var string
         * 
         * @ORM\Column(name="display_winning", type="string", options={"unsigned":true})
         */
        private $display_winning;
        /**
         * @var int
         * 
         * @ORM\Column(name="product_set", type="integer", options={"unsigned":true})
         */
        private $product_set;
        /**
         * @var string
         * 
         * @ORM\Column(name="color", type="string", options={"unsigned":true})
         */
        private $color;
        /**
         * Set id.
         *
         * @param int $id
         *
         * @return Config
         */
        public function setID($id)
        {
            $this->id = $id;
            return $this;
        }
        /**
         * Get id.
         *
         * @return int
         */
        public function getID()
        {
            return $this->id;
        }
        /**
         * Set productid.
         *
         * @param int $productid
         *
         * @return Config
         */
        public function setProductID($productid)
        {
            $this->product_id = $productid;
            return $this;
        }
        /**
         * Get productid.
         *
         * @return int
         */
        public function getProductID()
        {
            return $this->product_id;
        }
        /**
         * Set winning.
         *
         * @param string $winning
         *
         * @return Config
         */
        public function setWinning($winning)
        {
            $this->winning = $winning;
            return $this;
        }
        /**
         * Get winning.
         *
         * @return string
         */
        public function getWinning()
        {
            return $this->winning;
        }

         /**
         * Set rank_name.
         *
         * @param string $rank_name
         *
         * @return Config
         */
        public function setRankname($rankname)
        {
            $this->rank_name = $rankname;
            return $this;
        }
        /**
         * Get rank_name.
         *
         * @return string
         */
        public function getRankname()
        {
            return $this->rank_name;
        }
        /**
         * Set explain_text.
         *
         * @param string $explain_text
         *
         * @return Config
         */
        public function setExplaintext($explaintext)
        {
            $this->explain_text = $explaintext;
            return $this;
        }
        /**
         * Get explain_text.
         *
         * @return string
         */
        public function getExplaintext()
        {
            return $this->explain_text;
        }
         /**
         * Set winning_probability.
         *
         * @param int $winning_probability
         *
         * @return Config
         */
        public function setWinningProbability($winningprobability)
        {
            $this->winning_probability = $winningprobability;
            return $this;
        }
        /**
         * Get winning_probability.
         *
         * @return int
         */
        public function getWinningProbability()
        {
            return $this->winning_probability;
        }

        
        /**
         * Set display_winning.
         *
         * @param string $display_winning
         *
         * @return Config
         */
        public function setDisplayWinning($displaywinning)
        {
            $this->display_winning = $displaywinning;
            return $this;
        }
        /**
         * Get display_winning.
         *
         * @return string
         */
        public function getDisplayWinning()
        {
            return $this->display_winning;
        }
        /**
         * Set product_set.
         *
         * @param int $product_set
         *
         * @return Config
         */
        public function setProductSet($productset)
        {
            $this->product_set = $productset;
            return $this;
        }
        /**
         * Get product_set.
         *
         * @return int
         */
        public function getProductSet()
        {
            return $this->product_set;
        }
        /**
         * Set color.
         *
         * @param string $color
         *
         * @return Config
         */
        public function setColor($color)
        {
            $this->color = $color;
            return $this;
        }
        /**
         * Get color.
         *
         * @return string
         */
        public function getColor()
        {
            return $this->color;
        }
}