<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Repository;

use Eccube\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;


/**
 * plg_vt4g_payment_methodリポジトリクラス
 */
class Vt4gPaymentMethodRepository extends AbstractRepository
{

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * コンストラクタ
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Vt4gPaymentMethod::class);
    }

    /**
     * VT用固定値配列を設定します。
     * @param array $vt4gConst
     */
    public function setConst(array $vt4gConst)
    {
        $this->vt4gConst = $vt4gConst;

        return $this;
    }

    /**
     * 指定された支払方法IDのレコードを取得します。
     * @param int $payment_id
     * @return null|Vt4gPaymentMethod
     */
    public function get($payment_id = 1)
    {
        return $this->find($payment_id);
    }

    /**
     * 指定された支払方法内部IDのレコードを複数取得します。
     * @param int $paymentTypeId
     * @return array|null
     */
    public function getPaymentByTypeId($paymentTypeId)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('p')
            ->from('\Eccube\Entity\Payment', 'p')
            ->join('\Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod', 'g', 'WITH', 'p.id = g.payment_id')
            ->where(
                $qb->expr()->eq('g.memo03', ':x')
                );
        $qb->setParameter('x', $paymentTypeId);

        return $qb->getQuery()->getResult();

    }

    /**
     * プラグインコードがVeriTrans4Gのレコードを取得します。
     * @return array|NULL
     */
    public function getPaymentIdByPluginCode()
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('p.id')
            ->from('\Eccube\Entity\Payment', 'p')
            ->join('\Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod', 'g', 'WITH', 'p.id = g.payment_id')
            ->where($qb->expr()->andx(
                $qb->expr()->eq('g.plugin_code', ':code'))
                );
        $qb->setParameter('code', $this->vt4gConst['VT4G_CODE']);

        return $qb->getQuery()->getResult();
    }

    /**
     * 一部レコードを除外して決済方法名リストを取得
     *
     * @param  array $excludeList - 除外する決済方法名リスト
     * @return array                決済方法名リスト
     */
    public function getPaymentMethodList($excludeList = [])
    {
        $query = $this->createQueryBuilder('pm');

        $query
            ->select('pm.payment_id AS paymentId')
            ->where('pm.plugin_code = :pluginCode')
            ->setParameters(['pluginCode' => $this->vt4gConst['VT4G_CODE']]);

        if (!empty($excludeList)) {
            $query->andWhere($query->expr()->notIn('pm.memo03', $excludeList));
        }

        $records = $query->getQuery()->getResult();

        return array_map(function ($record) {
            return $record['paymentId'];
        }, $records);
    }

    /**
     * plg_vt4g_payment_methodに含まれないdtb_paymentの支払方法を取得
     * @return mixed
     */
    public function getNotExistsVt4gPaymentMethodList()
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('p.id')
            ->from('\Eccube\Entity\Payment', 'p')
            ->where(
                'NOT EXISTS(SELECT g.payment_id FROM \Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod g WHERE p.id = g.payment_id)'
                );

        return $qb->getQuery()->getResult();

    }
}
