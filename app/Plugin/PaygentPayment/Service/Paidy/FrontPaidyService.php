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

namespace Plugin\PaygentPayment\Service\Paidy;

use Eccube\Entity\Master\OrderItemType;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Plugin\PaygentPayment\Repository\PaidyOrderRepository;
use Plugin\PaygentPayment\Service\PaygentBaseService;

class FrontPaidyService extends PaygentBaseService
{
    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var PaidyOrderRepository
     */
    protected $paidyOrderRepository;

    /**
     * コンストラクタ
     * @param PaidyOrderRepository $paidyOrderRepository
     */
    public function __construct(
        ConfigRepository $configRepository,
        PaidyOrderRepository $paidyOrderRepository
    ) {
        $this->configRepository = $configRepository;
        $this->paidyOrderRepository = $paidyOrderRepository;
    }

    /**
     * PaidyCheckout パラメータ作成
     * @param $arrConfig
     * @param $order
     */
    public function buildPaidyCheckout($order){
        // プラグイン設定情報の取得
        $config = $this->configRepository->get();

        // Paidy注文データ
        $arrPaidy = [];
        $arrPaidy['amount'] = (int)$order->getPaymentTotal();
        $arrPaidy['currency'] = 'JPY';
        $arrPaidy['store_name'] = $config->getPaidyStoreName();
        $arrPaidy['buyer'] = $this->convertBuyer($order);
        $arrPaidy['buyer_data'] = $this->convertBuyerData($order);
        $arrPaidy['order'] = $this->convertOrder($order);
        $arrPaidy['shipping_address'] = $this->convertShippingAddress($order);

        return json_encode($arrPaidy);
    }

    /**
     * 購入者_顧客情報を取得する。
     * @param $order
     * @return $arrPaidy
     */
    public function convertBuyer($order)
    {
        $arrPaidy = [];
        $arrPaidy['email'] = $order->getEmail();
        $arrPaidy['name1'] = $order->getName01() . ' ' . $order->getName02();
        $arrPaidy['name2'] = $order->getKana01() . ' ' . $order->getKana02();
        $arrPaidy['phone'] = $order->getPhoneNumber();
        if ($order->getBirth()) {
            $arrPaidy['dob'] = date_format($order->getBirth(), 'Y-m-d');
        } else {
            $arrPaidy['dob'] = "";
        }
        return $arrPaidy;
    }

    /**
     * 購入者_購入情報を取得する。
     * @param  $order
     * @return $arrPaidy
     */
    public function convertBuyerData($order)
    {
        $arrPaidy = [];

        $customer = $order->getCustomer();

        // 会員購入の場合
        if ($customer) {

            // アカウント作成経過日数
            $arrPaidy['age'] = $this->getDayDiff(date_format($customer->getCreateDate(), 'Y-m-d'));

            // Paidyを含まない注文総数
            $noPaidyOrder = $this->paidyOrderRepository->getNoPaidyOrderCountFromCustomer($customer);

            $arrPaidy['order_count'] = $noPaidyOrder[0]['orderCount'];

            if ($noPaidyOrder[0]['orderSumPaymentTotal']) {
                $arrPaidy['ltv'] = $noPaidyOrder[0]['orderSumPaymentTotal'];
            } else {
                $arrPaidy['ltv'] = 0;
            }

            // Paidyを除いた最後に注文した金額(円)
            $lastNoPaidyOrder = $this->paidyOrderRepository->getNoPaidyLastOrderPaymentTotalFromCustomer($customer);
            if (count($lastNoPaidyOrder) > 0) {
                $arrPaidy['last_order_at'] = $this->getDayDiff(date_format($lastNoPaidyOrder[0]['create_date'], 'Y-m-d'));
                $arrPaidy['last_order_amount'] = $lastNoPaidyOrder[0]['payment_total'];
            } else {
                $arrPaidy['last_order_amount'] = 0;
                $arrPaidy['last_order_at'] = 0;
            }

            // ゲスト購入の場合
        } else {
            $arrPaidy['age'] = 0;
            $arrPaidy['order_count'] = 0;
            $arrPaidy['ltv'] = 0;
            $arrPaidy['last_order_amount'] = 0;
            $arrPaidy['last_order_at'] = 0;
        }

        return $arrPaidy;
    }

    /**
     * 注文情報を取得する。
     * @param $order
     * @return $arrPaidy
     */
    public function convertOrder($order)
    {
        $arrPaidy = [];
        $arrPaidy['items'] = [];
        foreach ($order->getProductOrderItems() as $orderItem) {
            $arrItem = [];
            if ($orderItem->getProductCode() == null) {
                $arrItem['id'] = '';
            } else {
                $arrItem['id'] = $orderItem->getProductCode();
            }
            $arrItem['quantity'] = $orderItem->getQuantity();
            $arrItem['title'] = $orderItem->getProductName();
            $arrItem['unit_price'] = (int)$orderItem->getPrice();
            $arrItem['description'] = '';
            $arrPaidy['items'][] = $arrItem;
        }

        // 値引き
        if ($order->getDiscount() > 0) {
            $arrItem = [];
            $arrItem['id'] = '';
            $arrItem['quantity'] = 1;
            $arrItem['title'] = '値引き';
            $arrItem['unit_price'] = -1 * (int)$order->getDiscount();
            $arrItem['description'] = '';
            $arrPaidy['items'][] = $arrItem;
        }

        // 手数料
        if ($order->getCharge() > 0) {
            $arrItem = [];
            $arrItem['id'] = '';
            $arrItem['quantity'] = 1;
            $arrItem['title'] = '手数料';
            $arrItem['unit_price'] = (int)$order->getCharge();
            $arrItem['description'] = '';
            $arrPaidy['items'][] = $arrItem;
        }
        // $arrOrder['id']は、int型となるため、キャストしないとJSに出力される値がダブルクォートでくくられない。
        $arrPaidy['order_ref'] = (String)$order->getId();
        $arrPaidy['shipping'] = (int)$this->getExcludingTaxTotal($order, OrderItemType::DELIVERY_FEE);
        $arrPaidy['tax'] = (int)$order->getTax();
        return $arrPaidy;
    }

    /**
     * 配送先情報を取得する。
     * @param $order
     * @return $arrPaidy
     */
    public function convertShippingAddress($order)
    {
        $shipping = $order->getShippings()->first();

        $arrPaidy = [];
        $arrPaidy['line1'] = '';
        $arrPaidy['line2'] = $shipping->getAddr02();
        $arrPaidy['city'] = $shipping->getAddr01();
        $arrPaidy['state'] = $shipping->getPref() ? $shipping->getPref()->getName() : '';
        $arrPaidy['zip'] = $shipping->getPostalCode();

        return $arrPaidy;
    }

    /**
     * 指定のOrderItem種類の税を含まない合計金額を取得
     *
     * @param $order
     * @param $orderItemType
     * @return int
     */
    public function getExcludingTaxTotal($order, $orderItemType) {
        $orderItems = $order->getOrderItems();

        $excludingTaxTotal = 0;

        foreach ($orderItems as $orderItem) {
            if ($orderItem->getOrderItemTypeId() === $orderItemType) {
                $excludingTaxTotal += ($orderItem->getPriceIncTax() - $orderItem->getTax()) * $orderItem->getQuantity();
            }
        }

        return $excludingTaxTotal;
    }

    /**
     * getDayDiff
     * @param string $fromDate
     * @return システム日付からの日数差
     */
    private function getDayDiff($fromDate)
    {
        if (!$fromDate) {
            return 0;
        }

        $fromDateTime = strtotime($fromDate);
        $toDateTime = strtotime(date('Y-m-d'));

        $timeDiff = $toDateTime - $fromDateTime;

        if ($timeDiff <= 0) {
            return 0;
        } else {
            return (int) ceil($timeDiff / (60 * 60 * 24));
        }
    }

    /**
     * Paidyの注文完了画面用の文言を取得
     * @param $order
     * @return string
     */
    public function getCompleteMessage($order)
    {
        $message = ' <p class="ec-reportDescription">Paidyからの通知受付後に注文完了メールを送信致します。<br />';
        $message .= '<span style="color: red; ">注文完了メールの送信をもって注文確定となります。</span>';
        $message .= '<br /><br /><strong>ご注文番号: '. $order->getOrderNo() .'</strong></p>';

        return $message;
    }
}
