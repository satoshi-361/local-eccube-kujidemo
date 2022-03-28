<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form\Type\Admin;

use Eccube\Common\EccubeConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Eccube\Form\Type\ToggleSwitchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * CSV決済依頼明細画面フォーム
 *
 */
class PaymentRequestItemType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * CategoryType constructor.
     *
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(EccubeConfig $eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('id', HiddenType::class)
            ->add('order_item_type_id', HiddenType::class)
            ->add('amount', NumberType::class, [
                'label' => false,
                'empty_data' => '0',
                'constraints' => [
                  new Assert\Length([
                      'max' => 10,
                  ]),
                  new Assert\GreaterThanOrEqual([
                      'value' => 0,
                  ]),
                  new Assert\Regex([
                      'pattern' => "/^\d+$/u",
                      'message' => 'form_error.numeric_only',
                  ]),
                ],
            ])
            ->add('point', TextType::class, [
                'label' => false,
                'empty_data' => '0',
                'constraints' => [
                  new Assert\Length([
                      'max' => 10,
                  ]),
                  new Assert\GreaterThanOrEqual([
                      'value' => 0,
                  ]),
                  new Assert\Regex([
                      'pattern' => "/^\d+$/u",
                      'message' => 'form_error.numeric_only',
                  ]),
                ],
            ])
            ->add('payment_target', ToggleSwitchType::class, [
                'label_on' => '',
                'label_off' => ''
            ])
            ;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'admin_payment_request_item';
    }
}
