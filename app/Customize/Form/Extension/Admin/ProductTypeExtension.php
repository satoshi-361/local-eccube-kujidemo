<?php

declare(strict_types=1);

namespace Customize\Form\Extension\Admin;

use Eccube\Common\EccubeConfig;
use Eccube\Form\Type\Admin\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Plugin\LotteryProbability\Form\Type\Admin\ConfigType as LotPro;
use Plugin\ProductAssist\Form\Type\Admin\ConfigType as ProductAssistType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class ProductTypeExtension extends AbstractTypeExtension
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * ProductType constructor.
     *
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
      EccubeConfig $eccubeConfig
    ) {
        $this->eccubeConfig = $eccubeConfig;
    }

    public function getExtendedType(): string
    {
        return ProductType::class;
    }

    public function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
          ->add('sales_start', DateTimeType::class, [
            'required' => false,
            'input' => 'datetime',
            'years' => range(date('Y'), date('Y') - $this->eccubeConfig['eccube_birth_max']),
            'widget' => 'single_text',             
            'constraints' => [
                new Assert\LessThanOrEqual([
                    'value' => date('Y-m-d', strtotime('-1 day')),
                    'message' => 'form_error.select_is_future_or_now_date',
                ]),
            ],
        ])
        ->add('sales_end', DateTimeType::class, [
            'required' => false,
            'input' => 'datetime',
            'years' => range(date('Y'), date('Y') - $this->eccubeConfig['eccube_birth_max']),
            'widget' => 'single_text',
            'constraints' => [
                new Assert\LessThanOrEqual([
                    'value' => date('Y-m-d', strtotime('-1 day')),
                    'message' => 'form_error.select_is_future_or_now_date',
                ]),
            ],
        ])
        ->add('limit_count', ChoiceType::class, [
            'choices' => [
                '制限なし' => '制限なし',
                '1日に1回' => '1日に1回',
                '1アカウントに1回' => '1アカウントに1回'
            ],
            'expanded' => true,
        ])
        ->add('lottery_probability', LotPro::class, [
            'mapped' => false
        ])
        ->add('product_assist', ProductAssistType::class, [
            'mapped' => false
        ]);
    }
}