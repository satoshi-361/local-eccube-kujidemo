<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Admin\Setting\Shop;

use Eccube\Event\EventArgs;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 管理管理CSV出力 拡張用クラス
 */
class CsvExportExtensionService
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
     * コンストラクタ
     * @param ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * 会員CSV出力の処理です。
     * @param EventArgs $event
     * @return void
     */
    public function onCustomerCsvExport(EventArgs $event)
    {
        if ($event->getArgument('Csv')->getFieldName() === $this->vt4gConst['VT4G_DTB_CSV']['CUSTOMER']['VT4G_ACCOUNT_ID']['FIELD_NAME']) {
            $event->getArgument('ExportCsvRow')->setData($event->getArgument('Customer')->vt4g_account_id);
        }
    }

    /**
     * 受注CSV出力の処理です。
     * @param EventArgs $event
     * @return void
     */
    public function adminOrderCsvExportOrder(EventArgs $event)
    {
        if ($event->getArgument('Csv')->getFieldName() === $this->vt4gConst['VT4G_DTB_CSV']['ORDER']['VT4G_PAYMENT_STATUS']['FIELD_NAME']) {

            $util = $this->container->get('vt4g_plugin.service.util');
            $orderPayment = $util->getOrderPaymetFindOneBy($event->getArgument('OrderItem')->getOrder()->getId());
            $paymentStatus = !empty($orderPayment) ? $util->getPaymentStatusName($orderPayment->getMemo04()) : '';
            $event->getArgument('ExportCsvRow')->setData($paymentStatus);
        }
    }

}
