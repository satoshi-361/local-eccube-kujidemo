<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin\Setting\Shop;

use Eccube\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Plugin\VeriTrans4G\Form\Type\Admin\PaymentRegistType;

/**
  * VT用決済方法選択画面の処理
  */
class PaymentRegistController extends AbstractController
{
    /**
     * コンテナ
     */
    protected $container;

    /**
     * エンティティーマネージャー
     */
    private $em;

    /**
     * ユーティリティサービス
     */
    private $util;

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
        $this->util = $container->get('vt4g_plugin.service.util');
        $this->em = $container->get('doctrine.orm.entity_manager');
    }

    /**
     * VT用支払方法新規登録画面の処理
     *
     * @Route("/%eccube_admin_route%/setting/shop/payment/payment_regist", name="vt4g_admin_payment_regist")
     * @param  Request $request     リクエストデータ
     * @return object           ビューレスポンス|レダイレクトレスポンス
     */
    public function index(Request $request)
    {
        $form = $this->formFactory->createBuilder(PaymentRegistType::class)->getForm();
        $form->handleRequest($request);

        // フォームのバリデーション結果
        $isValid = true;
        if ($form->isSubmitted()) {
            $isValid = $form->isValid();
        }

        if ($isValid && 'POST' === $request->getMethod()) {
            $paymentTypeId = $form['regist_payment_type']->getData();
            // savePayment()で新規登録を行うため$paymentにNullを指定
            $Payment = NULL;
            if (!empty($paymentTypeId)) {
                $Payment = $this->container->get('vt4g_plugin.service.admin.plugin.config')->savePayment($paymentTypeId, $Payment);
                $this->container->get('vt4g_plugin.service.admin.plugin.config')->saveVt4gPaymentMethod($paymentTypeId, $Payment);
                // EC-CUBE側の処理へリダイレクト
                return $this->redirectToRoute(
                    'admin_setting_shop_payment_edit',
                    [
                        'id' => $Payment->getId(),
                    ]
                );
            }
        }

        return $this->render(
            'VeriTrans4G/Resource/template/admin/Setting/Shop/payment_regist.twig',
            [
                'form'      => $form->createView(),
            ]
        );
    }
}