<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Form\Type\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Plugin\VeriTrans4G\Service\UtilService;

/**
 * 店舗設定 VT用支払方法新規登録画面の選択フォーム
 */
class PaymentRegistType extends AbstractType
{

    /**
     * コンテナ
     */
    protected $container;

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * ユーティリティサービス
     */
    private $util;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface   $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
        $this->util = $container->get('vt4g_plugin.service.util');
    }

    /**
     * フォーム生成
     *
     * @param  FormBuilderInterface $builder フォームビルダー
     * @param  array                $options フォーム生成に使用するデータ
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $paymentMethodChoices = [];
        $enablePaymentType = $this->util->getEnablePayIdList();
        foreach ($enablePaymentType as $paymentType){
            $paymentMethodChoices[$this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$paymentType]] = intval($paymentType);
        }
        asort($paymentMethodChoices);
        $builder
        ->add('regist_payment_type', ChoiceType::class, [
            'choices'     => $paymentMethodChoices,
            'constraints' => [
                new NotBlank([
                    'message' => 'vt4g_plugin.validate.regist.enable_payment_type.not_blank'
                ]),
            ]
        ]);
    }

}
