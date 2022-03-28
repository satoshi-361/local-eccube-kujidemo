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
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;

class LinkConfigType extends ConfigType
{
    /**
     * LinkConfigType buildForm.
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isLink = $options['isLink'];

        $arrFormConstraints = [];
        if ($isLink) {
            $arrFormConstraints = [
                'link_url' => [
                    new NotBlank(['message' => '※ リクエスト先URLが入力されていません。']),
                    new Url(['message' => '※ リクエスト先URLを正しく入力してください。']),
                    new Length([
                        'max' => $this->eccubeConfig['eccube_url_len'],
                        'maxMessage' => '※ リクエスト先URLは' . $this->eccubeConfig['eccube_url_len'] . '字以下で入力してください。',
                    ]),
                ],
                'hash_key' => [
                    new Regex([
                        'pattern' => "/^[A-z0-9]+$/",
                        'message' => '※ リンクタイプハッシュ値生成キーは英数字で入力してください。'
                    ]),
                    new Length([
                        'max' => 84,
                        'maxMessage' => '※ リンクタイプハッシュ値生成キーは84字以下で入力してください。',
                    ]),
                ],
                'link_payment_term' => [
                    new NotBlank(['message' => '※ 支払期限日は2～60日で設定してください。']),
                    new Regex([
                        'pattern' => "/^[0-9]+$/",
                        'message' => '※ 支払期限日は数字で入力してください。'
                    ]),
                    new GreaterThanOrEqual([
                        'value'=>'2',
                        'message' => '※ 支払期限日は2～60日で設定してください。',
                    ]),
                    new LessThanOrEqual([
                        'value'=>'60',
                        'message' => '※ 支払期限日は2～60日で設定してください。',
                    ]),
                ],
                'merchant_name' => [
                    new Regex([
                        'pattern' => "/^[^ \t\r\n\v\f]*$/",
                        'message' => '※ 店舗名(全角)に半角スペース・改行は入力できません。'
                    ]),
                    new Length([
                        'max' => 32,
                        'maxMessage' => '※ 店舗名(全角)は32字以下で入力してください。',
                    ]),
                ],
                'link_copy_right' => [
                    new Regex([
                        'pattern' => "/^[a-zA-Z0-9]+[ \t\r\n\v\f]*\(?[A-z0-9]*\)?[\w\d\s.\\\]*$/",
                        'message' => '※ 決済ページ用コピーライトは英数字で入力してください。'
                    ]),
                    new Length([
                        'max' => 128,
                        'maxMessage' => '※ 決済ページ用コピーライトは128字以下で入力してください。',
                    ]),
                ],
                'link_free_memo' => [
                    new Regex([
                        'pattern' => "/^[^ \t\r\n\v\f]*$/",
                        'message' => '※ 自由メモ欄(全角)に半角スペース・改行は入力できません。'
                    ]),
                    new Length([
                        'max' => 128,
                        'maxMessage' => '※ 自由メモ欄(全角)は128字以下で入力してください。',
                    ]),
                ],
                'return_url' => [
                    new Url([
                        'message' => '※ 決済完了後戻りURLを正しく入力してください。'
                    ]),
                    new Length([
                        'max' => $this->eccubeConfig['eccube_url_len'],
                        'maxMessage' => '※ 決済完了後戻りURLは' . $this->eccubeConfig['eccube_url_len'] . '字以下で入力してください。',
                    ]),
                ],
            ];
        }

        $builder->add('link_url', UrlType::class, [
            'constraints' => $arrFormConstraints['link_url'] ?? null,
        ]);
        $builder->add('hash_key', TextType::class, [
            'required' => false,
            'constraints' => $arrFormConstraints['hash_key'] ?? null,
        ]);
        $builder->add('link_payment_term', TextType::class, [
            'attr' => [
                'class' => 'lockon_card_row',
                'maxlength' => '2',
                'style' => 'width:50px',
            ],
            'constraints' => $arrFormConstraints['link_payment_term'] ?? null,
        ]);
        $builder->add(
            $builder
                ->create('merchant_name', TextType::class, [
                    'required' => false,
                    'constraints' => $arrFormConstraints['merchant_name'] ?? null,
                ])
                ->addEventSubscriber(new \Eccube\Form\EventListener\ConvertKanaListener('AK'))
        );
        $builder->add('link_copy_right', TextType::class, [
            'required' => false,
            'constraints' => $arrFormConstraints['link_copy_right'] ?? null,
        ]);
        $builder->add(
            $builder
                ->create('link_free_memo', TextType::class, [
                    'required' => false,
                    'constraints' => $arrFormConstraints['link_free_memo'] ?? null,
                ])
                ->addEventSubscriber(new \Eccube\Form\EventListener\ConvertKanaListener('AK'))
        );
        $builder->add('return_url', UrlType::class, [
            'required' => false,
            'constraints' => $arrFormConstraints['return_url'] ?? null,
        ]);

        $builder = $this->setDataForInvalidItem($builder, $isLink);
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
            'isLink' => false,
        ]);
    }
}
