<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Service\Shopping;

use Eccube\Event\TemplateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * 注文確認画面 拡張用クラス
 */
class ConfirmExtensionService
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
     * ユーティリティサービス
     */
    private $util;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->util = $container->get('vt4g_plugin.service.util');
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * 注文確認画面 レンダリング時のイベントリスナ
     * 支払方法が「楽天ペイ」の場合、注文ボタンを変更する
     * @param  TemplateEvent $event イベントデータ
     * @return void
     */
    public function onRenderBefore(TemplateEvent $event)
    {
        $eventParam = $event->getParameters();
        $paymentId = $eventParam['Order']['payment']->getId();
        $paymentMethod = $this->util->getPaymentMethod($paymentId);
        $memo03 = !empty($paymentMethod) ? intval($paymentMethod->getMemo03()) : '';
        if ($memo03 !== $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN']) {
            return;
        } else {
            $engine = $this->container->get('twig');
            // addSnippet()で追加すると、管理画面のページ管理からの変更が反映されない
            $btn_source = $engine->render(
                'VeriTrans4G/Resource/template/default/Shopping/vt4g_button_rakuten.twig',
                []
            );
            $search = '{% endblock %}';
            $replace = $btn_source.$search;
            $source = str_replace($search, $replace, $event->getSource());
            $event->setSource($source);
            $event->addSnippet('@VeriTrans4G/default/Shopping/vt4g_script_rakuten.twig');
        }
    }

}
