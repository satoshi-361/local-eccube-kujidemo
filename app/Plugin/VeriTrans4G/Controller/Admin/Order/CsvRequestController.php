<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin\Order;

use Plugin\VeriTrans4G\Entity\Vt4gPaymentReqEvent;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentRequest;
use Plugin\VeriTrans4G\Entity\Vt4gOrderPayment;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentRequestItem;
use Plugin\VeriTrans4G\Form\Type\Admin\CsvRequestListSearchType;
use Plugin\VeriTrans4G\Form\Type\Admin\BulkSettelmentFeeType;
use Plugin\VeriTrans4G\Form\Type\Admin\BulkPricePointType;
use Eccube\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\VeriTrans4G\Form\Type\Admin\PaymentReqEventType;
use Eccube\Entity\Customer;
use Knp\Component\Pager\PaginatorInterface;
use Eccube\Repository\Master\PageMaxRepository;
use Plugin\VeriTrans4G\Repository\Vt4gPaymentRequestRepository;
use Eccube\Util\FormUtil;
use Plugin\VeriTrans4G\Form\Type\Admin\PaymentRequestType;

use Plugin\VeriTrans4G\Entity\Vt4gPlugin;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV決済依頼管理
 *
 */
class CsvRequestController extends AbstractController
{
    /**
     * コンテナ
     */
    protected $container;

    /**
     * エンティティーマネージャー
     */
    protected $em;

    /**
     * VT用固定値配列
     */
    protected $vt4gConst;

    /**
     * 汎用処理用ユーティリティ
     */
    protected $util;

    /**
     * MDK Logger
     */
    protected $mdkLogger;

    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;

    /**
     * @var Vt4gPaymentRequestRepository
     */
    protected $vt4gPaymentRequetRepository;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container コンテナ
     * @return void
     */
    public function __construct(
        PageMaxRepository $pageMaxRepository,
        Vt4gPaymentRequestRepository $vt4gPaymentRequetRepository,
        ContainerInterface $container
    )
    {
        $this->container = $container;
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->util = $container->get('vt4g_plugin.service.util');
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
        $this->pageMaxRepository = $pageMaxRepository;
        $this->vt4gPaymentRequetRepository = $vt4gPaymentRequetRepository;
    }

    /**
     * CSV決済依頼管理（決済依頼イベント一覧の表示も兼ねる）
     *
     * @Route("/%eccube_admin_route%/order/vt4g_order_csv_request", name="vt4g_admin_order_csv_request")
     * @param Request $request リクエストデータ
     * @return object ビューレスポンス
     */
    public function index(Request $request)
    {
        // PaymentReqEventTypeでフォーム作成
        $builder = $this->formFactory->createBuilder(PaymentReqEventType::class);
        $form = $builder->getForm();
        // POSTリクエストのハンドリング
        $form->handleRequest($request);
        $searchData = [];

        if ('POST' === $request->getMethod()) {
            $searchData = $form->getData();
        }

        // 決済依頼イベント情報を取得
        $events = $this->em->getRepository(Vt4gPaymentReqEvent::class)->getRequestEventList($searchData);

        return $this->render(
            'VeriTrans4G/Resource/template/admin/Order/csv_request.twig',
            [
                'form' => $form->createView(),
                'events' => $events
            ]
        );
    }

    /**
     * CSV決済依頼管理 決済依頼一覧
     *
     * @Route("/%eccube_admin_route%/order/vt4g_order_csv_request/{event_id}", requirements={"event_id" = "\d+"}, name="vt4g_admin_order_csv_request_list")
     * @Route("/%eccube_admin_route%/order/vt4g_order_csv_request/page/{page_no}", requirements={"page_no" = "\d+"}, name="vt4g_admin_order_csv_request_list_page")
     * @Route("/%eccube_admin_route%/order/vt4g_order_csv_request/{event_id}/{mode}", requirements={"event_id" = "\d+", "mode" = "[a-zA-Z]+"}, name="vt4g_admin_order_csv_request_list_mode")
     * @param Request $request リクエストデータ
     * @param int $event_id  決済依頼イベント番号
     * @param string $mode 処理区分
     * @param int $page_no ページ番号
     * @param PaginatorInterface $paginator ページネーションデータ
     * @return object ビューレスポンス
     *
     */
    public function list(Request $request, int $event_id = null, string $mode = null, int $page_no = null, PaginatorInterface $paginator)
    {
        $session = $this->session;

        if (!is_null($event_id)) {
          // セッションに保存
          $session->set('eccube.admin.csvrequest.search.event_id', (int) $event_id);
        } else {
            // セッションから取得
            $event_id = $session->get('eccube.admin.csvrequest.search.event_id', '');
        }

        $bulk_price_form_data = ['event_id' => $event_id];
        $bulkfeeForm = $this->formFactory->createBuilder(BulkSettelmentFeeType::class)->getForm();
        $bulkpriceForm = $this->formFactory->createBuilder(BulkPricePointType::class, $bulk_price_form_data)->getForm();

        if ($mode == 'allenable') {
            $paymentTarget = $this->vt4gConst['VT4G_PAYMENT_TARGET_YES'];
            $reposPaymentRequest = $this->em->getRepository(Vt4gPaymentRequest::class);
            $reposPaymentRequest->updateRequestStatus($event_id, $paymentTarget);

            $this->addSuccess(trans('vt4g_plugin.admin.payment_request.list.allenable'), 'admin');
            $this->mdkLogger->info(
                trans('vt4g_plugin.admin.payment_request.list.allenable')
                .sprintf(trans('vt4g_plugin.admin.payment_request.event_id'),$event_id)
            );

            return $this->redirectToRoute('vt4g_admin_order_csv_request_list', ['event_id' => $event_id]);

        } elseif ($mode == 'alldisable') {
            $paymentTarget = $this->vt4gConst['VT4G_PAYMENT_TARGET_NO'];
            $this->em->getRepository(Vt4gPaymentRequest::class)
                    ->updateRequestStatus($event_id, $paymentTarget);

            $this->addSuccess(trans('vt4g_plugin.admin.payment_request.list.alldisable'), 'admin');
            $this->mdkLogger->info(
                trans('vt4g_plugin.admin.payment_request.list.alldisable')
                .sprintf(trans('vt4g_plugin.admin.payment_request.event_id'),$event_id)
            );

            return $this->redirectToRoute('vt4g_admin_order_csv_request_list', ['event_id' => $event_id]);
        } elseif ($mode == 'allsetfee') {
            // 決済手数料一括変更
            if ('POST' === $request->getMethod()) {
                $bulkfeeForm->handleRequest($request);
                if ($bulkfeeForm->isValid()) {
                    $post = $bulkfeeForm->getData();
                    $this->em->getRepository(Vt4gPaymentRequest::class)
                            ->updateBulkSettelmentFee($event_id, $post['fee']);

                    $this->addSuccess(trans('vt4g_plugin.admin.payment_request.list.allsetfee'), 'admin');
                    $this->mdkLogger->info(
                        trans('vt4g_plugin.admin.payment_request.list.allsetfee')
                        .sprintf(trans('vt4g_plugin.admin.payment_request.event_id'),$event_id)
                        .sprintf(trans('vt4g_plugin.admin.payment_request.fee'),$post['fee'])
                    );

                    return $this->redirectToRoute('vt4g_admin_order_csv_request_list', ['event_id' => $event_id]);
                }
                $request->setMethod('GET');
            }
        } else if ($mode == 'allsetprice') {
            // 商品・送料の金額・ポイント一括設定
            if ('POST' === $request->getMethod()) {
                $bulkpriceForm->handleRequest($request);
                $post = $bulkpriceForm->getData();
                if ($bulkpriceForm->isValid()) {
                    $post = $bulkpriceForm->getData();
                    $this->em->getRepository(Vt4gPaymentRequest::class)
                            ->updateBulkPricePoint($event_id, $post['item'], $post['amount'], $post['point']);

                    $this->addSuccess(trans('vt4g_plugin.admin.payment_request.list.allsetprice'), 'admin');

                    $msg = $post['item'] > 0
                            ? sprintf(trans('vt4g_plugin.admin.payment_request.prduct_class_id'), $post['item'])
                            : "送料 ";
                    $this->mdkLogger->info(
                        trans('vt4g_plugin.admin.payment_request.list.allsetprice')
                        .sprintf(trans('vt4g_plugin.admin.payment_request.event_id'), $event_id)
                        .$msg
                        .sprintf(trans('vt4g_plugin.admin.payment_request.price_and_point'), $post['amount'], $post['point'])
                    );


                    return $this->redirectToRoute('vt4g_admin_order_csv_request_list', ['event_id' => $event_id]);
                }
                $request->setMethod('GET');
            }
        } elseif  ($mode == 'resettlement') {

            // 販売種別IDの取得
            $request->setMethod('GET');
            return $this->redirectToRoute('vt4g_admin_order_csv_registration',
                                          [
                                            'resettlementEventId' => $event_id,
                                          ]);
        }

        // 決済依頼イベント情報を取得
        $reqEvent = $this->em->getRepository(Vt4gPaymentReqEvent::class);
        $event = $reqEvent->getRequestEvent($event_id);

        $searchFormBuilder = $this->formFactory->createBuilder(CsvRequestListSearchType::class);
        $searchForm = $searchFormBuilder->getForm();

        $pageMaxis = $this->pageMaxRepository->findAll();
        $pageCount = $session->get('eccube.admin.csvrequest.search.page_count', $this->eccubeConfig['eccube_default_page_count']);
        $pageCountParam = $request->get('page_count');
        if ($pageCountParam && is_numeric($pageCountParam)) {
            foreach ($pageMaxis as $pageMax) {
                if ($pageCountParam == $pageMax->getName()) {
                    $pageCount = $pageMax->getName();
                    $session->set('eccube.admin.csvrequest.search.page_count', $pageCount);
                    break;
                }
            }
        }

        if ('POST' === $request->getMethod()) {
            $searchForm->handleRequest($request);
            if ($searchForm->isValid()) {
                $searchData = $searchForm->getData();
                $page_no = 1;
                $session->set('eccube.admin.csvrequest.search', FormUtil::getViewData($searchForm));
                $session->set('eccube.admin.csvrequest.search.page_no', $page_no);
            } else {
                return $this->render(
                    'VeriTrans4G/Resource/template/admin/Order/csv_request_list.twig',
                    [
                        'event' => $event,
                        'bulkfeeForm' => $bulkfeeForm->createView(),
                        'bulkpriceForm' => $bulkpriceForm->createView(),
                        'searchForm'  => $searchForm->createView(),
                        'pagination' => [],
                        'pageMaxis' => $pageMaxis,
                        'page_no' => $page_no,
                        'page_count' => $pageCount,
                        'has_errors' => true,
                    ]
                );
            }
        } else {
            if (null !== $page_no || $request->get('resume')) {
                if ($page_no) {
                    $session->set('eccube.admin.csvrequest.search.page_no', (int) $page_no);
                } else {
                    $page_no = $session->get('eccube.admin.csvrequest.search.page_no', 1);
                }
                $viewData = $session->get('eccube.admin.csvrequest.search', []);
            } else {
                $page_no = 1;
                $viewData = FormUtil::getViewData($searchForm);
                $session->set('eccube.admin.csvrequest.search', $viewData);
                $session->set('eccube.admin.csvrequest.search.page_no', $page_no);
            }
            $searchData = FormUtil::submitAndGetData($searchForm, $viewData);
        }

        $qb = $this->vt4gPaymentRequetRepository
                ->getPayRequestListByEventId($event_id, $searchData['request_status'], $searchData['search_keyword']);

        // ページネーターをセット
        $pagination = $paginator->paginate(
            $qb,
            $page_no,
            $pageCount
        );

        //  決済依頼ステータスが「作成前」あるいは「反映待ち」の決済依頼がイベントに存在するか確認
        $paymentStatusBeforeOrWaitCnt = $this->vt4gPaymentRequetRepository
                              ->count(['req_event_id' => $event_id,
                                        'request_status' => [
                                                            $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['BEFORE_CREATION'],
                                                            $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['WAITING_FOR_REFRECTON']
                                                            ]
                                      ]);

        // 決済ステータスが「決済失敗」の決済依頼がイベントに存在するか確認
        $paymentStatusFailureCnt = $this->vt4gPaymentRequetRepository
                              ->count(['req_event_id' => $event_id,
                                        'request_status' => [
                                                            $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['FAILURE_PAYMENT']
                                                            ]
                                      ]);

        return $this->render(
            'VeriTrans4G/Resource/template/admin/Order/csv_request_list.twig',
            [
                'event' => $event,
                'paymentStatusBeforeOrWaitCnt' => $paymentStatusBeforeOrWaitCnt,
                'paymentStatusFailureCnt' => $paymentStatusFailureCnt,
                'request_status' => $this->getPayReqStatusList(),
                'bulkfeeForm' => $bulkfeeForm->createView(),
                'bulkpriceForm' => $bulkpriceForm->createView(),
                'searchForm' => $searchForm->createView(),
                'pagination' => $pagination,
                'pageMaxis' => $pageMaxis,
                'page_no' => $page_no,
                'page_count' => $pageCount,
                'has_errors' => false,
            ]
        );
    }

    /**
     * CSV決済依頼管理 明細
     *
     * @Route("/%eccube_admin_route%/order/vt4g_order_csv_request/{event_id}/edit/{request_id}", requirements={"event_id" = "\d+", "request_id" = "\d+"}, name="vt4g_admin_order_csv_request_edit")
     * @param Request $request リクエストデータ
     * @param int $event_id  決済依頼イベント番号
     * @param int $request_id 決済依頼番号
     * @return object ビューレスポンス
     *
     */
    public function edit(Request $request, int $event_id = null, int $request_id = null)
    {

        $requestStatus_before_creation = $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['BEFORE_CREATION'];
        $requestStatus_not_applicable = $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['NOT_APPLICABLE'];


        if ('POST' === $request->getMethod()) {

            // リクエストを設定する空のフォームを作成
            $builder = $this->formFactory->createBuilder(PaymentRequestType::class);
            $form = $builder->getForm();

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $this->mdkLogger->info(trans('vt4g_plugin.admin.payment_request.update.start'));

                $post = $form->getData();
                // 決済依頼明細
                $payment_target_not_exists = true;

                // 決済依頼情報
                $entPaymentRequest = $this->em->getRepository(Vt4gPaymentRequest::class)->findOneBy(['id' => $request_id]);

                // 決済依頼明細の更新
                // $point_total = $delivery_fee_total = 0;
                foreach ($post['pay_req_items'] as $item) {
                    $entPaymentRequestItem = $this->em->getRepository(Vt4gPaymentRequestItem::class)->findOneBy(['id' => $item['id']]);
                    // 明細区分が商品
                    if ($item['order_item_type_id'] == 1) {
                        // 付与ポイント
                        $entPaymentRequestItem->setPoint($item['point']);

                        $this->mdkLogger->info(
                            sprintf(trans('vt4g_plugin.admin.payment_request.request_id'),$request_id)
                            .sprintf(trans('vt4g_plugin.admin.payment_request.request_detail_id'),$item['id'])
                            .sprintf(trans('vt4g_plugin.admin.payment_request.point'),$item['point'])
                        );

                        // CSV対象
                        if ($entPaymentRequest->getRequestStatus() ===  $requestStatus_before_creation
                              || $entPaymentRequest->getRequestStatus() ===  $requestStatus_not_applicable) {
                            // 決済依頼ステータスが「未作成」か「対象外」の場合のみ更新

                            if (isset($item['payment_target'])) {  // ラベルの場合はfalseになる。

                                $entPaymentRequestItem->setPaymentTarget($item['payment_target']);

                                $msg = $item['payment_target'] == 1 ? "対象" : "対象外";
                                $this->mdkLogger->info(
                                    sprintf(trans('vt4g_plugin.admin.payment_request.request_id'),$request_id)
                                    .sprintf(trans('vt4g_plugin.admin.payment_request.request_detail_id'),$item['id'])
                                    .sprintf(trans('vt4g_plugin.admin.payment_request.csv_target'),$msg)
                                );

                                if ($item['payment_target'] == 1) {
                                  $payment_target_not_exists = false; // 一件でも決済対象が存在すれば良い
                                }
                            }
                        }
                        // $point_total += $item['point'];

                    } elseif ($item['order_item_type_id'] == 2) {
                    // 明細区分が送料

                        $entPaymentRequestItem->setAmount($item['amount']);

                        $this->mdkLogger->info(
                            sprintf(trans('vt4g_plugin.admin.payment_request.request_id'),$request_id)
                            .sprintf(trans('vt4g_plugin.admin.payment_request.request_detail_id'),$item['id'])
                            .sprintf(trans('vt4g_plugin.admin.payment_request.price'),$item['amount'])
                        );
                    }
                    $this->em->persist($entPaymentRequestItem);
                }

                // 決済依頼の更新
                $entPaymentRequest->setSettlementFee($post['settlement_fee']);

                $this->mdkLogger->info(
                    sprintf(trans('vt4g_plugin.admin.payment_request.request_id'),$request_id)
                    .sprintf(trans('vt4g_plugin.admin.payment_request.fee'),$post['settlement_fee'])
                );


                // 決済依頼ステータスの変更（CSV作成前、対象外の場合のみ）
                if ($entPaymentRequest->getRequestStatus() === $requestStatus_before_creation) {
                    // 現在のステータスが「作成前」である場合
                    if ($payment_target_not_exists) {
                        // CSV対象が存在しない場合は、決済依頼ステータスを「対象外」に変更
                        $entPaymentRequest->setRequestStatus($requestStatus_not_applicable);
                        $this->addInfo('vt4g_plugin.admin.payment_request.detail.not_applicable', 'admin');
                    }
                } elseif ($entPaymentRequest->getRequestStatus() === $requestStatus_not_applicable) {

                    // 現在の決済依頼ステータスが「対象外」である場合
                    if (!$payment_target_not_exists) {

                        // CSV対象が存在する場合は、決済依頼ステータスを「作成前」に変更
                        $entPaymentRequest->setRequestStatus($requestStatus_before_creation);
                        $this->addInfo('vt4g_plugin.admin.payment_request.detail.before_creation', 'admin');
                    }
                }
                $this->em->persist($entPaymentRequest);
                $this->em->flush();

                // CSV対象の明細を元に、決済依頼の合計商品金額、合計付与ポイントを再集計
                $this->em->getRepository(Vt4gPaymentRequest::class)->reaggregateOrderAndPointTotalForCsvTarget($request_id);

                // 合計配送料を再集計
                // 出荷IDごとにCSV対象の商品がいない場合は0、いる場合は送料に計上
                $this->em->getRepository(Vt4gPaymentRequest::class)->reaggregateDeliveryFeeTotalForCsvTarget($request_id);

                $this->mdkLogger->info(trans('vt4g_plugin.admin.payment_request.update.complete'));
                $this->addSuccess(trans('vt4g_plugin.admin.payment_request.update.complete'), 'admin');
            }

        }

        // 常に最新状態でフォームを作成

        // 決済依頼イベント情報
        $event = $this->em->getRepository(Vt4gPaymentReqEvent::class)->getRequestEvent($event_id);
        // 決済依頼情報
        $pay_request = $this->em->getRepository(Vt4gPaymentRequest::class)->getPaymentRequest($request_id);
        // 決済依頼イベント情報を取得
        $request_items = $this->em->getRepository(Vt4gPaymentRequestItem::class)->getItems($request_id);

        // PaymentReqEventTypeでフォーム作成
        // フォーム値を設定
        $pay_req = new Vt4gPaymentRequest();
        foreach ($request_items as $item) {
            $pay_req_item = new Vt4gPaymentRequestItem();
            $pay_req_item->setId($item['id']);
            $pay_req_item->setOrderItemTypeId($item['order_item_type_id']);
            $pay_req_item->setAmount($item['amount']);
            $pay_req_item->setQuantity($item['quantity']);
            $pay_req_item->setPoint($item['point']);
            $pay_req_item->setPaymentTarget($item['payment_target']==1);

            $pay_req->addPayReqItem($pay_req_item);
        }
        $pay_req->setSettlementFee($pay_request['settlement_fee']);

        // フォーム
        $builder = $this->formFactory->createBuilder(PaymentRequestType::class, $pay_req);
        $form = $builder->getForm();

        // エラー処理のためにここでハンドル
        $form->handleRequest($request);

        // 数値入力欄の制御のため決済依頼ステータスが「作成前」または「反映待ち」かを判定
        if ($this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['BEFORE_CREATION'] === $pay_request['request_status']
              || $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['WAITING_FOR_REFRECTON'] === $pay_request['request_status']) {

            $numericInputFieldDiable = false;
        } else {

          $numericInputFieldDiable = true;
        }

        return $this->render(
            'VeriTrans4G/Resource/template/admin/Order/csv_request_edit.twig',
            [
                'event_id' => $event_id,
                'request_id' => $request_id,
                'form' => $form->createView(),
                'item_vals' => $request_items,
                'event' => $event,
                'pay_request' => $pay_request,
                'request_status' => $this->getPayReqStatusList(),
                'vt4gconst' => $this->vt4gConst,
                'numericInputFieldDiable' => $numericInputFieldDiable,
            ]
        );
    }

    /**
     * 決済依頼ステータスリストを取得
     * @return array 決済依頼ステータスの名称
     */
    public function getPayReqStatusList()
    {
        return $this->util->getPayReqStatusList();
    }

    /**
     * 決済依頼CSVの出力.
     *
     * @Route("/%eccube_admin_route%/order/vt4g_order_csv_request/export/{event_id}", requirements={"event_id" = "\d+"}, name="vt4g_admin_order_csv_request_export")
     * @param Request $request リクエストデータ
     * @param int $event_id  決済依頼イベント番号
     * @return StreamedResponse $response CSVダウンロードレスポンスデータ
     *
     */
    public function export(Request $request, int $event_id = null)
    {
        // 決済依頼ステータス
        $WAITING_FOR_REFRECTON = $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['WAITING_FOR_REFRECTON'];

        // タイムアウトを無効にする.
        set_time_limit(0);
        // csv一時ファイル
        $tmp_hdlr = tmpfile();
        $tmp_file_path = stream_get_meta_data($tmp_hdlr)['uri'];
        // csvファイル生成
        $objCsvFile = new \SplFileObject($tmp_file_path, 'w');

        // プラグイン設定情報取得
        $plugin = $this->em->getRepository(Vt4gPlugin::class)->findAll();
        $pg_subdata = unserialize($plugin[0]->getSubData());
        // プラグイン決済情報取得用
        $orderPaymentRepo = $this->em->getRepository(Vt4gOrderPayment::class);
        // ユーザ情報取得用
        $customerRepo = $this->em->getRepository(Customer::class);

        // csvヘッダ行（先頭3行）
        $objCsvFile->fputcsv(['10001', $pg_subdata['dummy_mode_flg']]); // データ種別
        $objCsvFile->fputcsv(['21000', preg_replace('/^(.+?)cc$/', '$1', $pg_subdata['merchant_ccid'])]);  // マーチャントID
        $objCsvFile->fputcsv(['31001']); // -- 空行

        // 決済依頼データ(body)
        $find_by = [
            'req_event_id' => $event_id,
            'request_status' => [
                $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['BEFORE_CREATION'],
                $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['WAITING_FOR_REFRECTON'],
            ]
        ];
        $payment_requests = $this->em->getRepository(Vt4gPaymentRequest::class)->findBy($find_by);

        $cnt = 0;
        foreach ($payment_requests as $req) {
            $row = []; // 行配列初期化

            $order_payment = $orderPaymentRepo->findOneBy(['order_id' => $req->getFirstOrderId()]);
            $op_memo10 = unserialize($order_payment->getMemo10());

            $customer = $customerRepo->findOneBy(['id' => $req->getCustomerId()]);

            $row[] = '32001';     // レコード種別
            $row[] = 'Authorize'; // サービスコマンド
            $row[] = $req->getTransactionId(); // 取引ID
            $row[] = ''; // 元取引ID
            $row[] = floor($req->getOrderTotal() + $req->getDeliveryFeeTotal() + $req->getSettlementFee()); // 金額
            $row[] = ''; // カード番号
            $row[] = ''; // カード有効期限
            $row[] = $op_memo10['card_type']; // JPO 支払情報
            $row[] = 'false'; // 売上フラグ false:与信のみ (継続課金は与信のみで強制)
            $row[] = $customer->vt4g_account_id; // 会員ID
            $row[] = ''; // カードID
            $row[] = ''; // 標準カードフラグ
            $row[] = ''; // 課金グループID
            $row[] = ''; // 課金開始日
            $row[] = ''; // 課金終了日
            $row[] = ''; // 都度／初回課金金額
            $row[] = ''; // 継続課金金額
            $row[] = ''; // 取引メモ
            $row[] = ''; // キー情報

            $objCsvFile->fputcsv($row);
            $cnt++;

            $req->setRequestStatus($WAITING_FOR_REFRECTON);
            $this->em->persist($req);
            $this->em->flush();
        }

        // csvフッタ行 (最終3行)
        $objCsvFile->fputcsv(['39001', $cnt]); // データ件数
        $objCsvFile->fputcsv(['29000', $cnt]); // マーチャント毎データ件数
        $objCsvFile->fputcsv(['90001', $cnt]); // データ総件数

        $response = new StreamedResponse();
        $response->setCallback(function () use ($tmp_file_path) {
            readfile($tmp_file_path);
        });

        $now = new \DateTime();
        $filename = 'payment_request_'.$now->format('YmdHis').'.csv';

        // CSV作成日の更新
        $requsetEvent = $this->em->getRepository(Vt4gPaymentReqEvent::class)->findOneBy(['id' => $event_id]);
        $requsetEvent->setCsvCreateDate($now);
        $requsetEvent->setFileName($filename);

        $this->em->flush();

        // レスポンスの作成
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);
        $response->send();

        $this->mdkLogger->info(
            trans('vt4g_plugin.admin.payment_request.csv_request_export')
            .sprintf(trans('vt4g_plugin.admin.payment_request.event_id'),$event_id)
            .sprintf(trans('vt4g_plugin.file_name'),$filename)
        );

        return $response;
    }


    /**
     * 決済依頼明細情報のCSV出力
     *
     * @param Request $request リクエストデータ
     * @param int $event_id  決済依頼イベント番号
     * @return StreamedResponse $response CSVダウンロードレスポンスデータ
     * * @Route("/%eccube_admin_route%/order/vt4g_order_csv_request/detail_export/{event_id}", requirements={"event_id" = "\d+"}, name="vt4g_admin_order_csv_request_detail_export")
     *
     */
    public function detailExport(Request $request, int $event_id)
    {
        $dataList = $this->em->getRepository(Vt4gPaymentRequest::class)->getCsvtRequestDetail($event_id);
        $statusList = $this->getPayReqStatusList();

        // タイムアウトを無効にする.
        set_time_limit(0);
        // csv一時ファイル
        $tmp_hdlr = tmpfile();
        $tmp_file_path = stream_get_meta_data($tmp_hdlr)['uri'];
        // csvファイル生成
        $objCsvFile = new \SplFileObject($tmp_file_path, 'w');

        $header = $this->vt4gConst['VT4G_CSV_REQUEST_DETAIL_HEADER'];
        mb_convert_variables($this->eccubeConfig['eccube_csv_export_encoding'], 'UTF-8', $header);
        $objCsvFile->fputcsv($header);

        foreach ($dataList as $data) {
            // 決済依頼ステータスは名称に変換
            $data['request_status'] = $statusList[$data['request_status']];
            mb_convert_variables($this->eccubeConfig['eccube_csv_export_encoding'], 'UTF-8', $data);
            $objCsvFile->fputcsv($data);
        }

        // レスポンスの作成
        $response = new StreamedResponse();
        $response->setCallback(function () use ($tmp_file_path) {
            readfile($tmp_file_path);
        });

        $now = new \DateTime();
        $filename = 'payment_request_detail_event_id_'.$event_id.'_'.$now->format('YmdHis').'.csv';
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);
        $response->send();

        $this->mdkLogger->info(
            trans('vt4g_plugin.admin.payment_request.csv_request_detail_export')
            .sprintf(trans('vt4g_plugin.admin.payment_request.event_id'),$event_id)
            .sprintf(trans('vt4g_plugin.file_name'),$filename)
        );

        return $response;

    }
}
