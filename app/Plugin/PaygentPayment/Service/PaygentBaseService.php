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
use Eccube\Common\Constant;
use Eccube\Entity\Order;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system\PaygentB2BModule;
use Eccube\Repository\PluginRepository;
use Eccube\Common\EccubeConfig;
use Eccube\Repository\BaseInfoRepository;

/**
 * Paygent KS-システムとの連携・EC-CUBEの決済レコード作成を行う親クラス
 */
class PaygentBaseService {

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
     * @var BaseInfoRepository
     */
    protected $BaseInfo;

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var \Swift_Mailer
     */
    protected $mailer;

    /**
     * コンストラクタ
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param EntityManagerInterface $entityManager
     * @param ConfigRepository $configRepository
     * @param CacheConfig $cacheConfig
     * @param PluginRepository $pluginRepository
     * @param EccubeConfig $eccubeConfig
     * @param BaseInfoRepository $baseInfoRepository
     * @param \Twig_Environment $twig
     * @param \Swift_Mailer $mailer
     */
    public function __construct(
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        EntityManagerInterface $entityManager,
        ConfigRepository $configRepository,
        CacheConfig $cacheConfig,
        PluginRepository $pluginRepository,
        EccubeConfig $eccubeConfig,
        BaseInfoRepository $baseInfoRepository,
        \Twig_Environment $twig,
        \Swift_Mailer $mailer
        ) {
            $this->orderRepository = $orderRepository;
            $this->orderStatusRepository = $orderStatusRepository;
            $this->configRepository = $configRepository;
            $this->entityManager = $entityManager;
            $this->cacheConfig = $cacheConfig;
            $this->pluginRepository = $pluginRepository;
            $this->eccubeConfig = $eccubeConfig;
            $this->BaseInfo = $baseInfoRepository->get();
            $this->twig = $twig;
            $this->mailer = $mailer;
    }

    /**
     * 共通電文パラメータ作成
     * @param string $orderId 注文ID
     * @param string $paymentId 決済ID
     * @return string
     */
    function commonMakeParam($orderId,$paymentId) {
        // プラグイン設定情報の取得
        $config = $this->configRepository->get();

        // プラグイン設定画面での初回設定時以外
        $params = [];
        if ($config) {
            // マーチャントID
            $params['merchant_id'] = $config->getMerchantId();
            // 接続ID
            $params['connect_id'] = $config->getConnectId();
            // 接続パスワード
            $params['connect_password'] = $config->getConnectPassword();
        }
        // マーチャント取引ID
        $params['trading_id'] = $orderId;
        // 決済ID
        if ($paymentId) $params['payment_id'] = $paymentId;
        // 電文バージョン
        $params['telegram_version'] = $this->eccubeConfig['paygent_payment']['telegram_version'];
        // EC-CUBEからの電文であることを示す。
        $params['partner'] = 'lockon';
        // EC-CUBE本体のバージョン
        $params['eccube_version'] = Constant::VERSION;
        // 決済プラグインのバージョン
        $plugin = $this->pluginRepository->findOneBy(["code" => $this->eccubeConfig['paygent_payment']['paygent_payment_code']]);
        $params['eccube_plugin_version'] = $plugin->getVersion();

        return $params;
    }

    /**
     * 電文呼出ファンクション
     * @param  array $params 電文パラメータ配列
     * @return array $arrRes[0] レスポンス結果
     */
    function callApi($params) {
        // 接続モジュールのインスタンス取得 (コンストラクタ)と初期化
        $objPaygent = new PaygentB2BModule($this->eccubeConfig);
        $objPaygent->init();

        if (in_array($params['telegram_kind'], $this->isUseUtf8())) {
            $objPaygent->setEncoding($this->eccubeConfig['paygent_payment']['char_code']);
        }

        // 電文の送付
        foreach($params as $key => $val) {
            if (!in_array($params['telegram_kind'], $this->isUseUtf8())) {
                $val = mb_convert_encoding($val, $this->eccubeConfig['paygent_payment']['char_code_ks'], $this->eccubeConfig['paygent_payment']['char_code']);
            }
            $objPaygent->reqPut($key, $val);
            log_info("$key => $val");
        }

        $objPaygent->post();

        // レスポンスの取得
        while($objPaygent->hasResNext()) {
            # データが存在する限り、取得
            $arrRes = null;
            $arrRes[] = $objPaygent->resNext(); # 要求結果取得
        }

        // 処理結果 0=正常終了, 1=異常終了
        $resultStatus = $objPaygent->getResultStatus();

        // 異常終了時、レスポンスコードが取得できる
        $responseCode = $objPaygent->getResponseCode();

        // 異常終了時、レスポンス詳細が取得できる
        $responseDetail = $objPaygent->getResponseDetail();
        if (!in_array($params['telegram_kind'], $this->isUseUtf8())) {
            $responseDetail = mb_convert_encoding($objPaygent->getResponseDetail(), $this->eccubeConfig['paygent_payment']['char_code'], $this->eccubeConfig['paygent_payment']['char_code_ks']);
        }
        // 画面表示用の値をセット
        if ($responseCode) {
            if (preg_match('/^[P|E]/', $responseCode)) {
                $response = "（". $responseCode. "）";
            } else {
                $response = $responseDetail. "（". $responseCode. "）";
            }
        } else {
            $response = "";
        }

        // 取得した値をログに保存する。
        if($resultStatus === $this->eccubeConfig['paygent_payment']['result_error']) {
            $arrResOther = [];
            $arrResOther['result'] = $resultStatus;
            $arrResOther['code'] = $responseCode;
            $arrResOther['detail'] = $responseDetail;
            foreach($arrResOther as $key => $val) {
                log_info($key."->".$val);
            }
        }

        $arrRes[0]['resultStatus'] = $resultStatus;
        $arrRes[0]['responseCode'] = $responseCode;
        $arrRes[0]['responseDetail'] = $responseDetail;
        $arrRes[0]['response'] = $response;

        return $arrRes[0];
    }

    /**
     * 受注.対応状況の更新
     *
     * 必ず呼び出し元でトランザクションブロックを開いておくこと。
     *
     * @param  integer      $orderId     注文番号
     * @param  integer|null $newStatus   対応状況 (null=変更無し)
     * @return void
     */
    public function updateOrderPayment($orderId, $arrParams)
    {
        $order = $this->orderRepository->findBy(['id' => $orderId]);
        $arrOrder = $order[0];
        // If no such data exists, create a new one
        if (is_null($arrOrder)) {
            $arrOrder = new Order();
            $arrOrder->setUpdateDate('CURRENT_TIMESTAMP');
            $arrOrder->setCreateDate('CURRENT_TIMESTAMP');
        }

        if (isset($arrParams['paygent_error'])) {
            $arrOrder->setPaygentError($arrParams['paygent_error']);
        }
        if (isset($arrParams['paygent_payment_id'])) {
            $arrOrder->setPaygentPaymentId($arrParams['paygent_payment_id']);
        }
        // $arrParams配列に paygent_kindが未定義ではない場合
        if(array_key_exists('paygent_kind', $arrParams)){
            $arrOrder->setPaygentKind($arrParams['paygent_kind']);
        }

        $this->entityManager->persist($arrOrder);
        $this->entityManager->flush($arrOrder);
    }

    /**
     * 関数名：sfSetConvMSG
     * 処理内容：コンビニ情報表示用
     * 戻り値：取得結果
     */
    public function sfSetConvMSG($name, $value){
        return ["name" => $name, "value" => $value];
    }

    /**
     * ヘッダー情報の設定
     */
    public function setDefaultHeader($response)
    {
        $response->headers->set('Cache-control', 'no-cache, no-store, post-check=0, pre-check=0');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }

    /**
     * 受注編集画面内での決済名の取得
     *
     * @return array 決済名
     */
    public function getPaymentMethodName($methodId)
    {
        $methodName = null;

        foreach ($this->eccubeConfig['paygent_payment']['admin_target_payment_method_names'] as $paymentMethodId => $paymentMethodName) {
            if ($methodId == $this->eccubeConfig['paygent_payment'][$paymentMethodId]) {
                $methodName = $paymentMethodName;
            }
        }

        return $methodName;
    }

    /**
     * 受注編集画面内での決済ステータス名の取得
     *
     * @return array 決済ステータス名
     */
    public function getPaymentStatusName($statusId)
    {
        $statusName = null;

        foreach ($this->eccubeConfig['paygent_payment']['admin_payment_status_names'] as $paymentStatusId => $paymentStatusName) {
            if ($statusId == $this->eccubeConfig['paygent_payment'][$paymentStatusId]) {
                $statusName = $paymentStatusName;
            }
        }

        return $statusName;
    }

    /**
     * 受注編集画面内で使用するpaygent_kindの値を配列化して取得
     */
    public function getPaygentKindForEdit(){
        return [
            'paygent_credit' => $this->eccubeConfig['paygent_payment']['paygent_credit'],
            'paygent_card_commit' => $this->eccubeConfig['paygent_payment']['paygent_card_commit'],
            'paygent_card_commit_revice' => $this->eccubeConfig['paygent_payment']['paygent_card_commit_revice'],
            'paygent_career_commit' => $this->eccubeConfig['paygent_payment']['paygent_career_commit'],
            'paygent_career_commit_revice' => $this->eccubeConfig['paygent_payment']['paygent_career_commit_revice'],
            'paygent_paidy_authorized' => $this->eccubeConfig['paygent_payment']['paygent_paidy_authorized'],
            'paygent_paidy_commit' => $this->eccubeConfig['paygent_payment']['paygent_paidy_commit']
        ];
    }

    /**
     * 受注編集画面内で使用するpaygent_statusの値を配列化して取得
     */
    public function getPaygentStatusForEdit(){
        return [
            'status_authority_ok' => $this->eccubeConfig['paygent_payment']['status_authority_ok'],
            'status_authority_completed' => $this->eccubeConfig['paygent_payment']['status_authority_completed'],
            'status_authority_expired' => $this->eccubeConfig['paygent_payment']['status_authority_expired'],
            'status_pre_cleared' => $this->eccubeConfig['paygent_payment']['status_pre_cleared'],
            'status_pre_cleared_expiration_cancellation_sales' => $this->eccubeConfig['paygent_payment']['status_pre_cleared_expiration_cancellation_sales'],
            'status_complete_cleared' => $this->eccubeConfig['paygent_payment']['status_complete_cleared'],
        ];
    }

    /**
     * 管理者アドレスにステータス不整合メールを送信する
     */
    public function sendStatusAlertMailToAdmin($arrParam) {
        $config = $this->cacheConfig->getConfig();

        $body = $this->twig->render("@PaygentPayment/admin/status_alert_mail.twig", [
            'BaseInfo' => $this->BaseInfo,
            'merchant_id' => $config->getMerchantId(),
            'payment_id' => $arrParam['payment_id'],
            'order_id' => $arrParam['trading_id'],
        ]);
        $message = (new \Swift_Message())
            ->setSubject('['.$this->BaseInfo->getShopName().'] ' . $this->eccubeConfig['paygent_payment']['status_alert_mail_title'])
            ->setFrom($this->eccubeConfig['paygent_payment']['paygent_support_mail_address'])
            ->setTo($this->BaseInfo->getEmail01())
            ->setBcc($this->BaseInfo->getEmail01())
            ->setReplyTo($this->BaseInfo->getEmail03())
            ->setReturnPath($this->BaseInfo->getEmail04())
            ->setBody($body, 'text/plain');

        $this->mailer->send($message);

        return $message;
    }

    /**
     * 決済情報照会
     * @param array $arrParam
     * @return array $arreRes
     */
    public function getSettlementDetail($arrParam) {
        // 電文パラメータ設定
        $params = $this->commonMakeParam($arrParam['trading_id'], $arrParam['payment_id']);
        // 電文種別ID
        $params['telegram_kind'] = $this->eccubeConfig['paygent_payment']['paygent_settlement_detail'];
        // 電文送信
        $arrRes = $this->callApi($params);
        return $arrRes;
    }

    /**
     * エラーメッセージを取得
     * @param PaygentB2BModule $objPaygent
     * @param string $charCode
     * @return array $arrErrorMessage
     */
    public function getArrErrorMessage($objPaygent, $charCode) {
        $responseCode = $objPaygent->getResponseCode(); # 異常終了時、レスポンスコードが取得できる
        $responseDetail = $objPaygent->getResponseDetail(); # 異常終了時、レスポンス詳細が取得できる
        $responseDetail = mb_convert_encoding($responseDetail, $charCode, "Shift-JIS");

        $arrErrorMessage['paygent_error'] = "エラー詳細 : ".$responseDetail . "エラーコード" . $responseCode;
        if (preg_match('/^[P|E]/', $responseCode) <= 0) {
            $arrErrorMessage['response'] = $responseDetail. "（". $responseCode. "）";
        } elseif (strlen($responseCode) > 0) {
            $arrErrorMessage['response'] = "（". $responseCode. "）";
        } else {
            $arrErrorMessage['response'] = "";
        }

        return $arrErrorMessage;
    }

    /**
     * 変換対象外電文種別
     *
     * @return array
     */
    private function isUseUtf8() {
        return [
            $this->eccubeConfig['paygent_payment']['payment_card_3d2']        // 3Dセキュア2.0認証電文
        ];
    }
}
