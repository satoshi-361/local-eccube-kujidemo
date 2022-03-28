<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form\Type\Admin;

use Eccube\Common\EccubeConfig;
use Plugin\VeriTrans4G\Repository\Master\Vt4gSubscSaleTypeRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * CSV決済　販売種別名、イベント名フォーム
 *
 */
class PaymentReqEventType extends AbstractType
{

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var Vt4gSubscSaleTypeRepository
     */
    protected $saleTypeRepository;

    /**
     * CategoryType constructor.
     *
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(EccubeConfig $eccubeConfig, Vt4gSubscSaleTypeRepository $saleTypeRepository)
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->saleTypeRepository = $saleTypeRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $saleTypes = [];
        foreach ($this->saleTypeRepository->getList() as $st) {
            $saleTypes[$st->getSaleTypeId()] = $st->getName();
        }

        $builder
            ->add('sale_type_id', ChoiceType::class, [
                    'choices'  => array_flip($saleTypes),
                    'placeholder' => '',
                    'required' => false,
                ]
            )
            ->add('event_name', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => $this->eccubeConfig['eccube_stext_len'],
                    ]),
                ],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'admin_payment_req_event';
    }
}
