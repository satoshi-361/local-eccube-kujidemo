<?php
namespace Plugin\VeriTrans4G\Repository;

use Eccube\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\VeriTrans4G\Entity\Vt4gSubscOrderItem;

class Vt4gSubscOrderItemRepository extends AbstractRepository
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
        parent::__construct($registry, Vt4gSubscOrderItem::class);
        $this->container = $container;
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * 継続課金ステータスを取得します。
     * @param Array $orders
     * @return Array
     */
    public function getSubscStatusList($orders)
    {
        $subscStatusList = [];
        if (count($orders) > 0) {
          $qb = $this->createQueryBuilder('oi');

          $qb
            ->select('oi.order_id')
            ->Where(
              $qb->expr()->eq('oi.subsc_status', $this->vt4gConst['VTG4_SUBSC_STATUS_SUBSC'])
            );

          $orConditions = $qb->expr()->orX();
          foreach ($orders as $order ) {
            $orConditions->add(
              $qb->expr()->eq('oi.order_id', $order->getId())
            );
          }
          $qb->andWhere($orConditions);

          $qb->groupBy('oi.order_id');

          $subscStatusList = $qb->getQuery()->getArrayResult();
        }

        return $subscStatusList;
    }

    /**
     * 注文情報にない継続課金商品を解約にする
     *
     */
    public function cancelOrderNotExists()
    {
        $sql = "UPDATE plg_vt4g_subsc_order_item AS soi
                SET subsc_status=:set_subsc_status
                WHERE NOT EXISTS (
                    SELECT * FROM dtb_order_item oi
                        WHERE soi.order_id=oi.order_id AND soi.product_id=oi.product_id AND soi.product_class_id=oi.product_class_id
                ) AND soi.subsc_status=:wh_subsc_status";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute([
            'set_subsc_status' => $this->vt4gConst['VTG4_SUBSC_STATUS_CANCEL'],
            'wh_subsc_status' => $this->vt4gConst['VTG4_SUBSC_STATUS_SUBSC']
        ]);
    }
}
