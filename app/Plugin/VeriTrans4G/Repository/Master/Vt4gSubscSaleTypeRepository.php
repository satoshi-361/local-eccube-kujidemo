<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Repository\Master;

use Eccube\Entity\Master\SaleType;
use Plugin\VeriTrans4G\Entity\Master\Vt4gSubscSaleType;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;

/**
 * plg_vt4g_subsc_sale_typeリポジトリクラス
 */
class Vt4gSubscSaleTypeRepository extends \Eccube\Repository\AbstractRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Vt4gSubscSaleType::class);
    }


    /**
     * 全ての継続課金販売種別を取得する
     * @return array
     */
    public function getList()
    {
        $em = $this->getEntityManager();
        $records = $em->createQuery(
            "SELECT sm.id AS sale_type_id, sm.name AS name, sb.few_credit_flg AS few_credit_flg
             FROM Plugin\\VeriTrans4G\\Entity\\Master\\Vt4gSubscSaleType sb
             INNER JOIN Eccube\\Entity\\Master\\SaleType sm WITH (sm.id = sb.sale_type_id)
             ORDER BY sb.sale_type_id DESC"
        )
        ->execute();

        $resultList = [];
        foreach ($records as $record) {
            // Entityに変換
            $entity = new Vt4gSubscSaleType();
            $entity->setSaleTypeId($record['sale_type_id']);
            $entity->setName($record['name']);
            $entity->setFewCreditFlg($record['few_credit_flg']);

            array_push($resultList, $entity);
        }

        return $resultList;
    }

    /**
     * 販売種別を保存する.
     *
     * @param Vt4gSubscSaleType $subscSalesType 継続課金販売種別マスタエンティティ
     * @return int $saleTypeId 採番した販売種別ID
     */
    public function save($subscSalesType)
    {
        $em = $this->getEntityManager();

        // 販売種別マスタの最大値 （データなしの場合を想定してnullの場合0に変換。さらに1を足すことで、1から始まる）
        // 販売種別側で登録したらちゃんとidインクリメントしてくれる？？オートインクリメントのカウンターとちゃんと絡めるか
        $saleTypeId = $em->createQueryBuilder()
            ->select('(COALESCE(MAX(s.id), 0) + 1)')
            ->from(SaleType::class, 's')
            ->getQuery()
            ->getSingleScalarResult();

        // ソート番号（データなしの場合を想定してnullの場合-1に変換。さらに1を足すことで、0から始まる）
        $sortNo = $em->createQueryBuilder()
            ->select('(COALESCE(MAX(s.sort_no), -1) + 1)')
            ->from(SaleType::class, 's')
            ->getQuery()
            ->getSingleScalarResult();

        // 販売種別マスタも保存
        $salesType = new SaleType();
        $salesType->setId($saleTypeId);
        $salesType->setName($subscSalesType->getName());
        $salesType->setSortNo($sortNo);

        $em->persist($salesType);
        // 継続課金販売種別
        $subscSalesType->setSaleTypeId($saleTypeId);
        $subscSalesType->setSaleType($salesType);
        $em->persist($subscSalesType);

        $em->flush();

        return $saleTypeId;
    }

    /**
     * 販売種別を更新する.
     *
     * @param mixed $formData 継続課金販売種別フォームデータ
     */
    public function update($formData)
    {
        $em = $this->getEntityManager();

        // 販売種別マスタの更新
        $saleType = $em->getRepository(SaleType::class)->find($formData->getSaleTypeId());
        $saleType->setName($formData->getName());
        $em->persist($saleType);

        // 継続課金販売種別マスタの更新
        $subscSaleType = $em->getRepository(Vt4gSubscSaleType::class)->find($formData->getSaleTypeId());
        $subscSaleType->setFewCreditFlg($formData->getFewCreditFlg());
        $em->persist($subscSaleType);
        $em->flush();
    }


    /**
     * 販売種別を削除する.
     *
     * @param  SaleType $saleType 削除対象のタグ
     */
    public function delete($saleType)
    {
        $em = $this->getEntityManager();

        try {
            // 継続課金販売種別マスタの削除
            $qb = $em->createQueryBuilder()
                ->delete(Vt4gSubscSaleType::class, 'sb')
                ->where('sb.sale_type_id = :sale_type_id')
                ->setParameter('sale_type_id', $saleType->getId());

            $qb->getQuery()->execute();

        } catch (ForeignKeyConstraintViolationException $e) {
            // 外部キー参照エラーが発生した場合は削除処理を中止
            $em->rollback();
            throw $e;
        }

        try {
            // ソート順を修正
            $em->createQuery(
                  "UPDATE Eccube\\Entity\\Master\\SaleType sl SET sl.sort_no = sl.sort_no - 1 WHERE sl.sort_no > :sort_no"
                )
                ->setParameter('sort_no', $saleType->getSortNo())
                ->execute();

            // 販売種別マスタの削除
            $qb2 = $em->createQueryBuilder()
                ->delete(SaleType::class, 'sl')
                ->where('sl.id = :sale_type_id')
                ->setParameter('sale_type_id', $saleType->getId());

            $qb2->getQuery()->execute();

        } catch (ForeignKeyConstraintViolationException $e) {
            // 外部キー参照エラーが発生した場合は削除処理を中止
            $em->rollback();
            throw $e;
        }

    }

    /**
     * 商品IDをキーに商品規格の販売種別の中に継続課金用の販売種別が存在するか確認する.
     *
     * @param  string $productId 商品ID
     * @return array $records 継続課金用の販売種別が存在しているかどうか
     */
    public function existsSubscSaleTypeByProductId($productId)
    {
        $em = $this->getEntityManager();

        // 継続課金用の販売種別が商品規格に一件でも存在するか確認
        // add visible=1のデータのみ参照
        $sql =
            "SELECT
              pc.product_id
             FROM dtb_product_class pc
            WHERE pc.product_id = :product_id
              AND pc.visible = :visible
              AND EXISTS (
                    SELECT wk.sale_type_id
                    FROM 	plg_vt4g_subsc_sale_type wk
                    WHERE pc.sale_type_id = wk.sale_type_id
                    LIMIT 1
                )";
        $parms = ['product_id' => $productId, 'visible' => true];

        $records = $em->getConnection()->executeQuery($sql, $parms)->fetchAll();

        return $records;
    }

    /**
     * 商品IDと規格IDをキーに商品規格の販売種別の中に継続課金用の販売種別が存在しないか確認する.
     *
     * @param  int $productId 商品ID
     * @param  int $productClassId 商品規格ID
     * @return array $records 継続課金用の販売種別が存在しているかどうか
     */
    public function notExistsSubscSaleTypeByProductId($productId, $productClassId)
    {
        $em = $this->getEntityManager();

        // 継続課金用の販売種別が商品規格に登録されていない商品を検索する。
        // add visible=1のデータのみ参照
        $sql =
            "SELECT
              pc.product_id
             FROM dtb_product_class pc
            WHERE pc.product_id = :product_id
              AND pc.id = :product_class_Id
              AND pc.visible = :visible
              AND NOT EXISTS (
                    SELECT wk.sale_type_id
                    FROM 	plg_vt4g_subsc_sale_type wk
                    WHERE pc.sale_type_id = wk.sale_type_id
                    LIMIT 1
                )";
        $parms = ['product_id' => $productId, 'product_class_Id' => $productClassId, 'visible' => true];

        $records = $em->getConnection()->executeQuery($sql, $parms)->fetchAll();

        return $records;
    }

    /**
     * 注文の販売種別が継続課金販売種別か確認する.
     *
     * @param  int $orderId 注文ID
     * @return boolean 継続課金販売種別かどうか
     */
    public function judgmentSubscSaleTypeByOrderId($orderId)
    {
        $em = $this->getEntityManager();

        $sql =
        "SELECT
          oi.order_id
        FROM dtb_order_item oi
        WHERE
         EXISTS(
             SELECT sp.id
             FROM
              dtb_shipping sp
              INNER JOIN dtb_delivery dv
              ON sp.delivery_id = dv.id
              INNER JOIN plg_vt4g_subsc_sale_type sbs
              ON dv.sale_type_id = sbs.sale_type_id
              WHERE
                    oi.order_id = sp.order_id
                  AND sp.order_id = :order_id
           )
        LIMIT 1
        ";

        $parms = ['order_id' => $orderId];

        $records = $em->getConnection()->executeQuery($sql, $parms)->fetchAll();

        return !empty($records) ? true : false;
    }

}
