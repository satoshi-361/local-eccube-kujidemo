<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
 namespace Plugin\VeriTrans4G\Controller\Admin\Order;

 use Eccube\Entity\Order;
 use Eccube\Entity\Master\OrderStatus;
 use Eccube\Controller\AbstractController;
 use Symfony\Component\Routing\Annotation\Route;
 use Symfony\Component\HttpFoundation\Request;
 use Symfony\Component\HttpFoundation\RedirectResponse;
 use Symfony\Component\DependencyInjection\ContainerInterface;

 /**
  * 受注管理詳細画面 決済操作処理
  */
class EditController extends AbstractController
{
    /**
     * コンテナ
     */
    protected $container;

    /**
     * エンティティーマネージャー
     */
    private $em;

    /**
     * ユーティリティサービス
     */
    private $util;

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * MDK Logger
     */
    private $mdkLogger;

    /**
     * 一括操作モードフラグ
     */
    private $bulkMode = false;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->util = $container->get('vt4g_plugin.service.util');
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $this->container->getParameter('vt4g_plugin.const');
    }

    /**
     * 再オーソリ
     *
     * @Route("/%eccube_admin_route%/order/vt4g_edit/{orderId}/auth", name="vt4g_admin_order_edit_auth", requirements={"orderId"="\d+"}, methods={"POST"})
     * @param  Request          $request リクエストデータ
     * @param  integer          $orderId 注文ID
     * @return RedirectResponse          リダイレクトレスポンス
     */
    public function auth(Request $request, $orderId)
    {
        return $this->modifyPayment($request, $orderId, $this->vt4gConst['VT4G_OPERATION_AUTH']);
    }

    /**
     * 再売上(実売上)/売上確定(実売上)
     *
     * @Route("/%eccube_admin_route%/order/vt4g_edit/{orderId}/capture", name="vt4g_admin_order_edit_capture", requirements={"orderId"="\d+"}, methods={"POST"})
     * @param  Request          $request リクエストデータ
     * @param  integer          $orderId 注文ID
     * @return RedirectResponse          リダイレクトレスポンス
     */
    public function capture(Request $request, $orderId)
    {
        return $this->modifyPayment($request, $orderId, $this->vt4gConst['VT4G_OPERATION_CAPTURE']);
    }

    /**
     * 取消
     *
     * @Route("/%eccube_admin_route%/order/vt4g_edit/{orderId}/cancel", name="vt4g_admin_order_edit_cancel", requirements={"orderId"="\d+"}, methods={"POST"})
     * @param  Request          $request リクエストデータ
     * @param  integer          $orderId 注文ID
     * @return RedirectResponse          リダイレクトレスポンス
     */
    public function cancel(Request $request, $orderId)
    {
        return $this->modifyPayment($request, $orderId, $this->vt4gConst['VT4G_OPERATION_CANCEL']);
    }

    /**
     * 再売上(減額用)
     *
     * @Route("/%eccube_admin_route%/order/vt4g_edit/{orderId}/refund", name="vt4g_admin_order_edit_refund", requirements={"orderId"="\d+"}, methods={"POST"})
     * @param  Request          $request リクエストデータ
     * @param  integer          $orderId 注文ID
     * @return RedirectResponse          リダイレクトレスポンス
     */
    public function refund(Request $request, $orderId)
    {
        return $this->modifyPayment($request, $orderId, $this->vt4gConst['VT4G_OPERATION_REFUND']);
    }

    /**
     * 全額返金
     *
     * @Route("/%eccube_admin_route%/order/vt4g_edit/{orderId}/refundAll", name="vt4g_admin_order_edit_refund_all", requirements={"orderId"="\d+"}, methods={"POST"})
     * @param  Request          $request リクエストデータ
     * @param  integer          $orderId 注文ID
     * @return RedirectResponse          リダイレクトレスポンス
     */
    public function refundAll(Request $request, $orderId)
    {
        return $this->modifyPayment($request, $orderId, $this->vt4gConst['VT4G_OPERATION_REFUND_ALL']);
    }

    /**
     * 決済
     *
     * @Route("/%eccube_admin_route%/order/vt4g_edit/{orderId}/newly", name="vt4g_admin_order_edit_newly", requirements={"orderId"="\d+"}, methods={"POST"})
     * @param  Request          $request リクエストデータ
     * @param  integer          $orderId 注文ID
     * @return RedirectResponse          リダイレクトレスポンス
     */
    public function newly(Request $request, $orderId)
    {
        return $this->modifyPayment($request, $orderId, $this->vt4gConst['VT4G_OPERATION_NEWLY']);
    }

    /**
     * 決済の変更処理
     *
     * @param  Request          $request リクエストデータ
     * @param  integer          $orderId 注文ID
     * @param  string           $mode    決済操作の種別
     * @return RedirectResponse          リダイレクトレスポンス
     */
    private function modifyPayment(Request $request, $orderId, $mode)
    {
        // 一括操作モードフラグ
        $this->bulkMode = $request->request->get('bulk', false);

        try {
            // 注文データを取得
            $order = $this->em->getRepository(Order::class)->find($orderId);

            // 注文データが存在しない場合
            if (empty($order)) {
                return $this->handleError(
                    $orderId,
                    'vt4g_plugin.admin.order.update_payment_status.missing',
                    true
                );
            }

            // 決済データを取得
            $orderPayment = $this->util->getOrderPayment($orderId);
            if (empty($orderPayment)) {
                /**
                 * 購入処理中でMDKリクエスト前はベリトランス決済の場合でも
                 * レコードが存在しないためpaymentId基準で判定
                 */
                $messageKey = $this->util->isVt4gPayment($order)
                    ? 'vt4g_plugin.admin.order.update_payment_status.unavailable'
                    : 'vt4g_plugin.admin.order.update_payment_status.unsupport';

                return $this->handleError($orderId, $messageKey);
            }

            // 決済操作の実行可否を取得
            $operations = $this->util->getPaymentOperations($order);
            if (!array_key_exists($mode, $operations)) {
                return $this->handleError(
                    $orderId,
                    'vt4g_plugin.admin.order.update_payment_status.unavailable'
                );
            }

            // 一括操作時に操作できる決済かチェック
            $bulkAvailableOperations = [
                $this->vt4gConst['VT4G_OPERATION_CAPTURE'],
                $this->vt4gConst['VT4G_OPERATION_CANCEL']
            ];
            if ($this->bulkMode && !in_array($mode, $bulkAvailableOperations)) {
                return $this->handleError(
                    $orderId,
                    'vt4g_plugin.admin.order.update_payment_status.unavailable'
                );
            }
            if ($this->bulkMode
            && $orderPayment->getMemo03() == $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']
            && $orderPayment->getMemo04() == $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']
            && $mode == $this->vt4gConst['VT4G_OPERATION_CAPTURE']) {
                return $this->handleError(
                    $orderId,
                    'vt4g_plugin.admin.order.update_payment_status.unavailable'
                );
            }
            if ($this->bulkMode
            && $mode == $this->vt4gConst['VT4G_OPERATION_CAPTURE']) {
              $subscFewCreditExists = $this->util->checkSubscFewCreditExists($orderId);

              if ($subscFewCreditExists) {
                return $this->handleError(
                    $orderId,
                    'vt4g_plugin.admin.order.update_payment_status.subsc_few_credit'
                );
              }
            }

            $editExtension = $this->container->get('vt4g_plugin.service.admin.order_edit_extension');

            $payload = [
                'inputs'       => $request->request,
                'order'        => $order,
                'orderPayment' => $orderPayment,
                'mode'         => $mode,
            ];
            $beforeUpdatePaymentStatus = $payload['orderPayment']->getMemo04();
            $beforeUpdateOrderStatus = $payload['order']->getOrderStatus()['id'];

            if ($mode == $this->vt4gConst['VT4G_OPERATION_REFUND_ALL']) {
                $payload['refundAll'] = true;
                $mode = $this->vt4gConst['VT4G_OPERATION_REFUND'];
            }

            $suffix = ucfirst(strtolower($mode));
            $method = "operate{$suffix}";

            // メソッドが存在する場合は決済変更処理を実行
            $modifyResult = (method_exists($editExtension, $method))
                ? $editExtension->$method($payload)
                : [];

            // レスポンスチェック
            if (empty($modifyResult)) {
                return $this->handleError(
                    $orderId,
                    'vt4g_plugin.admin.order.update_payment_status.error'
                );
            }

            // 決済失敗
            if (!$modifyResult['isOK']) {
                return $this->handleError($orderId, $modifyResult['message']);
            }
        } catch (\Exception $e) {
            // 一括操作モードの場合
            if ($this->bulkMode) {
                return $this->json(['status' => 'NG'], 500);
            }

            return $this->handleError(
                $orderId,
                'vt4g_plugin.payment.shopping.error'
            );
        }

        $returnValue = $this->returnMsgCheck($payload, $mode, $beforeUpdatePaymentStatus, $beforeUpdateOrderStatus);
        $method = "handle{$returnValue['returnStatus']}";

        return $this->$method(
            $orderId,
            $returnValue['returnMsg']
        );

    }

    /**
     * 異常終了時のハンドリング
     *
     * @param  integer $orderId 注文ID
     * @param  string  $error   エラーメッセージ
     * @param  boolean $toIndex リダイレクト先を受注一覧にするか(false時は受注詳細)
     * @return object           JSONレスポンス|リダイレクトレスポンス
     */
    private function handleError($orderId, $error, $toIndex = false)
    {
        // 一括操作モードの場合
        if ($this->bulkMode) {
            // JSONレスポンスを返す
            return $this->json([
                'status'  => 'NG',
                'message' => "{$orderId}: ".trans($error)
            ]);
        }

        $url = $toIndex
            ? $this->util->generateUrl('admin_order')
            : $this->util->generateUrl('admin_order_edit', ['id' => $orderId]);

        return $this->redirectWithFlush($url, compact('error'));
    }

    /**
     * 正常終了時のハンドリング
     *
     * @param  integer $orderId 注文ID
     * @param  string  $success 完了メッセージ
     * @return object           JSONレスポンス|リダイレクトレスポンス
     */
    private function handleSuccess($orderId, $success)
    {
        // 一括操作モードの場合
        if ($this->bulkMode) {
            // JSONレスポンスを返す
            return $this->json([
                'status'  => 'OK',
                'message' => "{$orderId}: ".trans($success)
            ]);
        }

        return $this->redirectWithFlush(
            $this->util->generateUrl('admin_order_edit', ['id' => $orderId]),
            compact('success')
        );
    }

    /**
     * ワーニング終了時のハンドリング
     *
     * @param  integer $orderId 注文ID
     * @param  string  $warning 完了メッセージ
     * @return object           JSONレスポンス|リダイレクトレスポンス
     */
    private function handleWarning($orderId, $warning)
    {
        // 一括操作モードの場合
        if ($this->bulkMode) {
            // JSONレスポンスを返す
            return $this->json([
                'status'  => 'NG',
                'message' => "{$orderId}: ".trans($warning)
            ]);
        }

        return $this->redirectWithFlush(
            $this->util->generateUrl('admin_order_edit', ['id' => $orderId]),
            compact('warning')
        );
    }

    /**
     * リターンする時の内容を確認する。
     *
     * @param  array   $payload 決済処理に使用するデータ
     * @param  string  $mode    決済操作の種別
     * @param  string  $beforeUpdatePaymentStatus 更新前の決済ステータス
     * @param  string  $beforeUpdateOrderStatus 更新前の注文ステータス
     * @return array   $returnValue 返却値
     */
    private function returnMsgCheck($payload, $mode, $beforeUpdatePaymentStatus, $beforeUpdateOrderStatus)
    {
        $returnValue = [];

        //mode=CAPTUREまたはNEWLYかつ更新後の決済ステータスが売上の際に表示するメッセージを変更する。
        if(($mode == $this->vt4gConst['VT4G_OPERATION_CAPTURE'] || $mode == $this->vt4gConst['VT4G_OPERATION_NEWLY'])
            && $payload['orderPayment']->getMemo04() === $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']
        ){
            
            //注文ステータスが新規受付→入金済みへ変更した際のメッセージを表示する。
            if($beforeUpdateOrderStatus === OrderStatus::NEW && $payload['order']->getOrderStatus()['id'] === OrderStatus::PAID){
                $returnValue['returnStatus'] = 'Success';
                $returnValue['returnMsg'] = 'vt4g_plugin.admin.order.update_payment_and_order_status.complete';
            } else {
                $returnValue['returnStatus'] = 'Warning';
                $returnValue['returnMsg'] = 'vt4g_plugin.admin.order.update_payment_status.part.complete';
            }

            //入金済みのステータスが存在しない + 変更前の決済ステータスが与信の場合のメッセージを表示する。
            $orderStatus = $this->em->getRepository(OrderStatus::class)->find(OrderStatus::PAID);
            if(empty($orderStatus) && $beforeUpdatePaymentStatus == $this->vt4gConst['VT4G_PAY_STATUS']['AUTH']['VALUE']){
                $returnValue['returnStatus'] = 'Warning';
                $returnValue['returnMsg'] = 'vt4g_plugin.admin.order.update_payment_status.complete.no_order_status';
            }

        } else {
            $returnValue['returnStatus'] = 'Success';
            $returnValue['returnMsg'] = 'vt4g_plugin.admin.order.update_payment_status.complete';
        }

        return $returnValue;
    }

    /**
     * フラッシュメッセージを設定してリダイレクト
     *
     * @param  string           $url   リダイレクト先のURL
     * @param  array            $flush フラッシュメッセージ
     * @return RedirectResponse        リダイレクトレスポンス
     */
    private function redirectWithFlush($url, $flush = [])
    {
        if (isset($flush['success'])) {
            $this->addSuccess($flush['success'], 'admin');
        }
        if (isset($flush['warning'])) {
            $this->addWarning($flush['warning'], 'admin');
        }
        if (isset($flush['error'])) {
            $this->addError($flush['error'], 'admin');
        }

        return new RedirectResponse($url);
    }
}
