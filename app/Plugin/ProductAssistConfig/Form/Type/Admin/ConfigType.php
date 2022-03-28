<?php

namespace Plugin\ProductAssistConfig\Form\Type\Admin;

use Plugin\ProductAssistConfig\Entity\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

use Plugin\PrizeShow\Repository\PrizeListRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;

use Eccube\Common\EccubeConfig;
use Eccube\Repository\CategoryRepository;

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
	
	protected $prizeListRepo;

    /**
     * ProductType constructor.
     *
     * @param CategoryRepository $categoryRepository
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        CategoryRepository $categoryRepository,
        EccubeConfig $eccubeConfig,
		PrizeListRepository $prizeListRepo
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->eccubeConfig = $eccubeConfig;
		$this->prizeListRepo = $prizeListRepo;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$choices = array();
		foreach($this->prizeListRepo->findAll() as $item)
		{
			$choices[$item->getName()] = $item->getId();
		}
        $builder
        ->add('grade', TextType::class, [
            'required' => false,
        ])
        ->add('className', TextType::class, [
            'required' => false,
        ])
        ->add('descriptionText', TextareaType::class, [
            'required' => false,
        ])
        ->add('showText', TextType::class, [
            'required' => false,
        ])
        ->add('setOption', ChoiceType::class, [
            'required' => false,
            'choices' => $choices,
        ])
        ->add('setCount', NumberType::class, [
            'required' => false,
        ])
        ->add('colorName', ChoiceType::class, [
            'required' => false,
            'choices' => [
				'ホワイト' => 'white',
				'レッド' => 'red',
				'ブルー' => 'blue',
				'グリーン' => 'green',
				'グレー' => 'gray',
				'ピンク' => 'pink',
				'オレンジ' => 'orange',
				'イエロー' => 'yellow',
				'シルバー' => 'silver',
				'ゴールド' => 'gold',
			],
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
