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

namespace Plugin\PaygentPayment\Service\Conveni;

use Eccube\Common\EccubeConfig;
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
class FrontConveniService extends PaygentBaseService {

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
     * @param ConfigRepository $configRepository
     * @param EntityManagerInterface $entityManager
     * @param CacheConfig $cacheConfig
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
    public function applyProcess($order, $formParams)
    {
        // 電文パラメータ設定
        $params = $this->makeParam($order, $formParams);

        // 電文送信
        $arrRes = $this->callApi($params);

        // 受注情報更新
        $this->saveOrder($order,$arrRes, $formParams);

        return $arrRes;
    }

    /**
     * 電文パラメータの設定
     * @param array $inputOrder 受注情報
     * @return array $params 電文パラメータ
     */
    function makeParam($inputOrder, $formParams)
    {
        // プラグイン設定情報の取得
        $config = $this->cacheConfig->getConfig();

        /** 共通電文パラメータ **/
        $params = $this->commonMakeParam($inputOrder->getId(),"");

        /** 個別電文パラメータ **/
        // 電文種別ID
        $params['telegram_kind'] = $this->eccubeConfig['paygent_payment']['paygent_conveni_num'];
        // 決済金額
        $params['payment_amount'] = (int)$inputOrder->getPaymentTotal();
        // 利用者姓
        $params['customer_family_name'] = mb_convert_kana($inputOrder->getName01(), 'KVA');
        // 利用者名
        $params['customer_name'] = mb_convert_kana($inputOrder->getName02(), 'KVA');
        // 利用者姓半角カナ
        $params['customer_family_name_kana'] = mb_convert_kana($inputOrder->getKana01(),'k');
        $params['customer_family_name_kana'] = preg_replace("/ﾞ|ﾟ/", "", $params['customer_family_name_kana']);
        // 利用者名半角カナ
        $params['customer_name_kana'] = mb_convert_kana($inputOrder->getKana02(),'k');
        $params['customer_name_kana'] = preg_replace("/ﾞ|ﾟ/", "", $params['customer_name_kana']);
        // 利用者電話番号
        $params['customer_tel'] = mb_convert_kana($inputOrder->getPhoneNumber(), 'n');
        // 支払期限日
        $params['payment_limit_date'] = $config->getConveniLimitDateNum();
        // コンビニ企業コード
        $params['cvs_company_id'] = $formParams['cvs_company_id'];
        // 支払い種別
        $params['sales_type'] = $this->eccubeConfig['paygent_payment']['sales_type'];

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
        if (isset($arrRes['payment_id'])) {
            $order->setPaygentPaymentId($arrRes['payment_id']);
        }
        $order->setPaygentPaymentMethod($this->eccubeConfig['paygent_payment']['paygent_conveni_num']);

        $this->entityManager->persist($order);
        $this->entityManager->flush($order);
    }

    /**
     * 注文完了画面 メッセージの組立
     * @param array $arrRes 電文レスポンス情報
     * @return string $message 注文完了メッセージ
     */
    function makeCompleteMessage($arrRes, $arrConfirmMessage)
    {
        // 注文完了画面 メッセージ組立
        $message  = '<span style="color:red">' . $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['title'] . '</span><br>';
        $message .= $arrConfirmMessage['receipt_num_name'] . '：' . $arrRes['receipt_number'] . '<br>';
        if ($arrConfirmMessage['confirm_memo'] !== '') {
            $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['confirm_memo'] . '：' . $arrConfirmMessage['confirm_memo'] . '<br>';
            $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['confirm_name'] . '：' . $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['confirm_number'] . '<br>';
        }
        if (array_key_exists('phone_num', $arrConfirmMessage)) {
            $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['customer_tel'] . '：' . $arrConfirmMessage['phone_num'] . '<br>';
        }
        if ($arrRes['usable_cvs_company_id'] === $this->eccubeConfig['paygent_payment']['cvs_company_id']['seven-eleven']) {
            $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['receipt_print_url'] . '：' . '<a href=' . $arrRes['receipt_print_url'] . ' target="_blank">' . $arrRes['receipt_print_url'] . '</a><br>';
        }
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['usable_cvs_company_id'] . '：' . $this->makeUsableCvsCompanyName($arrRes['usable_cvs_company_id']) . '　' . '<br>';
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['payment_limit_date'] . '：' . date("Y年m月d日", strtotime($arrRes['payment_limit_date'])) . '<br><br>';
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['payment_help_urlname'] . '<br>';
        $message .= '<a href=' . $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['payment_help_url']  . ' target="_blank">' . $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['payment_help_url'] . '</a><br><br><br>';

        return $message;
    }

    /**
     * 注文完了メール メッセージの組立
     * @param array $arrRes 電文レスポンス情報
     * @return string $message 注文完了メッセージ
     */
    function makeCompleteMailMessage($arrRes, $arrConfirmMessage)
    {
        // 注文完了画面 メッセージ組立
        $message  = $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['title'] . ' ' . PHP_EOL;
        $message .= $arrConfirmMessage['receipt_num_name'] . '：' . $arrRes['receipt_number'] . PHP_EOL;
        if ($arrConfirmMessage['confirm_memo'] !== '') {
            $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['confirm_memo'] . '：' . $arrConfirmMessage['confirm_memo'] . ' ' . PHP_EOL;
            $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['confirm_name'] . '：' . $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['confirm_number'] . ' ' . PHP_EOL;
        }
        if (array_key_exists('phone_num', $arrConfirmMessage)) {
            $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['customer_tel'] . '：' . $arrConfirmMessage['phone_num'] . ' ' . PHP_EOL;
        }
        if ($arrRes['usable_cvs_company_id'] === $this->eccubeConfig['paygent_payment']['cvs_company_id']['seven-eleven']) {
            $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['receipt_print_url'] . '：' . $arrRes['receipt_print_url'] . ' ' .PHP_EOL;
        }
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['usable_cvs_company_id'] . '：' . $this->makeUsableCvsCompanyName($arrRes['usable_cvs_company_id']) . '　' . PHP_EOL;
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['payment_limit_date'] . '：' . date("Y年m月d日", strtotime($arrRes['payment_limit_date'])) . ' ' . PHP_EOL;
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['payment_help_urlname'] . ' ' . PHP_EOL;
        $message .= $this->eccubeConfig['paygent_payment']['complete_message']['conveni']['payment_help_url']  . ' ' . PHP_EOL;

        return $message;
    }

    /**
     * 支払い可能コンビニメッセージ作成
     */
    public function makeUsableCvsCompanyName($cvsCompanyId)
    {
        $usableCvsCompanyName = '';
        $arrCvsCompanyId = explode('-', $cvsCompanyId);
        foreach ($arrCvsCompanyId as $cvsCompanyId) {
            $usableCvsCompanyKey = array_search($cvsCompanyId, $this->eccubeConfig['paygent_payment']['cvs_company_id']);
            $usableCvsCompanyName .= $this->eccubeConfig['paygent_payment']['cvs_company_name'][$usableCvsCompanyKey];
            if ($cvsCompanyId !== end($arrCvsCompanyId)) {
                $usableCvsCompanyName .= '、';
            }
        }
        return $usableCvsCompanyName;
    }

    /**
     * 選択されたコンビニ毎の処理
     */
    public function cvsConfirm($cvsCompanyId, $usableCvsCompanyId, $phoneNumber) {
        $arrUsableCvsCompanyId = explode('-',$usableCvsCompanyId);
        $receiptNumName = '';
        $confirmMemo = '';
        $arrAllCvsCompanyName = $this->eccubeConfig['paygent_payment']['cvs_company_name'];
        $arrAllCvsCompanyId = $this->eccubeConfig['paygent_payment']['cvs_company_id'];
        switch ($cvsCompanyId) {
            // セブンイレブン
            case $arrAllCvsCompanyId['seven-eleven']:
                $receiptNumName = "払込票番号";
                $confirmMemo = "";
    		    break;

            // デイリーヤマザキ
            case $arrAllCvsCompanyId['daily-yamazaki']:
                $receiptNumName = "ケータイ／オンライン決済番号";
                $confirmMemo = $arrAllCvsCompanyName['law-son'] . "、" . $arrAllCvsCompanyName['mini-stop'];
                if (in_array($arrAllCvsCompanyId['family-mart'], $arrUsableCvsCompanyId)) {
                    $confirmMemo .= "、" . $arrAllCvsCompanyName['family-mart'];
                }
                $confirmMemo .= "でのお支払いには下記の確認番号も必要となります";
                break;

            // ローソン、ミニストップ
            case $arrAllCvsCompanyId['law-son']:
            case $arrAllCvsCompanyId['mini-stop']:
                if (in_array($arrAllCvsCompanyId['seiko-mart'], $arrUsableCvsCompanyId)) {
                    $receiptNumName = "受付番号";
                } else {
                    $receiptNumName = "お客様番号";
                    $confirmMemo = $arrAllCvsCompanyName['law-son'] . "、" . $arrAllCvsCompanyName['mini-stop'];
                    if (in_array($arrAllCvsCompanyId['family-mart'], $arrUsableCvsCompanyId)) {
                        $confirmMemo .= "、" . $arrAllCvsCompanyName['family-mart'];
                    }
                    $confirmMemo .= "でのお支払いには下記の確認番号も必要となります";
                }
                break;

            // ファミリーマート
            case $arrAllCvsCompanyId['family-mart']:
                if (in_array($arrAllCvsCompanyId['law-son'], $arrUsableCvsCompanyId)) {
                    $receiptNumName = "お客様番号";
                    $confirmMemo = $arrAllCvsCompanyName['law-son'] . "、" . $arrAllCvsCompanyName['mini-stop'] . "、" . $arrAllCvsCompanyName['family-mart'] . "でのお支払いには下記の確認番号も必要となります";
                } else {
                    // コンビニ接続タイプA の場合
                    $receiptNumName = "収納番号";
                    $confirmMemo = "";
                }
                break;

            // セイコーマート
            case $arrAllCvsCompanyId['seiko-mart']:
                $receiptNumName = "お客様の受付番号";
                $confirmMemo = "";
                break;
        }

        $arrConfirmMessage['receipt_num_name'] = $receiptNumName;
        $arrConfirmMessage['confirm_memo'] = $confirmMemo;

        // 電話番号
        if (in_array($arrAllCvsCompanyId['seiko-mart'], $arrUsableCvsCompanyId)) {
            // イーコンの場合は電話番号を表示
            $arrConfirmMessage['phone_num'] = $phoneNumber;
        }

        return $arrConfirmMessage;
    }
}
