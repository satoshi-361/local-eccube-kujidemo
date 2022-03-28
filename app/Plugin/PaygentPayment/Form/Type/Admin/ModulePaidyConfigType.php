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
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Url;

class ModulePaidyConfigType extends ConfigType
{
    /**
     * ModulePaidyConfigType buildForm.
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isPaidy = $options['isPaidy'];
        $isModule = $options['isModule'];

        $arrFormConstraints = [];
        if ($isPaidy && $isModule) {
            $arrFormConstraints = [
                'api_key' => [
                    new NotBlank(['message' => '※ パブリックキーが入力されていません。']),
                ],
                'logo_url' => [
                    new Url(['message' => '※ ロゴURLを正しく入力してください。']),
                    new Length([
                        'max' => $this->eccubeConfig['eccube_url_len'],
                        'maxMessage' => '※ ロゴURLは' . $this->eccubeConfig['eccube_url_len'] . '字以下で入力してください。',
                    ]),
                ],
                'paidy_store_name' => [
                    new Length([
                        'max' => 32,
                        'maxMessage' => '※ 店舗名(全角)は32字以下で入力してください。',
                    ]),
                ],
            ];
        }
        $builder->add('api_key', TextType::class, [
            'constraints' => $arrFormConstraints['api_key'] ?? null,
        ]);
        $builder->add('logo_url', UrlType::class, [
            'required' => false,
            'constraints' => $arrFormConstraints['logo_url'] ?? null,
        ]);
        $builder->add(
            $builder
                ->create('paidy_store_name', TextType::class, [
                    'required' => false,
                    'constraints' => $arrFormConstraints['paidy_store_name'] ?? null,
                ])
                ->addEventSubscriber(new \Eccube\Form\EventListener\ConvertKanaListener('AK'))
        );

        $builder = $this->setDataForInvalidItem($builder, $isModule && $isPaidy);
    }

    /**
     * ModulePaidyConfigType configureOptions.
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'inherit_data' => true,
            'isPaidy' => false,
            'isModule' => false,
        ]);
    }
}
