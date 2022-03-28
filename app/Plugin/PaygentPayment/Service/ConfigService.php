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

namespace Plugin\PaygentPayment\Service;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Payment;
use Eccube\Entity\PageLayout;
use Eccube\Repository\PaymentRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Repository\LayoutRepository;
use Eccube\Repository\PageLayoutRepository;
use Eccube\Repository\PageRepository;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Eccube\Common\EccubeConfig;

/**
 * Paygent KS-システムとの連携・EC-CUBEの決済レコード作成を行う設定クラス
 */
class ConfigService extends PaygentBaseService {

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var PluginRepository
     */
    protected $pluginRepository;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var PageRepository
     */
    protected $pageRepository;

    /**
     * @var LayoutRepository
     */
    protected $layoutRepository;

    /**
     * @var PageLayoutRepository
     */
    protected $pageLayoutRepository;

    /**
     * コンストラクタ
     * @param PaymentRepository $paymentRepository
     * @param ConfigRepository $configRepository
     * @param CacheConfig $cacheConfig
     * @param EntityManagerInterface $entityManager
     * @param PluginRepository $pluginRepository
     * @param EccubeConfig $eccubeConfig
     * @param PageRepository $pageRepository
     * @param LayoutRepository $layoutRepository
     * @param PageLayoutRepository $pageLayoutRepository
     */
    public function __construct(
        PaymentRepository $paymentRepository,
        ConfigRepository $configRepository,
        CacheConfig $cacheConfig,
        EntityManagerInterface $entityManager,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig,
        PageRepository $pageRepository,
        LayoutRepository $layoutRepository,
        PageLayoutRepository $pageLayoutRepository
        ) {
            $this->paymentRepository = $paymentRepository;
            $this->configRepository = $configRepository;
            $this->cacheConfig = $cacheConfig;
            $this->entityManager = $entityManager;
            $this->pluginRepository = $pluginRepository;
            $this->eccubeConfig = $eccubeConfig;
            $this->pageRepository = $pageRepository;
            $this->layoutRepository = $layoutRepository;
            $this->pageLayoutRepository = $pageLayoutRepository;
    }

    /**
     * 照会電文送信
     * @param array $config 電文パラメータセットをするための入力値
     * @return array $arrRes レスポンス結果
     */
    public function inquiry($config) {
        // 電文パラメータ設定
        $params = $this->makeParamInquiry($config);

        // 電文送信
        $arrRes = $this->callApi($params);

        return $arrRes;
    }

    /**
     * 電文パラメータの設定
     * @param array $inputTelegram 電文パラメータセットをするための入力値
     * @return array $params 電文パラメータ
     */
    function makeParamInquiry($inputTelegram) {
        /** 共通電文パラメータ **/
        $params = $this->commonMakeParam(0,null);

        /** プラグイン設定画面は、form値のマーチャントID、接続ID、接続パスワードを優先する。**/
        // マーチャントID
        $params['merchant_id'] = $inputTelegram->getMerchantId();
        // 接続ID
        $params['connect_id'] = $inputTelegram->getConnectId();
        // 接続パスワード
        $params['connect_password'] = $inputTelegram->getConnectPassword();

        /** 個別電文パラメータ **/
        // 電文種別ID
        $params['telegram_kind'] = $this->eccubeConfig['paygent_payment']['paygent_ref'];
        // 決済通知ID
        $params['payment_notice_id'] = 0;

        return $params;
    }

    /**
     * ペイジェント設定情報テーブル更新処理
     * @param array $config ペイジェント設定情報
     */
    function updatePaygentConfigInfo($config)
    {
        $this->entityManager->persist($config);
        $this->entityManager->flush($config);
    }

    /**
     * ペイジェント 支払方法レコード作成・更新処理
     * @param array $config 設定情報
     */
    function updatePaymentInfo($config)
    {
        $settlementId = $config->getSettlementDivision();
        $settlementName = array_flip($this->eccubeConfig['paygent_payment']['settlement_id'])[$settlementId];

        // システム種別が存在することを確認
        if (is_null($settlementId)) {
            return;
        }

        foreach($this->eccubeConfig['paygent_payment']['create_payment_param'] as $createParam) {
            $payment = $this->paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
            $sortNo = $payment ? $payment->getSortNo() + 1 : 1;

            $paymentFront= $this->paymentRepository->findOneBy(['method_class' => $createParam["method_class"]]);
            
            // 決済方法が既に存在していた場合
            if ($paymentFront) {
                $visible = false;
                if (in_array($createParam['payment_type'], $config->getPaygentPaymentMethod())) {
                    $visible = true;
                    // システム種別に対応したmethod_classを設定する
                    $paymentFront->setMethodClass($createParam['method_class'][$settlementName]);
                }
                $paymentFront->setVisible($visible);
                $paymentFront->setUpdateDate(new \DateTime());
                $this->entityManager->persist($paymentFront);
                $this->entityManager->flush($paymentFront);
            // 決済方法が存在していなかった場合
            } else {
                // 選択された決済方法を登録
                if (in_array($createParam['payment_type'], $config->getPaygentPaymentMethod())) {
                    $paymentCreate = new Payment();
                    $paymentCreate->setCharge(0);
                    $paymentCreate->setSortNo($sortNo);
                    $paymentCreate->setVisible(true);
                    $paymentCreate->setMethod($createParam["method"]);
                    // システム種別に対応したmethod_classを設定する
                    $paymentCreate->setMethodClass($createParam['method_class'][$settlementName]);

                    $this->entityManager->persist($paymentCreate);
                    $this->entityManager->flush($paymentCreate);
                }
            }
        }
    }

    /**
     * ペイジェント 支払方法レコード作成・削除
     * @param array $config 設定情報
     */
    function updatePaymentPage($config)
    {
        $settlementId = $config->getSettlementDivision();

        $isModule = $settlementId == $this->eccubeConfig['paygent_payment']['settlement_id']['module'];
        $isLink = $settlementId == $this->eccubeConfig['paygent_payment']['settlement_id']['link'];

        // モジュール型
        foreach($this->eccubeConfig['paygent_payment']['create_page_param']['module'] as $pageParam) {
            if (in_array($pageParam['payment_type'], $config->getPaygentPaymentMethod()) &&
                $isModule) {
                $this->createPage($pageParam);
            } else {
                $this->deletePage($pageParam);
            }
        }

        // リンク型
        $errorPageParam = $this->eccubeConfig['paygent_payment']['create_page_param']['link']['error_page'];

        if ($isModule) {
            $this->deletePage($errorPageParam);
        } elseif ($isLink) {
            $this->createPage($errorPageParam);
        }
    }

    /**
     * ペイジェント決済ページ レコード作成処理
     * @param array $pageParam
     */
    private function createPage($pageParam) {
        // dtb_page に存在しないことを確認する
        $pageFindResult = $this->pageRepository->findOneBy(["url" => $pageParam['url']]);
        if (isset($pageFindResult)) {
            return;
        }

        // dtb_layout から下層ページ用レイアウトを取得する
        $underLayout = $this->layoutRepository->findOneBy(["id" => 2]);

        // dtb_page_layout の次のSortNoを取得する
        $lastPageLayout = $this->pageLayoutRepository->findOneBy([], ['sort_no' => 'DESC']);
        $nextSortNo = $lastPageLayout->getSortNo() + 1;

        // EntityManager準備
        $this->entityManager->beginTransaction();

        // INSERT INTO dtb_page
        $page = $this->pageRepository->newPage();
        $page->setName($pageParam['name'])
            ->setUrl($pageParam['url'])
            ->setFileName($pageParam['file_name'])
            ->setEditType(2);
        $this->entityManager->persist($page);
        $this->entityManager->flush($page);

        // INSERT INTO dtb_page_layout
        $pageLayout = new PageLayout();
        $pageLayout->setLayout($underLayout)
            ->setLayoutId($underLayout->getId())
            ->setPageId($page->getId())
            ->setSortNo($nextSortNo)
            ->setPage($page);
        $this->entityManager->persist($pageLayout);
        $this->entityManager->flush($pageLayout);
        $this->entityManager->commit();
    }

    /**
     * ペイジェント決済ページ情報を削除 dtb_page, dtb_page_layout
     * @param array $pageParam
     */
    private function deletePage($pageParam) {
        // dtb_page に存在することを確認する
        $page = $this->pageRepository->findOneBy(["url" => $pageParam['url']]);
        if (is_null($page)) {
            return;
        }

        // EntityManager準備
        $this->entityManager->beginTransaction();

        // DELETE FROM dtb_page WHERE インストール時にINSERTしたページ
        $this->entityManager->remove($page);
        $this->entityManager->flush($page);

        // DELETE FROM dtb_page_layout WHERE インストール時にINSERTしたページレイアウト
        $pageLayout = $this->pageLayoutRepository->findOneBy(["page_id" => $page->getId()]);
        if(isset($pageLayout)){
            $this->entityManager->remove($pageLayout);
            $this->entityManager->flush($pageLayout);
        }
        $this->entityManager->commit();
    }
}