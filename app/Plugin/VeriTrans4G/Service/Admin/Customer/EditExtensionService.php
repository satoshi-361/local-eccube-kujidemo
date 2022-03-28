<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Admin\Customer;

use Eccube\Entity\Customer;
use Eccube\Entity\Master\CustomerStatus;
use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 会員管理登録画面 拡張用クラス
 */
class EditExtensionService
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
     * コンストラクタ
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->em = $container->get('doctrine.orm.entity_manager');
    }

    /**
     * 会員登録管理画面→登録後の処理です。
     * 退会処理の時にベリトランス会員IDを削除
     * @param EventArgs $event
     */
    public function onEditIndexComplete(EventArgs $event)
    {
        $customer = $event->getArgument('Customer');
        $statusId = $customer->getStatus()->getId();
        try {
            $accountId = $customer->vt4g_account_id;
            if ($statusId == CustomerStatus::WITHDRAWING && $accountId) {
                if(!$this->container->get('vt4g_plugin.service.vt4g_account_id')->deleteVt4gAccountId($accountId)) {
                    $this->container->get('session')->getFlashBag()->add('eccube.admin.danger', trans('vt4g_plugin.account.del.mdk.failed.msg'));
                }
    
                $customerRepo = $this->em->getRepository(Customer::class)->find($customer->getId());
                $customerRepo->vt4g_account_id = null;
                $this->em->flush();
            }
        } catch (\Exception $e) {}

    }

    /**
     * 会員登録管理画面 レンダリング時のイベントリスナ
     * ベリトランス会員ID情報を表示
     * @param  TemplateEvent $event イベントデータ
     * @return void
     */
    public function onRenderBefore(TemplateEvent $event)
    {
        $parameters = $event->getParameters();
        $accountId = $parameters['Customer']->vt4g_account_id;
        if ($accountId) {
            $cards = $this->container->get('vt4g_plugin.service.vt4g_account_id')->getAccountCardsWithMsg($accountId);
            $parameters['accountCards'] = $cards;
            $parameters['accountId'] = $accountId;
            $event->setParameters($parameters);
        } else {
            $parameters['accountCards'] = '';
            $parameters['accountId'] = '';
            $event->setParameters($parameters);
        }
        
        $event->addSnippet('@VeriTrans4G/admin/Customer/edit.twig');
    }
}
