<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Form\Type\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * 受注管理詳細 コンビニ選択フォーム
 */
class OrderEditCvsType extends AbstractType
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
     *
     * @param  ContainerInterface   $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
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
        $conveniChoices = $this->getConveniChoices($options['data']['paymentMethodInfo']);

        $builder
            ->add('conveni', ChoiceType::class, [
                'label'       => 'コンビニ選択',
                'placeholder' => '--',
                'choices'     => $conveniChoices,
                'required'    => false,
            ]);
    }

    /**
     * コンビニ支払設定で有効な対象の選択肢を取得
     *
     * @param  array $paymentInfo コンビニ支払設定
     * @return array              有効設定のコンビニ
     */
    private function getConveniChoices($paymentMethodInfo)
    {
        $master = $this->vt4gConst['VT4G_FORM']['CHOICES']['CONVENI'];
        return array_filter($master, function ($val) use ($paymentMethodInfo) {
            return in_array($val, $paymentMethodInfo['conveni'] ?? []);
        });
    }
}
