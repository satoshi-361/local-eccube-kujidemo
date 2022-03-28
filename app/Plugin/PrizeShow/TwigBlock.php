<?php

namespace Plugin\PrizeShow;

use Eccube\Common\EccubeTwigBlock;

class TwigBlock implements EccubeTwigBlock
{
    /**
     * @return array
     */
    public static function getTwigBlock()
    {
        return [
            '$PrizeShow/config.twig'
        ];
    }
}
