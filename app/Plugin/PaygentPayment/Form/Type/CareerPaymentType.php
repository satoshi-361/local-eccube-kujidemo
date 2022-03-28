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

namespace Plugin\PaygentPayment\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Plugin\PaygentPayment\Service\CacheConfig;

class CareerPaymentType extends AbstractType {

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * コンストラクタ
     * @param ConfigRepository $configRepository
     * @param CacheConfig $cacheConfig
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        ConfigRepository $configRepository,
        CacheConfig $cacheConfig,
        EccubeConfig $eccubeConfig
    ) {
        $this->configRepository = $configRepository;
        $this->cacheConfig = $cacheConfig;
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * Build result career type form
     *
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $arrCareer = array_flip($this->getArrCarerr());
        $builder
            ->add('career_type', ChoiceType::class, [
                'label' => 'キャリア決済選択',
                'choices' => $arrCareer,
                'required' => false,
                'constraints' => [
                    new Assert\NotBlank(['message' => '※ キャリア決済選択が入力されていません。']),
                ],
                'choice_attr' => function($val) {
                    return ['id' => 'career' . $val];
                },
        ]);
    }

    /**
     * 利用可能な携帯キャリア決済の名前一覧を取得する
     *
     * @return array 携帯キャリア決済
     */
    public function getArrCarerr() {
        // プラグイン設定情報の取得
        $config = $this->cacheConfig->getConfig();
        $selectedCareer = $config->getCareerDivision();

        $arrCareer = [
            '' => 'ご選択ください'
        ];

        foreach ($this->eccubeConfig['paygent_payment']['career_division_id'] as $careerName => $careerId) {
            if (in_array($careerId, $selectedCareer)) {
                $arrCareer[$careerId] = $this->eccubeConfig['paygent_payment']['career_division_names'][$careerName];
            }
        }
        return $arrCareer;
    }
}