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

use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\OrderRepository;
use Eccube\Common\EccubeConfig;
use Eccube\Service\MailService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\BaseInfoRepository;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system\PaygentB2BModule;
use Plugin\PaygentPayment\Service\CacheConfig;
use Plugin\PaygentPayment\Service\PaygentBaseService;

/**
 * Paidy差分通知機能のクラス
 */
class PaidyService extends PaygentBaseService {

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var PaidyAdminRequestService
     */
    protected $paidyAdminRequestService;

    /**
     * @var CacheConfig
     */
    protected $cacheConfig;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

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
     * @param MailService $mailService
     * @param EccubeConfig $eccubeConfig
     * @param PaidyAdminRequestService $paidyAdminRequestService
     * @param CacheConfig $cacheConfig
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param BaseInfoRepository $baseInfoRepository
     * @param \Twig_Environment $twig
     * @param \Swift_Mailer $mailer
     */
    public function __construct(
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        EntityManagerInterface $entityManager,
        MailService $mailService,
        EccubeConfig $eccubeConfig,
        PaidyAdminRequestService $paidyAdminRequestService,
        CacheConfig $cacheConfig,
        PurchaseFlow $shoppingPurchaseFlow,
        BaseInfoRepository $baseInfoRepository,
        \Twig_Environment $twig,
        \Swift_Mailer $mailer
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->entityManager = $entityManager;
        $this->mailService = $mailService;
        $this->eccubeConfig = $eccubeConfig;
        $this->paidyAdminRequestService = $paidyAdminRequestService;
        $this->cacheConfig = $cacheConfig;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->BaseInfo = $baseInfoRepository->get();
        $this->twig = $twig;
        $this->mailer = $mailer;
    }
    
    /**
     * ペイジェントステータスの更新
     * @param array $arrRet ペイジェントから送られてくるリクエストパラメータ
     */
    public function updatePaygentOrder($arrRet) {
        // 受注ステータスの初期化
        $arrVal = [];
        $arrVal['status'] = null;
        // 決済日時の初期化
        $arrVal['payment_date'] = null;

        // 受注情報テーブルから、決済IDを取得
        $order = $this->orderRepository->find($arrRet['trading_id']);
        if (empty($order['paygent_payment_id'])) {
            $arrVal['paygent_payment_id'] = $arrRet['payment_id'];
        }

        // paygent_payment_status = 決済ステータス
        $arrVal['paygent_payment_status'] = $arrRet['payment_status'];
        // payment_notice_id = 決済通知ID
        $arrVal['payment_notice_id'] = $arrRet['payment_notice_id'];

        // 受注対応状況の設定
        $arrVal['status'] = $this->getOrderStatusFromPaygentStatus($arrRet['payment_status'], $order);
        // Paidyステータスの設定
        $arrVal['kind'] = $this->getKindFromPaygentStatus($arrRet['payment_status']);

        // 消込済かつ支払日時が含まれる
        if ($arrRet['payment_status'] == $this->eccubeConfig['paygent_payment']['status_pre_cleared'] && $arrRet['payment_date'] != "") {
            // 入金日時 = 応答情報.支払日時
            $arrVal['payment_date'] = $arrRet['payment_date'];
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
        // 受注情報テーブルから、決済IDを取得
        $order = $this->orderRepository->find($arrRet['trading_id']);

        if (empty($order['paygent_payment_id'])) {
            $order = $this->orderRepository->findOneBy(['id' => $arrRet['trading_id']]);
            // 決済ID を更新条件（update 文の where 句）に含めないようにする
            $arrRet['payment_id'] = null;
        } else {
            $order = $this->orderRepository->findOneBy(['id' => $arrRet['trading_id'], 'paygent_payment_id' => $arrRet['payment_id']]);
        }

        if ($order) {
            // オーソリOK 通知時にメールを送信する
            if ($arrParams['status'] == OrderStatus::NEW){
                // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
                $this->purchaseFlow->commit($order, new PurchaseContext());
                // メールの送信
                $this->mailService->sendOrderMail($order);

            }

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
            if (isset($arrParams['kind'])) {
                $order->setPaygentKind($arrParams['kind']);
            }

            $order->setPaygentPaymentStatus($arrParams['paygent_payment_status']);
            $order->setPaymentNoticeId($arrParams['payment_notice_id']);
            $this->entityManager->persist($order);
            $this->entityManager->flush();
        }
    }

    /**
     * PaygentStatusから対応するOrderStatusの取得
     * @param $paymentStatus
     * @param $order
     * @return int|null
     */
    public function getOrderStatusFromPaygentStatus($paymentStatus, $order)
    {
        switch ($paymentStatus) {

            case $this->eccubeConfig['paygent_payment']['status_authority_ok']: // "20"：オーソリOK
                // 受注状態 = "1"：新規受付
                return OrderStatus::NEW;

            case $this->eccubeConfig['paygent_payment']['status_authority_canceled']: // "32"：オーソリ取消済
            case $this->eccubeConfig['paygent_payment']['status_authority_expired']: // "33"：オーソリ期限切
                // 受注状態 = "3"：キャンセル
                return OrderStatus::CANCEL;

            case $this->eccubeConfig['paygent_payment']['status_pre_cleared']: // "40"：消込済
                // 受注状態 = "6"：入金済み
                return OrderStatus::PAID;

            case $this->eccubeConfig['paygent_payment']['status_pre_sales_cancellation']: // "60"：売上取消済
                $arrResponseDetail = unserialize($order->getResponseDetail());
                // 受注管理画面で、値引きにより決済金額を0円にした場合
                if (0 < $arrResponseDetail['ecOrderData']['payment_total']) {
                    // 受注状態 = "3"：キャンセル
                    return OrderStatus::CANCEL;
                }
                break;
        }

        return null;
    }

    /**
     * PaygentStatusから対応するKindの取得
     * @param $paymentStatus
     * @return int|null
     */
    public function getKindFromPaygentStatus($paymentStatus)
    {
        switch ($paymentStatus) {

            case $this->eccubeConfig['paygent_payment']['status_authority_ok']: // "20"：オーソリOK
                // オーソリOK
                return $this->eccubeConfig['paygent_payment']['paygent_paidy_authorized'];

            case $this->eccubeConfig['paygent_payment']['status_authority_canceled']: // "32"：オーソリ取消済
                // オーソリキャンセル
                return $this->eccubeConfig['paygent_payment']['paygent_paidy_auth_canceled'];

            case $this->eccubeConfig['paygent_payment']['status_authority_expired']: // "33"：オーソリ期限切
                // オーソリ期限切れ
                return $this->eccubeConfig['paygent_payment']['paygent_paidy_auth_expired'];

            case $this->eccubeConfig['paygent_payment']['status_pre_cleared']: // "40"：消込済
                // 売上
                return $this->eccubeConfig['paygent_payment']['paygent_paidy_commit'];

            case $this->eccubeConfig['paygent_payment']['status_pre_cleared_expiration_cancellation_sales']: // "41"：消込済（取消期限切）
                // 売上(売上取消期限切れ)
                return $this->eccubeConfig['paygent_payment']['paygent_paidy_commit_expired'];

            case $this->eccubeConfig['paygent_payment']['status_pre_sales_cancellation']: // "60"：売上取消済
                // 売上キャンセル
                return $this->eccubeConfig['paygent_payment']['paygent_paidy_commit_canceled'];
        }

        return null;
    }

    // 例外時 取消処理
    private function exceptCancel($orderId, $paymentId){
        $charCode = $this->eccubeConfig['paygent_payment']['char_code'];

        // 接続モジュールのインスタンス取得 (コンストラクタ)と初期化
        $objPaygent = new PaygentB2BModule($this->eccubeConfig);
        $objPaygent->init();

        $kind = $this->eccubeConfig['paygent_payment']['paygent_paidy_auth_canceled'];

        // 電文パラメータ取得
        $arrSend = $this->paidyAdminRequestService->makeParam($kind, $orderId, $paymentId);

        // 電文送信
        $arrRes = $this->paidyAdminRequestService->sendRequest($objPaygent, $arrSend, $charCode);

        return $arrRes;
    }

    /**
     * 差分通知 ステータスチェック
     * @param string paymentStatus ペイジェントから送られてくるステータスパラメータ
     * @return 正常:True 異常:False
     */
    function isValidStatus($paymentStatus){
        // 差分通知対象のステータス
        $arrIsValidStatus = [
            $this->eccubeConfig['paygent_payment']['status_ng_authority'],
            $this->eccubeConfig['paygent_payment']['status_authority_ok'],
            $this->eccubeConfig['paygent_payment']['status_authority_canceled'],
            $this->eccubeConfig['paygent_payment']['status_authority_expired'],
            $this->eccubeConfig['paygent_payment']['status_pre_cleared'],
            $this->eccubeConfig['paygent_payment']['status_pre_cleared_expiration_cancellation_sales'],
            $this->eccubeConfig['paygent_payment']['status_pre_sales_cancellation'],
        ];

        if(in_array($paymentStatus, $arrIsValidStatus)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 決済金額改竄検知通知メール送信処理
     */
    public function sendPaidyMismatchMailToAdmin($arrParam, $paymentAmount) {
        $body = $this->twig->render("@PaygentPayment/admin/paidy_mismatch_mail.twig", [
            'BaseInfo' => $this->BaseInfo,
            'merchant_id' => $arrParam['trading_id'],
            'payment_id' => $arrParam['payment_id'],
            'trading_id' => $arrParam['trading_id'],
            'payment_amount' => $paymentAmount,
            'paidy_amount' => $arrParam['payment_amount'],
        ]);
        $message = (new \Swift_Message())
            ->setSubject('['.$this->BaseInfo->getShopName().'] ' . $this->eccubeConfig['paygent_payment']['paidy_mismatch_mail_title'])
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
     * 決済ステータス不整合メール送信判定
     * @param $settlementDivision
     * @param $paymentStatus
     * @param $order
     * @return bool
     */
    function isAlertMail($settlementDivision, $paymentStatus, $order) {
        // 決済処理中の場合は処理を行わない
        if ($order->getOrderStatus()->getId() == OrderStatus::PENDING) {
            return false;
        }

        // ステータスオーソリOK、PaymentNoticeId未設定
        if ($paymentStatus == $this->eccubeConfig['paygent_payment']['status_authority_ok'] &&
            $order->getPaymentNoticeId() === null) {
            return true;
        }

        return false;
    }

    
    /**
     * Paidyチェックファンクション
     */
    public function isValidPaidy ($arrParam) {
        // 受注情報の取得
        $arrOrder = $this->orderRepository->find($arrParam['trading_id']);

        if (isset($arrOrder) && $arrParam['payment_type'] == $this->eccubeConfig['paygent_payment']['payment_type_paidy']) {
            $paymentStatus = $arrParam['payment_status'];

            // 受注情報.レスポンス詳細のシリアライズ配列をアンシリアライズ
            $arrResponseDetail = unserialize($arrOrder->getResponseDetail());

            // PaidyCheckout呼び出し時にJavaScriptとして出力される注文IDを他人のものに書き換えるケース
            if ($paymentStatus == $this->eccubeConfig['paygent_payment']['status_authority_ok'] && $arrOrder->getPaymentNoticeId() !== null) {
                // オーソリキャンセル
                $arrRetCancel = $this->exceptCancel($arrParam['trading_id'], $arrParam['payment_id']);
                if ($arrRetCancel[0]['result'] == $this->eccubeConfig['paygent_payment']['result_success']) {
                    logs('paygent_payment')->error('既にオーソリOKにしている受注IDに、別決済IDのオーソリ通知が来ているためペイジェント側の注文のオーソリ取消を行いました。');
                }
                return false;
            }

            // キャンセル通知で、決済金額が0円の時は整合性チェックをスキップする。
            // スキップしない場合は、受注詳細画面で、全額割引(決済金額0円)として売上変更した後のキャンセル通知が、決済金額不整合による不正通知とみなされブロックされてしまう。
            // 尚、スキップされることにより、同条件のリクエストをcurl等で送信した時に不正通知としてみなされなくなるが、どちらにしても同じ内容の正常通知が来るので実害はない。
            if ($paymentStatus == $this->eccubeConfig['paygent_payment']['status_pre_sales_cancellation'] && $arrResponseDetail['ecOrderData']['payment_total'] == 0) {
                return true;
            }

            // 決済金額照合 差分通知_POST.決済金額 と 受注情報.決済金額を比較する。
            if ($arrParam['payment_amount'] != $arrResponseDetail['ecOrderData']['payment_total']) {
                if ($arrParam['payment_status'] == $this->eccubeConfig['paygent_payment']['status_authority_ok']) {
                    // 金額改竄アラートフラグを立てる
                    $arrResponseDetail['ecOrderData']['payment_total_check_status'] = true;

                    $arrOrder->setResponseDetail(serialize($arrResponseDetail));
                    $this->entityManager->persist($arrOrder);
                    $this->entityManager->flush();

                    // 金額改竄通知メール送信
                    $this->sendPaidyMismatchMailToAdmin($arrParam, $arrResponseDetail['ecOrderData']['payment_total']);

                }
                return false;
            }
        }

        return true;
    }
}
