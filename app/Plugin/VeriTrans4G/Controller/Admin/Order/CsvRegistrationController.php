<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin\Order;

use Eccube\Controller\AbstractController;
use Eccube\Common\EccubeConfig;
use Plugin\VeriTrans4G\Repository\Master\Vt4gSubscSaleTypeRepository;
use Plugin\VeriTrans4G\Repository\Vt4gSubscOrderRepository;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentReqEvent;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentRequest;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentRequestItem;
use Plugin\VeriTrans4G\Entity\Vt4gSubscOrderItem;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * CSV決済依頼登録
 *
 */
class CsvRegistrationController extends AbstractController
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
    protected $saletypeRepository;

    /**
     * @var Vt4gSubscOrderRepository
     */
    protected $subscOrderRepository;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container コンテナ
     * @return void
     */
    public function __construct(
        ContainerInterface $container,
        Vt4gSubscSaleTypeRepository $saletypeRepository,
        Vt4gSubscOrderRepository $subscOrderRepository,
        EccubeConfig $eccubeConfig
    )
    {
        $this->container = $container;
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->util = $container->get('vt4g_plugin.service.util');
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
        $this->columnHeader = $this->vt4gConst['ORDER_CSV_COLUMN_CONFIG']['NAME'];

        $this->saletypeRepository = $saletypeRepository;
        $this->subscOrderRepository = $subscOrderRepository;
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * 受注CSV決済依頼登録
     *
     * @Route("/%eccube_admin_route%/order/vt4g_order_csv_registration", name="vt4g_admin_order_csv_registration")
     * @Route("/%eccube_admin_route%/order/vt4g_order_csv_registration/{resettlementEventId}", requirements={"resettlementEventId" = "\d+"}, name="vt4g_admin_order_csv_registration")
     */
    public function index(Request $request, $resettlementEventId = null )
    {
        // フォームを生成
        $saleTypes = [];
        foreach ($this->saletypeRepository->getList() as $st) {
            $saleTypes[$st->getSaleTypeId()] = $st->getName();
        }
        $builder = $this->formFactory->createBuilder()
            ->add('sale_type_id', ChoiceType::class, [
                    'choices'  => array_flip($saleTypes),
                    'required' => true,
                ]
            )
            ->add('event_name', TextType::class, [
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length([
                        'max' => $this->eccubeConfig['eccube_stext_len'],
                    ]),
                ],
            ]);
        $form = $builder->getForm();

        // 再決済
        if (!is_null($resettlementEventId)) {
            $event = $this->em->getRepository(Vt4gPaymentReqEvent::class)->findOneBy(['id' => $resettlementEventId]);

            if (!is_null($event)) {
                // 販売種別の設定（初期値、表示制御）
                $form->get('sale_type_id')->setData($event->getSaleTypeId());
                // 再決済メッセージを設定
                $this->addInfo(sprintf('%sで決済失敗となった取引の再決済です。', $event->getEventName()), 'admin');
                // レンダリング
                return $this->render(
                    'VeriTrans4G/Resource/template/admin/Order/csv_registration.twig',
                    [
                        'form' => $form->createView(),
                        'saleTypeReadOnly' => 1,
                        'resettlementEventId' => $event->getId(),
                    ]
                );
            }
        }

        // POSTリクエストのハンドリング
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && 'POST' === $request->getMethod()) {

            $post = $form->getData();

            if(empty($post['sale_type_id'])) {
                $this->addError("販売種別名を選択してください", 'admin');
                return $this->render(
                    'VeriTrans4G/Resource/template/admin/Order/csv_registration.twig',
                    [
                        'form' => $form->createView(),
                        'saleTypeReadOnly' => 0,
                        'resettlementEventId' => '',
                    ]
                );
            }

            // 注文情報にない継続課金商品を解約にする
            $this->em->getRepository(Vt4gSubscOrderItem::class)->cancelOrderNotExists();

            // 決済依頼イベント情報を保存
            $paymentReqEvent = new Vt4gPaymentReqEvent();
            $paymentReqEvent->setSaleTypeId($post['sale_type_id'])
                            ->setEventName($post['event_name'])
                            ->setFileName('');
            $this->em->persist($paymentReqEvent);
            $this->em->flush();

            // 決済依頼イベントIDを取得
            $event_id = $paymentReqEvent->getId();
            $resettlementEventId = $request->request->get('resettlementEventId',null);

            // 再決済の決済依頼情報登録
            if (!is_null($resettlementEventId)) {
                $subsc_orders = $this->subscOrderRepository->getReWettlementOrderItems($resettlementEventId);
                $old_order_id = null;
                // 合計商品金額・合計配送料・合計手数料
                $orderTotal = $deliveryFeeTotal = 0;
                $subsc_count = 0;
                // 有効明細行フラグ
                $isActive = false;
                foreach ($subsc_orders as $subsc_order) {

                    // 継続の決済依頼明細件数が0件のデータはスキップ
                    if ($subsc_order['subsc_dtl_cnt'] == 0) {
                        continue;
                    }

                    // 決済依頼の登録（注文IDが変わったタイミングで登録）
                    if ($old_order_id != $subsc_order['order_id']) {
                        if (isset($old_order_id)) {
                            $paymentRequest->setOrderTotal($orderTotal)
                            ->setDeliveryFeeTotal($deliveryFeeTotal);
                            $this->em->persist($paymentRequest);
                            $this->em->flush();
                            // 合計商品金額・合計配送料・合計手数料の初期化
                            $orderTotal = $deliveryFeeTotal = 0;
                            // 有効明細行フラグリセット
                            $isActive = false;
                        }
                        $paymentRequest = new Vt4gPaymentRequest();
                        $paymentRequest->setReqEventId($event_id)
                        ->setCustomerId($subsc_order['customer_id'])
                        ->setFirstOrderId($subsc_order['order_id'])
                        ->setSettlementFee($subsc_order['settlement_fee']);
                        $this->em->persist($paymentRequest);
                        $this->em->flush();
                        // 決済依頼IDを取得
                        $payment_requst_id = $paymentRequest->getId();
                        // 取引IDを生成・保存
                        $paymentRequest->setTransactionId($subsc_order['transaction_id'].'_'.$payment_requst_id);
                        $this->em->persist($paymentRequest);
                        $this->em->flush();
                    }
                    // 継続課金ステータスチェック（解約はスキップ）
                    if ($subsc_order['order_item_type_id'] == 1) {
                        if (!isset($subsc_order['subsc_status'])) continue;
                    } else {
                        // 商品明細行が無い場合はスキップ
                        if (!$isActive) continue;
                    }

                    // 注文明細種別の処理
                    if ($subsc_order['order_item_type_id'] == 1) {
                        // 商品金額を集計(金額 * 数量)
                        $orderTotal += $subsc_order['amount'] * $subsc_order['quantity'];
                    } elseif ($subsc_order['order_item_type_id'] == 2) {
                        // 配送料の集計
                        $deliveryFeeTotal += $subsc_order['amount'];
                    }
                    // 決済依頼明細情報を生成
                    $paymentRequestItem = new Vt4gPaymentRequestItem();
                    $paymentRequestItem->setRequestId($payment_requst_id)
                    ->setSubscSaleTypeId($post['sale_type_id'])
                    ->setShippingId($subsc_order['shipping_id'])
                    ->setOrderItemTypeId($subsc_order['order_item_type_id'])
                    ->setProductId($subsc_order['product_id'])
                    ->setProductClassId($subsc_order['product_class_id'])
                    ->setAmount($subsc_order['amount'])
                    ->setQuantity($subsc_order['quantity']);
                    $this->em->persist($paymentRequestItem);
                    $this->em->flush();
                    // 現在の注文IDを保持
                    $old_order_id = $subsc_order['order_id'];
                    // 継続課金明細件数インクリメント
                    $subsc_count++;
                    // 有効明細行フラグ
                    $isActive = true;
                }
                // 最終の集計内容を保存
                if (isset($old_order_id)) {
                    $paymentRequest->setOrderTotal($orderTotal)
                    ->setDeliveryFeeTotal($deliveryFeeTotal);
                    $this->em->persist($paymentRequest);
                    $this->em->flush();
                }
            // 新規の決済依頼情報登録
            } else {
                // 除外ID用（決済成功履歴がある継続課金注文の注文IDをセットし、注文情報から決済依頼を作る処理からそれらを除外）
                $exclude_ids = [];
                /* -----------------------------------------------------------------
                 *  1 過去の決済成功履歴から決済依頼情報を作成（決済成功履歴のある継続課金注文のみ抽出）
                 * ----------------------------------------------------------------- */
                $subsc_orders = $this->subscOrderRepository->getExistingOrderItemsBySaleType($post['sale_type_id']);
                $old_order_id = null;

                // 合計商品金額・合計配送料・合計手数料
                $orderTotal = $deliveryFeeTotal = 0;
                $subsc_count = 0;
                // 有効明細行フラグ
                $isActive = false;
                foreach ($subsc_orders as $subsc_order) {

                    // 継続の決済依頼明細件数が0件のデータはスキップ
                    if ($subsc_order['subsc_dtl_cnt'] == 0) {
                        continue;
                    }

                    // 決済依頼の登録（注文IDが変わったタイミングで登録）
                    if ($old_order_id != $subsc_order['order_id']) {
                        if (isset($old_order_id)) {
                            $paymentRequest->setOrderTotal($orderTotal)
                            ->setDeliveryFeeTotal($deliveryFeeTotal);
                            $this->em->persist($paymentRequest);
                            $this->em->flush();
                            // 合計商品金額・合計配送料・合計手数料の初期化
                            $orderTotal = $deliveryFeeTotal = 0;
                            // 有効明細行フラグリセット
                            $isActive = false;
                        }
                        $paymentRequest = new Vt4gPaymentRequest();
                        $paymentRequest->setReqEventId($event_id)
                        ->setCustomerId($subsc_order['customer_id'])
                        ->setFirstOrderId($subsc_order['order_id'])
                        ->setSettlementFee($subsc_order['settlement_fee']);
                        $this->em->persist($paymentRequest);
                        $this->em->flush();
                        // 決済依頼IDを取得
                        $payment_requst_id = $paymentRequest->getId();
                        // 取引IDを生成・保存
                        $paymentRequest->setTransactionId($subsc_order['transaction_id'].'_'.$payment_requst_id);
                        $this->em->persist($paymentRequest);
                        $this->em->flush();
                    }
                    // 継続課金ステータスチェック（解約はスキップ）
                    if ($subsc_order['order_item_type_id'] == 1) {
                        if (!isset($subsc_order['subsc_status'])) continue;
                    } else {
                        // 商品明細行が無い場合はスキップ
                        if (!$isActive) continue;
                    }

                    // 注文明細種別の処理
                    if ($subsc_order['order_item_type_id'] == 1) {
                        // 商品金額を集計(金額 * 数量)
                        $orderTotal += $subsc_order['amount'] * $subsc_order['quantity'];
                    } elseif ($subsc_order['order_item_type_id'] == 2) {
                        // 配送料の集計
                        $deliveryFeeTotal += $subsc_order['amount'];
                    }
                    // 決済依頼明細情報を生成
                    $paymentRequestItem = new Vt4gPaymentRequestItem();
                    $paymentRequestItem->setRequestId($payment_requst_id)
                    ->setSubscSaleTypeId($post['sale_type_id'])
                    ->setShippingId($subsc_order['shipping_id'])
                    ->setOrderItemTypeId($subsc_order['order_item_type_id'])
                    ->setProductId($subsc_order['product_id'])
                    ->setProductClassId($subsc_order['product_class_id'])
                    ->setAmount($subsc_order['amount'])
                    ->setQuantity($subsc_order['quantity']);
                    $this->em->persist($paymentRequestItem);
                    $this->em->flush();
                    // 現在の注文IDを保持
                    $old_order_id = $subsc_order['order_id'];
                    // 除外ID
                    $exclude_ids[] = $subsc_order['order_id'];
                    // 継続課金明細件数インクリメント
                    $subsc_count++;
                    // 有効明細行フラグ
                    $isActive = true;
                }
                // 最終の集計内容を保存
                if (isset($old_order_id)) {
                    $paymentRequest->setOrderTotal($orderTotal)
                    ->setDeliveryFeeTotal($deliveryFeeTotal);
                    $this->em->persist($paymentRequest);
                    $this->em->flush();
                }

                /* -----------------------------------------------------------------
                 *  2 注文データから決済依頼データを作成（決済成功履歴なしの継続課金注文のみ抽出）
                 * ----------------------------------------------------------------- */
                $subsc_orders = $this->subscOrderRepository->getOrderItemsBySaleType($post['sale_type_id'], $exclude_ids);
                $old_order_id = null;

                // 合計商品金額・合計配送料・合計手数料
                $orderTotal = $deliveryFeeTotal = $settlementFeeTotal = 0;
                // 有効明細行フラグ
                $isActive = false;
                foreach ($subsc_orders as $subsc_order) {

                    // 継続の決済依頼明細件数が0件のデータはスキップ
                    if ($subsc_order['subsc_dtl_cnt'] == 0) {
                        continue;
                    }

                    if ($old_order_id != $subsc_order['order_id']) {
                        if (isset($old_order_id)) {
                            $paymentRequest->setOrderTotal($orderTotal)
                            ->setDeliveryFeeTotal($deliveryFeeTotal)
                            ->setSettlementFee($settlementFeeTotal);
                            $this->em->persist($paymentRequest);
                            $this->em->flush();
                            // 合計商品金額・合計配送料・合計手数料の初期化
                            $orderTotal = $deliveryFeeTotal = $settlementFeeTotal = 0;
                            // 有効明細行フラグリセット
                            $isActive = false;
                        }
                        $paymentRequest = new Vt4gPaymentRequest();
                        $paymentRequest->setReqEventId($event_id)
                        ->setCustomerId($subsc_order['customer_id'])
                        ->setFirstOrderId($subsc_order['order_id']);
                        $this->em->persist($paymentRequest);
                        $this->em->flush();
                        // 決済依頼IDを取得
                        $payment_requst_id = $paymentRequest->getId();
                        // 取引IDを生成・保存
                        $paymentRequest->setTransactionId($subsc_order['transaction_id'].'_'.$payment_requst_id);
                        $this->em->persist($paymentRequest);
                        $this->em->flush();
                    }
                    // 継続課金ステータスチェック（解約はスキップ）
                    if ($subsc_order['order_item_type_id'] == 1) {
                        if (!isset($subsc_order['subsc_status'])) continue;
                    } else {
                        // 商品明細行が無い場合はスキップ
                        if (!$isActive) continue;
                    }

                    // 明細毎の金額
                    $amount = 0;
                    // 注文明細種別の処理
                    if ($subsc_order['order_item_type_id'] == 1) {
                        // 商品金額を集計(商品に税をたし、数量を掛ける)
                        $amount = $subsc_order['price'] + $subsc_order['tax'];
                        $orderTotal += $amount * $subsc_order['quantity']; // 商品金額には数量を掛ける
                    } elseif ($subsc_order['order_item_type_id'] == 2) {
                        // 配送料の集計（税込）
                        $amount = $subsc_order['price'];
                        $deliveryFeeTotal += $amount;
                    } elseif ($subsc_order['order_item_type_id'] == 3) {
                        // 手数料の集計（税込）
                        $amount = $subsc_order['price'];
                        $settlementFeeTotal += $amount;
                        continue;
                    } else {
                        continue;
                    }
                    // 決済依頼明細情報を生成
                    $paymentRequestItem = new Vt4gPaymentRequestItem();
                    $paymentRequestItem->setRequestId($payment_requst_id)
                    ->setSubscSaleTypeId($post['sale_type_id'])
                    ->setShippingId($subsc_order['shipping_id'])
                    ->setOrderItemTypeId($subsc_order['order_item_type_id'])
                    ->setProductId($subsc_order['product_id'])
                    ->setProductClassId($subsc_order['product_class_id'])
                    ->setAmount($amount)
                    ->setQuantity($subsc_order['quantity']);
                    $this->em->persist($paymentRequestItem);
                    $this->em->flush();
                    // 現在の注文IDを保持
                    $old_order_id = $subsc_order['order_id'];
                    // 継続課金明細件数インクリメント
                    $subsc_count++;
                    // 有効明細行フラグ
                    $isActive = true;
                }
                // 最終の集計内容を保存
                if (isset($old_order_id)) {
                    $paymentRequest->setOrderTotal($orderTotal)
                    ->setDeliveryFeeTotal($deliveryFeeTotal)
                    ->setSettlementFee($settlementFeeTotal);
                    $this->em->persist($paymentRequest);
                    $this->em->flush();
                }

            }


            // 登録が成功したらCSV決済依頼管理の決済依頼一覧画面へリダイレクト
            if ($subsc_count > 0) {
                $this->mdkLogger->info(
                    trans('vt4g_plugin.admin.payment_request.save.complete')
                    .sprintf(trans('vt4g_plugin.admin.payment_request.event_id'),$event_id)
                );
                $this->addSuccess(trans('vt4g_plugin.admin.payment_request.save.complete'), 'admin');
                return $this->redirectToRoute('vt4g_admin_order_csv_request_list', ['event_id' => $event_id]);
            }

            // 登録0件の場合はリダイレクトせずにイベント情報を保存しないようにロールバックする
            $this->mdkLogger->info(
                trans('vt4g_plugin.no_data')
                .sprintf(trans('vt4g_plugin.admin.payment_request.sale_type'),$post['sale_type_id'])
            );
            $this->addWarning(trans('vt4g_plugin.no_data'), 'admin');
            $this->em->rollback();
        }

        return $this->render(
            'VeriTrans4G/Resource/template/admin/Order/csv_registration.twig',
            [
                'form' => $form->createView(),
                'saleTypeReadOnly' => 0,
                'resettlementEventId' => '',
            ]
        );
    }

}
