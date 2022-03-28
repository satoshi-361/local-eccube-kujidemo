<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Service\Cart;

use Eccube\Event\TemplateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\VeriTrans4G\Entity\Vt4gSubscOrderItem;

/**
 * カート画面 拡張用クラス
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
     * アプリケーション
     */
    private $app;

    /**
     * コンストラクタ
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
        $this->authorizationChecker = $container->get('security.authorization_checker');
    }

    /**
     * カート レンダリング前の処理です。
     *
     * @param TemplateEvent $event
     */
    public function onCartRenderBefore(TemplateEvent $event)
    {
        // 継続課金商品重複購入フラグ
        $subscProductDuplicateWarnFlg = false;

        $Carts = $event->getParameter('Carts');

        // ベリトランス会員ログイン済　かつ　カートに商品が存在する場合
        if ($this->authorizationChecker->isGranted('ROLE_USER') && count($Carts)) {

            $customerId = $Carts[0]->getCustomer()->getId();

            // ベリトランス会員である場合
            if (!is_null($customerId)) {

                // カートに入っている商品（規格）で継続課金注文明細を検索する
                $qb = $this->em->getRepository(Vt4gSubscOrderItem::class)->createQueryBuilder('som');
                $qb->select()
                    // 親と結合して会員IDで絞り込む
                    ->innerJoin('\Plugin\VeriTrans4G\Entity\Vt4gSubscOrder', 'so', 'WITH', 'so.order_id = som.order_id')
                    ->where( // 会員IDで絞り込み
                            $qb->expr()->eq('so.customer_id', $customerId)
                    )
                    ->andWhere( // 継続課金ステータスが継続の注文明細を検
                            $qb->expr()->eq('som.subsc_status', $this->vt4gConst['VTG4_SUBSC_STATUS_SUBSC'])
                    );
                // カートの商品規格をor条件にセットする
                $orConditions = $qb->expr()->orX();
                foreach($Carts as $cart) {

                    $productClasses = $cart->getItems()->getProductClasses();
                    foreach($productClasses as $productClass) {

                        $orConditions->add(
                            $qb->expr()->eq('som.product_class_id', $productClass->getProductClass()->getId())
                            );
                      }
                }
                // or条件のまとまりをand条件でクエリに設定
                $qb->andWhere($orConditions);

                $result = $qb->getQuery()->setMaxResults(1)->getOneOrNullResult();

                if (!is_null($result)) {
                    // 一件でもあれば
                    $subscProductDuplicateWarnFlg = true;
                }
            }
        }

        $event->setParameter('subscProductDuplicateWarnFlg', $subscProductDuplicateWarnFlg);
    }

}
