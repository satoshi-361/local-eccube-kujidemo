<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service\Admin\Product;

use Plugin\VeriTrans4G\Entity\Master\Vt4gSubscSaleType;
use Plugin\VeriTrans4G\Entity\Master\Vt4gSubscProduct;
use Plugin\VeriTrans4G\Entity\Master\Vt4gSubscProductOrderCmpMailInfo;
use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * 商品登録画面/商品一覧画面 拡張用クラス
 */
class SubscExtensionService
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
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * 商品登録 レンダリング前の処理です。
     *
     * @param TemplateEvent $event
     */
    public function onAdminProductRenderBefore(TemplateEvent $event)
    {
        // 継続課金商品注文完了メール情報からデータ取得してフォーム項目に設定
        $id = $event->getParameter('id');
        $mailInfo = $this->em->getRepository(Vt4gSubscProductOrderCmpMailInfo::class)->findOneBy(['product_id' => $id]);

        $form = $event->getParameter('form');

        if (isset($mailInfo)) {
            // データが存在する場合に初期値設定（更新時を想定）
            $form['order_mail_title1']->vars['value'] = $mailInfo->getOrderCmpMailTitle();
            $form['order_mail_body1']->vars['value'] = $mailInfo->getOrderCompMailBody();
        }
        // 商品IDは常に設定
        $form['product_Id_ForAjax']->vars['value'] = $id;
    }

    /**
     * 商品登録 登録完了後の処理です。
     *
     * @param EventArgs $event
     */
    public function onAdminProductEditComplete(EventArgs $event)
    {
        $product = $event->getArgument('Product');
        $this->saveSubscProduct($product);
        // 継続課金商品用の注文完了メール情報を保存あるいは削除する
        $this->saveOrDeleteSubscProductOrdCmpMailInfo($event, $product);

        // 継続課金用ではない販売種別が登録されていたら継続課金用のデータを削除
        $records = $this->em->getRepository(Vt4gSubscSaleType::class)->existsSubscSaleTypeByProductId($product->getId());
        if (count($records) === 0) {
            $this->deleteSubscProduct($product);
            $this->deleteSubscProductOrdCmpMailInfo($product);
        }
    }

    /**
     * 商品一覧 削除完了後の処理です。
     *
     * @param EventArgs $event
     */
    public function onAdminProductDeleteComplete(EventArgs $event)
    {
        // 継続課金商品マスタの削除（商品IDキー）
        $id = $event->getRequest()->get('id');


        // 継続課金商品マスタを削除
        $qb = $this->em->createQueryBuilder()
            ->delete(Vt4gSubscProduct::class, 'sp')
            ->where('sp.product_id = :product_id')
            ->setParameter('product_id', $id);

        $qb->getQuery()->execute();

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.subsc_product.del.complete'),$id));

        // 継続課金商品注文完了メール情報を削除
        $qb = $this->em->createQueryBuilder()
            ->delete(Vt4gSubscProductOrderCmpMailInfo::class, 'sm')
            ->where('sm.product_id = :product_id')
            ->setParameter('product_id', $id);

        $qb->getQuery()->execute();

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.subsc_order_mail.del.complete'),$id));
    }

    /**
     * 商品一覧 コピー完了後の処理です。
     *
     * @param EventArgs $event
     */
    public function onAdminProductCopyComplete(EventArgs $event)
    {
        // 継続課金商品の登録
        $CopyProduct = $event->getArgument('CopyProduct');
        $this->saveSubscProduct($CopyProduct);
        // メール情報複製
        $product = $event->getArgument('Product');
        $this->copySubscProductOrdCmpMailInfo($product, $CopyProduct);

    }

    /**
     * 商品規格登録 商品規格登録完了後の処理です。
     *
     * @param  TemplateEvent $event
     */
    public function onAdminProductClassSaveComplete(TemplateEvent $event)
    {
        $product = $event->getParameter('Product');

        //新規継続課金対象となる商品を継続課金商品マスタに登録する
        $this->saveSubscProduct($product);
        
        //非継続課金対象となる商品を継続課金商品マスタより削除する
        //商品規格一覧を取得する
        $productClasses = $product->getProductClasses()
            ->filter(function ($pc) {
                return $pc->getClassCategory1() !== null;
            });
        
        //商品規格一覧より非継続課金対象商品を検索し、削除を行う。
        foreach($productClasses as $productClass) {
            //非継続課金対象商品か検索を行う。
            $records = $this->em->getRepository(Vt4gSubscSaleType::class)->notExistsSubscSaleTypeByProductId($product->getId(),$productClass->getId());
            //検索の結果、非継続課金対象商品の場合は継続課金商品マスタより削除する。
            if(count($records) > 0){
                $this->deleteSubscProduct($product,$productClass->getId());
            }
        }

        // 継続課金用ではない販売種別が登録されていたら継続課金用のデータを削除
        $records = $this->em->getRepository(Vt4gSubscSaleType::class)->existsSubscSaleTypeByProductId($product->getId());
        if (count($records) === 0) {
            $this->deleteSubscProductOrdCmpMailInfo($product);
        }

    }

    /**
     * 継続課金商品マスタを登録する
     *
     * @param $product
     */
    private function saveSubscProduct($product)
    {
        // 商品IDで商品規格テーブルから商品IDと販売種別の組み合わせを取得
        // 継続課金販売種別マスタとinner join で存在する販売種別の商品規格に絞る
        // 継続課金商品マスタに存在しないパターンのみ取得する（not exists）
        $sql =
          "SELECT
            pc.product_id as product_id
           ,pc.id as product_class_id
           ,pc.sale_type_id as sale_type_id
          FROM dtb_product_class pc
          INNER JOIN plg_vt4g_subsc_sale_type sst
          ON pc.sale_type_id = sst.sale_type_id
          WHERE pc.product_id = :product_id
            AND NOT EXISTS (
              SELECT wk.product_id
              FROM plg_vt4g_subsc_product wk
              WHERE pc.product_id = wk.product_id
                AND pc.id = wk.product_class_id
                AND pc.sale_type_id = wk.subsc_sale_type_id
              LIMIT 1
              )
          GROUP BY pc.product_id, pc.id, pc.sale_type_id";
        $parms = ['product_id' => $product->getId()];

        $stmt = $this->em->getConnection()->prepare($sql);
        $stmt->execute($parms);
        $records = $stmt->fetchAll();

        // 取得できた組み合わせの分だけ継続課金商品マスタを登録する
        // メモ　ORM使用のため明示的トランザクション不要
        foreach ($records as $record) {
            $vt4gSubscProduct = $this->em->getRepository(Vt4gSubscProduct::class)->find([
                    'product_id' => $record['product_id'],
                    'product_class_id' => $record['product_class_id']
                ]);

            if(empty($vt4gSubscProduct)) {
                $vt4gSubscProduct = new Vt4gSubscProduct();
                $vt4gSubscProduct->setProductId($record['product_id'])
                                 ->setProductClassId($record['product_class_id']);
            }

            $vt4gSubscProduct->setSubscSaleTypeId($record['sale_type_id'])
                             ->setMyPageDispFlg($this->vt4gConst['VT4G_SUBSC_PRODUCT_CULUMN']['MY_PAGE_DISP_FLG_ON']);

            $this->em->persist($vt4gSubscProduct);
            $this->em->flush($vt4gSubscProduct);
        }

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.subsc_product.save.complete'),$product->getId()));
    }

    /**
     * 継続課金商品マスタを削除する
     *
     * @param $product
     * @param $productClassId
     */
    private function deleteSubscProduct($product, $productClassId = '')
    {
        // 継続課金商品マスタを削除
        $qb = $this->em->createQueryBuilder()
                   ->delete(Vt4gSubscProduct::class, 'sp')
                   ->where('sp.product_id = :product_id')
                   ->setParameter('product_id', $product->getId());
        
        if($productClassId !== ''){
            $qb->andWhere('sp.product_class_id = :product_class_id')
                ->setParameter('product_class_id', $productClassId);
        }

        $qb->getQuery()->execute();

        $msg = $productClassId !== '' 
            ? sprintf(trans('vt4g_plugin.admin.subsc_product_class.del.complete'),$product->getId(),$productClassId)
            : sprintf(trans('vt4g_plugin.admin.subsc_product.del.complete'),$product->getId());

        $this->mdkLogger->info($msg);

    }

    /**
     * 継続課金商品注文完了メール情報を保存あるいは削除する
     * @param $event
     * @param $product
     */
    private function saveOrDeleteSubscProductOrdCmpMailInfo($event, $product)
    {
        $request = $event->getRequest();
        $admin_product = $request->get('admin_product');

        // メール項目が設定されていたら保存
        if (!empty($admin_product['order_mail_title1']) || !empty($admin_product['order_mail_body1'])) {

            $mailInfo = $this->em->getRepository(Vt4gSubscProductOrderCmpMailInfo::class)->findOneBy(['product_id' => $request->get('id')]);
            if (empty($mailInfo)) {
            // id指定がない場合、登録
            // 想定するパターン：新規登録、メールデータなし更新

                $entity = new Vt4gSubscProductOrderCmpMailInfo();
                $entity
                    ->setProductId($product->getId())
                    ->setOrderCmpMailTitle($admin_product['order_mail_title1'])
                    ->setOrderCompMailBody($admin_product['order_mail_body1']);

                $this->em->persist($entity);
                $this->em->flush($entity);
            } else {
            // 更新
            // 想定するパターン：メールデータあり更新
                $this->em->createQuery(
                    "UPDATE Plugin\\VeriTrans4G\\Entity\\Master\\Vt4gSubscProductOrderCmpMailInfo sm
                      SET sm.order_cmp_mail_title = :order_cmp_mail_title
                        , sm.order_cmp_mail_body = :order_cmp_mail_body
                     WHERE sm.product_id = :product_id"
                    )
                    ->setParameter('order_cmp_mail_title', $admin_product['order_mail_title1'])
                    ->setParameter('order_cmp_mail_body', $admin_product['order_mail_body1'])
                    ->setParameter('product_id', $product->getId())
                    ->execute();
            }

            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.subsc_order_mail.save.complete'),$product->getId()));

        } else {
            // 削除
            $mailRepository = $this->em->getRepository(Vt4gSubscProductOrderCmpMailInfo::class);
            $mailRepository->deleteWithProductId($product->getId());
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.subsc_order_mail.del.complete'),$product->getId()));
        }
    }

    /**
     * 継続課金商品注文完了メール情報を複製する
     * @param $product
     * @param $CopyProduct
     */
    private function copySubscProductOrdCmpMailInfo($product, $CopyProduct)
    {
        $mailRepository = $this->em->getRepository(Vt4gSubscProductOrderCmpMailInfo::class);
        $originMailInfo = $mailRepository->findOneBy(['product_id' => $product->getId()]);

        if (isset($originMailInfo)) {
            $entity = new Vt4gSubscProductOrderCmpMailInfo();
            $entity->setProductId($CopyProduct->getId());
            $entity->setOrderCmpMailTitle($originMailInfo->getOrderCmpMailTitle());
            $entity->setOrderCompMailBody($originMailInfo->getOrderCompMailBody());

            $this->em->persist($entity);
            $this->em->flush($entity);
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.subsc_order_mail.save.complete'),$product->getId()));
        }
    }

    /**
     * 継続課金商品注文完了メール情報を削除する
     * @param $product
     */
    private function deleteSubscProductOrdCmpMailInfo($product)
    {
        $productId = $product->getId();

        // 継続課金用の販売種別がvisibleな商品規格に一件でも存在するか確認
        $records = $this->em->getRepository(Vt4gSubscSaleType::class)->existsSubscSaleTypeByProductId($productId);

        if (count($records) === 0) {
            // 上記、存在しない場合、継続課金商品注文完了メール情報を削除
            $mailRepository = $this->em->getRepository(Vt4gSubscProductOrderCmpMailInfo::class);
            $mailRepository->deleteWithProductId($productId);
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.subsc_order_mail.del.complete'),$productId));
        }
    }

}
