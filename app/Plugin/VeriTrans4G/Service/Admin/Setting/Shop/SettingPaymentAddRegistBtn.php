<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Service\Admin\Setting\Shop;

use Eccube\Event\TemplateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * 支払方法設定画面クラス
 */
class SettingPaymentAddRegistBtn
{
    /**
     * VT用支払方法選択画面にベリトランス設定項目を追加します。
     * @param TemplateEvent $event
     */
    public function onRenderBefore(TemplateEvent $event)
    {
        $event->addSnippet('@VeriTrans4G/admin/Setting/Shop/payment_add_regist_btn.twig');
    }

}
