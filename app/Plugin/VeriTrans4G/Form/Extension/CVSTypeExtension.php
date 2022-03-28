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
 * コンビニ支払設定画面用フォーム拡張クラス
 */
class CVSTypeExtension extends AbstractTypeExtension
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
        if (!ExtensionUtil::hasPaymentId($Vt4gPaymentMethod, $this->vt4gConst['VT4G_PAYTYPEID_CVS'])) {
            return;
        }
        $savedData = ExtensionUtil::getSaveData($Vt4gPaymentMethod);

        $builder
            ->add('conveni', ChoiceType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_choice',
                ],
                'required' => true,
                'expanded' => true,
                'multiple' => true,
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['CONVENI'],
                'label' => 'コンビニ選択',
                'mapped' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ])
                ],
                'data' => $savedData['conveni'] ?? [],
            ])
            ->add('payment_term_day', TextType::class, [
                'attr' => [
                    'class' => 'vt4g_from_input_text__payment_term_day',
                    'maxlength' => $this->vt4gConst['VT4G_FORM']['LENGTH']['MAX']['PAYMENT_TERM'],
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank'
                    ]),
                    new Regex([
                        'pattern' => '/^[0-9]+$/',
                        'message' => 'vt4g_plugin.validate.num',
                    ]),
                    new Range([
                        'min' => $this->vt4gConst['VT4G_FORM']['RANGE']['MIN']['PAYMENT_TERM'],
                        'max' => $this->vt4gConst['VT4G_FORM']['RANGE']['MAX']['PAYMENT_TERM'],
                    ]),
                    new Length([
                        'max' => $this->vt4gConst['VT4G_FORM']['LENGTH']['MAX']['PAYMENT_TERM_DAY'],
                    ]),
                ],
                'mapped' => false,
            ])
            ->add('free1', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'vt4g_from_input_text__small',
                    'maxlength' => $this->vt4gConst['VT4G_FORM']['LENGTH']['MAX']['FREE']
                ],
                'constraints' => [
                    new Length([
                        'max' => $this->vt4gConst['VT4G_FORM']['LENGTH']['MAX']['FREE']
                    ])
                ],
                'mapped' => false,
            ])
            ->add('free2', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'vt4g_from_input_text__small',
                    'maxlength' => $this->vt4gConst['VT4G_FORM']['LENGTH']['MAX']['FREE']
                ],
                'constraints' => [
                    new Length([
                        'max' => $this->vt4gConst['VT4G_FORM']['LENGTH']['MAX']['FREE']
                    ])
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
