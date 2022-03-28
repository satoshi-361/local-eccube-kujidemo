<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Repository;

use Eccube\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentReqEvent;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * plg_vt4g_payment_req_eventリポジトリクラス
 */
class Vt4gPaymentReqEventRepository extends AbstractRepository
{

    /**
     * コンテナ
     */
    protected $container;

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * コンストラクタ
     * @param RegistryInterface $registry
     * @param ContainerInterface $container
     */
    public function __construct(RegistryInterface $registry, ContainerInterface $container)
    {
        parent::__construct($registry, Vt4gPaymentReqEvent::class);
        $this->container = $container;
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * 決済依頼イベント情報を取得する.
     *
     * @param int $event_id
     */
    public function getRequestEvent($event_id)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('e.id, e.sale_type_id, e.event_name, e.file_name, s.name sale_type_name')
           ->innerJoin('\Eccube\Entity\Master\SaleType', 's', 'WITH', 'e.sale_type_id = s.id')
           ->where('e.id = :event_id')
           ->setParameter('event_id', $event_id);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * 決済依頼イベント一覧情報を取得する.
     *
     * @param array $post
     */
    public function getRequestEventList($post)
    {
      $em = $this->getEntityManager();

      // 決済依頼イベント一覧データを取得
      $sql =
        "SELECT
         rev.id
        ,rev.sale_type_id
        ,sl.name
        ,rev.event_name
        ,rev.csv_create_date
        ,rev.file_name
        ,COALESCE((SELECT COUNT(pre.id) FROM plg_vt4g_payment_request pre WHERE pre.req_event_id = rev.id GROUP BY pre.req_event_id), 0) AS total_cnt
        ,COALESCE((SELECT COUNT(pre.id) FROM plg_vt4g_payment_request pre WHERE pre.req_event_id = rev.id AND pre.request_status = :beforeCreation GROUP BY pre.req_event_id), 0) AS before_creation_cnt
        ,COALESCE((SELECT COUNT(pre.id) FROM plg_vt4g_payment_request pre WHERE pre.req_event_id = rev.id AND pre.request_status = :notAppilcable GROUP BY pre.req_event_id), 0) AS not_appilcable_cnt
        ,COALESCE((SELECT COUNT(pre.id) FROM plg_vt4g_payment_request pre WHERE pre.req_event_id = rev.id AND pre.request_status = :waitingForReflection GROUP BY pre.req_event_id), 0) AS waiting_for_reflection_cnt
        ,COALESCE((SELECT COUNT(pre.id) FROM plg_vt4g_payment_request pre WHERE pre.req_event_id = rev.id AND pre.request_status = :successPayment GROUP BY pre.req_event_id), 0) AS success_payment_cnt
        ,COALESCE((SELECT COUNT(pre.id) FROM plg_vt4g_payment_request pre WHERE pre.req_event_id = rev.id AND pre.request_status = :failurePayment GROUP BY pre.req_event_id), 0) AS failure_payment_cnt
        FROM
        plg_vt4g_payment_req_event rev
        LEFT OUTER JOIN mtb_sale_type sl
        ON sl.id = rev.sale_type_id
        WHERE 1 = 1
        ";

      $parms = ['beforeCreation' => $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['BEFORE_CREATION'],
                'notAppilcable' => $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['NOT_APPLICABLE'],
                'waitingForReflection' => $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['WAITING_FOR_REFRECTON'],
                'successPayment' => $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['SUCCESS_PAYMENT'],
                'failurePayment' => $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['FAILURE_PAYMENT'],
              ];

      if (isset($post['sale_type_id'])) {
          $sql = $sql . " AND rev.sale_type_id = :sale_type_id ";
          $parms['sale_type_id'] = $post['sale_type_id'];
      }

      if (isset($post['event_name'])) {
          $sql = $sql . " AND rev.event_name LIKE " . ":event_name";
          $parms['event_name'] = "%" . $post['event_name'] . "%";
      }

      $stmt = $em->getConnection()->prepare($sql);
      $stmt->execute($parms);
      $records = $stmt->fetchAll();

      return $records;
    }

    /**
     * 決済依頼イベントに含まれる商品を取得する.
     *
     * @param int $event_id
     */
    public function getProductListByEventId($event_id)
    {
        $sql = "SELECT DISTINCT
                    pri.product_class_id,
                    CONCAT(oi.product_name, ' ', COALESCE(oi.class_category_name1,''), ' ', COALESCE(oi.class_category_name2,'')) AS product_name
                 FROM plg_vt4g_payment_request pr
                INNER JOIN plg_vt4g_payment_request_item pri
                   ON pr.id=pri.request_id
                INNER JOIN dtb_order_item oi
                   ON pr.first_order_id=oi.order_id
                  AND pri.product_id=oi.product_id
                  AND pri.product_class_id=oi.product_class_id
                WHERE oi.order_item_type_id = 1
                  AND pr.req_event_id = :req_event_id
                UNION
                SELECT DISTINCT
                    oi.product_class_id,
                    CONCAT(oi.product_name, ' ', COALESCE(oi.class_category_name1,''), ' ', COALESCE(oi.class_category_name2,'')) AS product_name
                 FROM plg_vt4g_payment_request pr
                INNER JOIN dtb_order_item oi
                   ON pr.first_order_id=oi.order_id
                WHERE oi.order_item_type_id IN (2)
                  AND pr.req_event_id = :req_event_id";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute(['req_event_id' => $event_id, ]);

        return $stmt->fetchAll();
    }

}
