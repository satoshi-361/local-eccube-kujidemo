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

namespace Plugin\PaygentPayment\Controller\Admin;

use Eccube\Common\Constant;
use Eccube\Controller\AbstractController;
use Eccube\Repository\Master\PageMaxRepository;
use Eccube\Util\FormUtil;
use Knp\Component\Pager\PaginatorInterface;
use Plugin\PaygentPayment\Form\Type\Admin\SearchPaymentType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Eccube\Common\EccubeConfig;
use Eccube\Repository\OrderRepository;
use Plugin\PaygentPayment\Service\PaymentFactory;
use Plugin\PaygentPayment\Service\PaymentStatusSearchService;
use Plugin\PaygentPayment\Service\PaygentOrderRollbackService;
use Plugin\PaygentPayment\Service\PaygentBaseService;
use Plugin\PaygentPayment\Service\PaymentOperationFactory;

/**
 * 決済状況管理
 */
class PaymentStatusController extends AbstractController
{
    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var PaymentOperationFactory
     */
    protected $paymentOperationFactory;

    /**
     * @var PaymentStatusSearchService
     */
    protected $paymentStatusSearchService;

    /**
     * @var PaygentOrderRollbackService
     */
    protected $paygentOrderRollbackService;

    /**
     * @var PaygentBaseService
     */
    protected $paygentBaseService;


    /**
     * コンストラクタ
     * @param PageMaxRepository $pageMaxRepository
     * @param EccubeConfig $eccubeConfig
     * @param OrderRepository $orderRepository
     * @param PaymentOperationFactory $paymentOperationFactory
     * @param PaymentStatusSearchService $paymentStatusSearchService
     * @param PaygentOrderRollbackService $paygentOrderRollbackService
     * @param PaygentBaseService $paygentBaseService
     */
    public function __construct(
        PageMaxRepository $pageMaxRepository,
        EccubeConfig $eccubeConfig,
        OrderRepository $orderRepository,
        PaymentOperationFactory $paymentOperationFactory,
        PaymentStatusSearchService $paymentStatusSearchService,
        PaygentOrderRollbackService $paygentOrderRollbackService,
        PaygentBaseService $paygentBaseService
    ) {
        $this->pageMaxRepository = $pageMaxRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->orderRepository = $orderRepository;
        $this->paymentOperationFactory = $paymentOperationFactory;
        $this->paymentStatusSearchService = $paymentStatusSearchService;
        $this->paygentOrderRollbackService = $paygentOrderRollbackService;
        $this->paygentBaseService = $paygentBaseService;
    }

    /**
     * 決済状況一覧画面
     *
     * @Route("/%eccube_admin_route%/paygent_payment/payment_status", name="paygent_payment_admin_payment_status")
     * @Route("/%eccube_admin_route%/paygent_payment/payment_status/page/{page_no}", requirements={"page_no" = "\d+"}, name="paygent_payment_admin_payment_status_pageno")
     * @Template("@PaygentPayment/admin/Order/payment_status.twig")
     */
    public function index(Request $request, $page_no = null, PaginatorInterface $paginator)
    {
        $searchForm = $this->createForm(SearchPaymentType::class);

        /**
         * ページの表示件数は, 以下の順に優先される.
         * - リクエストパラメータ
         * - セッション
         * - デフォルト値
         * また, セッションに保存する際は mtb_page_maxと照合し, 一致した場合のみ保存する.
         **/
        $pageCount = $this->session->get('paygent_payment.admin.payment_status.search.page_count',
            $this->eccubeConfig->get('eccube_default_page_count'));

        $pageCountParam = (int) $request->get('page_count');
        $pageMaxis = $this->pageMaxRepository->findAll();

        if ($pageCountParam) {
            foreach ($pageMaxis as $pageMax) {
                if ($pageCountParam == $pageMax->getName()) {
                    $pageCount = $pageMax->getName();
                    $this->session->set('paygent_payment.admin.payment_status.search.page_count', $pageCount);
                    break;
                }
            }
        }

        if ('POST' === $request->getMethod()) {
            $searchForm->handleRequest($request);

            if ($searchForm->isValid()) {
                /**
                 * 検索が実行された場合は, セッションに検索条件を保存する.
                 * ページ番号は最初のページ番号に初期化する.
                 */
                $page_no = 1;
                $searchData = $searchForm->getData();

                // 検索条件, ページ番号をセッションに保持.
                $this->session->set('paygent_payment.admin.payment_status.search', FormUtil::getViewData($searchForm));
                $this->session->set('paygent_payment.admin.payment_status.search.page_no', $page_no);
            } else {
                // 検索エラーの際は, 詳細検索枠を開いてエラー表示する.
                return [
                    'searchForm' => $searchForm->createView(),
                    'pagination' => [],
                    'pageMaxis' => $pageMaxis,
                    'page_no' => $page_no,
                    'page_count' => $pageCount,
                    'has_errors' => true,
                ];
            }
        } else {
            if (null !== $page_no || $request->get('resume')) {
                // ページ送りの場合または、他画面から戻ってきた場合は, セッションから検索条件を復旧する.
                if ($page_no) {
                    // ページ送りで遷移した場合.
                    $this->session->set('paygent_payment.admin.payment_status.search.page_no', (int) $page_no);
                } else {
                    // 他画面から遷移した場合.
                    $page_no = $this->session->get('paygent_payment.admin.payment_status.search.page_no', 1);
                }
                $viewData = $this->session->get('paygent_payment.admin.payment_status.search', []);
                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);
            } else {
                // 初期表示の場合.
                $page_no = 1;
                $searchData = [];

                // セッション中の検索条件, ページ番号を初期化.
                $this->session->set('paygent_payment.admin.payment_status.search', $searchData);
                $this->session->set('paygent_payment.admin.payment_status.search.page_no', $page_no);
            }
        }

        $arrDispKind = $this->getDispKind();
        $paymentMethod = $this->getPaymentForAdminOrder();

        $queryBuilder = $this->paymentStatusSearchService->createQueryBuilder($searchData);
        $pagination = $paginator->paginate(
            $queryBuilder,
            $page_no,
            $pageCount
        );

        $arrPaidyAlertId = [];
        foreach ($pagination as $order) {
            if ($order->getResponseDetail() && $order->getPaygentPaymentMethod() == $this->eccubeConfig['paygent_payment']['paygent_paidy']) {
                $arrResponseDetail = unserialize($order->getResponseDetail());
                if (isset($arrResponseDetail['ecOrderData']['payment_total_check_status'])) {
                    if ($arrResponseDetail['ecOrderData']['payment_total_check_status'] == 1) {
                        $arrPaidyAlertId[] = $order->getId();
                    }
                }
            }
        }

        $response = $this->paygentBaseService->setDefaultHeader(new Response());

        $arrReturn = [
            'searchForm' => $searchForm->createView(),
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $pageCount,
            'has_errors' => false,
            'arrDispKind' => $arrDispKind,
            'paymentMethod' => $paymentMethod,
            'arrPaidyAlertId' => $arrPaidyAlertId,
        ];

        return $this->render('@PaygentPayment/admin/Order/payment_status.twig', $arrReturn, $response);
    }

    /**
     * 一括処理.
     *
     * @Route("/%eccube_admin_route%/paygent_payment/payment_status/request_action", name="paygent_payment_admin_payment_status_request")
     */
    public function requestAction(Request $request)
    {
        if($this->isTokenValid()){
            $mode = $request->get('mode');

            if ($mode == 'paygent_commit') {
                $orderName = "commit_id";
            } elseif ($mode == 'paygent_cancel') {
                $orderName = "cancel_id";
            }
            $arrOrderId = $request->get($orderName);

            // 一括受注連携
            list($arrSuccessMsg, $arrFailMsg) = $this->paygentOrderBulkCooperate($arrOrderId, $mode);

            if(count($arrSuccessMsg) > 0){
                $this->addSuccess(trans(
                    'paygent_payment.admin.payment_status.success_count', [
                        '%successCount%' => count($arrSuccessMsg), 
                    ]
                ).PHP_EOL.implode(PHP_EOL, $arrSuccessMsg), 'admin');
            }

            if(count($arrFailMsg) > 0){
                $this->addError(trans(
                    'paygent_payment.admin.payment_status.error_count', [
                        '%errorCount%' => count($arrFailMsg), 
                    ]
                ).PHP_EOL.implode(PHP_EOL, $arrFailMsg), 'admin');
            }

            return $this->redirectToRoute('paygent_payment_admin_payment_status_pageno', ['resume' => Constant::ENABLED]);
        }
    }

    /**
     * 決済処理中ステータス受注のロールバック
     * @Route("/%eccube_admin_route%/paygent_payment/rollback", name="paygent_order_rollback")
     */
    public function pendingOrderRollback()
    {
        if ($this->isTokenValid()) {
            logs('paygent_payment')->info("Begin paygent order rollback process.");

            // 決済処理中ステータス受注の取得
            $arrOrder = $this->paygentOrderRollbackService->getPendingOrder();

            $arrOrderIds = [];
            // 取得した受注のロールバック処理
            foreach($arrOrder as $order){
                $this->paygentOrderRollbackService->rollbackPaygentOrder($order);
                $arrOrderIds[] = "注文番号：".$order->getId();
            }

            if(count($arrOrderIds) > 0){
                // ロールバック件数表示
                $this->addSuccess(trans('paygent_payment.admin.payment_status.execute_count', [
                        '%execute_count%' => count($arrOrder), 
                    ]).PHP_EOL.implode(PHP_EOL, $arrOrderIds), 'admin');
            }else{
                $this->addSuccess(trans('paygent_payment.admin.payment_status.execute_zero_count.message'), 'admin');
            }

            logs('paygent_payment')->info("End paygent order rollback process.");

            return $this->redirectToRoute('paygent_payment_admin_payment_status_pageno', ['resume' => Constant::ENABLED]);
        }
    }

    // 一括受注連携
    function paygentOrderBulkCooperate($arrPaygentCommit, $mode) {

        // 初期設定
        $arrDispKind = $this->getDispKind();
        $arrSuccessMsg = [];
        $arrFailMsg = [];

        // 受注連携
        foreach ($arrPaygentCommit as $val) {
            // 連携種別と受注ID
            $paygentCommit = explode(",", $val);

            $output = "受注番号：". $paygentCommit[1]. " → ";

            /** @var Order $order */
            $order = $this->orderRepository->findBy(['id' => $paygentCommit[1]]);

            // 支払いの種類に該当するインスタンスを取得。取得できなければ、エラーを返す。
            $this->paymentOperationInstance = $this->paymentOperationFactory->getInstance($order[0]->getPaygentPaymentMethod());

            // 連携
            $res = $this->paymentOperationInstance->process($paygentCommit[0], $paygentCommit[1]);

            // 結果出力
            if ($res['return'] === true) {
                $arrSuccessMsg[] = $output. $arrDispKind[$res['kind']]. "成功";
            } else {
                $arrFailMsg[] = $output. $arrDispKind[$res['kind']]. "失敗 ". $res['response'];
            }
        }

        return [$arrSuccessMsg, $arrFailMsg];
    }

    function getDispKind(){
        return [
            $this->eccubeConfig['paygent_payment']['paygent_auth_cancel'] => 'オーソリキャンセル',
            $this->eccubeConfig['paygent_payment']['paygent_card_commit'] => '売上',
            $this->eccubeConfig['paygent_payment']['paygent_card_commit_revice'] => '売上変更',
            $this->eccubeConfig['paygent_payment']['paygent_card_commit_revice_processing'] => '売上変更処理中',
            $this->eccubeConfig['paygent_payment']['paygent_card_commit_cancel'] => '売上キャンセル',
            $this->eccubeConfig['paygent_payment']['paygent_credit'] => 'オーソリ変更',
            $this->eccubeConfig['paygent_payment']['paygent_credit_processing'] => 'オーソリ変更処理中',
            $this->eccubeConfig['paygent_payment']['paygent_career_commit'] => '売上',
            $this->eccubeConfig['paygent_payment']['paygent_career_commit_cancel'] => '取消',
            $this->eccubeConfig['paygent_payment']['paygent_career_commit_revice'] => '売上変更',
            $this->eccubeConfig['paygent_payment']['paygent_paidy_authorized'] => 'オーソリOK',
            $this->eccubeConfig['paygent_payment']['paygent_paidy_auth_canceled'] => 'オーソリキャンセル',
            $this->eccubeConfig['paygent_payment']['paygent_paidy_auth_expired'] => 'オーソリ期限切れ',
            $this->eccubeConfig['paygent_payment']['paygent_paidy_commit'] => '売上',
            $this->eccubeConfig['paygent_payment']['paygent_paidy_commit_expired'] => '売上(売上取消期限切れ）',
            $this->eccubeConfig['paygent_payment']['paygent_paidy_commit_canceled'] => '売上キャンセル',
        ];
    }

    function getPaymentForAdminOrder(){
        return [
            'PAYGENT_PAYMENT_CODE' => $this->eccubeConfig['paygent_payment']['paygent_payment_code'],
            'PAYGENT_CREDIT' => $this->eccubeConfig['paygent_payment']['paygent_credit'],
            'PAYGENT_CARD_COMMIT' => $this->eccubeConfig['paygent_payment']['paygent_card_commit'],
            'PAYGENT_CARD_COMMIT_REVICE' => $this->eccubeConfig['paygent_payment']['paygent_card_commit_revice'],
            'PAYGENT_CAREER_D' => $this->eccubeConfig['paygent_payment']['paygent_career_d'],
            'PAYGENT_CAREER_A' => $this->eccubeConfig['paygent_payment']['paygent_career_a'],
            'PAYGENT_CAREER_S' => $this->eccubeConfig['paygent_payment']['paygent_career_s'],
            'PAYGENT_CAREER_COMMIT' => $this->eccubeConfig['paygent_payment']['paygent_career_commit'],
            'PAYGENT_CAREER_COMMIT_REVICE' => $this->eccubeConfig['paygent_payment']['paygent_career_commit_revice'],
            'PAYGENT_PAIDY' => $this->eccubeConfig['paygent_payment']['paygent_paidy'],
            'PAYGENT_PAIDY_AUTHORIZED' => $this->eccubeConfig['paygent_payment']['paygent_paidy_authorized'],
            'PAYGENT_PAIDY_COMMIT' => $this->eccubeConfig['paygent_payment']['paygent_paidy_commit'],
        ];
    }
}
