<?php
namespace Plugin\VeriTrans4G\Repository;

use Eccube\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Plugin\VeriTrans4G\Entity\Vt4gPlugin;

class Vt4gPluginRepository extends AbstractRepository
{
    /**
     * コンストラクタ
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Vt4gPlugin::class);
    }

    /**
     * 指定されたplugin_idのレコードを取得します。
     * @param int $plugin_id
     * @return null|Vt4gPlugin
     */
    public function get($plugin_id = 1)
    {
        return $this->find($plugin_id);
    }

    /**
     * サブデータを取得します。
     * @param string $pluginCode
     * @return array|false
     */
    public function getSubData($pluginCode)
    {
        $query = $this->createQueryBuilder('m')
                    ->where('m.plugin_code = :plugin_code')
                    ->setParameter('plugin_code', $pluginCode)
                    ->getQuery();
        $Module = $query->getOneOrNullResult();
        if (!empty($Module)) {
            return $Module->getSubData();
        }
        return false;
    }

}