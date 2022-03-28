<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright (c) 2006 PAYGENT Co.,Ltd. All rights reserved.
 *
 * https://www.paygent.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Plugin\PaygentPayment;

use Eccube\Entity\PageLayout;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Page;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\PaygentPayment\Entity\Config;

class PluginManager extends AbstractPluginManager
{
    /**
     * プラグイン有効ボタン押下時の処理
     * @param array $meta
     * @param ContainerInterface $container
     * {@inheritDoc}
     * @see \Eccube\Plugin\AbstractPluginManager::enable()
     */
    public function enable(array $meta, ContainerInterface $container)
    {
        $this->createPlgPaygentConfig($container);
    }

    /**
     * プラグイン無効ボタン押下時の処理
     * @param array $meta
     * @param ContainerInterface $container
     * {@inheritDoc}
     * @see \Eccube\Plugin\AbstractPluginManager::disable()
     */
    public function disable(array $meta, ContainerInterface $container)
    {
        $this->deleteAllPage($container);
    }

    /**
     * プラグインアップデート時の処理
     * @param array $meta
     * @param ContainerInterface $container
     * {@inheritDoc}
     * @see \Eccube\Plugin\AbstractPluginManager::disable()
     */
    public function update(array $meta, ContainerInterface $container)
    {
        $this->createPlgPaygentConfig($container);
    }

    /**
     * create table plg_paygent_payment_config
     *
     * @param ContainerInterface $container
     */
    public function createPlgPaygentConfig(ContainerInterface $container)
    {
        $entityManage = $container->get('doctrine')->getManager();
        $config = $entityManage->find(Config::class, 1);
        if ($config) {
            if (is_null($config->getSettlementDivision())) {
                // 設定が存在するがシステム種別の値が存在しない場合
                $config->setSettlementDivision(2);
            }
            $this->setInitValue($config, $entityManage);
            return;
        }

        // プラグイン情報初期セット
        $config = new Config();
        $this->setInitValue($config, $entityManage);
    }

    /**
     * ペイジェント決済 全てのページ情報を削除 dtb_page, dtb_page_layout
     * @param ContainerInterface $container
     */
    private function deleteAllPage(ContainerInterface $container) {
        $eccubeConfig = new EccubeConfig($container);
        $arrPageParam = [];
        // モジュール型
        foreach($eccubeConfig['paygent_payment']['create_page_param']['module'] as $pageParam) {
            $arrPageParam[] = $pageParam;
        }
        // リンク型
        $arrPageParam[] = $eccubeConfig['paygent_payment']['create_page_param']['link']['error_page'];

        foreach ($arrPageParam as $pageParam) {
            $this->deletePage($container, $pageParam);
        }

    }

    /**
     * ペイジェント決済エラーページ情報を削除 dtb_page, dtb_page_layout
     * @param ContainerInterface $container
     * @param array $pageParam
     */
    private function deletePage(ContainerInterface $container, $pageParam) {
        // EntityManager準備
        $entityManager = $container->get('doctrine')->getManager();

        // dtb_page に存在することを確認する
        $pageRepository = $entityManager->getRepository(Page::class);
        $page = $pageRepository->findOneBy(["url" => $pageParam['url']]);
        if (is_null($page)) {
            return;
        }

        $entityManager->beginTransaction();

        // DELETE FROM dtb_page WHERE インストール時にINSERTしたページ
        $entityManager->remove($page);
        $entityManager->flush($page);

        // DELETE FROM dtb_page_layout WHERE インストール時にINSERTしたページレイアウト
        $pageLayoutRepository = $entityManager->getRepository(PageLayout::class);
        $pageLayout = $pageLayoutRepository->findOneBy(["page_id" => $page->getId()]);
        if(isset($pageLayout)){
            $entityManager->remove($pageLayout);
            $entityManager->flush($pageLayout);
        }
        $entityManager->commit();
    }

    /**
     * PAYGENT決済 初期値の設定
     * @param Config $config
     */
    private function setInitValue($config, $entityManage) {
        if (is_null($config->getSettlementDivision())) {
            $config->setSettlementDivision(1);
        }

        $config = $this->setLinkTypeInitValue($config);
        $config = $this->setModuleTypeInitValue($config);

        $entityManage->persist($config);
        $entityManage->flush($config);
    }

    /**
     * PAYGENT決済 リンク型項目の初期値の設定
     * @param Config $config
     */
    private function setLinkTypeInitValue($config)
    {
        if (is_null($config->getCardClass())) {
            $config->setCardClass(0);
        }
        if (is_null($config->getCardConf())) {
            $config->setCardConf(0);
        }
        if (is_null($config->getStockCard())) {
            $config->setStockCard(0);
        }
        if (is_null($config->getLinkPaymentTerm())) {
            $config->setLinkPaymentTerm(5);
        }
        if (is_null($config->getRollbackTargetTerm()) && $config->getSettlementDivision() == 2) {
            $config->setRollbackTargetTerm(7);
        }

        return $config;
    }

    /**
     * PAYGENT決済 モジュール型項目の初期値の設定
     * @param Config $config
     */
    private function setModuleTypeInitValue($config)
    {
        if (!$config->getPaymentDivision()) {
            $config->setPaymentDivision([]);
        }
        if (is_null($config->getSecurityCode())) {
            $config->setSecurityCode(0);
        }
        if (is_null($config->getCredit3d())) {
            $config->setCredit3d(0);
        }
        if (is_null($config->getModuleStockCard())) {
            $config->setModuleStockCard(0);
        }
        if (is_null($config->getTokenEnv())) {
            $config->setTokenEnv(0);
        }
        if (is_null($config->getConveniLimitDateNum())) {
            $config->setConveniLimitDateNum(15);
        }
        if (is_null($config->getAtmLimitDate())) {
            $config->setAtmLimitDate(30);
        }
        if (is_null($config->getAspPaymentTerm())) {
            $config->setAspPaymentTerm(7);
        }
        if (is_null($config->getRollbackTargetTerm()) && $config->getSettlementDivision() == 1) {
            $config->setRollbackTargetTerm(10);
        }
        if (!$config->getCareerDivision()) {
            $config->setCareerDivision([]);
        }
        if (!$config->getCardValidCheck()) {
            $config->setCardValidCheck(0);
        }

        return $config;
    }
}
