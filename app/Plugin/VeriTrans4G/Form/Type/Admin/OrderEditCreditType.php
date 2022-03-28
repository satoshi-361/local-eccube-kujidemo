<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Form\Type\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * 受注管理詳細 クレジット情報選択フォーム
 */
class OrderEditCreditType extends AbstractType
{
    /**
     * コンテナ
     */
    private $container;

    /**
     * エンティティーマネージャー
     */
    private $em;

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface   $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * フォーム生成
     *
     * @param  FormBuilderInterface $builder フォームビルダー
     * @param  array                $options フォーム生成に使用するデータ
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $accountCardsChoices = $this->getAccountCardsChoices($options['data']['creditInfo']['accountCards']);
        $reTradeCardsChoices = $this->getReTradeCardsChoices($options['data']['creditInfo']['reTradeCards']);
        $paymentTypeChoices = $this->getPaymentTypeChoices($options['data']['paymentMethodInfo']);
        $withCaptureChoices = $this->getWithCaptureChoices();

        $builder
            ->add('accountCards', ChoiceType::class, [
                'label'       => 'ベリトランス会員ID決済利用',
                'placeholder' => '--',
                'choices'     => $accountCardsChoices,
                'required'    => false,
            ])
            ->add('reTradeCards', ChoiceType::class, [
                'label'       => 'かんたん決済(再取引)利用',
                'placeholder' => '--',
                'choices'     => $reTradeCardsChoices,
                'required'    => false,
            ])
            ->add('paymentType', ChoiceType::class, [
                'label'       => 'お支払い方法',
                'placeholder' => '--',
                'choices'     => $paymentTypeChoices,
                'required'    => false,
            ])
            ->add('withCapture', ChoiceType::class, [
                'label'       => '売上フラグ',
                'placeholder' => '--',
                'choices'     => $withCaptureChoices,
                'required'    => false,
            ]);
    }

    /**
     * ベリトランス会員ID決済利用情報をフォーム用に整形
     *
     * @param  array $paymentInfo ベリトランス会員ID決済利用情報
     * @return array              ベリトランス会員ID決済利用情報(整形後)
     */
    private function getAccountCardsChoices($accountCards)
    {
        $accountCardsChoices = [];
        foreach ($accountCards as $accountCard) {
            $id = '';
            $number = '';
            $expire = '';
            foreach ($accountCard as $label => $value) {
                switch ($label) {
                    case 'cardId':
                        $id = $value;
                        break;
                    case 'cardNumber':
                        $number = $value;
                        break;
                    case 'expire':
                        $expire = $value;
                        break;
                }
            }
            $accountCardsChoices['カード番号：'.$number.' - 有効期限：'.$expire] = $id;
        }

        return $accountCardsChoices;
    }

    /**
     * かんたん決済(再取引)利用情報をフォーム用に整形
     *
     * @param  array $paymentInfo かんたん決済(再取引)利用情報
     * @return array              かんたん決済(再取引)利用情報(整形後)
     */
    private function getReTradeCardsChoices($reTradeCards)
    {
        $reTradeCardsChoices = [];
        foreach ($reTradeCards as $reTradeCard) {
            $id = '';
            $number = '';
            $orderId = '';
            foreach ($reTradeCard as $label => $value) {
                switch ($label) {
                    case 'paymentOrderId':
                        $id = $value;
                        break;
                    case 'cardNumber':
                        $number = $value;
                        break;
                    case 'orderId':
                        $orderId = $value;
                        break;
                }
            }
            $reTradeCardsChoices['カード番号：'.$number.' - 注文番号：'.$orderId] = $id;
        }

        return $reTradeCardsChoices;
    }

    /**
     * クレジット支払設定で有効なお支払い方法の選択肢を取得
     *
     * @param  array $paymentInfo クレジット支払設定
     * @return array              有効設定のお支払い方法
     */
    private function getPaymentTypeChoices($paymentMethodInfo)
    {
        $payMethodMap = $this->vt4gConst['VT4G_FORM']['CHOICES']['CREDIT_PAY_METHOD'];
        $payMethodList = (array)$paymentMethodInfo['credit_pay_methods'];
        $payMethodChoices = [];
        foreach ($payMethodMap as $label => $value) {
            if (in_array($value, $payMethodList, true)) {
                $payMethodChoices[$label] = $value;
            }
        }

        return $payMethodChoices;
    }

    /**
     * 売上フラグの選択肢を取得
     *
     * @return array              売上フラグ
     */
    private function getWithCaptureChoices()
    {
        return $this->vt4gConst['VT4G_FORM']['CHOICES']['WITH_CAPTURE'];
    }
}
