<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin\Customer;

use Eccube\Controller\AbstractController;
use Plugin\VeriTrans4G\Repository\Master\Vt4gSubscSaleTypeRepository;
use Plugin\VeriTrans4G\Repository\Vt4gSubscOrderRepository;
use Plugin\VeriTrans4G\Entity\Vt4gSubscOrder;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentRequest;
use Plugin\VeriTrans4G\Entity\Vt4gSubscOrderItem;
use Plugin\VeriTrans4G\Form\Type\Admin\PaymentReqEventType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Knp\Component\Pager\PaginatorInterface;
use Eccube\Repository\Master\PageMaxRepository;

use Eccube\Util\FormUtil;

/**
 * 継続課金会員管理画面
 *
 */
class SubscCustomerController extends AbstractController
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
     * CSV取り込み結果
     */
    private $csvResult = [];

    /**
     * CSVヘッダー
     */
    private $columnHeader = '';

    /**
     * @var Vt4gSubscSaleTypeRepository
     */
    protected $saleTypeRepository;

    /**
     * @var Vt4gSubscOrderRepository
     */
    protected $subscOrderRepository;

    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container コンテナ
     * @return void
     */
    public function __construct(
        ContainerInterface $container,
        Vt4gSubscSaleTypeRepository $saleTypeRepository,
        Vt4gSubscOrderRepository $subscOrderRepository,
        PageMaxRepository $pageMaxRepository
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
        $this->saleTypeRepository = $saleTypeRepository;
        $this->subscOrderRepository = $subscOrderRepository;
    }

    /**
     * 継続課金会員一覧
     *
     * @Route("/%eccube_admin_route%/customer/vt4g_subsc_customer", name="vt4g_admin_subsc_customer")
     * @Route("/%eccube_admin_route%/customer/vt4g_subsc_customer/page/{page_no}", requirements={"page_no" = "\d+"}, name="vt4g_admin_subsc_customer_page")
     * @Route("/%eccube_admin_route%/customer/vt4g_subsc_customer/page/{page_no}/{customer_id}/{product_id}/{product_class_id}/{shipping_id}/{order_id}/{status}", requirements={"page_no" = "\d+", "customer_id" = "\d+", "product_id" = "\d+", "product_class_id" = "\d+", "shipping_id" = "\d+", "order_id" = "\d+", "status" = "\d+"}, name="vt4g_admin_subsc_customer_page_edit_status")
     */
    public function index(Request $request, $page_no = null, PaginatorInterface $paginator, $customer_id = null, $product_id = null, $product_class_id = null, $shipping_id = null, $order_id = null, $status = null)
    {

        // ステータスの変更
        if (isset($status) && isset($product_id) && is_numeric($product_class_id) && isset($shipping_id) && isset($order_id)) {
            $this->changeStatus($order_id, $product_id, $product_class_id, $shipping_id, $status, $customer_id);
            return $this->redirectToRoute('vt4g_admin_subsc_customer_page', ['page_no' => $page_no]);
        }

        $session = $this->session;

        $saleTypes = [];
        foreach ($this->saleTypeRepository->getList() as $st) {
            $saleTypes[$st->getSaleTypeId()] = $st->getName();
        }

        $builder = $this->createFormBuilder()
            ->add('sale_type_id', ChoiceType::class, [
                    'choices'  => array_flip($saleTypes),
                ]
            )
            ->add('event_name', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => $this->eccubeConfig['eccube_stext_len'],
                    ]),
                ],
            ]
        );
        $form = $builder->getForm();

        // ページ行数リスト
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

        $pagination = null;
        if ('POST' === $request->getMethod()) {
             // POSTリクエストのハンドリング
             $form->handleRequest($request);

            // 検索フォームなどのチェックがokの場合
             $searchCond = $form->getData();
             if ($form->isSubmitted() && $form->isValid()) {

                $page_no = 1;

                $session->set('eccube.admin.subsc.customer.search', FormUtil::getViewData($form));
                $session->set('eccube.admin.subsc.customer.search.page_no', $page_no);

            } else {
            // チェックエラー
                return $this->render(
                    'VeriTrans4G/Resource/template/admin/Customer/subsc_customer.twig',
                    [
                        'form' => $form->createView(),
                        'pagination' => [],
                        'page_no' => $page_no,
                        'pageMaxis' => $pageMaxis,
                        'page_count' => $pageCount,
                        'has_errors' => true,
                        'first_display' => false,
                    ]
                );
            }
        } else {

            // ページング検索　または　詳細→一覧の再表示
            // resumeは詳細画面から戻ってきたとき
            if (null !== $page_no || $request->get('resume')) {

                if (!is_null($page_no)) {
                  // ページ指定あり
                  $session->set('eccube.admin.subsc.customer.search.page_no', (int) $page_no);
                } else {
                  // セッションから取得
                  $page_no = $session->get('eccube.admin.subsc.customer.search.page_no', 1);
                }
                // 検索条件の復旧
                $viewData = $session->get('eccube.admin.subsc.customer.search', []);
            } else {

                return $this->render(
                    'VeriTrans4G/Resource/template/admin/Customer/subsc_customer.twig',
                    [
                        'form' => $form->createView(),
                        'pagination' => [],
                        'page_no' => $page_no,
                        'pageMaxis' => $pageMaxis,
                        'page_count' => $pageCount,
                        'has_errors' => false,
                        'first_display' => true,
                    ]
                );
            }
            // 検索条件の取得
            $searchCond = FormUtil::submitAndGetData($form, $viewData);
         }

         // 継続課金会員情報を取得
         $qb = $this->em->getRepository(Vt4gSubscOrder::class)
             ->getSubscCustomerList($searchCond);

         $pagination = $paginator->paginate(
             $qb,
             $page_no,
             $pageCount,
             [
               'distinct' => false,
             ]
         );

         return $this->render(
             'VeriTrans4G/Resource/template/admin/Customer/subsc_customer.twig',
             [
                 'form' => $form->createView(),
                 'pagination' => $pagination,
                 'page_no' => $page_no,
                 'pageMaxis' => $pageMaxis,
                 'page_count' => $pageCount,
                 'has_errors' => false,
                 'first_display' => false,
             ]
         );
     }

     /**
      * 継続課金会員管理(商品・履歴)
      *
      * @Route("/%eccube_admin_route%/customer/vt4g_subsc_customer/{customer_id}/{product_id}/{product_class_id}/{shipping_id}/{order_id}", requirements={"customer_id" = "\d+", "product_id" = "\d+", "product_class_id" = "\d+", "shipping_id" = "\d+", "order_id" = "\d+"}, name="vt4g_admin_subsc_customer_edit")
      * @Route("/%eccube_admin_route%/customer/vt4g_subsc_customer/{customer_id}/{product_id}/{product_class_id}/{shipping_id}/{order_id}/{status}", requirements={"customer_id" = "\d+", "product_id" = "\d+", "product_class_id" = "\d+", "shipping_id" = "\d+", "order_id" = "\d+", "status" = "\d+"}, name="vt4g_admin_subsc_customer_edit_status")
      */
     public function edit(Request $request, $customer_id = null, $product_id = null, $product_class_id = null, $shipping_id = null, $order_id = null, $status = null)
      {
          // ステータスの変更
          if (isset($status) && isset($product_id) && is_numeric($product_class_id) && isset($shipping_id) && isset($order_id)) {
              $this->changeStatus($order_id, $product_id, $product_class_id, $shipping_id, $status, $customer_id);
              return $this->redirectToRoute('vt4g_admin_subsc_customer_edit', ['customer_id' => $customer_id, 'product_id' => $product_id, 'product_class_id' => $product_class_id, 'shipping_id' => $shipping_id, 'order_id' => $order_id]);
          }
          // PaymentReqEventTypeでフォーム作成
          $builder = $this->formFactory->createBuilder(PaymentReqEventType::class);
          $form = $builder->getForm();

          $payment_request = $this->em->getRepository(Vt4gPaymentRequest::class)->getSubscCustomerProduct($customer_id, $product_id, $product_class_id, $shipping_id, $order_id);

          return $this->render(
              'VeriTrans4G/Resource/template/admin/Customer/subsc_customer_edit.twig',
              [
                  'form' => $form->createView(),
                  'payment_request' => $payment_request,
                  'request_status_list' => $this->util->getPayReqStatusList()
              ]
          );
      }

      /**
       * 継続課金ステータスを切り替えます
       * @param int $order_id 注文番号
       * @param int $product_id 商品ID
       * @param int $product_class_id 商品規格ID
       * @param int $shipping_id 出荷ID
       * @param int $status 継続課金ステータス(1:継続、2:解約)
       * @param int $customer_id 会員ID
       */
      public function changeStatus($order_id, $product_id, $product_class_id, $shipping_id, $status, $customer_id)
      {
          $soi = $this->em->getRepository(Vt4gSubscOrderItem::class)
          ->findOneBy(['order_id' => $order_id, 'product_id' => $product_id, 'product_class_id' => $product_class_id, 'shipping_id' => $shipping_id]);
          $soi->setSubscStatus(($status==1)?1:2);
          $this->em->persist($soi);
          $this->em->flush();

          $msg = sprintf(trans('vt4g_plugin.admin.subsc_customer.change_status.complete'),$status == 1 ? '継続' : '解約');
          $addMsg = sprintf(trans('vt4g_plugin.admin.subsc_customer.change_status.add_msg'),$customer_id,$order_id,$shipping_id,$product_id,$product_class_id);
          $this->addSuccess($msg, 'admin');
          $this->mdkLogger->info($msg.$addMsg);

      }
}
