<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Form\Type\Shopping;

use Eccube\Service\CartService;
use Eccube\Repository\OrderRepository;
use Plugin\VeriTrans4G\Repository\Vt4gPluginRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Length;
/**
 * クレジットカード情報入力フォーム
 */
class PaymentCarrierType extends AbstractType
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
     * @param  CartService          $cartService
     * @param  OrderRepository      $orderRepository
     * @param  Vt4gPluginRepository $vt4gPluginRepository
     * @return void
     */
    public function __construct(
        ContainerInterface $container,
        CartService $cartService,
        OrderRepository $orderRepository,
        Vt4gPluginRepository $vt4gPluginRepository
    )
    {
        $this->container = $container;
        $this->cartService = $cartService;
        $this->orderRepository = $orderRepository;
        $this->vt4gPluginRepository = $vt4gPluginRepository;
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
        $builder
            ->add('service_option_type', ChoiceType::class, [
                'label' => 'サービスオプションタイプ',
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['CARRIER_SERVICE_TYPE'],
            ])
            // ->add('terminal_kind', ChoiceType::class, [
            //     'label' => '端末種別',
            //     'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['CARRIER_TERMINAL_KIND'],
            // ])
            // ->add('item_type', ChoiceType::class, [
            //     'label' => '商品タイプ',
            //     'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['CARRIER_ITEM_TYPE'],
            // ])
            ->add('accounting_type', ChoiceType::class, [
                'label' => '課金種別',
                'choices' => $this->vt4gConst['VT4G_FORM']['CHOICES']['CARRIER_ACCOUNT_TYPE'],
            ])
            ->add('mp_first_date', TextType::class, [
                'label' => '初回課金年月日',
                'required' => false
            ])
            ->add('mp_day', TextType::class, [
                'label' => '継続課金日',
                'required' => false
            ])
            ->add('item_id', TextType::class, [
                'label' => '商品番号',
                'required' => false
            ])
            ->add('item_info', TextType::class, [
                'label' => '商品情報',
                'required' => false
            ])
            ->add('open_id', TextType::class, [
                'label' => 'OpenID',
                'required' => false
            ])
            ->add('flets_area', ChoiceType::class, [
                'label' => 'フレッツエリア',
                'required' => false,
                'choices' => [
                    '東日本' => 0,
                    '西日本' => 1
                ]
            ])
            ->add('login_au_id', ChoiceType::class, [
                'label' => 'auIDログインフラグ',
                'required' => false,
                'choices' => [
                    'ID連携' => 0,
                    'auIDログイン' => 1
                ]
            ]);
    }
}
