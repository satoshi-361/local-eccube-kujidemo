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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Plugin\PaygentPayment\Service\CacheConfig;

class CreditPaymentType extends AbstractType
{
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
     * Build result credit type form
     *
     * @param FormBuilderInterface $builder
	 * @param array $options
     */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
        $config = $this->cacheConfig->getConfig();
        $arrPaymentDivisions = array_flip($this->getPaymentDivisions());
        $arrCreditMonth = $this->getArrCreditMonth();
        $arrCreditYear = $this->getArrCreditYear();

        // 共通部分
        $builder->add('delete_card', HiddenType::class);
        $builder->add('card_token', HiddenType::class);
        $builder->add('card_token_stock', HiddenType::class);
        $builder->add('payment_class', ChoiceType::class, [
            'choices' => $arrPaymentDivisions,
            'constraints' => [
                new Assert\NotBlank(['message' => '※ 支払い回数が入力されていません。']),
            ],
        ]);
        $builder->add('card_no01', TextType::class, [
            'required' => false,
            'attr' => [
                'minlength' => '0',
                'maxlength' => '4',
                'autocomplete' => 'off',
                'size' => '6'
            ]
        ]);
        $builder->add('card_no02', TextType::class, [
            'required' => false,
            'attr' => [
                'minlength' => '0',
                'maxlength' => '4',
                'autocomplete' => 'off',
                'size' => '6'
            ]
        ]);
        $builder->add('card_no03', TextType::class, [
            'required' => false,
            'attr' => [
                'minlength' => '0',
                'maxlength' => '4',
                'autocomplete' => 'off',
                'size' => '6'
            ]
        ]);
        $builder->add('card_no04', TextType::class, [
            'required' => false,
            'attr' => [
                'minlength' => '0',
                'maxlength' => '4',
                'autocomplete' => 'off',
                'size' => '6'
            ]
        ]);
        $builder->add('card_name01', TextType::class, [
            'required' => false,
            'attr' => [
                'maxlength' => '24',
            ]
        ]);
        $builder->add('card_name02', TextType::class, [
            'required' => false,
            'attr' => [
                'maxlength' => '25',
            ]
        ]);
        $builder->add('card_month', ChoiceType::class, [
            'choices' => $arrCreditMonth,
            'required' => false,
        ]);
        $builder->add('card_year', ChoiceType::class, [
            'choices' => $arrCreditYear,
            'required' => false,
        ]);
        if ($config->getModuleStockCard()) {
        $builder->add('stock', CheckBoxType::class, [
            'required' => false,
            'label' => '登録カードを利用する' 
        ]);
        $builder->add('stock_new', CheckBoxType::class, [
            'required' => false,
            'label' => '登録する' 
        ]);
        }
        if ($config->getSecurityCode()) {
            $builder->add('security_code', TextType::class, [
                'required' => false,
                'attr' => [
                    'maxlength' => '4',
                    'autocomplete' => 'off',
                ]
            ]);
        }
    }

    /**
     * 支払い回数の名前一覧を取得する
     *
     * @return array 支払い回数
     */
    function getPaymentDivisions()
    {
        $paymentDivisions = [];
        $config = $this->cacheConfig->getConfig();
        $selectedPayment = $config->getPaymentDivision();

        foreach ($this->eccubeConfig['paygent_payment']['payment_division_id'] as $paymentName => $paymentId) {
            if (in_array($paymentId, $selectedPayment)) {
                // 分割払いの場合
                if ($paymentId == 61) {
                    $paymentDivisions[$paymentId . '-2'] = '分割2回払い';
                    $paymentDivisions[$paymentId . '-3'] = '分割3回払い';
                    $paymentDivisions[$paymentId . '-6'] = '分割6回払い';
                    $paymentDivisions[$paymentId . '-10'] = '分割10回払い';
                    $paymentDivisions[$paymentId . '-15'] = '分割15回払い';
                    $paymentDivisions[$paymentId . '-20'] = '分割20回払い';
                } else {
                    $paymentDivisions[$paymentId] = $this->eccubeConfig['paygent_payment']['payment_division_names'][$paymentName];
                }
            }
        }
        return $paymentDivisions;
    }

    /**
     * 利用可能な有効期限(年)の配列を取得する
     *
     * @return array 有効期限(年)の配列
     */
    function getArrCreditYear()
    {
        $year = date('Y');
        $endYear = $year + 15;

        $arrCreditYear = [];

        for ($i = $year; $i <= $endYear; $i++) {
            $val = substr($i, -2);
            $arrCreditYear[$val] = $val;
        }

        return $arrCreditYear;
    }

    /**
     * 利用可能な有効期限(月)の配列を取得する
     *
     * @return array 有効期限(月)の配列
     */
    function getArrCreditMonth()
    {
        $arrCreditMonth = [];
        for ($i = 1; $i <= 12; $i++) {
            $val = sprintf('%02d', $i);
            $arrCreditMonth[$val] = $val;
        }

        return $arrCreditMonth;
    }
}