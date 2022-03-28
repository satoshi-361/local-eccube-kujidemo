<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright (c) 2006 PAYGENT Co.,Ltd. All rights reserved.
 *
 * https://www.paygent.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Plugin\PaygentPayment\Form\Type\Admin;

use Eccube\Entity\Shipping;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Eccube\Common\EccubeConfig;
use Eccube\Form\Type\PriceType;
use Eccube\Form\Type\Master\OrderStatusType;
use Eccube\Repository\PaymentRepository;

class SearchPaymentType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    public function __construct(
        EccubeConfig $eccubeConfig,
        PaymentRepository $paymentRepository
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->paymentRepository = $paymentRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // 受注ID・注文者名・注文者（フリガナ）・注文者会社名
            ->add('multi', TextType::class, [
                'label' => 'paygent_payment.admin.order.multi_search_label',
                'required' => false,
                'constraints' => [
                    new Assert\Length(['max' => $this->eccubeConfig['eccube_stext_len']]),
                ],
            ])
            ->add('status', OrderStatusType::class, [
                'label' => 'paygent_payment.admin.order.order_status',
                'expanded' => true,
                'multiple' => true,
            ]);
        $builder = $this->detailBuildForm($builder);
        $builder = $this->detailPeriodBuildForm($builder);
    }

    /**
     * 詳細検索項目
     */
    public function detailBuildForm($builder)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'paygent_payment.admin.order.orderer_name',
                'required' => false,
            ])
            ->add($builder
                ->create('kana', TextType::class, [
                    'label' => 'paygent_payment.form_error.kana_only',
                    'required' => false,
                    'constraints' => [
                        new Assert\Regex([
                            'pattern' => '/^[ァ-ヶｦ-ﾟー]+$/u',
                            'message' => trans('paygent_payment.form_error.kana_only'),
                        ]),
                    ],
                ])
                ->addEventSubscriber(new \Eccube\Form\EventListener\ConvertKanaListener('CV')
            ))
            ->add('company_name', TextType::class, [
                'label' => 'paygent_payment.admin.order.orderer_company_name',
                'required' => false,
            ])
            ->add('email', TextType::class, [
                'label' => 'paygent_payment.admin.order.mail_address',
                'required' => false,
            ])
            ->add('order_no', TextType::class, [
                'label' => 'paygent_payment.admin.order.order_no',
                'required' => false,
            ])
            ->add('phone_number', TextType::class, [
                'label' => 'paygent_payment.admin.order.phone_number',
                'required' => false,
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => "/^[\d-]+$/u",
                        'message' => trans('paygent_payment.form_error.graph_and_hyphen_only'),
                    ]),
                ],
            ])
            ->add('tracking_number', TextType::class, [
                'label' => 'paygent_payment.admin.order.tracking_number',
                'required' => false,
            ])
            ->add('shipping_mail', ChoiceType::class, [
                'label' => 'paygent_payment.admin.order.shipping_mail',
                'placeholder' => false,
                'choices' => [
                    'paygent_payment.admin.order.shipping_mail__unsent' => Shipping::SHIPPING_MAIL_UNSENT,
                    'paygent_payment.admin.order.shipping_mail__sent' => Shipping::SHIPPING_MAIL_SENT,
                ],
                'expanded' => true,
                'multiple' => true,
            ])
            ->add('payment', ChoiceType::class, [
                'label' => 'paygent_payment.admin.order.payment_method',
                'required' => false,
                'choices' => $this->getPaymentMethod(),
                'expanded' => true,
                'multiple' => true,
            ])
            ->add('payment_total_start', PriceType::class, [
                'label' => 'paygent_payment.admin.order.purchase_price__start',
                'required' => false,
            ])
            ->add('payment_total_end', PriceType::class, [
                'label' => 'paygent_payment.admin.order.purchase_price__end',
                'required' => false,
            ])
            ->add('buy_product_name', TextType::class, [
                'label' => 'paygent_payment.admin.order.purchase_product',
                'required' => false,
            ]);

        return $builder;
    }

    /**
     * 詳細検索項目(期間指定する項目)
     */
    public function detailPeriodBuildForm($builder)
    {
        $builder = $this->orderDatePeriod($builder);
        $builder = $this->paymentDatePeriod($builder);
        $builder = $this->updateDatePeriod($builder);
        $builder = $this->shippingDatePeriod($builder);

        return $builder;
    }

    /**
     * 注文日
     */
    public function orderDatePeriod($builder)
    {
        $builder
            ->add('order_date_start', DateType::class, [
                'label' => 'paygent_payment.admin.order.order_date__start',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                'attr' => [
                    'class' => 'datetimepicker-input',
                    'data-target' => '#'.$this->getBlockPrefix().'_order_date_start',
                    'data-toggle' => 'datetimepicker',
                ],
            ])
            ->add('order_date_end', DateType::class, [
                'label' => 'paygent_payment.admin.order.order_date__end',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                'attr' => [
                    'class' => 'datetimepicker-input',
                    'data-target' => '#'.$this->getBlockPrefix().'_order_date_end',
                    'data-toggle' => 'datetimepicker',
                ],
            ]);

        return $builder;
    }

    /**
     * 入金日
     */
    public function paymentDatePeriod($builder)
    {
        $builder
            ->add('payment_date_start', DateType::class, [
                'label' => 'paygent_payment.admin.order.payment_date__start',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                'attr' => [
                    'class' => 'datetimepicker-input',
                    'data-target' => '#'.$this->getBlockPrefix().'_payment_date_start',
                    'data-toggle' => 'datetimepicker',
                ],
            ])
            ->add('payment_date_end', DateType::class, [
                'label' => 'paygent_payment.admin.order.payment_date__start',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                'attr' => [
                    'class' => 'datetimepicker-input',
                    'data-target' => '#'.$this->getBlockPrefix().'_payment_date_end',
                    'data-toggle' => 'datetimepicker',
                ],
            ]);

        return $builder;
    }

    /**
     * 更新日
     */
    public function updateDatePeriod($builder)
    {
        $builder
            ->add('update_date_start', DateType::class, [
                'label' => 'paygent_payment.admin.order.update_date__start',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                'attr' => [
                    'class' => 'datetimepicker-input',
                    'data-target' => '#'.$this->getBlockPrefix().'_update_date_start',
                    'data-toggle' => 'datetimepicker',
                ],
            ])
            ->add('update_date_end', DateType::class, [
                'label' => 'paygent_payment.admin.order.update_date__end',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                'attr' => [
                    'class' => 'datetimepicker-input',
                    'data-target' => '#'.$this->getBlockPrefix().'_update_date_end',
                    'data-toggle' => 'datetimepicker',
                ],
            ]);

        return $builder;
    }

    /**
     * お届け日
     */
    public function shippingDatePeriod($builder)
    {
        $builder
            ->add('shipping_delivery_date_start', DateType::class, [
                'label' => 'paygent_payment.admin.order.delivery_date__start',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                'attr' => [
                    'class' => 'datetimepicker-input',
                    'data-target' => '#'.$this->getBlockPrefix().'_shipping_delivery_date_start',
                    'data-toggle' => 'datetimepicker',
                ],
            ])
            ->add('shipping_delivery_date_end', DateType::class, [
                'label' => 'paygent_payment.admin.order.delivery_date__end',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                'attr' => [
                    'class' => 'datetimepicker-input',
                    'data-target' => '#'.$this->getBlockPrefix().'_shipping_delivery_date_end',
                    'data-toggle' => 'datetimepicker',
                ],
            ]);

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'admin_search_order';
    }

    /**
     * 検索条件用ペイジェント決済情報取得
     */
    public function getPaymentMethod()
    {
        $arrPayment = [];
        foreach($this->eccubeConfig['paygent_payment']['create_payment_param'] as $createParam) {
            $payment = $this->paymentRepository->findOneBy(['method_class' => $createParam["method_class"]]);
            if ($payment){
                $arrPayment[$payment->getMethod()] = $payment->getId();
            }
        }

        return $arrPayment;
    }
}
