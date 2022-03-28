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

namespace Plugin\PaygentPayment\Service\Bank;

use Eccube\Common\EccubeConfig;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\CartService;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\PaygentPayment\Service\CacheConfig;
use Plugin\PaygentPayment\Service\PaygentBaseService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Paygent KS-システムとの連携・EC-CUBEの決済レコード作成を行う子クラス
 */
class FrontBankService extends PaygentBaseService {

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var PluginRepository
     */
    protected $pluginRepository;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param EntityManagerInterface $entityManager
     * @param CartService $cartService
     * @param ConfigRepository $configRepository
     * @param PluginRepository $pluginRepository
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        CartService $cartService,
        ConfigRepository $configRepository,
        EntityManagerInterface $entityManager,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig,
        ContainerInterface $container
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->cartService = $cartService;
        $this->configRepository = $configRepository;
        $this->entityManager = $entityManager;
        $this->cacheConfig = $cacheConfig;
        $this->pluginRepository = $pluginRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->container = $container;
    }

    /**
     * 新規申込
     * @param array $order 受注情報
     * @param array $form  フォーム情報
     * @return array $arreRes レスポンス情報
     */
    public function applyProcess($order)
    {
        // 電文パラメータ設定
        $params = $this->makeParam($order);

        // 電文送信
        $arrRes = $this->callApi($params);

        // 受注情報更新
        $this->saveOrder($order, $arrRes);

        return $arrRes;
    }

    /**
     * 電文パラメータの設定
     * @param array $inputOrder 受注情報
     * @return array $params 電文パラメータ
     */
    function makeParam($inputOrder)
    {
        // プラグイン設定情報の取得
        $config = $this->cacheConfig->getConfig();

        /** 共通電文パラメータ **/
        $params = $this->commonMakeParam($inputOrder->getId(),"");

        /** 個別電文パラメータ **/
        // 電文種別ID
        $params['telegram_kind'] = $this->eccubeConfig['paygent_payment']['paygent_bank'];
        // 決済金額
        $params['amount'] = (int)$inputOrder->getPaymentTotal();
        // マーチャント名
        $claimKanji = $config->getClaimKanji();
        $params['merchant_name'] = mb_convert_kana($claimKanji, 'KVA');
        // 店舗名(全角)
        $params['claim_kanji'] = mb_convert_kana($claimKanji, 'KVA');
        // 店舗名(カナ)
        $params['claim_kana'] = mb_convert_kana($config->getClaimKana(), 'k');
        $params['claim_kana'] = preg_replace("/ｰ/", "-", $params['claim_kana']);
        // 利用者姓
        $params['customer_family_name'] = mb_convert_kana($inputOrder->getName01(), 'KVA');
        // 利用者名
        $params['customer_name'] = mb_convert_kana($inputOrder->getName02(), 'KVA');
        // 利用者姓半角カナ
        $params['customer_family_name_kana'] = mb_convert_kana($inputOrder->getKana01(),'k');
        $params['customer_family_name_kana'] = preg_replace("/ｰ/", "-", $params['customer_family_name_kana']);
        $params['customer_family_name_kana'] = preg_replace("/ﾞ|ﾟ/", "", $params['customer_family_name_kana']);
        // 利用者名半角カナ
        $params['customer_name_kana'] = mb_convert_kana($inputOrder->getKana02(),'k');
        $params['customer_name_kana'] = preg_replace("/ｰ/", "-", $params['customer_name_kana']);
        $params['customer_name_kana'] = preg_replace("/ﾞ|ﾟ/", "", $params['customer_name_kana']);
        // 完了後の戻りURL
        $params['return_url'] = $this->container->get('router')->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'paygent_payment_asp_complete';
        // 中断時の戻りURL
        $params['stop_return_url'] = $this->container->get('router')->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'paygent_payment_asp_cancel'
         . '?' . 'order_id=' . $inputOrder->getId();
        // コピーライト
        $params['copy_right'] = $config->getCopyRight();
        // 自由メモ欄
        $params['free_memo'] = mb_convert_kana($config->getFreeMemo(), 'KVA');
        // 支払い期間(0DDhhmm)
        $params['asp_payment_term'] = sprintf("0%02d0000", $config->getAspPaymentTerm());

        return $params;
    }

    /**
     * 受注テーブル更新処理
     * @param array $inputOrder 受注情報
     * @param array $arrRes 電文レスポンス情報
     */
    function saveOrder($inputOrder, $arrRes)
    {
        $order = $this->orderRepository->find($inputOrder->getId());

        $order->setPaygentCode($this->eccubeConfig['paygent_payment']['paygent_payment_code']);
        $order->setResponseResult($arrRes['resultStatus']);
        $order->setResponseCode($arrRes['responseCode']);
        $order->setResponseDetail($arrRes['responseDetail']);
        // クレジット決済でエラーかつレスポンスコードが2003になった場合(1G65等)、受注に決済IDが登録された状態になる
        // その後銀行ネットで消込済の差分通知を受信しても決済IDが異なり入金済みに変更できないのでnullを設定する
        $order->setPaygentPaymentId(null);
        $order->setPaygentPaymentMethod($this->eccubeConfig['paygent_payment']['paygent_bank']);

        $this->entityManager->persist($order);
        $this->entityManager->flush($order);
    }
}
