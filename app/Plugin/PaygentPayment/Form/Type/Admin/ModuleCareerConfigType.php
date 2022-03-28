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

use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\Entity\Config;
use Plugin\PaygentPayment\Form\Type\Admin\ConfigType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ModuleCareerConfigType extends ConfigType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * ModuleCareerConfigType constructor.
     * 
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        EccubeConfig $eccubeConfig
    ) {
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * ModuleCareerConfigType buildForm.
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isCareer = $options['isCareer'];
        $isModule = $options['isModule'];

        $arrCareerDivisions = array_flip($this->getCareerDivisions());

        $arrFormConstraints = [];
        if ($isCareer && $isModule) {
            $arrFormConstraints = [
                'career_division' => [
                    new NotBlank(['message' => '※ 利用決済が入力されていません。']),
                ],
            ];
        }
        $builder->add($builder->create('career_division', ChoiceType::class, [
            'choices' => $arrCareerDivisions,
            'expanded' => true,
            'multiple' => true,
            'constraints' => $arrFormConstraints['career_division'] ?? null,
        ])->addModelTransformer($this->getTransformer()));

        $builder = $this->setDataForInvalidItem($builder, $isModule && $isCareer);
    }

    /**
     * ModuleCareerConfigType configureOptions.
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'inherit_data' => true,
            'isCareer' => false,
            'isModule' => false,
        ]);
    }

    /**
     * 決済モジュールで利用出来る携帯キャリア決済の名前一覧を取得する
     *
     * @return array 携帯キャリア決済
     */
    private function getCareerDivisions()
    {
        $careerDivisions = [];

        foreach ($this->eccubeConfig['paygent_payment']['career_division_id'] as $careerName => $careerId) {
            $careerDivisions[$careerId] = $this->eccubeConfig['paygent_payment']['career_division_names'][$careerName];
        }

        return $careerDivisions;
    }
}
