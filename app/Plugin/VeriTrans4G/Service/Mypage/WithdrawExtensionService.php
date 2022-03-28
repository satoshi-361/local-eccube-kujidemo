<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Mypage;

use Eccube\Entity\Customer;
use Eccube\Event\EventArgs;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * マイページ退会手続き画面 拡張用クラス
 */
class WithdrawExtensionService
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
     * マイページ→退会手続き完了後の処理です。
     *
     * @param EventArgs $event
     */
    public function onWithdrawComplete(EventArgs $event)
    {
        $customer = $event->getArgument('Customer');
        $accountId = $customer->vt4g_account_id;
        if ($accountId) {
            $this->container->get('vt4g_plugin.service.vt4g_account_id')->deleteVt4gAccountId($accountId);

            $customerRepo = $this->em->getRepository(Customer::class)->find($customer->getId());
            $customerRepo->vt4g_account_id = null;
            $this->em->flush();
        }
    }
}
