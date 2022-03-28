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

/**
 * LINE Pay決済設定画面用フォーム拡張クラス
 */
class LinepayTypeExtension extends AbstractTypeExtension
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
        if (!ExtensionUtil::hasPaymentId($Vt4gPaymentMethod, $this->vt4gConst['VT4G_PAYTYPEID_LINEPAY'])) {
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
            ->add('item_name', TextType::class, [
                'required' => false,
                'attr' => [
                    'maxlength' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["ITEM_NAME"],
                ],
                'mapped' => false,
                'constraints' => [
                    new NotBlank(
                        [
                            'message' => 'vt4g_plugin.validate.not_blank'
                        ]
                    ),
                    new Length(
                        [
                            'max' => $this->vt4gConst['VT4G_FORM']["LENGTH"]["MAX"]["ITEM_NAME"]
                        ]
                    ),
                ],
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
