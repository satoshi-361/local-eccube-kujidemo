<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Customize\Controller\Admin\Order;

use Eccube\Common\Constant;
use Eccube\Controller\AbstractController;
use Eccube\Entity\ExportCsvRow;
use Eccube\Entity\Master\CsvType;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\OrderPdf;
use Eccube\Entity\Shipping;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\Admin\OrderPdfType;
use Eccube\Form\Type\Admin\SearchOrderType;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\Master\PageMaxRepository;
use Eccube\Repository\Master\ProductStatusRepository;
use Eccube\Repository\Master\SexRepository;
use Eccube\Repository\OrderPdfRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PaymentRepository;
use Eccube\Repository\ProductStockRepository;
use Eccube\Service\CsvExportService;
use Eccube\Service\MailService;
use Eccube\Service\OrderPdfService;
use Eccube\Service\OrderStateMachine;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Util\FormUtil;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use Plugin\PrizesPerProduct\Entity\Config as PrizesPerProduct;
use Plugin\PrizeShow\Entity\PrizeList as PrizeList;
use Eccube\Entity\Master\CustomerStatus;
use Eccube\Repository\Master\CustomerStatusRepository;
use Eccube\Util\StringUtil;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Eccube\Util\CacheUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Eccube\Service\CsvImportService;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Eccube\Form\Type\Admin\CsvImportType;
use Eccube\Service\PurchaseFlow\PurchaseException;

class OrderController extends AbstractController
{
    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var CsvExportService
     */
    protected $csvExportService;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var SexRepository
     */
    protected $sexRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;

    /**
     * @var ProductStatusRepository
     */
    protected $productStatusRepository;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /** @var OrderPdfRepository */
    protected $orderPdfRepository;

    /**
     * @var ProductStockRepository
     */
    protected $productStockRepository;

    /** @var OrderPdfService */
    protected $orderPdfService;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var OrderStateMachine
     */
    protected $orderStateMachine;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * OrderController constructor.
     *
     * @param PurchaseFlow $orderPurchaseFlow
     * @param CsvExportService $csvExportService
     * @param CustomerRepository $customerRepository
     * @param PaymentRepository $paymentRepository
     * @param SexRepository $sexRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param PageMaxRepository $pageMaxRepository
     * @param ProductStatusRepository $productStatusRepository
     * @param ProductStockRepository $productStockRepository
     * @param OrderRepository $orderRepository
     * @param OrderPdfRepository $orderPdfRepository
     * @param ValidatorInterface $validator
     * @param OrderStateMachine $orderStateMachine ;
     */
    public function __construct(
        PurchaseFlow $orderPurchaseFlow,
        CsvExportService $csvExportService,
        CustomerRepository $customerRepository,
        PaymentRepository $paymentRepository,
        SexRepository $sexRepository,
        OrderStatusRepository $orderStatusRepository,
        PageMaxRepository $pageMaxRepository,
        ProductStatusRepository $productStatusRepository,
        ProductStockRepository $productStockRepository,
        OrderRepository $orderRepository,
        OrderPdfRepository $orderPdfRepository,
        ValidatorInterface $validator,
        OrderStateMachine $orderStateMachine,
        MailService $mailService
    ) {
        $this->purchaseFlow = $orderPurchaseFlow;
        $this->csvExportService = $csvExportService;
        $this->customerRepository = $customerRepository;
        $this->paymentRepository = $paymentRepository;
        $this->sexRepository = $sexRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->pageMaxRepository = $pageMaxRepository;
        $this->productStatusRepository = $productStatusRepository;
        $this->productStockRepository = $productStockRepository;
        $this->orderRepository = $orderRepository;
        $this->orderPdfRepository = $orderPdfRepository;
        $this->validator = $validator;
        $this->orderStateMachine = $orderStateMachine;
        $this->mailService = $mailService;
    }

    /**
     * 受注一覧画面.
     *
     * - 検索条件, ページ番号, 表示件数はセッションに保持されます.
     * - クエリパラメータでresume=1が指定された場合、検索条件, ページ番号, 表示件数をセッションから復旧します.
     * - 各データの, セッションに保持するアクションは以下の通りです.
     *   - 検索ボタン押下時
     *      - 検索条件をセッションに保存します
     *      - ページ番号は1で初期化し、セッションに保存します。
     *   - 表示件数変更時
     *      - クエリパラメータpage_countをセッションに保存します。
     *      - ただし, mtb_page_maxと一致しない場合, eccube_default_page_countが保存されます.
     *   - ページング時
     *      - URLパラメータpage_noをセッションに保存します.
     *   - 初期表示
     *      - 検索条件は空配列, ページ番号は1で初期化し, セッションに保存します.
     *
     * @Route("/%eccube_admin_route%/order", name="admin_order")
     * @Route("/%eccube_admin_route%/order/page/{page_no}", requirements={"page_no" = "\d+"}, name="admin_order_page")
     * @Template("@admin/Order/index.twig")
     */
    public function index(Request $request, $page_no = null, PaginatorInterface $paginator)
    {
      $csvForm = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
      $headers = $this->getOrderCsvHeader();

        $builder = $this->formFactory
            ->createBuilder(SearchOrderType::class);

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_ORDER_INDEX_INITIALIZE, $event);

        $searchForm = $builder->getForm();

        /**
         * ページの表示件数は, 以下の順に優先される.
         * - リクエストパラメータ
         * - セッション
         * - デフォルト値
         * また, セッションに保存する際は mtb_page_maxと照合し, 一致した場合のみ保存する.
         **/
        $page_count = $this->session->get('eccube.admin.order.search.page_count',
            $this->eccubeConfig->get('eccube_default_page_count'));

        $page_count_param = (int) $request->get('page_count');
        $pageMaxis = $this->pageMaxRepository->findAll();

        if ($page_count_param) {
            foreach ($pageMaxis as $pageMax) {
                if ($page_count_param == $pageMax->getName()) {
                    $page_count = $pageMax->getName();
                    $this->session->set('eccube.admin.order.search.page_count', $page_count);
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
                $this->session->set('eccube.admin.order.search', FormUtil::getViewData($searchForm));
                $this->session->set('eccube.admin.order.search.page_no', $page_no);
            } else {
                // 検索エラーの際は, 詳細検索枠を開いてエラー表示する.
                return [
                    'searchForm' => $searchForm->createView(),
                    'pagination' => [],
                    'pageMaxis' => $pageMaxis,
                    'page_no' => $page_no,
                    'page_count' => $page_count,
                    'has_errors' => true,
                ];
            }
        } else {
            if (null !== $page_no || $request->get('resume')) {
                /*
                 * ページ送りの場合または、他画面から戻ってきた場合は, セッションから検索条件を復旧する.
                 */
                if ($page_no) {
                    // ページ送りで遷移した場合.
                    $this->session->set('eccube.admin.order.search.page_no', (int) $page_no);
                } else {
                    // 他画面から遷移した場合.
                    $page_no = $this->session->get('eccube.admin.order.search.page_no', 1);
                }
                $viewData = $this->session->get('eccube.admin.order.search', []);
                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);
            } else {
                /**
                 * 初期表示の場合.
                 */
                $page_no = 1;
                $viewData = [];

                if ($statusId = (int) $request->get('order_status_id')) {
                    $viewData = ['status' => $statusId];
                }

                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);

                // セッション中の検索条件, ページ番号を初期化.
                $this->session->set('eccube.admin.order.search', $viewData);
                $this->session->set('eccube.admin.order.search.page_no', $page_no);
            }
        }

        $qb = $this->orderRepository->getQueryBuilderBySearchDataForAdmin($searchData);

        $event = new EventArgs(
            [
                'qb' => $qb,
                'csvForm' => $csvForm,
                'searchData' => $searchData,
            ],
            $request
        );

        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_ORDER_INDEX_SEARCH, $event);

        $pagination = $paginator->paginate(
            $qb,
            $page_no,
            $page_count
        );

        return [
            'searchForm' => $searchForm->createView(),
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $page_count,
            'has_errors' => false,
            'OrderStatuses' => $this->orderStatusRepository->findBy([], ['sort_no' => 'ASC']),
            'csvForm' => $csvForm->createView(),
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/order/bulk_delete", name="admin_order_bulk_delete", methods={"POST"})
     */
    public function bulkDelete(Request $request)
    {
        $this->isTokenValid();
        $ids = $request->get('ids');
        foreach ($ids as $order_id) {
            $Order = $this->orderRepository
                ->find($order_id);
            if ($Order) {
                $this->entityManager->remove($Order);
                log_info('受注削除', [$Order->getId()]);
            }
        }

        $this->entityManager->flush();

        $this->addSuccess('admin.common.delete_complete', 'admin');

        return $this->redirect($this->generateUrl('admin_order', ['resume' => Constant::ENABLED]));
    }

    /**
     * 受注CSVの出力.
     *
     * @Route("/%eccube_admin_route%/order/export/order/{click_arr}", name="admin_order_export_order")
     *
     * @param Request $request
     *
     * @return StreamedResponse
     */
    public function exportOrder(Request $request, $click_arr = null)
    {
        $filename = 'order_'.(new \DateTime())->format('YmdHis').'.csv';
        $response = $this->exportCsv($request, 6, $filename, json_decode($click_arr));
        log_info('受注CSV出力ファイル名', [$filename]);

        return $response;
    }

    /**
     * 配送CSVの出力.
     *
     * @Route("/%eccube_admin_route%/order/export/shipping", name="admin_order_export_shipping")
     *
     * @param Request $request
     *
     * @return StreamedResponse
     */
    public function exportShipping(Request $request)
    {
        $filename = 'shipping_'.(new \DateTime())->format('YmdHis').'.csv';
        $response = $this->exportCsv($request, CsvType::CSV_TYPE_SHIPPING, $filename);
        log_info('配送CSV出力ファイル名', [$filename]);

        return $response;
    }

    /**
     * @param Request $request
     * @param $csvTypeId
     * @param string $fileName
     * @param $clicked_arr
     *
     * @return StreamedResponse
     */
    protected function exportCsv(Request $request, $csvTypeId, $fileName, $clicked_arr = null)
    {
        // タイムアウトを無効にする.
        set_time_limit(0);

        // sql loggerを無効にする.
        $em = $this->entityManager;
        $em->getConfiguration()->setSQLLogger(null);
        $response = new StreamedResponse();

        $array_result = array();          

        if(empty($clicked_arr)){
            $temp = $this->orderRepository->findAll();
            foreach($temp as $temp_item)
                array_push($clicked_arr, $temp_item->getId());
        }
        foreach($clicked_arr as $clicked_item) {
            $array_filter = array();
            $Order = $this->orderRepository->findOneBy(['id' => $clicked_item]);
            $OtherOrderItems = $Order->getMergedProductOrderItems();
    
            $PrizesPerProductRepo = $this->getDoctrine()->getRepository(PrizesPerProduct::class);
            $PrizeListRepo = $this->getDoctrine()->getRepository(PrizeList::class);
            foreach($OtherOrderItems as $OtherOrderItem) {
                $array = $PrizesPerProductRepo->findBy(['orderId' => $Order->getId(), 'productId' => $OtherOrderItem->getProduct()->getId(), 'prizeGrade' => NULL]);
                $text = '';
                foreach($array as $item) {
                    $text .= $item->getPrizeName();
                }
    
                $array = $PrizesPerProductRepo->findBy(['orderId' => $Order->getId(), 'productId' => $OtherOrderItem->getProduct()->getId()]);
                $Categories_temp = $OtherOrderItem->getProduct()->getProductCategories();
                $Categories = array();
                foreach($Categories_temp as $key => $item) {
                    array_push($Categories, $item->getCategory()->getName());
                }
                $is_bulk = false;
                foreach($array as $item) {
                    if($item->getPrizeGrade() != NULL) {
                        $count = substr_count($text, $item->getId().',');
                        if (in_array('大人買いくじ', $Categories)) {
                            $prizeList = $PrizeListRepo->findOneBy(['name' => $item->getPrizeName()]);
                            $adult_prizes = $prizeList->getSettings()->getValues();
                            foreach($adult_prizes as $adult_item) {
                                array_push($array_filter, array( $adult_item->getName(), $count ));
                            }
                            $is_bulk = false;
                        } else if(in_array('まとめ買いくじ', $Categories)) {
                            if (!$is_bulk) {
                                $bulk_array = explode('個###', $item->getPrizeName());
                                foreach($bulk_array as $key => $bulk_item) {
                                    if ($key == count($bulk_array) - 1) break;
                                    $bulk_item_name = explode(':', $bulk_item)[0];
                                    $bulk_item_count = intval(explode(':', $bulk_item)[1]);
                                    $temp_count = $bulk_item_count * $count;
        
                                    // $prizeList = $PrizeListRepo->findOneBy(['name' => $bulk_item_name]);
                                    // $text = $bulk_item_name. '( ';
                                    // $adult_prizes = $prizeList->getSettings()->getValues();
                                    // $i = 0;
                                    // foreach($adult_prizes as $adult_item) {
                                    //     $text .= $adult_item->getName();
                                    //     if($i++ < count($adult_prizes) - 1) $text .= ', ';
                                    // }
                                    // $text .= ' )';
                                    array_push($array_filter, array( $bulk_item_name, $temp_count ));
                                }
                                $is_bulk = true;
                            }
                            else continue;
                        }
                        else {
                            if ($count) array_push($array_filter, array($item->getPrizeName(), $count));
                            $is_bulk = false;
                        }
                    }
                }
            }
            $result_index = array();
            $result = array();
    
            foreach($array_filter as $item) array_push($result_index, $item[0]);
            $result_index = array_unique($result_index, SORT_REGULAR);
            
            foreach($result_index as $item_index) {
                $sum = 0;
                foreach($array_filter as $item) {
                    if ($item[0] == $item_index) $sum += $item[1];
                }
                array_push($result, array($item_index, $sum));
            }
            $array_result[$clicked_item] = $result;
        }

        $response->setCallback(function () use ($request, $csvTypeId, $clicked_arr, $array_result) {
        // $response->setCallback(function () use ($request, $csvTypeId) {
            // CSV種別を元に初期化.
            $this->csvExportService->initCsvType($csvTypeId);

            // ヘッダ行の出力.
            $max_column_count = 0;
            foreach($array_result as $array_result_item) {
                if ($max_column_count < count($array_result_item))
                    $max_column_count = count($array_result_item);
            }
            $this->csvExportService->exportHeader($max_column_count);

            //受注データ検索用のクエリビルダを取得.
            $qb = $this->csvExportService
                ->getOrderQueryBuilder($request);

            //データ行の出力.
            $this->csvExportService->setExportQueryBuilder($qb);

            $this->csvExportService->exportData(function ($entity, $csvService) use ($request, $clicked_arr, $array_result) {
            // $this->csvExportService->exportData(function ($entity, $csvService) use ($request) {
                $Csvs = $csvService->getCsvs();

                $Order = $entity;
                
                if(in_array($Order->getId(), $clicked_arr) || empty($clicked_arr)){

                    $OrderItems = $Order->getOrderItems();

                    // foreach ($OrderItems as $OrderItem) {
                        $OrderItem = $OrderItems[0];
                        $ExportCsvRow = new ExportCsvRow();
                        // CSV出力項目と合致するデータを取得.
                        foreach ($Csvs as $Csv) {
                            // 受注データを検索.
                            $ExportCsvRow->setData($csvService->getData($Csv, $Order));
                            if ($ExportCsvRow->isDataNull()) {
                                // 受注データにない場合は, 受注明細を検索.
                                $ExportCsvRow->setData($csvService->getData($Csv, $OrderItem));
                            }
                            if ($ExportCsvRow->isDataNull() && $Shipping = $OrderItem->getShipping()) {
                                // 受注明細データにない場合は, 出荷を検索.
                                $ExportCsvRow->setData($csvService->getData($Csv, $Shipping));

                                $shipping_name = $Shipping->getName01().$Shipping->getName02();
                                if ($csvService->getData($Csv, $shipping_name, 'shipping_name') != null)
                                    $ExportCsvRow->setData($csvService->getData($Csv, $shipping_name, 'shipping_name'));  

                                $shipping_furi = $Shipping->getKana01().$Shipping->getKana02();
                                if ($csvService->getData($Csv, $shipping_furi, 'shipping_furi') != null)
                                    $ExportCsvRow->setData($csvService->getData($Csv, $shipping_furi, 'shipping_furi'));  
                            }

                            $name = $Order->getName01().$Order->getName02();
                            if ($csvService->getData($Csv, $name, 'name') != null)
                                $ExportCsvRow->setData($csvService->getData($Csv, $name, 'name'));     

                            $name_furi = $Order->getKana01().$Order->getKana02();
                            if ($csvService->getData($Csv, $name_furi, 'name_furi') != null)
                                $ExportCsvRow->setData($csvService->getData($Csv, $name_furi, 'name_furi'));  

                            $event = new EventArgs(
                                [
                                    'csvService' => $csvService,
                                    'Csv' => $Csv,
                                    'OrderItem' => $OrderItem,
                                    'ExportCsvRow' => $ExportCsvRow,
                                ],
                                $request
                            );
                            $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_ORDER_CSV_EXPORT_ORDER, $event);
    
                            $ExportCsvRow->pushData();
                        }

                        foreach($array_result[$Order->getId()] as $item) {
                            if ( $csvService->getData($Csv, $item[0], 'prize_info') != null )
                                $ExportCsvRow->setData($csvService->getData($Csv, $item[0], 'prize_info'));  
                              
                                $event = new EventArgs(
                                    [
                                        'csvService' => $csvService,
                                        'Csv' => $Csv,
                                        'OrderItem' => $OrderItem,
                                        'ExportCsvRow' => $ExportCsvRow,
                                    ],
                                    $request
                                );
                                $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_ORDER_CSV_EXPORT_ORDER, $event); 
                                $ExportCsvRow->pushData();

                            if ( $csvService->getData($Csv, $item[1], 'prize_info') != null ) 
                                $ExportCsvRow->setData($csvService->getData($Csv, $item[1], 'prize_info'));     

                                $event = new EventArgs(
                                    [
                                        'csvService' => $csvService,
                                        'Csv' => $Csv,
                                        'OrderItem' => $OrderItem,
                                        'ExportCsvRow' => $ExportCsvRow,
                                    ],
                                    $request
                                );
                                $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_ORDER_CSV_EXPORT_ORDER, $event);
        
                                $ExportCsvRow->pushData();
                        }
                        $csvService->fputcsv($ExportCsvRow->getRow());
                }
            });
        });

        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$fileName);
        $response->send();

        return $response;
    }

    /**
     * Update to order status
     *
     * @Route("/%eccube_admin_route%/shipping/{id}/order_status", requirements={"id" = "\d+"}, name="admin_shipping_update_order_status", methods={"PUT"})
     *
     * @param Request $request
     * @param Shipping $Shipping
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function updateOrderStatus(Request $request, Shipping $Shipping)
    {
        if (!($request->isXmlHttpRequest() && $this->isTokenValid())) {
            return $this->json(['status' => 'NG'], 400);
        }

        $Order = $Shipping->getOrder();
        $OrderStatus = $this->entityManager->find(OrderStatus::class, $request->get('order_status'));

        if (!$OrderStatus) {
            return $this->json(['status' => 'NG'], 400);
        }

        $result = [];
        try {
            if ($Order->getOrderStatus()->getId() == $OrderStatus->getId()) {
                log_info('対応状況一括変更スキップ');
                $result = ['message' => trans('admin.order.skip_change_status', ['%name%' => $Shipping->getId()])];
            } else {
                if ($this->orderStateMachine->can($Order, $OrderStatus)) {
                    if ($OrderStatus->getId() == OrderStatus::DELIVERED) {
                        if (!$Shipping->isShipped()) {
                            $Shipping->setShippingDate(new \DateTime());
                        }
                        $allShipped = true;
                        foreach ($Order->getShippings() as $Ship) {
                            if (!$Ship->isShipped()) {
                                $allShipped = false;
                                break;
                            }
                        }
                        if ($allShipped) {
                            $this->orderStateMachine->apply($Order, $OrderStatus);
                        }
                    } else {
                        $this->orderStateMachine->apply($Order, $OrderStatus);
                    }

                    if ($request->get('notificationMail')) { // for SimpleStatusUpdate
                        $this->mailService->sendShippingNotifyMail($Shipping);
                        $Shipping->setMailSendDate(new \DateTime());
                        $result['mail'] = true;
                    } else {
                        $result['mail'] = false;
                    }
                    // 対応中・キャンセルの更新時は商品在庫を増減させているので商品情報を更新
                    if ($OrderStatus->getId() == OrderStatus::IN_PROGRESS || $OrderStatus->getId() == OrderStatus::CANCEL) {
                        foreach ($Order->getOrderItems() as $OrderItem) {
                            $ProductClass = $OrderItem->getProductClass();
                            if ($OrderItem->isProduct() && !$ProductClass->isStockUnlimited()) {
                                $this->entityManager->flush($ProductClass);
                                $ProductStock = $this->productStockRepository->findOneBy(['ProductClass' => $ProductClass]);
                                $this->entityManager->flush($ProductStock);
                            }
                        }
                    }
                    $this->entityManager->flush($Order);
                    $this->entityManager->flush($Shipping);

                    // 会員の場合、購入回数、購入金額などを更新
                    if ($Customer = $Order->getCustomer()) {
                        $this->orderRepository->updateOrderSummary($Customer);
                        $this->entityManager->flush($Customer);
                    }
                } else {
                    $from = $Order->getOrderStatus()->getName();
                    $to = $OrderStatus->getName();
                    $result = ['message' => trans('admin.order.failed_to_change_status', [
                        '%name%' => $Shipping->getId(),
                        '%from%' => $from,
                        '%to%' => $to,
                    ])];
                }

                log_info('対応状況一括変更処理完了', [$Order->getId()]);
            }
        } catch (\Exception $e) {
            log_error('予期しないエラーです', [$e->getMessage()]);

            return $this->json(['status' => 'NG'], 500);
        }

        return $this->json(array_merge(['status' => 'OK'], $result));
    }

    /**
     * Update to Tracking number.
     *
     * @Route("/%eccube_admin_route%/shipping/{id}/tracking_number", requirements={"id" = "\d+"}, name="admin_shipping_update_tracking_number", methods={"PUT"})
     *
     * @param Request $request
     * @param Shipping $shipping
     *
     * @return Response
     */
    public function updateTrackingNumber(Request $request, Shipping $shipping)
    {
        if (!($request->isXmlHttpRequest() && $this->isTokenValid())) {
            return $this->json(['status' => 'NG'], 400);
        }

        $trackingNumber = mb_convert_kana($request->get('tracking_number'), 'a', 'utf-8');
        /** @var \Symfony\Component\Validator\ConstraintViolationListInterface $errors */
        $errors = $this->validator->validate(
            $trackingNumber,
            [
                new Assert\Length(['max' => $this->eccubeConfig['eccube_stext_len']]),
                new Assert\Regex(
                    ['pattern' => '/^[0-9a-zA-Z-]+$/u', 'message' => trans('admin.order.tracking_number_error')]
                ),
            ]
        );

        if ($errors->count() != 0) {
            log_info('送り状番号入力チェックエラー');
            $messages = [];
            /** @var \Symfony\Component\Validator\ConstraintViolationInterface $error */
            foreach ($errors as $error) {
                $messages[] = $error->getMessage();
            }

            return $this->json(['status' => 'NG', 'messages' => $messages], 400);
        }

        try {
            $shipping->setTrackingNumber($trackingNumber);
            $this->entityManager->flush($shipping);
            log_info('送り状番号変更処理完了', [$shipping->getId()]);
            $message = ['status' => 'OK', 'shipping_id' => $shipping->getId(), 'tracking_number' => $trackingNumber];

            return $this->json($message);
        } catch (\Exception $e) {
            log_error('予期しないエラー', [$e->getMessage()]);

            return $this->json(['status' => 'NG'], 500);
        }
    }

    /**
     * @Route("/%eccube_admin_route%/order/export/pdf", name="admin_order_export_pdf")
     * @Template("@admin/Order/order_pdf.twig")
     *
     * @param Request $request
     *
     * @return array|RedirectResponse
     */
    public function exportPdf(Request $request)
    {
        // requestから出荷番号IDの一覧を取得する.
        $ids = $request->get('ids', []);

        if (count($ids) == 0) {
            $this->addError('admin.order.delivery_note_parameter_error', 'admin');
            log_info('The Order cannot found!');

            return $this->redirectToRoute('admin_order');
        }

        /** @var OrderPdf $OrderPdf */
        $OrderPdf = $this->orderPdfRepository->find($this->getUser());

        if (!$OrderPdf) {
            $OrderPdf = new OrderPdf();
            $OrderPdf
                ->setTitle(trans('admin.order.delivery_note_title__default'))
                ->setMessage1(trans('admin.order.delivery_note_message__default1'))
                ->setMessage2(trans('admin.order.delivery_note_message__default2'))
                ->setMessage3(trans('admin.order.delivery_note_message__default3'));
        }

        /**
         * @var FormBuilder
         */
        $builder = $this->formFactory->createBuilder(OrderPdfType::class, $OrderPdf);

        /* @var \Symfony\Component\Form\Form $form */
        $form = $builder->getForm();

        // Formへの設定
        $form->get('ids')->setData(implode(',', $ids));

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/order/export/pdf/download", name="admin_order_pdf_download")
     * @Template("@admin/Order/order_pdf.twig")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function exportPdfDownload(Request $request, OrderPdfService $orderPdfService)
    {
        /**
         * @var FormBuilder
         */
        $builder = $this->formFactory->createBuilder(OrderPdfType::class);

        /* @var \Symfony\Component\Form\Form $form */
        $form = $builder->getForm();
        $form->handleRequest($request);

        // Validation
        if (!$form->isValid()) {
            log_info('The parameter is invalid!');

            return $this->render('@admin/Order/order_pdf.twig', [
                'form' => $form->createView(),
            ]);
        }

        $arrData = $form->getData();

        // 購入情報からPDFを作成する
        $status = $orderPdfService->makePdf($arrData);

        // 異常終了した場合の処理
        if (!$status) {
            $this->addError('admin.order.export.pdf.download.failure', 'admin');
            log_info('Unable to create pdf files! Process have problems!');

            return $this->render('@admin/Order/order_pdf.twig', [
                'form' => $form->createView(),
            ]);
        }

        // ダウンロードする
        $response = new Response(
            $orderPdfService->outputPdf(),
            200,
            ['content-type' => 'application/pdf']
        );

        $downloadKind = $form->get('download_kind')->getData();

        // レスポンスヘッダーにContent-Dispositionをセットし、ファイル名を指定
        if ($downloadKind == 1) {
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$orderPdfService->getPdfFileName().'"');
        } else {
            $response->headers->set('Content-Disposition', 'inline; filename="'.$orderPdfService->getPdfFileName().'"');
        }

        log_info('OrderPdf download success!', ['Order ID' => implode(',', $request->get('ids', []))]);

        $isDefault = isset($arrData['default']) ? $arrData['default'] : false;
        if ($isDefault) {
            // Save input to DB
            $arrData['admin'] = $this->getUser();
            $this->orderPdfRepository->save($arrData);
        }

        return $response;
    }
    
    /**
     * 商品登録CSVヘッダー定義
     *
     * @return array
     */
    protected function getOrderCsvHeader()
    {
        return [
            trans('姓') => [
                'id' => 'name01',
                'description' => '姓',
                'required' => true,
            ],
            trans('名') => [
                'id' => 'name02',
                'description' => '名',
                'required' => true,
            ],
            trans('姓フリガナ') => [
                'id' => 'kana01',
                'description' => '姓フリガナ',
                'required' => false,
            ],
            trans('名フリガナ') => [
                'id' => 'kana02',
                'description' => '名フリガナ',
                'required' => false,
            ],
            trans('郵便番号') => [
                'id' => 'postal_code',
                'description' => '郵便番号',
                'required' => false,
            ],
            trans('国') => [
                'id' => 'Country',
                'description' => '国',
                'required' => false,
            ],
            trans('都道府県') => [
                'id' => 'Pref',
                'description' => '都道府県',
                'required' => false,
            ],
            trans('市区郡町村') => [
                'id' => 'addr01',
                'description' => '市区郡町村',
                'required' => false,
            ],
            trans('番地') => [
                'id' => 'addr02',
                'description' => '番地',
                'required' => false,
            ],
            trans('電話番号') => [
                'id' => 'phone_number',
                'description' => '電話番号',
                'required' => false,
            ],
            trans('Eメール') => [
                'id' => 'email',
                'description' => 'Eメール',
                'required' => true,
            ],
            trans('入会日') => [
                'id' => 'update_date',
                'description' => '入会日',
                'required' => true,
            ],
            trans('保有PT') => [
                'id' => 'point',
                'description' => '保有PT',
                'required' => true,
            ],
        ];
    }
    
    /**
     * @Route("/%eccube_admin_route%/order/csv_split_import", name="admin_order_csv_split_import", methods={"POST"})
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function importCsv(Request $request, CsrfTokenManagerInterface $tokenManager)
    {
        $this->isTokenValid();

        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }

        $choices = $this->getCsvTempFiles();

        $filename = $request->get('file_name');
        if (!isset($choices[$filename])) {
            throw new BadRequestHttpException();
        }

        $path = $this->eccubeConfig['eccube_csv_temp_realdir'].'/'.$filename;
        $request->files->set('admin_csv_import', ['import_file' => new UploadedFile(
            $path,
            'import.csv',
            'text/csv',
            filesize($path),
            null,
            true
        )]);

        $request->setMethod('POST');
        $request->request->set('admin_csv_import', [
            Constant::TOKEN_NAME => $tokenManager->getToken('admin_csv_import')->getValue(),
            'is_split_csv' => true,
            'csv_file_no' => $request->get('file_no'),
        ]);

        return $this->forwardToRoute('admin_order_csv_import');
    }

      /**
     * 会員登録CSVアップロード
     *
     * @Route("/%eccube_admin_route%/order/order_csv_upload", name="admin_order_csv_import", methods={"GET", "POST"})
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function csvOrder(Request $request, CacheUtil $cacheUtil)
    {        
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $headers = $this->getOrderCsvHeader();
        if ('POST' === $request->getMethod()) {
            // $form->handleRequest($request);
            // if ($form->isValid()) {
                // $this->isSplitCsv = $form['is_split_csv']->getData();
                // $this->csvFileNo = $form['csv_file_no']->getData();
                // $formFile = $form['import_file']->getData();

                $this->isSplitCsv = $request->request->get('admin_csv_import')['is_split_csv'];
                $this->csvFileNo = $request->request->get('admin_csv_import')['csv_file_no'];

                $formFile = $request->files->get('admin_csv_import')['import_file'];
                if (!empty($formFile)) {
                    log_info('商品CSV登録開始');
                    $data = $this->getImportData($formFile);
                    if ($data === false) {
                        $this->addErrors(trans('admin.common.csv_invalid_format'));

                        return $this->renderWithError($form, $headers, false);
                    }
                    $getId = function ($item) {
                        return $item['id'];
                    };
                    $requireHeader = array_keys(array_map($getId, array_filter($headers, function ($value) {
                        return $value['required'];
                    })));

                    $columnHeaders = $data->getColumnHeaders();

                    $size = count($data);

                    if ($size < 1) {
                        $this->addErrors(trans('admin.common.csv_invalid_no_data'));

                        return $this->renderWithError($form, $headers, false);
                    }

                    $headerSize = count($columnHeaders);
                    $headerByKey = array_flip(array_map($getId, $headers));
                    $deleteImages = [];

                    // $columnHeaders = array_flip($columnHeaders);

                    // foreach($headerByKey as $key => $header) {
                    //   $headerByKey[$key] = $columnHeaders[$header];
                    // }

                    $this->entityManager->getConfiguration()->setSQLLogger(null);
                    $this->entityManager->getConnection()->beginTransaction();
                    // CSVファイルの登録処理
                    foreach ($data as $row) {print_r($row); exit;
                        $line = $data->key() + 1;
                        
                        
                        if (isset($row[$headerByKey['name01']]) && StringUtil::isNotBlank($row[$headerByKey['name01']])) {
                          $Customer->setName01($row[$headerByKey['name01']]);
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }

                        $request1 = clone $request;
                        $request1->request->set('order', [
                            Constant::TOKEN_NAME => $tokenManager->getToken('admin_csv_import')->getValue(),
                            'OrderStatus' => '1',

                        ]);

                        $this->forwardToRoute('admin_order_csv_register');
                    }
                    $this->entityManager->flush();
                    $this->entityManager->getConnection()->commit();

                    log_info('商品CSV登録完了');
                    $message = 'admin.common.csv_upload_complete';
                    $this->session->getFlashBag()->add('eccube.admin.success', $message);

                    $cacheUtil->clearDoctrineCache();
                }
            // }
            
          return $this->json(['success' => true, 'message' => 'success']);
        }

        return $this->json(['success' => false, 'message' => 'failed']);
    }

    /**
     * 会員登録CSVアップロード
     *
     * @Route("/%eccube_admin_route%/order/order_csv_register", name="admin_order_csv_register", methods={"GET", "POST"})
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function csvRegister(Request $request)
    {
        $TargetOrder = null;
        $OriginOrder = null;
        
        $TargetOrder = new Order();
        $TargetOrder->addShipping((new Shipping())->setOrder($TargetOrder));

        $preOrderId = $this->orderHelper->createPreOrderId();
        $TargetOrder->setPreOrderId($preOrderId);

        // 編集前の受注情報を保持
        $OriginOrder = clone $TargetOrder;
        $OriginItems = new ArrayCollection();
        foreach ($TargetOrder->getOrderItems() as $Item) {
            $OriginItems->add($Item);
        }

        $builder = $this->formFactory->createBuilder(OrderType::class, $TargetOrder);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'OriginOrder' => $OriginOrder,
                'TargetOrder' => $TargetOrder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_ORDER_EDIT_INDEX_INITIALIZE, $event);

        $form = $builder->getForm();

        $form->handleRequest($request);
        $purchaseContext = new PurchaseContext($OriginOrder, $OriginOrder->getCustomer());

        if ($form['OrderItems']->isValid()) {
            $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_ORDER_EDIT_INDEX_PROGRESS, $event);

            $flowResult = $this->purchaseFlow->validate($TargetOrder, $purchaseContext);
            if ($flowResult->hasWarning() || $flowResult->hasError()) return false;
            
            if (!$flowResult->hasError() && $form->isValid()) {
                try {
                    $this->purchaseFlow->prepare($TargetOrder, $purchaseContext);
                    $this->purchaseFlow->commit($TargetOrder, $purchaseContext);
                } catch (PurchaseException $e) {
                    return false;
                }

                $OldStatus = $OriginOrder->getOrderStatus();
                $NewStatus = $TargetOrder->getOrderStatus();

                // ステータスが変更されている場合はステートマシンを実行.
                if ($TargetOrder->getId() && $OldStatus->getId() != $NewStatus->getId()) {
                    // 発送済に変更された場合は, 発送日をセットする.
                    if ($NewStatus->getId() == OrderStatus::DELIVERED) {
                        $TargetOrder->getShippings()->map(function (Shipping $Shipping) {
                            if (!$Shipping->isShipped()) {
                                $Shipping->setShippingDate(new \DateTime());
                            }
                        });
                    }
                    // ステートマシンでステータスは更新されるので, 古いステータスに戻す.
                    $TargetOrder->setOrderStatus($OldStatus);
                    try {
                        // FormTypeでステータスの遷移チェックは行っているのでapplyのみ実行.
                        $this->orderStateMachine->apply($TargetOrder, $NewStatus);
                    } catch (ShoppingException $e) {
                        $this->addError($e->getMessage(), 'admin');
                        return false;
                    }
                }

                $this->entityManager->persist($TargetOrder);
                $this->entityManager->flush();

                foreach ($OriginItems as $Item) {
                    if ($TargetOrder->getOrderItems()->contains($Item) === false) {
                        $this->entityManager->remove($Item);
                    }
                }
                $this->entityManager->flush();

                // 新規登録時はMySQL対応のためflushしてから採番
                $this->orderNoProcessor->process($TargetOrder, $purchaseContext);
                $this->entityManager->flush();

                // 会員の場合、購入回数、購入金額などを更新
                if ($Customer = $TargetOrder->getCustomer()) {
                    $this->orderRepository->updateOrderSummary($Customer);
                    $this->entityManager->flush();
                }

                $event = new EventArgs(
                    [
                        'form' => $form,
                        'OriginOrder' => $OriginOrder,
                        'TargetOrder' => $TargetOrder,
                        'Customer' => $Customer,
                    ],
                    $request
                );
                $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_ORDER_EDIT_INDEX_COMPLETE, $event);

                $this->addSuccess('admin.common.save_complete', 'admin');

                log_info('受注登録完了', [$TargetOrder->getId()]);

                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * @Route("/%eccube_admin_route%/order/csv_split", name="admin_order_csv_split", methods={"POST"})
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function splitCsv(Request $request)
    {
        $this->isTokenValid();

        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }

        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dir = $this->eccubeConfig['eccube_csv_temp_realdir'];
            if (!file_exists($dir)) {
                $fs = new Filesystem();
                $fs->mkdir($dir);
            }

            $data = $form['import_file']->getData();
            $src = new \SplFileObject($data->getRealPath());
            $src->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY);

            $fileNo = 1;
            $fileName = StringUtil::random(8);

            $dist = new \SplFileObject($dir.'/'.$fileName.$fileNo.'.csv', 'w');
            $header = $src->current();
            $src->next();
            $dist->fputcsv($header);

            $i = 0;
            while ($row = $src->current()) {
                $dist->fputcsv($row);
                $src->next();

                if (!$src->eof() && ++$i % $this->eccubeConfig['eccube_csv_split_lines'] === 0) {
                    $fileNo++;
                    $dist = new \SplFileObject($dir.'/'.$fileName.$fileNo.'.csv', 'w');
                    $dist->fputcsv($header);
                }
            }

            return $this->json(['success' => true, 'file_name' => $fileName, 'max_file_no' => $fileNo]);
        }

        return $this->json(['success' => false, 'message' => $form->getErrors(true, true)]);
    }

    protected function getCsvTempFiles()
    {
        $files = Finder::create()
            ->in($this->eccubeConfig['eccube_csv_temp_realdir'])
            ->name('*.csv')
            ->files();

        $choices = [];
        foreach ($files as $file) {
            $choices[$file->getBaseName()] = $file->getRealPath();
        }

        return $choices;
    }
    
    /**
     * 登録、更新時のエラー画面表示
     *
     * @param FormInterface $form
     * @param array $headers
     * @param bool $rollback
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    protected function renderWithError($form, $headers, $rollback = true)
    {
        if ($this->hasErrors()) {
            if ($rollback) {
                $this->entityManager->getConnection()->rollback();
            }
        }

        // $this->removeUploadedFile();

        return [
            'form' => $form->createView(),
            'headers' => $headers,
            'errors' => $this->errors,
        ];
    }
    
    /**
     * 登録、更新時のエラー画面表示
     */
    protected function addErrors($message)
    {
        $this->errors[] = $message;
    }

    /**
     * @return array
     */
    protected function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return boolean
     */
    protected function hasErrors()
    {
        return count($this->getErrors()) > 0;
    }

      /**
     * アップロードされたCSVファイルの行ごとの処理
     *
     * @param UploadedFile $formFile
     *
     * @return CsvImportService|bool
     */
    protected function getImportData(UploadedFile $formFile)
    {
        // アップロードされたCSVファイルを一時ディレクトリに保存
        $this->csvFileName = 'upload_'.StringUtil::random().'.'.$formFile->getClientOriginalExtension();
        $formFile->move($this->eccubeConfig['eccube_csv_temp_realdir'], $this->csvFileName);

        $file = file_get_contents($this->eccubeConfig['eccube_csv_temp_realdir'].'/'.$this->csvFileName);

        if ('\\' === DIRECTORY_SEPARATOR && PHP_VERSION_ID >= 70000) {
            // Windows 環境の PHP7 の場合はファイルエンコーディングを CP932 に合わせる
            // see https://github.com/EC-CUBE/ec-cube/issues/1780
            setlocale(LC_ALL, ''); // 既定のロケールに設定
            if (mb_detect_encoding($file) === 'UTF-8') { // UTF-8 を検出したら SJIS-win に変換
                $file = mb_convert_encoding($file, 'SJIS-win', 'UTF-8');
            }
        } else {
            // アップロードされたファイルがUTF-8以外は文字コード変換を行う
            $encode = StringUtil::characterEncoding($file, $this->eccubeConfig['eccube_csv_import_encoding']);
            if (!empty($encode) && $encode != 'UTF-8') {
                $file = mb_convert_encoding($file, 'UTF-8', $encode);
            }
        }

        $file = StringUtil::convertLineFeed($file);

        $tmp = tmpfile();
        fwrite($tmp, $file);
        rewind($tmp);
        $meta = stream_get_meta_data($tmp);
        $file = new \SplFileObject($meta['uri']);

        set_time_limit(0);

        // アップロードされたCSVファイルを行ごとに取得
        $data = new CsvImportService($file, $this->eccubeConfig['eccube_csv_import_delimiter'], $this->eccubeConfig['eccube_csv_import_enclosure']);

        return $data->setHeaderRowNumber(0) ? $data : false;
    }
}
