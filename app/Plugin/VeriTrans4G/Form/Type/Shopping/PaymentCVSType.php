<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form\Type\Shopping;

use Eccube\Service\CartService;
use Eccube\Repository\OrderRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class PaymentCVSType extends AbstractType
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
     * @return void
     */
    public function __construct(
        ContainerInterface $container,
        CartService $cartService,
        OrderRepository $orderRepository
    )
    {
        $this->container = $container;
        $this->cartService = $cartService;
        $this->orderRepository = $orderRepository;
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
        $preOrderId = $this->cartService->getPreOrderId();
        $order = $this->orderRepository->findOneBy(['pre_order_id' => $preOrderId]);

        if (is_null($order)) {
            return;
        }

        $conviniList = (array)$options['data']['paymentInfo']['conveni'];
        $conveniMap = array_column( $this->vt4gConst['VT4G_CONVENI'], 'CODE', 'LABEL');
        $conveniChoices = [];
        // コンビニ決済の設定で有効なものを絞り込む
        foreach ($conveniMap as $label => $value) {
            if (in_array($value, $conviniList, true)) {
                $conveniChoices[$label] = $value;
            }
        }

        $builder
            ->add('conveni', ChoiceType::class, [
                'required' => true,
                'expanded' => false,
                'multiple' => false,
                'placeholder' => '選択してください',
                'choices' => $conveniChoices,
                'label' => 'コンビニ選択',
                'mapped' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'vt4g_plugin.validate.not_blank.choice'
                    ])
                ]
            ]);
    }
}