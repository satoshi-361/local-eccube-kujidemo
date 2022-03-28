<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Admin\Customer;

use Eccube\Event\EventArgs;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 会員管理一覧画面 拡張用クラス
 */
class IndexExtensionService
{
    /**
     * コンテナ
     */
    private $container;

    /**
     * コンストラクタ
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * 会員登録管理画面→削除後の処理です。
     *
     * @param EventArgs $event
     */
    public function onDeleteComplete(EventArgs $event)
    {
        $customer = $event->getArgument('Customer');
        $id = $customer->getId();
        $accountId = $customer->vt4g_account_id;
        if (!$id && $accountId) {
            if(!$this->container->get('vt4g_plugin.service.vt4g_account_id')->deleteVt4gAccountId($accountId)) {
                $this->container->get('session')->getFlashBag()->add('eccube.admin.danger', trans('vt4g_plugin.account.del.mdk.failed.msg'));
            }
        }
    }
}
