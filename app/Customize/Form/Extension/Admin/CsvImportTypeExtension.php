<?php

declare(strict_types=1);

namespace Customize\Form\Extension\Admin;

use Eccube\Common\EccubeConfig;
use Eccube\Form\Type\Admin\CsvImportType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CsvImportTypeExtension extends AbstractTypeExtension
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
        return CsvImportType::class;
    }

    public function getExtendedTypes(): iterable
    {
        return [CsvImportType::class];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('is_split_csv', CheckboxType::class, [
            'label' => false,
            'mapped' => false,
            'required' => false,
        ])
        ->add('csv_file_no', IntegerType::class, [
            'label' => false,
            'mapped' => false,
            'required' => false,
        ]);
    }
}