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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

class ModuleConveniConfigType extends ConfigType
{
    /**
     * ModuleConveniConfigType buildForm.
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isConveni = $options['isConveni'];
        $isModule = $options['isModule'];

        $arrFormConstraints = [];
        if ($isConveni && $isModule) {
            $arrFormConstraints = [
                'conveni_limit_date_num' => [
                    new NotBlank(['message' => '※ 支払期限日が入力されていません。']),
                    new Regex([
                        'pattern' => "/^[0-9]+$/",
                        'message' => '※ 支払期限日は数字で入力してください。'
                    ]),
                    new GreaterThanOrEqual([
                        'value'=>'1',
                        'message' => '※ 支払期限日は1～60日で設定してください。',
                    ]),
                    new LessThanOrEqual([
                        'value'=>'60',
                        'message' => '※ 支払期限日は1～60日で設定してください。',
                    ]),
                ],
            ];
        }

        $builder->add('conveni_limit_date_num', TextType::class, [
            'attr' => [
                'style' => 'width:50px',
                'maxlength' => '2',
            ],
            'constraints' => $arrFormConstraints['conveni_limit_date_num'] ?? null,
        ]);

        $builder = $this->setDataForInvalidItem($builder, $isModule && $isConveni);
    }

    /**
     * ModuleConveniConfigType configureOptions.
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'inherit_data' => true,
            'isConveni' => false,
            'isModule' => false,
        ]);
    }
}
