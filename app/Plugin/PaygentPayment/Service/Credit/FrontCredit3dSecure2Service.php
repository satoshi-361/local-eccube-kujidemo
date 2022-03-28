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

namespace Plugin\PaygentPayment\Service\Credit;

use Eccube\Common\EccubeConfig;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Repository\PaymentRepository;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Plugin\PaygentPayment\Repository\Credit3dSecure2OrderRepository;
use Plugin\PaygentPayment\Service\CacheConfig;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FrontCredit3dSecure2Service extends FrontCreditService
{
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

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
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * @var PaymentRepository
     */
    private $paymentRepository;

    /**
     * @var Credit3dSecure2OrderRepository
     */
    private $credit3dSecure2OrderRepository;

    /**
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param CustomerRepository $customerRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param EntityManagerInterface $entityManager
     * @param ConfigRepository $configRepository
     * @param PluginRepository $pluginRepository
     * @param CacheConfig $cacheConfig
     * @param EccubeConfig $eccubeConfig
     * @param UrlGeneratorInterface $router
     * @param PaymentRepository $paymentRepository
     * @param Credit3dSecure2OrderRepository $credit3dSecure2OrderRepository
     */
    public function __construct(
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        OrderStatusRepository $orderStatusRepository,
        ConfigRepository $configRepository,
        EntityManagerInterface $entityManager,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig,
        UrlGeneratorInterface $router,
        PaymentRepository $paymentRepository,
        Credit3dSecure2OrderRepository $credit3dSecure2OrderRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->configRepository = $configRepository;
        $this->entityManager = $entityManager;
        $this->cacheConfig = $cacheConfig;
        $this->pluginRepository = $pluginRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->router = $router;
        $this->paymentRepository = $paymentRepository;
        $this->credit3dSecure2OrderRepository = $credit3dSecure2OrderRepository;
    }

    /**
     * 3Dセキュア2.0認証申込み
     * @param Order $order          受注情報
     * @param array $inputParams    入力パラメータ(FormまたはGET)
     * @param string $telegramKind  電文種別
     * @return array $arrRes        レスポンス情報
     */
    public function send3dSecure2Auth($order, $inputParams, $telegramKind)
    {
        // 電文パラメータ設定
        $params = $this->make3dSecure2AuthParam($order, $inputParams, $telegramKind);

        // 電文送信
        $arrRes = $this->callApi($params);

        // 受注情報更新
        $this->saveOrder($order, $inputParams, $arrRes);

        return $arrRes;
    }

    /**
     * 3Dセキュア2.0認証後カード決済申込
     * @param Order $order          受注情報
     * @param array $inputParams    入力パラメータ(FormまたはGET)
     * @param string $credit3dsAuthId  3Dセキュア認証ID
     * @return array $arrRes        レスポンス情報
     */
    public function sendCreditAuth($order, $inputParams, $credit3dsAuthId)
    {
        // 電文パラメータ設定
        $params = $this->makeCreditAuthParam($order, $inputParams, $credit3dsAuthId);

        // 電文送信
        $arrRes = $this->callApi($params);
    
        // 受注情報更新
        $this->saveOrder($order, $inputParams, $arrRes);

        return $arrRes;
    }

    /**
     * 3Dセキュア2.0認証電文のパラメータを設定する
     *
     * @param Order $inputOrder
     * @param array $inputParams
     * @param string $telegramKind
     * @return array
     */
    private function make3dSecure2AuthParam($inputOrder, $inputParams, $telegramKind)
    {
        // プラグイン設定情報の取得
        $config = $this->cacheConfig->getConfig();
        /** 共通電文パラメータ **/
        $params = $this->commonMakeParam($inputOrder->getId(),"");

        $sysDateTime = new \DateTime();

        $params['telegram_kind'] = $telegramKind; // 電文種別ID

        $params['authentication_type'] = $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['authentication_type']; // 認証用途

        // 3-Dセキュア2.0戻りURL
        $sUrl = $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $params['term_url'] =  $sUrl . 'paygent_payment_credit_3d2';

        // ログイン方法
        $customer = $inputOrder->getCustomer();
        if ($customer) {
            $params['login_type'] = $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['login_user'];
        } else {
            $params['login_type'] = $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['login_guest'];
        }

        $params['merchant_name'] = $config->getCredit3dMerchantName(); // 加盟店名

        $params['account_indicator'] = $this->getAccountIndicator($params, $customer, $sysDateTime); // アカウントの保有時間

        // 会員に関する情報を設定する
        $params = array_merge($params, $this->setParamsForCustomer($params, $inputOrder, $customer, $sysDateTime));

        // 配送方法の取得
        $shippings = $inputOrder->getShippings();
        $firstShipping = $shippings->first();

        // 住所の確認
        if ($this->isMatchAddress($inputOrder, $firstShipping)) {
            $params['address_match'] = $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['address_match'];
        } else {
            $params['address_match'] = $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['address_not_match'];
        }

        $params['bill_address_city'] = $inputOrder->getPref()->getName(); // 請求先情報（都市）

        $params['bill_address_country'] = $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['country_code_jp']; // 請求先情報（国番号）

        $params['bill_address_line1'] = $inputOrder->getAddr01(); // 請求先情報（住所1）

        $params['bill_address_line2'] = $inputOrder->getAddr02(); // 請求先情報（住所2）

        $params['bill_address_post_code'] = $inputOrder->getPostalCode(); // 請求先情報（郵便番号）

        $params['bill_address_state'] = sprintf('%02d', $inputOrder->getPref()->getId()); // 請求先情報（州・都道府県）

        $params['email_address'] = $inputOrder->getEmail(); // メールアドレス

        $params['ship_address_city'] = $firstShipping->getPref()->getName(); // 配送先情報（都市）

        $params['ship_address_country'] = $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['country_code_jp']; // 配送先情報（国番号）

        $params['ship_address_line1'] = $firstShipping->getAddr01(); // 配送先情報（住所1）

        $params['ship_address_line2'] = $firstShipping->getAddr02(); // 配送先情報（住所2）

        $params['ship_address_post_code'] = $firstShipping->getPostalCode(); // 配送先情報（郵便番号）

        $params['ship_address_state'] = sprintf('%02d', $firstShipping->getPref()->getId()); // 配送先情報（州・都道府県）

        // 出荷方法
        if ($params['address_match'] === $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['address_match']) { // 住所の確認に一致する場合
            $params['ship_address_first_use_date'] = $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['ship_address_first_use_date_match'];
        } elseif ($params['address_match'] === $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['address_not_match']) { // 住所の確認に一致しない場合
            $params['ship_address_first_use_date'] = $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['ship_address_first_use_date_not_match'];
        }

        $params['payment_amount'] = (int)$inputOrder->getPaymentTotal(); // 決済金額

        // カード情報の指定方法に関する項目を設定
        $params = array_merge($params, $this->setParamsByCardSetMethod($inputParams, $inputOrder));

        return $params;
    }

    /**
     * 3Dセキュア2.0認証後のカード決済申込電文のパラメータを作成する
     *
     * @param Order $inputOrder
     * @param array $inputParams
     * @param string $credit3dsAuthId
     * @return array
     */
    private function makeCreditAuthParam($inputOrder, $inputParams, $credit3dsAuthId)
    {
        // プラグイン設定情報の取得
        $config = $this->cacheConfig->getConfig();
        /** 共通電文パラメータ **/
        $params = $this->commonMakeParam($inputOrder->getId(),"");

        // 電文種別ID
        $params['telegram_kind'] = $this->eccubeConfig['paygent_payment']['paygent_credit'];

        /** 個別電文パラメータ **/
        // 決済金額
        $params['payment_amount'] = (int)$inputOrder->getPaymentTotal();
        if (isset($inputParams['card_token'])) {
            // トークン
            $params['card_token'] = $inputParams['card_token'];
        }

        // セキュリティコード利用
        if ($config->getSecurityCode()) {
            // セキュリティーコード利用
            $params['security_code_use'] = $this->eccubeConfig['paygent_payment']['credit_auth_apply_input']['security_code_use_on'];
        }

        // 支払い区分
        $params['payment_class'] = $inputParams['payment_class'];
        if (isset($inputParams['split_count'])) {
            // 分割回数
            $params['split_count'] = $inputParams['split_count'];
        }

        // カード情報お預かり機能
        if ($config->getModuleStockCard() && $inputParams['stock']) {
            if ($config->getSecurityCode()) {
                // セキュリティーコードトークン利用
                $params['security_code_token'] = $this->eccubeConfig['paygent_payment']['credit_auth_apply_input']['security_code_token_on'];
            }

            // カード情報お預りモード
            $params['stock_card_mode'] = $this->eccubeConfig['paygent_payment']['credit_auth_apply_input']['stock_card_mode_on'];
            // 顧客ID
            $params['customer_id'] = $inputOrder->getCustomer()->getId();
            // 顧客カードID
            $params['customer_card_id'] = $inputParams['customer_card_id'];
        }

        // 3Dセキュア利用タイプ
        $params['3dsecure_use_type'] = $this->eccubeConfig['paygent_payment']['credit_auth_apply_input']['3dsecure_use_type_2'];
        // 3Dセキュア認証ID
        $params['3ds_auth_id'] = $credit3dsAuthId;

        return $params;
    }

    /**
     * アカウント保有時間区分を取得する
     *
     * @param array $params
     * @param Customer|null $customer
     * @param DateTime $sysDateTime
     * @return string
     */
    private function getAccountIndicator($params, $customer, $sysDateTime)
    {
        // 非会員の場合
        if ($params['login_type'] === $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['login_guest']) {
            return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_indicator_guest'];
        }

        // 会員の場合アカウント保有時間を計算する
        $customerCreateDate = $customer->getCreateDate();
        $customerCreateDateInterval = $sysDateTime->diff($customerCreateDate);
        $accountIndicator = $customerCreateDateInterval->format('%a');

        // 30日未満
        if ($accountIndicator < $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_indicator_30_day']) {
            return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_indicator_30_under'];
        // 30～60日
        } elseif ($accountIndicator <= $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_indicator_60_day']) {
            return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_indicator_60_under'];
        }

        return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_indicator_60_over'];
    }

    /**
     * アカウント保有時間経過区分を取得
     *
     * @param DateTime $sysDateTime
     * @param DateTime $customerUpdateDate
     * @return string
     */
    private function getAccountChangeIndicator($sysDateTime, $customerUpdateDate)
    {
        // アカウントの更新経過時間を計算
        $customerUpdateDateInterval = $sysDateTime->diff($customerUpdateDate);
        $accountChangeIndicator = $customerUpdateDateInterval->format('%a');

        // 30日未満
        if ($accountChangeIndicator < $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_indicator_30_day']) {
            return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_change_indicator_30_under'];
        // 30～60日
        } elseif ($accountChangeIndicator <= $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_indicator_60_day']) {
            return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_change_indicator_60_under'];
        }

        return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_change_indicator_60_over'];
    }

    /**
     * 配送先住所を初回利用してからの経過時間の区分を取得
     *
     * @param Order|null $shipAddressUseFirstDateOrder
     * @param DateTime $sysDateTime
     * @return string
     */
    private function getShipAddressUseIndicator($shipAddressUseFirstDateOrder, $sysDateTime)
    {
        // 初回配送の住所が存在しない場合、この取引が初回利用とみなす
        if (is_null($shipAddressUseFirstDateOrder)) {
            return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['ship_address_use_indicator_this_trade'];
        }

        $shipAddressFirstUseDateDiff = $sysDateTime->diff($shipAddressUseFirstDateOrder->getOrderDate())->format('%a');
        // 30日未満
        if ($shipAddressFirstUseDateDiff < $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_indicator_30_day']) {
            return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['ship_address_use_indicator_30_under'];
        //30～60日
        } elseif ($shipAddressFirstUseDateDiff <= $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['account_indicator_60_day']) {
            return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['ship_address_use_indicator_60_under'];
        }

        return  $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['ship_address_use_indicator_60_over'];
    }

    /**
     * 再注文区分を取得する
     *
     * @param Order $inputOrder
     * @param Customer $customer
     * @return string
     */
    private function getReorderIndicator($inputOrder, $customer)
    {
        // 再注文回数を取得
        $reorderCount = $this->credit3dSecure2OrderRepository->getOrderCountSameProduct($customer, $inputOrder);

        // 同じ商品を購入した受注が存在する場合再注文
        if ($reorderCount) {
            return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['reorder_indicator_reorder'];
        }

        return $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['reorder_indicator_first_order'];
    }

    /**
     * 配送先の初回利用日を取得
     *
     * @param Order|null $order
     * @param DateTime $nowDate
     * @return DateTime
     */
    private function getShipAddressUseDate($order, $nowDate)
    {
        // 配送先の初回利用が存在しない場合、この取引が初回利用とみなし現在時刻を設定する
        if (is_null($order)) {
            $shipAddressUseDateUtc = $nowDate;
        } else {
            $shipAddressUseDateUtc = $order->getOrderDate();
        }

        $shipAddressUseDateUtc->setTimeZone(new \DateTimeZone('UTC'));
        return $shipAddressUseDateUtc->format('Ymd');
    }

    /**
     * 会員に関する情報を設定する
     *
     * @param array $params
     * @param Order $inputOrder
     * @param Customer|null $customer
     * @param DateTime $sysDateTime
     * @return array
     */
    private function setParamsForCustomer($params, $inputOrder, $customer, $sysDateTime)
    {
        $returnParams = [];

        // 非会員の場合は以降を行わない
        if ($params['login_type'] !== $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['login_user']) {
            return $returnParams;
        }

        // 配送方法の取得
        $shippings = $inputOrder->getShippings();
        $firstShipping = $shippings->first();

        // アカウント更新日
        $customerUpdateDate = $this->credit3dSecure2OrderRepository->getCustomerLatestUpdateDate($customer);
        $customerChangeDate = $customerUpdateDate
            ->setTimeZone(new \DateTimeZone('UTC'))
            ->format('Ymd');
        $returnParams['account_change_date'] = $customerChangeDate;

        // アカウント更新後の経過時間
        $returnParams['account_change_indicator'] = $this->getAccountChangeIndicator($sysDateTime, $customerUpdateDate);

        // アカウント作成日
        $customerCreateDate = $customer->getCreateDate();
        $accountCreateDate = $customerCreateDate
            ->setTimeZone(new \DateTimeZone('UTC'))
            ->format('Ymd');
        $returnParams['account_create_date'] = $accountCreateDate;

        // 購入回数（全決済手段）
        $cloneSysDateTime = clone $sysDateTime;
        $sixMonthAgoDay = $cloneSysDateTime->modify('-'.$this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['half_year_ago'] .' days');
        $returnParams['purchase_count'] = $this->credit3dSecure2OrderRepository->getPurchaseCount($customer, $sixMonthAgoDay);

        // クレジットカード支払いを取得
        $payment = $this->paymentRepository->findOneBy(['method_class' => 'Plugin\\PaygentPayment\\Service\\Method\\Module\\Credit']);

        // 取引回数（過去24時間）
        $cloneSysDateTime = clone $sysDateTime;
        $oneDayAgo = $cloneSysDateTime->modify('-'.$this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['one_day_ago'] .'hours');
        $returnParams['activity_count_day'] = $this->credit3dSecure2OrderRepository->getActiveCount($customer, $payment, $oneDayAgo);
        // 取引回数（過去1年）
        $cloneSysDateTime = clone $sysDateTime;
        $oneYearAgo = $cloneSysDateTime->modify('-'.$this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['one_year_ago'] .'years');
        $returnParams['activity_count_year'] = $this->credit3dSecure2OrderRepository->getActiveCount($customer, $payment, $oneYearAgo);

        // 配送先住所の初回利用受注を取得
        $shipAddressUseFirstDateOrder = $this->credit3dSecure2OrderRepository->getShipAddressUseFirstDateOrder($customer, $firstShipping);

        // 配送先住所の初回利用日
        $returnParams['ship_address_use_date'] = $this->getShipAddressUseDate($shipAddressUseFirstDateOrder, clone $sysDateTime);

        // 配送先住所を初回利用してからの経過時間
        $returnParams['ship_address_use_indicator'] = $this->getShipAddressUseIndicator($shipAddressUseFirstDateOrder, $sysDateTime);

        // 再注文区分
        $returnParams['reorder_indicator'] = $this->getReorderIndicator($inputOrder, $customer);

        return $returnParams;
    }

    /**
     * カード情報の指定方法に関する項目を設定する
     *
     * @param array $inputParams
     * @param Order $inputOrder
     * @return array
     */
    private function setParamsByCardSetMethod($inputParams, $inputOrder)
    {
        $returnParams = [];

        // カード情報の指定方法
        if ($this->cacheConfig->getConfig()->getModuleStockCard() && $inputParams['stock']) {
            $returnParams['card_set_method'] = $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['card_set_method_customer'];

            // 顧客ID
            $returnParams['customer_id'] = $inputOrder->getCustomer()->getId();
            // 顧客カードID
            $returnParams['customer_card_id'] = $inputParams['customer_card_id'];
        } elseif (isset($inputParams['card_token'])) {
            $returnParams['card_set_method'] = $this->eccubeConfig['paygent_payment']['credit_3dSecure2_apply_input']['card_set_method_token'];

            // カード情報トークン
            $returnParams['card_token'] = $inputParams['card_token'];
        }

        return $returnParams;
    }

    /**
     * 注文者の住所と配送先の住所が一致するか判定
     * 一つでも一致しない項目がある場合false
     *
     * @param Order $order
     * @param Shipping $shipping
     * @return boolean
     */
    private function isMatchAddress($order, $shipping)
    {
        if ($order->getPref()->getId() !== $shipping->getPref()->getId()) {
            return false;
        }

        if ($order->getAddr01() !== $shipping->getAddr01()) {
            return false;
        }

        if ($order->getAddr02() !== $shipping->getAddr02()) {
            return false;
        }

        return true;
    }
}
