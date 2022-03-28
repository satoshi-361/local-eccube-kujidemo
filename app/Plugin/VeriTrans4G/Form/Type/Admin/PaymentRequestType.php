<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form\Type\Admin;

use Eccube\Common\EccubeConfig;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * CSV決済依頼画面フォーム
 *
 */
class PaymentRequestType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

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
            ->add('settlement_fee', NumberType::class, [
                'label' => false,
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
            ->add('pay_req_items', CollectionType::class, [
                'required' => false,
                'entry_type' => PaymentRequestItemType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
            ])
            ;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'admin_payment_request';
    }
}
