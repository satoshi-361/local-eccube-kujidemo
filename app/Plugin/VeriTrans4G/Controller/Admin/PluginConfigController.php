<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\VeriTrans4G\Form\Type\Admin\PluginConfigType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


/**
 * プラグイン設定画面コントローラー
 *
 */
class PluginConfigController extends AbstractController
{

    /**
     * プラグイン設定画面アクセス時の処理
     * @Route("/%eccube_admin_route%/veritrans4g/config", name="veri_trans4_g_admin_config")
     */
    public function index(Request $request)
    {
        $configService = $this->get('vt4g_plugin.service.admin.plugin.config');
        $subData = $configService->getSubData();

        $form = $this->formFactory->createBuilder(PluginConfigType::class)->getForm();
        $form->setData($subData);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $configService->savePaymentData($formData);

            // 3GPSMDK.propertiesの更新
            $configService->setMdkProperties($formData);

            $this->addSuccess('vt4g_plugin.admin.config.save.complete', 'admin');
            return $this->redirectToRoute('veri_trans4_g_admin_config');
        }
        return $this->render(
            'VeriTrans4G/Resource/template/admin/Plugin/vt4g_config.twig',
            [
                'form' => $form->createView(),
                'recv_url' => $this->generateUrl('vt4g_plugin_shopping_payment_recv',[],UrlGeneratorInterface::ABSOLUTE_URL),
                'usedMail' => $configService->getUsedMailData(),
            ]
        );
    }

}
