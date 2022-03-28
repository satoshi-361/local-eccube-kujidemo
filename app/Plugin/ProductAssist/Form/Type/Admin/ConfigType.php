<?php

namespace Plugin\ProductAssist\Form\Type\Admin;

use Plugin\ProductAssist\Entity\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

use Plugin\ProductAssistConfig\Form\Type\Admin\ConfigType as ProductAssistConfigType;
use Eccube\Common\EccubeConfig;
use Eccube\Repository\CategoryRepository;
use Eccube\Repository\ProductRepository;

class ConfigType extends AbstractType
{

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;
	
	protected $productRepo;
    /**
     * ProductType constructor.
     *
     * @param CategoryRepository $categoryRepository
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        CategoryRepository $categoryRepository,
        EccubeConfig $eccubeConfig,
		ProductRepository $productRepo
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->eccubeConfig = $eccubeConfig;
		$this->productRepo = $productRepo;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices_bulk = array();
        $Products = $this->productRepo->findAll();
        foreach($Products as $Product){
            $Categories = $Product->getProductCategories();
            foreach($Categories as $Category)
            {	
                if ($Category->getCategory()->getName() == "まとめ買いくじ")
                {
                    $choices_bulk[$Product->getName()] = $Product->getId();
                }
            }
        }

        $choices_normal = array();
        foreach($Products as $Product){
            $Categories = $Product->getProductCategories();
            foreach($Categories as $Category)
            {	
                if ($Category->getCategory()->getName() == "通常くじ")
                {
                    $choices_normal[$Product->getName()] = $Product->getId();
                }
            }
        }

        $builder
        ->add('bulk_config_lottery', ChoiceType::class, [
            'required' => false,
            'choices' => $choices_bulk
        ])
        ->add('product_link_lottery', ChoiceType::class, [
            'required' => false,
            'choices' => $choices_normal
        ])
        ->add('cart_button_text', TextType::class, [
            'required' => false,
        ])
        ->add('bulk_setting', CollectionType::class, [
            'required' => false,
            'entry_type' => ProductAssistConfigType::class,
            'prototype' => true,
            'mapped' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ])
        ->add('confirmed_lottery', CollectionType::class, [
            'required' => false,
            'entry_type' => ProductAssistConfigType::class,
            'prototype' => true,
            'mapped' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ])
        ->add('ship_count', NumberType::class, [
            'required' => false,
        ])
        ->add('is_animate', CheckboxType::class, [
            'required' => false,
			'label' => '適用します',
        ])
        ->add('winning_count', NumberType::class, [
            'required' => false,
        ])
        ->add('lottery_probability', CollectionType::class, [
            'required' => false,
            'entry_type' => ProductAssistConfigType::class,
            'prototype' => true,
            'mapped' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ])
        ->add('free_text', TextType::class, [
            'required' => false,
        ])
        ->add('delivery_day_text', TextType::class, [
            'required' => false,
        ])
        ->add('sale_end_text', TextType::class, [
            'required' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }
}
