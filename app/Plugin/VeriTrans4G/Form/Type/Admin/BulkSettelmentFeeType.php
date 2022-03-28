<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form\Type\Admin;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * CSV決済依頼管理 決済依頼一覧画面　決済手数料一括変更フォーム
 *
 */
class BulkSettelmentFeeType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('fee', TextType::class, [
                'label' => false,
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => '/^[0-9]+$/',
                        'message' => 'vt4g_plugin.validate.positive_or_zero',
                    ]),
                ],
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'bulk_settelment_fee';
    }
}
