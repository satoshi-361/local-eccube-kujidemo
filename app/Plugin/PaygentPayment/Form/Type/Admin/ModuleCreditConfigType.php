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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Length;

class ModuleCreditConfigType extends ConfigType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * ModuleCreditConfigType constructor.
     * 
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        EccubeConfig $eccubeConfig
    ) {
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * ModuleCreditConfigType buildForm.
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isCredit3d = $options['isCredit3d'];
        $isModule = $options['isModule'];
        $isCredit = $options['isCredit'];

        $arrPaymentDivisions = array_flip($this->getPaymentDivisions());
        $arrCreditTokenEnvDivisions = array_flip($this->getCreditTokenEnvDivisions());

        $arrFormConstraints = [];
        if ($isCredit && $isModule) {
            $arrFormConstraints = [
                'payment_division' => [
                    new NotBlank(['message' => '※ 支払回数が入力されていません。']),
                ],
                'security_code' => [
                    new NotBlank(),
                ],
                'credit_3d' => [
                    new NotBlank(),
                ],
                'module_stock_card' => [
                    new NotBlank(),
                ],
                'token_env' => [
                    new NotBlank(),
                ],
                'token_key' => [
                    new NotBlank(['message' => '※ トークン生成鍵が入力されていません。']),
                    new Regex([
                        'pattern' => "/^[A-z0-9]+$/",
                        'message' => '※ トークン生成鍵は英数字で入力してください。'
                    ]),
                    new Length([
                        'max' => 100,
                        'maxMessage' => '※ トークン生成鍵は100字以下で入力してください。',
                    ]),
                ],
            ];

            if ($isCredit3d != $this->eccubeConfig['paygent_payment']['credit_3d']['3dSecure_off']) {
                $arrFormConstraints += [
                    'credit_3d_merchant_name' => [
                        new Regex([
                            'pattern' => "/^[\x21-\x7e]+$/",
                            'message' => '※ 加盟店名(半角英数字記号)は半角英数記号で入力してください。'
                        ]),
                        new Length([
                            'max' => 25,
                            'maxMessage' => '※ 加盟店名(半角英数字記号)は25字以下で入力してください。',
                        ]),
                    ],
                    'credit_3d_hash_key' => [
                        new NotBlank(['message' => '※ 3Dセキュア結果受付ハッシュ鍵が入力されていません。']),
                        new Regex([
                            'pattern' => "/^[A-z0-9]+$/",
                            'message' => '※ 3Dセキュア結果受付ハッシュ鍵は英数字で入力してください。'
                        ]),
                        new Length([
                            'max' => 100,
                            'maxMessage' => '※ 3Dセキュア結果受付ハッシュ鍵は100字以下で入力してください。',
                        ]),
                    ]
                ];
                if ($isCredit3d == $this->eccubeConfig['paygent_payment']['credit_3d']['3dSecure_2']) {
                    $arrFormConstraints['credit_3d_merchant_name'][] = new NotBlank(['message' => '※ 加盟店名(半角英数字記号)が入力されていません。']);
                }
            }

            $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'setDataForDisabledCredit']);
        }

        $builder->add($builder->create('payment_division', ChoiceType::class, [
            'choices' => $arrPaymentDivisions,
            'expanded' => true,
            'multiple' => true,
            'constraints' => $arrFormConstraints['payment_division'] ?? null,
        ])->addModelTransformer($this->getTransformer()));

        $builder->add('security_code', ChoiceType::class, [
            'choices' => ['要' => 1,'不要' => 0],
            'expanded' => true,
            'constraints' => $arrFormConstraints['security_code'] ?? null,
        ]);
        $builder->add('credit_3d', ChoiceType::class, [
            'choices' => [
                '1.0' => $this->eccubeConfig['paygent_payment']['credit_3d']['3dSecure_1'],
                '2.0' => $this->eccubeConfig['paygent_payment']['credit_3d']['3dSecure_2'],
                '不要' => $this->eccubeConfig['paygent_payment']['credit_3d']['3dSecure_off'],
            ],
            'expanded' => true,
            'constraints' => $arrFormConstraints['credit_3d'] ?? null,
        ]);
        $builder->add('module_stock_card', ChoiceType::class, [
            'choices' => ['要' => 1,'不要' => 0],
            'expanded' => true,
            'constraints' => $arrFormConstraints['module_stock_card'] ?? null,
        ]);
        $builder->add('token_env', ChoiceType::class, [
            'choices' => $arrCreditTokenEnvDivisions,
            'expanded' => true,
            'constraints' => $arrFormConstraints['token_env'] ?? null,
        ]);
        $builder->add('token_key', TextType::class, [
            'constraints' => $arrFormConstraints['token_key'] ?? null,
        ]);
        $builder->add('credit_3d_hash_key', TextType::class, [
            'constraints' => $arrFormConstraints['credit_3d_hash_key'] ?? null,
        ]);
        $builder->add('credit_3d_merchant_name', TextType::class, [
            'constraints' => $arrFormConstraints['credit_3d_merchant_name'] ?? null,
        ]);
        $builder->add('card_valid_check', ChoiceType::class, [
            'choices' => ['要' => 1,'不要' => 0],
            'expanded' => true,
            'constraints' => $arrFormConstraints['card_valid_check'] ?? null,
        ]);

        $builder = $this->setDataForInvalidItem($builder, $isModule && $isCredit);
    }

    /**
     * ModuleCreditConfigType configureOptions.
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'inherit_data' => true,
            'isCredit3d' => false,
            'isModule' => false,
            'isCredit' => false,
        ]);
    }

    /**
     * 決済モジュールで利用出来る支払い回数の名前一覧を取得する
     *
     * @return array 支払回数
     */
    private function getPaymentDivisions()
    {
        $paymentDivisions = [];

        foreach ($this->eccubeConfig['paygent_payment']['payment_division_id'] as $payName => $payId) {
            $paymentDivisions[$payId] = $this->eccubeConfig['paygent_payment']['payment_division_names'][$payName];
        }

        return $paymentDivisions;
    }

    /**
     * 決済モジュールで利用出来るトークン接続先の名前一覧を取得する
     *
     * @return array トークン接続先
     */
    private function getCreditTokenEnvDivisions()
    {
        $creditTokenEnvDivisions = [];

        foreach ($this->eccubeConfig['paygent_payment']['credit_token_env_id'] as $creditEnvName => $creditEnvId) {
            $creditTokenEnvDivisions[$creditEnvId] = $this->eccubeConfig['paygent_payment']['credit_token_env_names'][$creditEnvName];
        }

        return $creditTokenEnvDivisions;
    }

    public function setDataForDisabledCredit(FormEvent $event) {
        $form = $event->getForm();
        $requestData = $event->getData();

        // クレジット決済設定でdisabledが設定される項目は元のデータをリクエストに設定する
        if ($requestData['credit_3d'] == $this->eccubeConfig['paygent_payment']['credit_3d']['3dSecure_off']) {
            $requestData['credit_3d_merchant_name'] = $form['credit_3d_merchant_name']->getData();
            $requestData['credit_3d_hash_key'] = $form['credit_3d_hash_key']->getData();

        } elseif ($requestData['credit_3d'] == $this->eccubeConfig['paygent_payment']['credit_3d']['3dSecure_1']) {
            $requestData['credit_3d_merchant_name'] = $form['credit_3d_merchant_name']->getData();
        }

        if (!$requestData['module_stock_card']) {
            $requestData['card_valid_check'] = $form['card_valid_check']->getData();
        }

        $event->setData($requestData);
    }
}
