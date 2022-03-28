<?php

namespace Plugin\LotteryProbability\Form\Type\Admin;

use Eccube\Common\EccubeConfig;
use Plugin\LotteryProbability\Entity\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints as Assert;
use Plugin\PrizeShow\Repository\PrizeListRepository;

class ConfigType extends AbstractType
{

    /**
     * @var PrizeListRepository
     */
    protected $prizeListRepository;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * ProductType constructor.
     *
     * @param PrizeListRepository $prizeListRepository
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        PrizeListRepository $prizeListRepository,
        EccubeConfig $eccubeConfig
    ) {
        $this->prizeListRepository = $prizeListRepository;
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices = array();
        $prizes = $this->prizeListRepository->findAll();
        foreach($prizes as $prize)
            $choices[$prize->getName()] = $prize->getId();

        $builder
            ->add('winning', TextType::class, [
                'label' => '等数',
                'required' => false
            ])
            ->add('rank_name', TextType::class, [
                'label' => '景品ランク呼び名',
                'required' => false      
            ])
            ->add('explain_text', TextareaType::class, [
                'constraints' => [
                    new Assert\Length(['max' => 255])
                ],
                'label' => '説明文',
                'required' => false
            ])
            ->add('winning_probability', NumberType::class, [
                'label' => '当選確率',
                'required' => true
            ])
            ->add('display_winning', TextType::class, [
                'label' => '当選確率の表示',
                'required' => true
            ])
            ->add('product_set', ChoiceType::class, [
                'choices' => $choices,
                'label' => '設定する商品',
                'required' => false
            ])
            ->add('color', ChoiceType::class, [
                'choices' => [
                    'ホワイト' => 0,
                    'レッド' => 1,
                    'ブルー' => 2,
                    'グリーン' => 3,
                    'ピンク' => 4,
                    'オレンジ' => 5,
                    'イエロー' => 6,
                    'シルバー' => 7,
                    'ゴールド' => 8
                ],
                'label' => '販売種別',
                'required' => false
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
