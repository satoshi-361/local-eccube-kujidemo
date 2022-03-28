<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form\Extension;

use Eccube\Form\Type\Admin\PaymentRegisterType;
use Plugin\VeriTrans4G\Form\ExtensionUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Range;

/**
 * クレジット支払設定画面用フォーム拡張クラス
 */
class CreditTypeExtension extends AbstractTypeExtension
{

    /**
     * コンテナ
     */
    private $container;

    /**
     * エンティティーマネージャー
     */
    private $em;

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
        $this->container = $container;
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $this->container->getParameter('vt4g_plugin.const');
    }

    /**
     * フォームを作成します。
     * {@inheritDoc}
     * @see \Symfony\Component\Form\AbstractTypeExtension::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $Vt4gPaymentMethod = ExtensionUtil::getPaymentMethod($builder, $this->em);
        if (!ExtensionUtil::hasPaymentId($Vt4gPaymentMethod, $this->vt4gConst['VT4G_PAYTYPEID_CREDIT'])) {
            return;
        }
        $savedData = ExtensionUtil::getSaveData($Vt4gPaymentMethod);

        $builder
            ->add('withCapture', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'required' => true,
                'expanded' => true,
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['WITH_CAPTURE'],
                'label' => '処理区分',
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ]),
                ],
                'mapped' => false,
                'data' => $savedData['withCapture'] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['WITH_CAPTURE'],
            ])
            ->add('credit_pay_methods', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['CREDIT_PAY_METHOD'],
                'required' => true,
                'expanded' => true,
                'multiple' => true,
                'mapped' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ]),
                ],
                'mapped' => false,
                'data' => $savedData['credit_pay_methods'] ?? [],
            ])
            ->add('security_flg', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'expanded' => true,
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['COMMON_FLG'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ])
                ],
                'mapped' => false,
                'data' => $savedData['security_flg'] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['SECURITY_FLG'],
            ])
            ->add('mpi_flg', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'expanded' => true,
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['COMMON_FLG'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ])
                ],
                'mapped' => false,
                'data' => $savedData['mpi_flg'] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['MPI_FLG'],
            ])
            ->add('secure_second_flg', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'expanded' => true,
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['COMMON_FLG'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ])
                ],
                'mapped' => false,
                'data' => $savedData['secure_second_flg'] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['SECURE_SECOND_FLG'],
            ])
            ->add('mpi_option', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'expanded' => true,
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['MPI_OPTION'],
                'mapped' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ])
                ],
                'data' => $savedData['mpi_option'] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['MPI_OPTION'],
            ])
            ->add('one_click_flg', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'expanded' => true,
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['ONE_CLICK_FLG'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ])
                ],
                'mapped' => false,
                'data' => $savedData['one_click_flg'] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['ONE_CLICK_FLG'],
            ])
            ->add('veritrans_id_prefix', TextType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'vt4g_from_input_text__veritrans_id_prefix',
                    'maxlength' => $this->vt4gConst['VT4G_FORM']['LENGTH']['MAX']['VERITRANS_ID_PREFIX']
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank'
                    ]),
                    new Regex([
                        'pattern' => $this->vt4gConst['VT4G_FORM']['REGEX']['VERITRANS_ID_PREFIX'],
                        'message' => 'vt4g_plugin.validate.alnum.hyphen.underscore.period'
                    ]),
                    new Length([
                        'max' => $this->vt4gConst['VT4G_FORM']['LENGTH']['MAX'
                        ]['VERITRANS_ID_PREFIX'],
                    ]),
                ],
                'mapped' => false,
            ])
            ->add('order_mail_title1', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'vt4g_from_input_text__large',
                    'maxlength' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["ORDER_MAIL_TITLE1"],
                ],
                'mapped' => false,
                'constraints' => [
                    new Length(
                        [
                            'max' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["ORDER_MAIL_TITLE1"]
                        ]
                    ),
                ],
            ])
            ->add('order_mail_body1', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'vt4g_from_input_textarea',
                    'maxlength' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["ORDER_MAIL_BODY1"],
                ],
                'mapped' => false,
                'constraints' => [
                    new Length(
                        [
                            'max' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["ORDER_MAIL_BODY1"]
                        ]
                    ),
                ],
            ])
            ->add('cardinfo_regist_default', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'expanded' => true,
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['REGISTRATION_FLG'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ])
                ],
                'mapped' => false,
                'data' => $savedData['cardinfo_regist_default'] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['CARDINFO_REGIST_DEFAULT'],
            ])
            ->add('cardinfo_regist_max', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'vt4g_from_input_text__payment_term_day',
                ],
                'mapped' => false,
                'constraints' => [
                    new NotBlank(
                        [
                            'message' => 'vt4g_plugin.validate.not_blank'
                        ]
                    ),
                    new Regex(
                        [
                            'pattern' => '/^[0-9]+$/',
                            'message' => 'vt4g_plugin.validate.num',
                        ]
                    ),
                    new Range(
                        [
                            'min' => $this->vt4gConst['VT4G_FORM']["RANGE"]["MIN"]["CARDINFO_REGIST_MAX"],
                            'max' => $this->vt4gConst['VT4G_FORM']["RANGE"]["MAX"]["CARDINFO_REGIST_MAX"]
                        ]
                    ),
                ],
                'data' => $savedData['cardinfo_regist_max'] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['CARDINFO_REGIST_MAX'],
            ]);
    }

    /**
     * 拡張元クラスを取得します。
     * {@inheritDoc}
     * @see \Symfony\Component\Form\FormTypeExtensionInterface::getExtendedType()
     */
    public function getExtendedType()
    {
        return PaymentRegisterType::class;
    }

    /**
     * Return the class of the type being extended.
     * EX-CUBE4.1への更新に伴うEC-CUBE4.0.x(Symfony3.4)と互換性を保ための関数
     * getExtendedTypes()が未定義のExtension.phpが存在する場合、
     * 4.1以降のプラグイン有効化にてエラーが発生する。
     */
    public static function getExtendedTypes(): iterable
    {
        return [PaymentRegisterType::class];
    }

}
