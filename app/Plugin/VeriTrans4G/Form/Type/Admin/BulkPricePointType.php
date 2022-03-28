<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form\Type\Admin;

use Eccube\Common\EccubeConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Validator\Constraints as Assert;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentReqEvent;

/**
 * CSV決済依頼管理 決済依頼一覧画面　金額ポイント一括変更フォーム
 *
 */
class BulkPricePointType extends AbstractType
{
    /**
     * コンテナ
     */
    private $container;

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * CategoryType constructor.
     *
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(ContainerInterface $container, EccubeConfig $eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
        $this->em = $container->get('doctrine.orm.entity_manager');
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $products = $this->em->getRepository(Vt4gPaymentReqEvent::class)
                    ->getProductListByEventId($options['data']['event_id']);
        $productList = [];
        foreach ($products as $product) {
            $productList[$product['product_name']] = (int)$product['product_class_id'];
        }

        $builder
            ->add('item', ChoiceType::class, [
                    'choices'  => $productList,
                    'constraints' => [
                        new Assert\NotBlank(),
                    ]
                ]
            )
            ->add('amount', TextType::class, [
                'required' => false,
                'label' => false,
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => '/^[0-9]+$/',
                        'message' => 'vt4g_plugin.validate.positive_or_zero',
                    ]),
                ]
            ])
            ->add('point', TextType::class, [
                    'required' => false,
                    'constraints' => [
                        new Assert\Regex([
                            'pattern' => '/^[0-9]+$/',
                            'message' => 'vt4g_plugin.validate.positive_or_zero',
                        ]),
                    ],
                ]
            )
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
                $form = $event->getForm();
                $amount = $form['amount']->getData();
                $point = $form['point']->getData();

                if ( (empty($amount) && $amount >= 0 ) && ( empty($point) && $point >= 0 ) ) {
                    $form['amount']->addError(new FormError(trans('vt4g_plugin.admin.payment_request.list.require_price_or_point')));
                }
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'bulk_price_point';
    }
}
