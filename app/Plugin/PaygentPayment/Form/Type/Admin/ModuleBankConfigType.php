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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

class ModuleBankConfigType extends ConfigType
{
    /**
     * ModuleBankConfigType buildForm.
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isBank = $options['isBank'];
        $isModule = $options['isModule'];

        $arrFormConstraints = [];
        if ($isBank && $isModule) {
            $arrFormConstraints = [
                'asp_payment_term' => [
                    new NotBlank(['message' => '※ 支払期限日が入力されていません。']),
                    new Regex([
                        'pattern' => "/^[0-9]+$/",
                        'message' => '※ 支払期限日は数字で入力してください。'
                    ]),
                    new GreaterThanOrEqual([
                        'value'=>'1',
                        'message' => '※ 支払期限日は1～99日で設定してください。',
                    ]),
                    new LessThanOrEqual([
                        'value'=>'99',
                        'message' => '※ 支払期限日は1～99日で設定してください。',
                    ]),
                ],
                'claim_kanji' => [
                    new Regex([
                        'pattern' => "/^[^ \t\r\n\v\f]*$/",
                        'message' => '※ 店舗名(全角)に半角スペース・改行は入力できません。'
                    ]),
                    new Length([
                        'max' => 32,
                        'maxMessage' => '※ 店舗名(全角)は32字以下で入力してください。',
                    ]),
                ],
                'claim_kana' => [
                    new NotBlank(['message' => '※ 店舗名(カナ)が入力されていません。']),
                    new Regex([
                        'pattern' => "/^[^ \t\r\n\v\f]*$/",
                        'message' => '※ 店舗名(カナ)に半角スペース・改行は入力できません。'
                    ]),
                    new Regex([
                        'pattern' => "/^[ァ-ヶｦ-ﾟー]+$/u",
                        'message' => '※ 店舗名はカタカナで入力してください。'
                    ]),
                    new Length([
                        'max' => 32,
                        'maxMessage' => '※ 店舗名(カナ)は32字以下で入力してください。',
                    ]),
                ],
                'copy_right' => [
                    new Regex([
                        'pattern' => "/^[a-zA-Z0-9]+[ \t\r\n\v\f]*\(?[A-z0-9]*\)?[\w\d\s.\\\]*$/",
                        'message' => '※ 決済ページ用コピーライトは英数字で入力してください。'
                    ]),
                    new Length([
                        'max' => 128,
                        'maxMessage' => '※ 決済ページ用コピーライトは128字以下で入力してください。',
                    ]),
                ],
                'free_memo' => [
                    new Regex([
                        'pattern' => "/^[^ \t\r\n\v\f]*$/",
                        'message' => '※ 自由メモ欄(全角)に半角スペース・改行は入力できません。'
                    ]),
                    new Length([
                        'max' => 128,
                        'maxMessage' => '※ 自由メモ欄(全角)は128字以下で入力してください。',
                    ]),
                ],
            ];
        }

        $builder->add('asp_payment_term', TextType::class, [
            'attr' => [
                'style' => 'width:50px',
                'maxlength' => '2',
            ],
            'constraints' => $arrFormConstraints['asp_payment_term'] ?? null,
        ]);
        $builder->add(
            $builder
                ->create('claim_kanji', TextType::class, [
                    'constraints' => $arrFormConstraints['claim_kanji'] ?? null,
                ])
                ->addEventSubscriber(new \Eccube\Form\EventListener\ConvertKanaListener('AK'))
        );
        $builder->add('claim_kana', TextType::class, [
            'constraints' => $arrFormConstraints['claim_kana'] ?? null,
        ]);
        $builder->add('copy_right', TextType::class, [
            'required' => false,
            'constraints' => $arrFormConstraints['copy_right'] ?? null,
        ]);
        $builder->add(
            $builder
                ->create('free_memo', TextType::class, [
                    'required' => false,
                    'constraints' => $arrFormConstraints['free_memo'] ?? null,
                ])
                ->addEventSubscriber(new \Eccube\Form\EventListener\ConvertKanaListener('AK'))
        );

        $builder = $this->setDataForInvalidItem($builder, $isModule && $isBank);
    }

    /**
     * ModuleBankConfigType configureOptions.
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'inherit_data' => true,
            'isBank' => false,
            'isModule' => false,
        ]);
    }
}
