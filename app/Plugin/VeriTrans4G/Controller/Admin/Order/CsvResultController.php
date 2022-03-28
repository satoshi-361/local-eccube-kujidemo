<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin\Order;

use Eccube\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Eccube\Entity\Customer;
use Eccube\Form\Type\Admin\CsvImportType;
use Plugin\VeriTrans4G\Entity\Vt4gPlugin;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentRequest;
use Plugin\VeriTrans4G\Entity\Vt4gCsvResultLog;
use Plugin\VeriTrans4G\Entity\Vt4gSubscOrder;

/**
 * CSV決済結果反映
 *
 */
class CsvResultController extends AbstractController
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

    private $errors = [];

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container コンテナ
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->util = $container->get('vt4g_plugin.service.util');
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
        $this->columnHeader = $this->vt4gConst['ORDER_CSV_COLUMN_CONFIG']['NAME'];
    }

    /**
     * 受注CSVアップロード
     *
     * @Route("/%eccube_admin_route%/order/vt4g_order_csv_result", name="vt4g_admin_order_csv_result")
     */
    public function index(Request $request)
    {
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {

                $formFile = $form['import_file']->getData();

                if (!empty($formFile)) {
                    // ステータス値取得
                    $WAITING_FOR_REFRECTON = $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['WAITING_FOR_REFRECTON'];
                    $SUCCESS_PAYMENT = $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['SUCCESS_PAYMENT'];
                    $FAILURE_PAYMENT = $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS']['FAILURE_PAYMENT'];

                    // プラグイン設定情報取得
                    $plugin = $this->em->getRepository(Vt4gPlugin::class)->findAll();
                    if(empty($plugin)) {
                        $msg = trans('vt4g_plugin.admin.order_csv_result.plugin_data_error');
                        $this->mdkLogger->error($msg);
                        throw new \Exception($msg);
                    }
                    $pg_subdata = unserialize($plugin[0]->getSubData());

                    $paymentRequestRepository = $this->em->getRepository(Vt4gPaymentRequest::class);

                    // 既存の改行コード自動検出設定を保存
                    $auto_detect_line_endings = ini_get('auto_detect_line_endings');
                    // 改行コード自動検出設定を有効化
                    ini_set('auto_detect_line_endings', 1);

                    try {
                        // 5C対策の為、事前にUTF-8に変換
                        $tmp = tmpfile();
                        fwrite($tmp, mb_convert_encoding(file_get_contents($formFile->getPathname()), 'UTF-8', 'sjis-win'));
                        rewind($tmp);
                        $meta = stream_get_meta_data($tmp);
                        // csvファイルオブジェクト
                        $csvfile = new \SplFileObject($meta['uri'], 'r');

                        // csv各行をループ処理
                        $row_cnt = 0;

                        $msg = sprintf(trans('vt4g_plugin.admin.order_csv_result.start'),$formFile->getClientOriginalName());
                        $this->mdkLogger->info($msg);

                        while ($csvfile->valid()) {
                            $row_cnt++;
                            // 行取得
                            $row = $csvfile->fgetcsv();
                            // 1行目
                            if ($row_cnt === 1) {
                                if ($row[0] != '10001') {
                                    $msg = trans('vt4g_plugin.admin.order_csv_result.format_error').sprintf(trans('vt4g_plugin.admin.order_csv_result.row'),$row_cnt);
                                    $this->mdkLogger->error($msg);
                                    throw new \Exception($msg);
                                }
                                continue;
                            }
                            // 2行目
                            if ($row_cnt === 2) {
                                if ($row[0] != '21000') {
                                    $msg = trans('vt4g_plugin.admin.order_csv_result.format_error').sprintf(trans('vt4g_plugin.admin.order_csv_result.row'),$row_cnt);
                                    $this->mdkLogger->error($msg);
                                    throw new \Exception($msg);
                                }
                                // マーチャントIDが不一致の場合はエラー停止
                                if ($row[1] != preg_replace('/^(.+?)cc$/', '$1', $pg_subdata['merchant_ccid'])) {
                                    $msg = trans('vt4g_plugin.admin.order_csv_result.mid_unmatch').sprintf(trans('vt4g_plugin.admin.order_csv_result.row'),$row_cnt);
                                    $this->mdkLogger->error($msg);
                                    throw new \Exception($msg);
                                }
                                continue;
                            }
                            // 3行目
                            if ($row_cnt === 3) {
                                if ($row[0] != '31001') {
                                    $msg = trans('vt4g_plugin.admin.order_csv_result.format_error').sprintf(trans('vt4g_plugin.admin.order_csv_result.row'),$row_cnt);
                                    $this->mdkLogger->error($msg);
                                    throw new \Exception($msg);
                                }
                                continue;
                            }

                            if ($row_cnt >= 4) {
                                // 最終2行目 データ件数 $row[1]
                                if ($row[0] == '39001') continue;
                                // 最終1行目 マーチャント毎データ件数 $row[1]
                                if ($row[0] == '29000') continue;
                                // 最終行 データ総件数 $row[1]
                                if ($row[0] == '90001') break;
                                // 決済結果処理
                                if ($row[0] != '32001') {
                                    $msg = trans('vt4g_plugin.admin.order_csv_result.format_error').sprintf(trans('vt4g_plugin.admin.order_csv_result.row'),$row_cnt);
                                    $this->mdkLogger->error($msg);
                                    throw new \Exception($msg);
                                }
                                // CSVの取引IDで決済依頼データを取得
                                $transaction_id = $row[5]; // 取引ID
                                $payment_request = $paymentRequestRepository->findOneBy(['transaction_id' => $transaction_id]);
                                if (is_null($payment_request)){
                                    $msg = trans('vt4g_plugin.admin.order_csv_result.not_exists_transaction_id').sprintf(trans('vt4g_plugin.admin.order_csv_result.row'),$row_cnt);
                                    $this->mdkLogger->error($msg);
                                    throw new \Exception($msg);
                                }
                                // 決済依頼ステータスが反映待ち以外だったらスキップ
                                if ($payment_request->getRequestStatus() != $WAITING_FOR_REFRECTON) {
                                    $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.order_csv_result.skip'),$transaction_id));
                                    continue;
                                }
                                // 処理結果コード "success"：正常終了 "failure"：異常終了 "pending"：保留
                                $result_code = $row[1];
                                if ($result_code == 'success') {
                                    $payment_request->setRequestStatus($SUCCESS_PAYMENT);
                                    // ポイント加算
                                    $customer = $this->em->getRepository(Customer::class)->findOneBy(['id' => $payment_request['customer_id']]);
                                    $point = $payment_request['point_total'] + $customer->getPoint();
                                    $customer->setPoint($point);
                                    $this->em->persist($customer);
                                    // 継続課金テーブルに最新決済依頼番号を保存
                                    $subscOrder = $this->em->getRepository(Vt4gSubscOrder::class)
                                            ->findOneBy(['order_id' => $payment_request->getFirstOrderId()]);
                                    $subscOrder->setLatestPaymentReqNo($payment_request->getId());
                                    $this->em->persist($subscOrder);
                                } else {
                                    $payment_request->setRequestStatus($FAILURE_PAYMENT);
                                }

                                $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.order_csv_result.payment_result'),$transaction_id,$result_code));

                                // 決済反映日
                                $reflect_date = date('Y-m-d H:i:s'); // デフォルト（結果csvアップロード日時）
                                $reflect_date = $this->cnvDateTimeFormat($row[10]) ?: $reflect_date; // ゲートウェイ応答日時
                                $reflect_date = $this->cnvDateTimeFormat($row[12]) ?: $reflect_date; // センター応答日時
                                $obj_reflect_date = new \DateTime($reflect_date);
                                $payment_request->setReflectDate($obj_reflect_date);

                                // 決済依頼データの保存
                                $this->em->persist($payment_request);

                                // CSV決済ログテーブルの登録
                                $csvLogResult = new Vt4gCsvResultLog();
                                // $csvLogResult->setId() オートインクリメント
                                $csvLogResult->setRequestId($payment_request->getId());
                                $csvLogResult->setResultCode($result_code);
                                $csvLogResult->setErrMessage($row[3]);

                                $this->em->persist($csvLogResult);

                                $this->em->flush(); // 永続化
                            }

                        }

                        // 改行コードの自動検出を元の設定に戻す
                        ini_set('auto_detect_line_endings', $auto_detect_line_endings);

                    } catch (\Exception $e) {
                        $this->em->rollback();
                        $this->errors[] = $e->getMessage();
                    }
                }
            }

            if (empty($this->errors)) {
                // 完了通知
                $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.order_csv_result.complete'),$formFile->getClientOriginalName()));
                $this->addSuccess('vt4g_plugin.admin.order_csv_result.csv_upload.complete', 'admin');
            }
        }

        return $this->render(
            'VeriTrans4G/Resource/template/admin/Order/csv_result.twig',
            [
                'form' => $form->createView(),
                'errors' => $this->errors
            ]
        );
    }

    /**
     * YmdHis を Y-m-d H:i:s の形式に変換します
     * @param string $timestamp YmdHis形式の日時
     * @return string Y-m-d H:i:s形式の日時
     */
    function cnvDateTimeFormat($timestamp)
    {
        return preg_replace('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', '$1-$2-$3 $4:$5:$6', $timestamp);
    }
}
