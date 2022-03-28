<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form\Type\Admin;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Plugin\VeriTrans4G\Form\Constraint\ContainsUsedMailFlg;

/**
 * プラグイン設定画面用フォームクラス
 */
class PluginConfigType extends AbstractType
{
    /**
     * コンテナ
     */
    private $container;

    /**
     * ユーティリティサービス
     */
    private $util;

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * コンストラクタ
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
        $this->util = $container->get('vt4g_plugin.service.util');
    }

    /**
     * フォームを作成します。
     * {@inheritDoc}
     * @see \Symfony\Component\Form\AbstractType::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $savedData = $this->util->getPluginSetting();

        $builder
            ->add('merchant_ccid', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank'
                    ]),
                    new Length(
                        [
                            'max' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["MERCHANT_CCID"]
                        ]
                    ),
                    new Regex([
                        'pattern' => '/^[[:alnum:]]+$/i',
                        'message' => 'vt4g_plugin.validate.alnum',
                    ]),
                ],
            ])
            ->add('merchant_pass', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank'
                    ]),
                    new Length(
                        [
                            'max' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["MERCHANT_PASS"]
                        ]
                    ),
                    new Regex([
                        'pattern' => '/^[[:alnum:]]+$/i',
                        'message' => 'vt4g_plugin.validate.alnum',
                    ]),
                ],
            ])
            ->add('merchant_id', TextType::class, [
                'constraints' => [
                    new Length(
                        [
                            'max' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["MERCHANT_ID"]
                        ]
                    ),
                    new Regex([
                        'pattern' => '/^[[:alnum:]]+$/i',
                        'message' => 'vt4g_plugin.validate.alnum',
                    ]),
                ],
                'required' => false,
            ])
            ->add('merchant_hash', TextType::class, [
                'constraints' => [
                    new Length(
                        [
                            'max' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["MERCHANT_HASH"]
                        ]
                    ),
                    new Regex([
                        'pattern' => '/^[[:alnum:]]+$/i',
                        'message' => 'vt4g_plugin.validate.alnum',
                    ]),
                ],
                'required' => false,
            ])
            ->add('token_api_key', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank'
                    ]),
                    new Length(
                        [
                            'max' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["TOKEN_API_KEY"]
                        ]
                    ),
                    new Regex([
                        'pattern' => '/^[[:alnum:]\-]+$/i',
                        'message' => 'vt4g_plugin.validate.alnum.hyphen',
                    ]),
                ],
            ])
            ->add('vt4g_order_id_prefix', TextType::class, [
                'constraints' => [
                    new Length(
                        [
                            'max' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["VT4G_ORDER_ID_PREFIX"]
                        ]
                    ),
                    new Regex([
                        'pattern' => '/^[[:alnum:]_\-]+$/i',
                        'message' => 'vt4g_plugin.validate.alnum.hyphen.underscore',
                    ]),
                ],
                'required' => false,
            ])
            ->add('enable_payment_type', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'choices' => array(
                    $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_10'] => $this->vt4gConst['VT4G_PAYTYPEID_CREDIT'],
                    $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_20'] => $this->vt4gConst['VT4G_PAYTYPEID_CVS'],
                    $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_30'] => $this->vt4gConst['VT4G_PAYTYPEID_BANK'],
                    $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_31'] => $this->vt4gConst['VT4G_PAYTYPEID_ATM'],
                    $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_40'] => $this->vt4gConst['VT4G_PAYTYPEID_UPOP'],
                    $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_50'] => $this->vt4gConst['VT4G_PAYTYPEID_ALIPAY'],
                    $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_60'] => $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN'],
                    $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_61'] => $this->vt4gConst['VT4G_PAYTYPEID_RECRUIT'],
                    $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_62'] => $this->vt4gConst['VT4G_PAYTYPEID_LINEPAY'],
                    $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_63'] => $this->vt4gConst['VT4G_PAYTYPEID_CARRIER'],
                    $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_70'] => $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL'],
                ),
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ]),
                ],
                'expanded' => true,
                'multiple' => true,
            ])
            ->add('dummy_mode_flg', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['DUMMY_MODE_FLG'],
                'data' => $savedData["dummy_mode_flg"] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['DUMMY_MODE_FLG'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ]),
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('order_mail_timing_flg', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['ORDER_MAIL_TIMING_FLG'],
                'data' => $savedData["order_mail_timing_flg"] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['ORDER_MAIL_TIMING_FLG'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ]),
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('used_mail_flg', ChoiceType::class, [
                'choices' => array(
                    'メール送信に同意する' => '1',
                ),
                'constraints' => [
                    new ContainsUsedMailFlg(),
                ],
                'expanded' => true,
                'multiple' => true,
                'placeholder' => false,
                'required' => false,
            ]);
    }

}
