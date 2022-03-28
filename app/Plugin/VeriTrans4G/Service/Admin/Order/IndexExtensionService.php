<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Service\Admin\Order;

use Plugin\VeriTrans4G\Entity\Vt4gOrderPayment;
use Eccube\Entity\Order;
use Eccube\Entity\Shipping;
use Eccube\Event\TemplateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 受注管理一覧画面 拡張用クラス
 */
class IndexExtensionService
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
     * ユーティリティサービス
     */
    private $util;

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * MDK Logger
     */
    private $mdkLogger;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->util = $container->get('vt4g_plugin.service.util');
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $this->container->getParameter('vt4g_plugin.const');
    }

    /**
     * 受注管理一覧画面 レンダリング時のイベントリスナ
     *
     * @param  TemplateEvent $event イベントデータ
     * @return void
     */
    public function onRenderBefore(TemplateEvent $event)
    {
        // 配送先ごとの注文ID・決済ステータス 取得
        $orderList       = $event->getParameter('pagination');
        $shippingInfoMap = $this->getShippingInfoMap($orderList);
        // 決済ステータスの表示名データ 取得
        $payStatusLabel = $this->util->getPaymentStatus();

        // 更新操作用の決済ステータス 取得
        $controlPayStatusMap = $this->util->getPaymentStatus(true);

        // テンプレートで渡すデータ
        $extension = compact(
            'shippingInfoMap',
            'payStatusLabel',
            'controlPayStatusMap'
        );
        $event->setParameter('vt4g', $extension);
        // テンプレートの読み込みを追加
        $event->addSnippet('@VeriTrans4G/admin/Order/index.twig');
    }

    /**
     * 注文リストから配送先ID - 注文ID, 決済ステータスのマップを取得
     *
     * @param  object $orderList 注文リストのペジネーションインスタンス
     * @return array             配送先ID - 注文ID, 決済ステータスのマップ
     */
    private function getShippingInfoMap($orderList)
    {
        $shippingIdList = [];
        foreach ($orderList as $order) {
            foreach ($order->getShippings() as $shipping) {
                $shippingIdList[] = $shipping->getId();
            }
        }

        if (empty($shippingIdList)) {
            return [];
        }

        // 配送先IDをもとに注文IDと決済ステータスを取得
        $query = $this->em->getRepository(Shipping::class)->createQueryBuilder('s');
        $query
            ->select(
                's.id AS shippingId',
                'o.id AS orderId',
                'op.memo04 AS payStatus'
            )
            ->leftJoin(Vt4gOrderPayment::class, 'op', 'WITH', 'op.order_id = s.Order')
            ->innerJoin(Order::class, 'o', 'WITH', 'o.id = s.Order')
            ->where(
                $query->expr()->in('s.id', $shippingIdList)
            );

        $records = $query->getQuery()->getResult();

        if (empty($records)) {
            return [];
        }

        // 配送先ID - 注文ID, 決済ステータスのマップに整形
        return array_reduce($records, function ($carry, $item) {
            $carry[$item['shippingId']] = [
                'orderId'   => $item['orderId'],
                'payStatus' => $item['payStatus']
            ];
            return $carry;
        }, []);
    }
}
