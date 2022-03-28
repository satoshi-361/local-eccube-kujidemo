<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright (c) 2006 PAYGENT Co.,Ltd. All rights reserved.
 *
 * https://www.paygent.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Plugin\PaygentPayment\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Customer")
 */
trait CustomerTrait
{
    /**
     * クレジットカード情報をPaygentで保持しているか
     * @var int
     *
     * @ORM\Column(name="paygent_card_stock", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $paygent_card_stock;

    /**
     * @return string
     */
    public function getPaygentCardStock()
    {
        return $this->paygent_card_stock;
    }

    /**
     * @param string $paygent_card_stock
     * @return $this
     */
    public function setPaygentCardStock($paygent_card_stock)
    {
        $this->paygent_card_stock = $paygent_card_stock;

        return $this;
    }
}
