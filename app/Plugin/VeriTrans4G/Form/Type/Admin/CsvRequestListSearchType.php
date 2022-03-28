<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form\Type\Admin;

use Eccube\Common\EccubeConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CSV決済依頼管理 決済依頼一覧画面　検索フォーム
 *
 */
class CsvRequestListSearchType extends AbstractType
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
     * コンストラクタ
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(ContainerInterface $container, EccubeConfig $eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // 決済依頼ステータス
        // 指定しない(全ての決済依頼ステータスを対象とする)ケースを99とします
        $request_status = ['' => '99'];
        foreach ($this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS'] as $k => $v) {
            $request_status[$this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS_NAME'][$k]] = $v;
        }

        $builder
            ->add('search_keyword', TextType::class, [
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => $this->eccubeConfig['eccube_stext_len'],
                        ]),
                    ],
                ]
            )
            ->add('request_status', ChoiceType::class, [
                    'choices'  => $request_status,
                ]
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'admin_csv_request_list';
    }
}
