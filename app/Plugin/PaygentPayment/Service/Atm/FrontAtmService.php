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

namespace Plugin\PaygentPayment\Service\Atm;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\PaygentPayment\Service\CacheConfig;
use Plugin\PaygentPayment\Service\PaygentBaseService;

/**
 * Paygent KS-システムとの連携・EC-CUBEの決済レコード作成を行う子クラス
 */
class FrontAtmService extends PaygentBaseService {

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

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
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param EntityManagerInterface $entityManager
     * @param ConfigRepository $configRepository
     * @param PluginRepository $pluginRepository
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        ConfigRepository $configRepository,
        EntityManagerInterface $entityManager,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->configRepository = $configRepository;
        $this->entityManager = $entityManager;
        $this->cacheConfig = $cacheConfig;
        $this->pluginRepository = $pluginRepository;
        $this->eccubeConfig = $eccubeConfig;
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
        $this->saveOrder($order,$arrRes);

        return $arrRes;
    }

    /**
     * 電文パラメータの設定
     * @param array $inputOrder 受注情報
     * @return array $params 電文パラメータ
     */
    function makeParam($inputOrder) {
        // プラグイン設定情報の取得
        $config = $this->cacheConfig->getConfig();

        /** 共通電文パラメータ **/
        $params = $this->commonMakeParam($inputOrder->getId(),"");

        /** 個別電文パラメータ **/
        // 電文種別ID
        $params['telegram_kind'] = $this->eccubeConfig['paygent_payment']['paygent_atm'];
        // 決済金額
        $params['payment_amount'] = (int)$inputOrder->getPaymentTotal();
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
        // 店舗名カナ
        $params['payment_detail'] = $config->getPaymentDetail();
        // 店舗名半角カナ
        $params['payment_detail_kana'] = mb_convert_kana($config->getPaymentDetail(),'k');
        $params['payment_detail_kana'] = preg_replace("/ｰ/", "-", $params['payment_detail_kana']);
        // 支払期限日
        $params['payment_limit_date'] = $config->getAtmLimitDate();

        return $params;
    }

    /**
     * 受注テーブル更新処理
     * @param array $inputOrder 受注情報
     * @param array $arrRes 電文レスポンス情報
     */
    function saveOrder($inputOrder,$arrRes)
    {
        $order = $this->orderRepository->find($inputOrder->getId());

        $order->setPaygentCode($this->eccubeConfig['paygent_payment']['paygent_payment_code']);
        $order->setResponseResult($arrRes['resultStatus']);
        $order->setResponseCode($arrRes['responseCode']);
        $order->setResponseDetail($arrRes['responseDetail']);
        if (isset($arrRes['payment_id'])) {
            $order->setPaygentPaymentId($arrRes['payment_id']);
        }
        $order->setPaygentPaymentMethod($this->eccubeConfig['paygent_payment']['paygent_atm']);

        $this->entityManager->flush($order);
    }

    /**
     * 注文完了画面 メッセージの組立
     * @param array $arrRes 電文レスポンス情報
     * @return string $message 注文完了メッセージ
     */
    function makeCompleteMessage($arrRes) {
        // 注文完了画面 メッセージ組立
        $message  = '<span style="color:red">' . $this->eccubeConfig['paygent_payment']['complete_message']['atm']['title'] . '</span><br>';
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['atm']['pay_center_number'] . '：' . $arrRes['pay_center_number'] . '<br>';
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['atm']['customer_number'] . '：' . $arrRes['customer_number'] . '<br>';
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['atm']['conf_number'] . '：' . $arrRes['conf_number'] . '<br>';
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['atm']['payment_limit_date'] . '：' . date("Y年m月d日", strtotime($arrRes['payment_limit_date'])).'<br><br>';
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['atm']['payment_help_urlname'] . '<br>';
        $message .= '<a href=' . $this->eccubeConfig['paygent_payment']['complete_message']['atm']['payment_help_url']  . ' target="_blank">' . $this->eccubeConfig['paygent_payment']['complete_message']['atm']['payment_help_url'] . '</a><br><br><br>';

        return $message;
    }

    /**
     * 注文完了メール メッセージの組立
     * @param array $arrRes 電文レスポンス情報
     * @return string $message 注文完了メッセージ
     */
    function makeCompleteMailMessage($arrRes) {
        // 注文完了メール メッセージ組立
        // EC-CUBE本体のバージョン4.0.0は、Order::setCompleteMailMessageで、改行が反映されない問題がある為、改行前に全角スペースを入れている。
        $message  = $this->eccubeConfig['paygent_payment']['complete_message']['atm']['title'].'　'.PHP_EOL;
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['atm']['pay_center_number'] . '：' . $arrRes['pay_center_number'].'　'.PHP_EOL;
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['atm']['customer_number'] . '：' . $arrRes['customer_number'].'　'.PHP_EOL;
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['atm']['conf_number'] . '：' . $arrRes['conf_number'].'　'.PHP_EOL;
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['atm']['payment_limit_date'] . '：' . date("Y年m月d日", strtotime($arrRes['payment_limit_date'])).'　'.PHP_EOL.PHP_EOL;
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['atm']['payment_help_urlname'].'　'.PHP_EOL;
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['atm']['payment_help_url'].'　'.PHP_EOL;

        return $message;
    }

    /**
     * ペイジェントステータスの更新
     * @param array $arrRet ペイジェントから送られてくるリクエストパラメータ
     */
    public function updatePaygentOrder($arrRet){
        // 受注ステータスの初期化
        $arrVal = [];
        $arrVal['status'] = null;
        // 決済日時の初期化
        $arrVal['payment_date'] = null;

        // paygent_payment_status = 決済ステータス
        $arrVal['paygent_payment_status'] = $arrRet['payment_status'];
        // payment_notice_id = 決済通知ID
        $arrVal['payment_notice_id'] = $arrRet['payment_notice_id'];

        // ステータスを設定
        switch ($arrRet['payment_status']) {

            case $this->eccubeConfig['paygent_payment']['status_pre_cleared']: // "40"：消込済

                // 受注状態 = "6"：入金済み
                $arrVal['status'] = OrderStatus::PAID;

                // 入金日時 = 応答情報.支払日時
                if ($arrRet['payment_date'] != "") {
                    $arrVal['payment_date'] = $arrRet['payment_date'];
                }

                break;

            case $this->eccubeConfig['paygent_payment']['status_payment_expired']: // "12"：支払期限切

                // 受注状態 = "3"：キャンセル
                $arrVal['status'] = OrderStatus::CANCEL;
                break;
        }

        // 受注情報（dtb_order）の更新
        $this->updateOrderStatus($arrRet, $arrVal);
    }

    /**
     * 受注.対応状況の更新
     * @param  array $arrRet
     * @param  array $arrParams
     */
    public function updateOrderStatus($arrRet, $arrParams)
    {
        if (is_null($arrRet['payment_id'])) {
            $order = $this->orderRepository->findOneBy(['id' => $arrRet['trading_id']]);
        } else {
            $order = $this->orderRepository->findOneBy(['id' => $arrRet['trading_id'], 'paygent_payment_id' => $arrRet['payment_id']]);
        }

        if ($order) {
            // dtb_orderの更新
            if (isset($arrParams['status'])) {
                $orderStatus = $this->orderStatusRepository->find($arrParams['status']);
                $order->setOrderStatus($orderStatus);
            }
            if (isset($arrParams['payment_date'])) {
                $order->setPaymentDate(\DateTime::createFromFormat('YmdHis', $arrParams['payment_date']));
            }
            if (isset($arrParams['paygent_payment_id'])) {
                $order->setPaygentPaymentId($arrParams['paygent_payment_id']);
            }
            $order->setPaygentPaymentStatus($arrParams['paygent_payment_status']);
            $order->setPaymentNoticeId($arrParams['payment_notice_id']);
            $this->entityManager->persist($order);
            $this->entityManager->flush();
        }
    }

    /**
     * 差分通知 ステータスチェック
     * @param string paymentStatus ペイジェントから送られてくるステータスパラメータ
     * @return 正常:True 異常:False
     */
    function isValidStatus($paymentStatus){
        // 差分通知対象のステータス
        $arrIsValidStatus = [
            $this->eccubeConfig['paygent_payment']['status_pre_registration'],
            $this->eccubeConfig['paygent_payment']['status_payment_expired'],
            $this->eccubeConfig['paygent_payment']['status_pre_cleared']
        ];

        if(in_array($paymentStatus, $arrIsValidStatus)) {
            return true;
        } else {
            return false;
        }
    }

}
