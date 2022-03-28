<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Repository;

use Eccube\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Plugin\VeriTrans4G\Entity\Vt4gSubscOrder;
use Eccube\Entity\Customer;
use Eccube\Entity\Master\OrderStatus;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Eccube\Util\StringUtil;

/**
 * plg_vt4g_subsc_orderリポジトリクラス
 */
class Vt4gSubscOrderRepository extends AbstractRepository
{
    /**
     * コンテナ
     */
    protected $container;

    /**
     * VT用固定値配列
     */
    protected $vt4gConst;

    /**
     * コンストラクタ
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry, ContainerInterface $container)
    {
        parent::__construct($registry, Vt4gSubscOrder::class);
        $this->container = $container;
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * 注文データから作成する決済依頼データを取得する
     * @param int $sale_type 販売種別ID
     * @param array $exclude_ids 対象外にする注文IDの配列
     * @return array
     */
    public function getOrderItemsBySaleType($sale_type, $exclude_ids = [])
    {
        $sql = "SELECT
                    so.order_id,
                    so.customer_id,
                    op.memo01 AS transaction_id,
                    oi.product_id,
                    oi.product_class_id,
                    oi.shipping_id,
                    oi.order_item_type_id,
                    oi.price,
                    oi.tax,
                    oi.quantity,
                    soi.subsc_status,
                    (SELECT COALESCE(count(wksoi.order_id), 0) FROM plg_vt4g_subsc_order_item wksoi WHERE so.order_id = wksoi.order_id AND wksoi.subsc_status = :subsc_status) AS subsc_dtl_cnt -- 継続の明細カウント
                FROM plg_vt4g_subsc_order so
                    INNER JOIN dtb_order o ON so.order_id=o.id AND o.order_status_id != 3 -- 3:注文取り消し以外
                    INNER JOIN dtb_order_item oi ON so.order_id = oi.order_id
                    INNER JOIN dtb_customer c ON so.customer_id = c.id AND c.customer_status_id = 2 -- 2:本会員
                    INNER JOIN plg_vt4g_order_payment op ON so.order_id = op.order_id
                    LEFT JOIN plg_vt4g_subsc_order_item soi ON oi.order_id = soi.order_id
                    AND oi.product_id = soi.product_id AND oi.product_class_id = soi.product_class_id
                    AND oi.shipping_id = soi.shipping_id AND soi.subsc_status = :subsc_status
                WHERE
                    so.subsc_sale_type_id = :sale_type_id
                AND
                    (oi.shipping_id IS NULL OR oi.shipping_id not in (select soi.shipping_id from plg_vt4g_subsc_order_item soi where soi.subsc_status = :subsc_status_cancel)) ";
        $sql .= !empty($exclude_ids) ? 'AND so.order_id NOT IN ('.implode(',', $exclude_ids).') ' : '';
        $sql .= "GROUP BY
                    so.order_id,
                    so.customer_id,
                    op.memo01,
                    oi.product_id,
                    oi.product_class_id,
                    oi.shipping_id,
                    oi.order_item_type_id,
                    oi.price,
                    oi.tax,
                    oi.quantity,
                    soi.subsc_status
                ORDER BY
                    so.order_id, oi.order_item_type_id, oi.product_id";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute([
            'subsc_status' => $this->vt4gConst['VTG4_SUBSC_STATUS_SUBSC'], // 1:継続
            'sale_type_id' => $sale_type,
            'subsc_status_cancel' => $this->vt4gConst['VTG4_SUBSC_STATUS_CANCEL'], // 2:解約
        ]);
        $result = $stmt->fetchAll();

        return $result;
    }

    /**
     * 過去の成功課金情報から作成する決済依頼データを取得する
     * @param int $sale_type 販売種別ID
     * @param array $exclude_ids 対象外にする注文IDの配列
     * @return array
     */
    public function getExistingOrderItemsBySaleType($sale_type, $exclude_ids = [])
    {
        $sql = "SELECT
                    so.order_id,
                    so.customer_id,
                    op.memo01 AS transaction_id,
                    pri.product_id,
                    pri.product_class_id,
                    pri.shipping_id,
                    pri.order_item_type_id,
                    pri.amount,
                    pr.settlement_fee,
                    pri.quantity,
                    soi.subsc_status,
                    (SELECT COALESCE(count(wksoi.order_id), 0) FROM plg_vt4g_subsc_order_item wksoi WHERE so.order_id = wksoi.order_id AND wksoi.subsc_status = :subsc_status) AS subsc_dtl_cnt -- 継続の明細カウント
                FROM plg_vt4g_subsc_order so
                    INNER JOIN plg_vt4g_payment_request pr ON so.latest_payment_req_no = pr.id
                    INNER JOIN plg_vt4g_payment_request_item pri ON pr.id = pri.request_id
                    INNER JOIN plg_vt4g_order_payment op ON so.order_id = op.order_id
                    INNER JOIN dtb_customer c ON so.customer_id = c.id  AND c.customer_status_id = 2 -- 2:本会員
                    LEFT JOIN plg_vt4g_subsc_order_item soi ON
                        so.order_id = soi.order_id AND pri.product_id = soi.product_id AND pri.product_class_id = soi.product_class_id
                WHERE
                    so.subsc_sale_type_id = :sale_type_id ";
        $sql .= !empty($exclude_ids) ? 'AND so.order_id NOT IN ('.implode(',', $exclude_ids).') ' : '';
        $sql .= "GROUP BY
                    so.order_id,
                    so.customer_id,
                    op.memo01,
                    pri.product_id,
                    pri.product_class_id,
                    pri.shipping_id,
                    pri.order_item_type_id,
                    pri.amount,
                    pr.settlement_fee,
                    pri.quantity,
                    soi.subsc_status
                ORDER BY
                    so.order_id, pri.order_item_type_id, pri.product_id";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute([
                'subsc_status' => $this->vt4gConst['VTG4_SUBSC_STATUS_SUBSC'], // 1:継続
                'sale_type_id' => $sale_type
              ]);
        $result = $stmt->fetchAll();

        return $result;
    }

    /**
     * 再決済用の決済依頼データを取得する
     * @param int $resettlementEventId 再決済対象の決済依頼イベントID
     */
    public function getReWettlementOrderItems($resettlementEventId)
    {
        $sql = "SELECT
                    pr.first_order_id AS order_id,
                    pr.customer_id,
                    op.memo01 AS transaction_id,
                    pri.product_id,
                    pri.product_class_id,
                    pri.shipping_id,
                    pri.order_item_type_id,
                    pri.amount,
                    pr.settlement_fee,
                    pri.quantity,
                    soi.subsc_status,
                    (SELECT COALESCE(count(wksoi.order_id), 0) FROM plg_vt4g_subsc_order_item wksoi WHERE pr.first_order_id = wksoi.order_id AND wksoi.subsc_status = :subsc_status) AS subsc_dtl_cnt -- 継続の明細カウント
                FROM plg_vt4g_payment_request pr
                    INNER JOIN plg_vt4g_payment_request_item pri ON pr.id = pri.request_id
                    INNER JOIN plg_vt4g_order_payment op ON pr.first_order_id = op.order_id
                    INNER JOIN dtb_customer c ON pr.customer_id = c.id  AND c.customer_status_id = 2 -- 2:本会員
                    LEFT JOIN plg_vt4g_subsc_order_item soi ON
                        pr.first_order_id = soi.order_id AND pri.product_id = soi.product_id AND pri.product_class_id = soi.product_class_id AND pri.shipping_id = soi.shipping_id
                WHERE
                    pr.req_event_id = :resettlementEventId
                  AND
                    pr.request_status = :request_status ";
        $sql .= "GROUP BY
                    pr.first_order_id,
                    pr.customer_id,
                    op.memo01,
                    pri.product_id,
                    pri.product_class_id,
                    pri.shipping_id,
                    pri.order_item_type_id,
                    pri.amount,
                    pr.settlement_fee,
                    pri.quantity,
                    soi.subsc_status
                ORDER BY
                    pr.first_order_id, pri.order_item_type_id, pri.product_id, pri.shipping_id";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute([
            'subsc_status' => $this->vt4gConst['VTG4_SUBSC_STATUS_SUBSC'], // 1:継続
            'resettlementEventId' => $resettlementEventId,
            'request_status' => $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['FAILURE_PAYMENT'],
        ]);
        $result = $stmt->fetchAll();

        return $result;
    }

    /**
     * 継続課金会員検索のクエリビルダを取得する
     * @param array $searchCond 検索フォームの配列
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getSubscCustomerList($searchCond)
    {
        $qb = $this->createQueryBuilder('so');
        $qb->select(
              '
                st.name sale_type_name
              , so.customer_id
              , c.name01
              , c.name02
              , oi.product_name
              , so.order_id AS first_order_id
              , soi.subsc_status
              , soi.product_id
              , soi.product_class_id
              , soi.shipping_id
              ')
        ->innerJoin('\Plugin\VeriTrans4G\Entity\Vt4gSubscOrderItem', 'soi', 'WITH', 'so.order_id=soi.order_id')
        ->innerJoin('\Eccube\Entity\Master\SaleType', 'st', 'WITH', 'so.subsc_sale_type_id=st.id')
        ->innerJoin('\Eccube\Entity\Customer', 'c', 'WITH', 'so.customer_id=c.id')
        ->leftJoin('\Eccube\Entity\OrderItem', 'oi', 'WITH', 'so.order_id=oi.Order AND soi.product_id=oi.Product AND soi.product_class_id=oi.ProductClass AND soi.shipping_id=oi.Shipping')
        ->where('1 = 1');

        // 決済依頼回数のスカラー問合せ
        $sub_query = "
                      (SELECT COUNT(pr.id)
                      FROM \Plugin\VeriTrans4G\Entity\Vt4gPaymentRequest pr
                      INNER JOIN \Plugin\VeriTrans4G\Entity\Vt4gPaymentRequestItem pri
                      WITH pr.id = pri.request_id
                      WHERE pr.first_order_id = so.order_id AND pri.product_id = soi.product_id AND pri.product_class_id = soi.product_class_id) AS pay_total
                      ";
        $qb->addSelect($sub_query);


        // 条件　販売種別
        if (!empty($searchCond['sale_type_id']) && $searchCond['sale_type_id']) {
          $qb
              ->andWhere('so.subsc_sale_type_id=:sale_type')
              ->setParameter('sale_type', $searchCond['sale_type_id']);
        }
        // 条件　会員ID・お名前
        if (isset($searchCond['event_name']) && StringUtil::isNotBlank($searchCond['event_name'])) {
            //スペース除去
            $clean_key_multi = preg_replace('/\s+|[　]+/u', '', $searchCond['event_name']);
            $id = preg_match('/^\d{0,10}$/', $clean_key_multi) ? $clean_key_multi : null;
            $qb
                ->andWhere('c.id = :customer_id OR CONCAT(c.name01, c.name02) LIKE :name OR CONCAT(c.kana01, c.kana02) LIKE :kana')
                ->setParameter('customer_id', $id)
                ->setParameter('name', '%'.$clean_key_multi.'%')
                ->setParameter('kana', '%'.$clean_key_multi.'%');
        }

        return $qb;
    }


    /**
     * 会員IDに紐づく継続課金注文情報のクエリビルダを取得します。
     * @param int $customer_id 会員ID
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getQueryBuilderByCustomerId($customer_id)
    {
        $qb = $this->createQueryBuilder('so');
        $qb->select(
            '   so.customer_id
              , oi.product_name
              , oi.class_category_name1
              , oi.class_category_name2
              , oi.price + oi.tax AS price_inc_tax
              , oi.quantity
              , so.order_id AS first_order_id
              , o.order_no AS first_order_no
              , soi.subsc_status
              , soi.product_id
              , soi.product_class_id
              , soi.shipping_id
              , pi.file_name
              , CONCAT(si.name01, si.name02) AS shipping_name
              , CONCAT(si.addr01, si.addr02) AS shipping_addr
              ');
        $qb->innerJoin('\Plugin\VeriTrans4G\Entity\Vt4gSubscOrderItem', 'soi', 'WITH', 'soi.order_id=soi.order_id');
        $qb->innerJoin('\Eccube\Entity\Order', 'o', 'WITH', 'so.order_id=o.id');
        $qb->innerJoin('\Eccube\Entity\OrderItem', 'oi', 'WITH', 'so.order_id=oi.Order AND soi.product_id=oi.Product AND soi.product_class_id=oi.ProductClass AND soi.shipping_id=oi.Shipping');
        $qb->leftJoin('\Eccube\Entity\ProductImage', 'pi', 'WITH', 'pi.Product=oi.Product AND pi.sort_no = 1');
        $qb->innerJoin('\Eccube\Entity\Shipping', 'si', 'WITH', 'soi.shipping_id=si.id');
        $qb->where('so.customer_id=:customer_id');
        $qb->setParameter('customer_id', $customer_id);

        return $qb;

    }

}
