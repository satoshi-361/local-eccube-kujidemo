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

namespace Plugin\PaygentPayment\Form\Type\Admin;

use Plugin\PaygentPayment\Entity\Config;
use Plugin\PaygentPayment\Form\Type\Admin\ConfigType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class LinkCreditConfigType extends ConfigType
{
    /**
     * LinkCreditConfigType buildForm.
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isCredit = $options['isCredit'];
        $isLink = $options['isLink'];

        $arrFormConstraints = [];
        if ($isCredit && $isLink) {
            $arrFormConstraints = [
                'card_class' => [
                    new NotBlank(),
                ],
                'card_conf' => [
                    new NotBlank(),
                ],
                'stock_card' => [
                    new NotBlank(),
                ],
            ];
        }

        $builder->add('card_class', ChoiceType::class, [
            'choices' => ['1回払いのみ' => 0, '全て' => 1, 'ボーナス一括以外全て' => 2],
            'expanded' => true,
            'constraints' => $arrFormConstraints['card_class'] ?? null,
        ]);
        $builder->add('card_conf', ChoiceType::class, [
            'choices' => ['要' => 1,'不要' => 0],
            'expanded' => true,
            'constraints' => $arrFormConstraints['card_conf'] ?? null,
        ]);
        $builder->add('stock_card', ChoiceType::class, [
            'choices' => ['要' => 1,'不要' => 0],
            'expanded' => true,
            'constraints' => $arrFormConstraints['stock_card'] ?? null,
        ]);

        $builder = $this->setDataForInvalidItem($builder, $isLink && $isCredit);
    }

    /**
     * LinkCreditConfigType configureOptions.
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'inherit_data' => true,
            'isCredit' => false,
            'isLink' => false,
        ]);
    }
}
