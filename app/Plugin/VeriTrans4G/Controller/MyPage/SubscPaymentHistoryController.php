<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\MyPage;

use Eccube\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Plugin\VeriTrans4G\Entity\Vt4gSubscOrder;
use Plugin\VeriTrans4G\Repository\Vt4gPaymentRequestRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * マイページ　継続課金決済履歴画面
 *
 */
class SubscPaymentHistoryController extends AbstractController
{

    /**
     * コンテナ
     */
    protected $container;

    /**
    * @var Vt4gPaymentRequestRepository
    */
    protected $paymentRequestRepository;

   /**
    * コンストラクタ
    * @param Vt4gPaymentRequestRepository $paymentRequestRepository
    */
    public function __construct(ContainerInterface $container, Vt4gPaymentRequestRepository $paymentRequestRepository)
    {
        $this->container = $container;
        $this->paymentRequestRepository = $paymentRequestRepository;
    }

    /**
     * 継続課金注文一覧を表示する.
     * @Route("/mypage/mypage_subsc_payment", name="mypage_vt4g_subsc_payment")
     * @param Request $request リクエストデータ
     * @param PaginatorInterface $paginator ページネーションデータ
     * @return object ビューレスポンス
     */
    public function index(Request $request , PaginatorInterface $paginator)
    {
        $customer = $this->getUser();

        $em = $this->container->get('doctrine.orm.entity_manager');
        $subscOrder = $em->getRepository(Vt4gSubscOrder::class);

        // 継続課金注文を取得する
        $qb = $subscOrder->getQueryBuilderByCustomerId($customer->getId());

        $pagination = $paginator->paginate(
            $qb,
            $request->get('pageno', 1),
            $this->eccubeConfig['eccube_search_pmax']
            );

        return $this->render(
            'VeriTrans4G/Resource/template/default/Mypage/vt4g_subsc_payment.twig',
            [
                'Customer'   => $customer,     // ナビの下のようこそ欄で使う
                'pagination' => $pagination,
            ]
        );
    }


   /**
    * 決済履歴詳細を表示する.
    *
    * @Route("/mypage/mypage_subsc_payment_history/{order_id}", name="mypage_vt4g_subsc_payment_history")
     * @param Request $request リクエストデータ
     * @param PaginatorInterface $paginator ページネーションデータ
     * @param int $order_id 注文番号
     * @return object ビューレスポンス
    */
    public function history(Request $request, PaginatorInterface $paginator, int $order_id = null)
    {
        $customer = $this->getUser();

        $session = $this->session;

        // ページネーション利用時は$order_idがnullになるのでセッションに保持しておく
        if (is_null($order_id)) {
            $order_id = $session->get('order_id');
        } else {
            $session->set('order_id', $order_id);
        }

        // 決済依頼履歴を取得する
        $qb = $this->paymentRequestRepository->getQueryBuilderByOrderId($order_id);

        $pagination = $paginator->paginate(
            $qb,
            $request->get('pageno', 1),
            $this->eccubeConfig['eccube_search_pmax']
        );

        return $this->render(
            'VeriTrans4G/Resource/template/default/Mypage/vt4g_subsc_payment_history.twig',
            [
              'Customer'   => $customer,     // ナビの下のようこそ欄で使う
              'pagination' => $pagination,
            ]
        );
    }

 }
