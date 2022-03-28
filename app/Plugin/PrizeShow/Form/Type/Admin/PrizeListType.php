<?php

namespace Plugin\PrizeShow\Form\Type\Admin;

use Plugin\PrizeShow\Entity\PrizeList;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

use Eccube\Common\EccubeConfig;
use Eccube\Repository\CategoryRepository;
use Plugin\PrizeShow\Form\Type\Admin\ConfigType;

class PrizeListType extends AbstractType
{
    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * ProductType constructor.
     *
     * @param CategoryRepository $categoryRepository
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        CategoryRepository $categoryRepository,
        EccubeConfig $eccubeConfig
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class, [
            'required' => true
        ])
        ->add('prize_list', CollectionType::class, [
            'entry_type' => ConfigType::class,
            'prototype' => true,
            'mapped' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'admin_prize';
    }
}
