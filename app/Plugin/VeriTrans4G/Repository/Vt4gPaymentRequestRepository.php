<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Repository;

use Eccube\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentRequest;
use Eccube\Util\StringUtil;

/**
 * plg_vt4g_payment_requestリポジトリクラス
 */
class Vt4gPaymentRequestRepository extends AbstractRepository
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
        parent::__construct($registry, Vt4gPaymentRequest::class);
        $this->container = $container;
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * 注文番号に紐づくCSV決済依頼情報を取得するクエリビルダを取得する
     * @param  string $order_id
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getQueryBuilderByOrderId($order_id)
    {
        $qb = $this->createQueryBuilder('pr')
            ->where('pr.first_order_id = :first_order_id')
            ->andWhere('pr.request_status = :request_status')
            ->setParameter('first_order_id', $order_id)
            ->setParameter(
                'request_status', $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['SUCCESS_PAYMENT']
            );

        // Order By
        $qb->addOrderBy('pr.id', 'DESC');

        return $qb;
    }

    /**
     * イベントIDに紐づくCSV決済依頼情報を取得するクエリビルダを取得する
     * @param int $event_id
     * @param int $request_status
     * @param string $search_keyword
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getPayRequestListByEventId($event_id, $request_status = null, $search_keyword = null)
    {
        $qb = $this->createQueryBuilder('pr');
        $qb->select('pr.id, pr.customer_id, pr.order_total, pr.delivery_fee_total, pr.settlement_fee, pr.point_total, pr.request_status, pr.transaction_id, pr.first_order_id, c.name01, c.name02')
           ->innerJoin('\Eccube\Entity\Customer', 'c', 'WITH', 'pr.customer_id = c.id')
           ->where('pr.req_event_id = :event_id')
           ->setParameter('event_id', $event_id);

        // 決済依頼ステータス(指定なし:99の値を受け取った場合は条件式に含めない)
           if (isset($request_status) && $request_status <> '99') {
            $qb->andWhere('pr.request_status = :request_status')
               ->setParameter('request_status', $request_status);
        }

        // 決済依頼番号・会員名(会員検索を参考)
        if (isset($search_keyword) && StringUtil::isNotBlank($search_keyword)) {
            // スペース除去
            $clean_key_multi = preg_replace('/\s+|[　]+/u', '', $search_keyword);
            $id = preg_match('/^\d{0,10}$/', $clean_key_multi) ? $clean_key_multi : null;

            if (isset($id)) {
                $qb->andWhere('pr.id = :id')->setParameter('id', $id);
            } else {
                $qb->andWhere('CONCAT(c.name01, c.name02) LIKE :name OR CONCAT(c.kana01, c.kana02) LIKE :kana')
                   ->setParameter('name', '%'.$clean_key_multi.'%')
                   ->setParameter('kana', '%'.$clean_key_multi.'%');
            }
        }

        return $qb;
    }

    /**
     * 決済依頼IDに紐づくCSV決済依頼情報を取得する
     * @param int $request_id
     * @return array
     */
    public function getPaymentRequest($request_id)
    {
        $qb = $this->createQueryBuilder('pr');
        $qb->select('pr.id, pr.customer_id, pr.order_total, pr.delivery_fee_total, pr.settlement_fee, pr.point_total, pr.request_status, pr.transaction_id, pr.first_order_id')
           ->addSelect('c.name01, c.name02, l.err_message')
           ->innerJoin('\Eccube\Entity\Customer', 'c', 'WITH', 'pr.customer_id = c.id')
           ->leftJoin('\Plugin\VeriTrans4G\Entity\Vt4gCsvResultLog', 'l', 'WITH', 'pr.id = l.request_id')
           ->where('pr.id = :request_id')
           ->setParameter('request_id', $request_id);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * plg_vt4g_payment_requestのステータスを更新する
     * @param int $event_id
     * @param int $paymentTarget
     */
    public function updateRequestStatus($event_id, $paymentTarget)
    {
        $WAITING_FOR_REFRECTON = $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['WAITING_FOR_REFRECTON'];
        $BEFORE_CREATION = $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['BEFORE_CREATION'];
        $NOT_APPLICABLE = $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['NOT_APPLICABLE'];

        $em = $this->getEntityManager();

        // 決済依頼明細
        $sql = "UPDATE plg_vt4g_payment_request_item SET payment_target= :set_status
            WHERE request_id IN (SELECT id FROM plg_vt4g_payment_request WHERE req_event_id = :req_event_id AND request_status < :wh_status)";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute([
            'req_event_id' => $event_id,
            'set_status' => $paymentTarget,
            'wh_status' => $WAITING_FOR_REFRECTON
        ]);

        // 決済依頼
        $request_status = ($paymentTarget == $this->vt4gConst['VT4G_PAYMENT_TARGET_YES']) ? $BEFORE_CREATION : $NOT_APPLICABLE;
        $sql = "UPDATE plg_vt4g_payment_request AS pr SET request_status= :set_status ";
        // 対象なら再集計、対象外なら集計値ゼロクリア
        if ($paymentTarget == $this->vt4gConst['VT4G_PAYMENT_TARGET_YES']) {
            $sql .= " , order_total = (SELECT COALESCE(sum(wk1.amount * wk1.quantity), 0) as total_amount FROM plg_vt4g_payment_request_item wk1
                                        WHERE wk1.request_id = pr.id AND wk1.order_item_type_id = '1')
                      , delivery_fee_total = (SELECT COALESCE(sum(wk1.amount), 0) as total_delivery_fee_total FROM plg_vt4g_payment_request_item wk1
                                                WHERE wk1.request_id = pr.id AND wk1.order_item_type_id = '2')
                      , point_total = (SELECT COALESCE(sum(wk1.point), 0) as total_point FROM plg_vt4g_payment_request_item wk1
                                        WHERE wk1.request_id = pr.id AND wk1.order_item_type_id = '1')
                  ";
        } else {
            $sql .= ", order_total = 0
                     , delivery_fee_total = 0
                     , point_total = 0";
        }
        $sql .= " WHERE pr.req_event_id = :req_event_id AND pr.request_status < :wh_status";

        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute([
            'req_event_id' => $event_id,
            'set_status' => $request_status,
            'wh_status' => $WAITING_FOR_REFRECTON
        ]);
    }

    /**
     * 会員ID、商品ID、商品規格ID、注文番号に紐づく決済依頼情報を取得する
     * @return array
     */
    public function getSubscCustomerProduct($customer_id, $product_id, $product_class_id, $shipping_id, $order_id)
    {

        $sql =
            "SELECT DISTINCT pr.*,CONCAT(c.name01, c.name02) customer_name, pri.product_id, pri.product_class_id, pri.shipping_id, p.name product_name, soi.subsc_status, pre.event_name, pr.first_order_id
            FROM plg_vt4g_payment_request pr
                INNER JOIN plg_vt4g_payment_request_item pri ON pr.id=pri.request_id
                INNER JOIN dtb_customer c ON pr.customer_id=c.id
                INNER JOIN plg_vt4g_payment_req_event pre ON pr.req_event_id=pre.id
                INNER JOIN plg_vt4g_subsc_order_item soi ON pr.first_order_id=soi.order_id AND pri.product_id=soi.product_id AND pri.product_class_id=soi.product_class_id AND pri.shipping_id=soi.shipping_id
                INNER JOIN dtb_product_class pc ON soi.product_id=pc.product_id AND soi.product_class_id=pc.id
                INNER JOIN dtb_product p ON soi.product_id=p.id
            WHERE pr.customer_id = :customer_id
                AND pri.product_id = :product_id
                AND pri.product_class_id = :product_class_id
                AND pri.shipping_id = :shipping_id
                AND pr.first_order_id = :order_id
            ORDER BY
                pr.reflect_date DESC
            ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute([
            'customer_id' => $customer_id,
            'product_id' => $product_id,
            'product_class_id' => $product_class_id,
            'shipping_id' => $shipping_id,
            'order_id' => $order_id
        ]);
        $result = $stmt->fetchAll();

        return $result;
    }

    /**
     * イベントIDを元に決済依頼の決済手数料を一括更新する
     *
     * @param int $event_id
     * @param int $settelment_fee
     */
    public function updateBulkSettelmentFee($event_id, $settelment_fee)
    {
        $sql = "UPDATE plg_vt4g_payment_request SET settlement_fee = :settlement_fee WHERE req_event_id = :req_event_id";
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute(['req_event_id' => $event_id, 'settlement_fee' => $settelment_fee]);
    }

    /**
     * イベントIDを元に決済依頼の送料・ポイントを一括更新する
     *
     * @param int $event_id
     * @param int $product_class_id
     * @param int $price
     * @param int $point
     */
    public function updateBulkPricePoint($event_id, $product_class_id, $price, $point)
    {
        $param = ['event_id' => (int)$event_id];
        $set = [];
        if ($price != '') {
            $set[] = 'amount = :amount';
            $param['amount'] = (int)$price;
        }
        if ($product_class_id > 0 && $point != '') {
            $set[] = 'point = :point';
            $param['point'] = (int)$point;
        }

        $sql = "UPDATE plg_vt4g_payment_request_item SET ";
        $sql .= implode(',', $set);
        $sql .= " WHERE request_id IN (SELECT id FROM plg_vt4g_payment_request WHERE req_event_id=:event_id) ";

        if ($product_class_id > 0) {
            $sql .= "AND product_class_id = :product_class_id";
            $param['product_class_id'] = (int)$product_class_id;
        } else {
            $sql .= "AND order_item_type_id = 2";
        }
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute($param);

        // 商品金額、ポイント再集計
        $em = $this->getEntityManager();
        $sql = "SELECT request_id, COALESCE(SUM(amount * quantity), 0) amount, COALESCE(SUM(point), 0) point
                FROM plg_vt4g_payment_request_item
                WHERE order_item_type_id = 1
                AND request_id IN (SELECT id FROM plg_vt4g_payment_request WHERE req_event_id = :req_event_id)
                GROUP BY request_id";
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute(['req_event_id' => $event_id]);
        foreach ($stmt->fetchAll() as $res) {
            $item = $this->find($res['request_id']);
            $item->setOrderTotal($res['amount'])
                 ->setPointTotal($res['point']);
            $em->persist($item);
        }
        $em->flush();

        // 送料再集計
        $sql = "SELECT request_id, COALESCE(SUM(amount), 0) amount
                FROM plg_vt4g_payment_request_item
                WHERE order_item_type_id = 2
                AND request_id IN (SELECT id FROM plg_vt4g_payment_request WHERE req_event_id = :req_event_id)
                GROUP BY request_id";
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute(['req_event_id' => $event_id]);
        foreach ($stmt->fetchAll() as $res) {
            $item = $this->find($res['request_id']);
            $item->setDeliveryFeeTotal($res['amount']);
            $em->persist($item);
        }
        $em->flush();
    }

    /**
     * 決済依頼の合計商品金額、合計付与ポイントを再集計する
     *
     * @param int $request_id
     */
    public function reaggregateOrderAndPointTotalForCsvTarget($request_id)
    {

        $sql = "UPDATE plg_vt4g_payment_request
                SET   order_total = (SELECT COALESCE(sum(wk1.amount * wk1.quantity), 0) FROM plg_vt4g_payment_request_item wk1 WHERE wk1.order_item_type_id = '1' AND wk1.payment_target='1' AND wk1.request_id = :request_id)
                    , point_total = (SELECT COALESCE(sum(wk1.point), 0) FROM plg_vt4g_payment_request_item wk1 WHERE wk1.order_item_type_id = '1' AND wk1.payment_target='1' AND wk1.request_id = :request_id)
                WHERE id = :request_id";
        $parm = ['request_id' => $request_id];

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute($parm);
    }

    /**
     * 決済依頼の合計送料をクリアする
     *
     * @param int $request_id
     */
    public function clearDeliveryFeeTotal($request_id)
    {
        $sql = "UPDATE plg_vt4g_payment_request
                SET delivery_fee_total = 0
                WHERE id = :request_id";
        $parm = ['request_id' => $request_id];

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute($parm);
    }

    /**
     * 決済依頼の合計送料を再計算する
     *
     * @param int $request_id
     */
    public function reaggregateDeliveryFeeTotalForCsvTarget($request_id)
    {

        $sql = "UPDATE plg_vt4g_payment_request
                SET delivery_fee_total =
                    (SELECT COALESCE(sum(wk1.amount), 0)
                        FROM plg_vt4g_payment_request_item wk1
                        WHERE wk1.request_id = :request_id
                        AND wk1.order_item_type_id = 2
                        AND EXISTS(
                            SELECT '1'
                            FROM plg_vt4g_payment_request_item wk2
                            WHERE wk1.request_id = wk2.request_id
                            AND wk1.shipping_id = wk2.shipping_id
                            AND wk2.order_item_type_id = 1
                            AND wk2.payment_target = 1
                        ))
                WHERE id = :request_id";
        $parm = ['request_id' => $request_id ];

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute($parm);
    }


    /**
     * CSV出力用の決済依頼明細情報を取得する
     * @param int $event_id 決済依頼イベント番号
     * @return array CSV出力用の決済依頼明細情報
     */
    public function getCsvtRequestDetail($event_id)
    {
        $qb = $this->createQueryBuilder('pr');
        $qb->select(
            ' pr.req_event_id
            , pre.event_name
            , st.name
            , pr.id as request_id
            , pr.request_status
            , pr.customer_id
            , concat(c.name01,c.name02) as customer_name
            , pr.first_order_id
            , pr.transaction_id
            , pr.order_total
            , pr.settlement_fee
            , pri.id
            , case when pri.order_item_type_id = \'1\' then pd.name else \'送料\' end as product_name
            , pri.amount
            , pri.quantity
            , pri.point
            , pri.shipping_id
            , concat(sp.name01,sp.name02) as delively_name
            , concat(pf.name,sp.addr01,sp.addr02) as delively_addr
            , case when pri.order_item_type_id = \'1\'
                then case when pri.payment_target = \'1\' then \'対象\' else \'対象外\' end
                else \'\' end
            ')
            ->innerJoin('\Plugin\VeriTrans4G\Entity\Vt4gPaymentReqEvent', 'pre', 'WITH', 'pr.req_event_id = pre.id')
            ->innerJoin('\Plugin\VeriTrans4G\Entity\Vt4gPaymentRequestItem', 'pri', 'WITH', 'pr.id = pri.request_id')
            ->innerJoin('\Eccube\Entity\Customer', 'c', 'WITH', 'pr.customer_id = c.id')
            ->innerJoin('\Eccube\Entity\Shipping', 'sp', 'WITH', 'pri.shipping_id = sp.id')
            ->innerJoin('\Eccube\Entity\Master\Pref', 'pf', 'WITH', 'pf.id = sp.Pref')
            ->innerJoin('\Eccube\Entity\Master\SaleType', 'st', 'WITH', 'pre.sale_type_id = st.id')
            ->leftJoin('\Eccube\Entity\Product', 'pd', 'WITH', 'pri.product_id = pd.id')
            ->where('pr.req_event_id = :event_id')
            ->setParameter('event_id', $event_id);

        return $qb->getQuery()->getResult();
    }
}
