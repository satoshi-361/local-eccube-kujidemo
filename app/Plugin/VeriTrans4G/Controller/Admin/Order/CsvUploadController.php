<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin\Order;

use Eccube\Controller\Admin\AbstractCsvImportController;
use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Form\Type\Admin\CsvImportType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 受注CSVアップロード
 *
 */
class CsvUploadController extends AbstractCsvImportController
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
     * @Route("/%eccube_admin_route%/order/vt4g_order_csv_upload", name="vt4g_admin_order_csv_upload")
     */
    public function index(Request $request)
    {
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $formFile = $form['import_file']->getData();
                if (!empty($formFile)) {
                    $this->mdkLogger->info(trans('vt4g_plugin.order.csv.upload.start'));
                    $csv = $this->getImportData($formFile);
                    $size = count($csv);

                    if ($csv === false) {
                        $this->addCsvResult(trans('admin.common.csv_invalid_format'));
                    } elseif ($size < 1) {
                        $this->addCsvResult(trans('admin.common.csv_invalid_no_data'));
                    }
                    if ($this->hasCsvResult()) {
                        $this->mdkLogger->info(trans('vt4g_plugin.order.csv.upload.end'));
                        return $this->renderWithMessage($form);
                    }
                    // 受注一覧でダウンロードした受注csvを使用すると送料が重複して注文番号が取得できないため
                    // key重複でも取り込めるように対応
                    $csv->setHeaderRowNumber(0, $csv::DUPLICATE_HEADERS_MERGE);

                    $arrOrderIds = [];
                    // CSVファイルの登録処理
                    foreach ($csv as $line => $row) {
                        // 必須カラムのkey確認
                        if (!array_key_exists($this->columnHeader, $row)) {
                            $this->addCsvResult(trans('admin.common.csv_invalid_required', ['%line%' => $line, '%name%' => $this->columnHeader]));
                        // 必須カラムのvalue確認
                        } elseif (!isset($row[$this->columnHeader])) {
                            $this->addCsvResult(trans('admin.common.csv_invalid_required', ['%line%' => $line, '%name%' => $this->columnHeader]));
                        // 数値型の確認
                        } elseif (!is_numeric($row[$this->columnHeader])) {
                            is_array($row[$this->columnHeader])
                                ? $this->addCsvResult(trans('vt4g_plugin.order.csv.upload.order.duplicate'))
                                : $this->addCsvResult(sprintf(trans('vt4g_plugin.order.csv.upload.invalid.number'),
                                                        $line,
                                                        $this->columnHeader));
                        }
                        if ($this->hasCsvResult()) {
                            $this->mdkLogger->info(trans('vt4g_plugin.order.csv.upload.end'));
                            return $this->renderWithMessage($form);
                        }
                        $arrOrderIds[] = $row[$this->columnHeader];
                    }

                    $arrResult = [];
                    try {
                        $arrResult = $this->updateListPaymentStatus($arrOrderIds);
                    } catch (\Exception $e) {
                        $this->em->rollback(); // ロールバック
                        $this->mdkLogger->error($e);
                        $this->addCsvResult(trans('vt4g_plugin.db.regist.error'));
                    }
                    // エラーの際のメッセージの取得
                    foreach ($arrResult as $orderId => $arrVal){
                        $this->addCsvResult("{$this->columnHeader}:" . $orderId . '　' . $arrVal['message'], $arrVal['isOK']);
                    }

                    $this->removeUploadedFile();
                    $this->mdkLogger->info(trans('vt4g_plugin.order.csv.upload.end'));
                    $this->session->getFlashBag()->add('eccube.admin.success', 'admin.common.csv_upload_complete');
                }
            }
        }

        return $this->renderWithMessage($form);
    }

    /**
     * アップロード用CSV雛形ファイルダウンロード
     *
     * @Route("/%eccube_admin_route%/order/vt4g_order_csv_template", name="vt4g_admin_order_csv_template")
     */
    public function csvTemplate(Request $request)
    {
        $filename = $this->vt4gConst['ORDER_CSV_TEMPLATE'];
        $columns  = [$this->vt4gConst['ORDER_CSV_COLUMN_CONFIG']['NAME']];
        return $this->sendTemplateResponse($request, $columns, $filename);
    }

    /**
     * CSV登録処理のメッセージを画面に表示
     *
     * @param  object $form CsvImportType
     * @return object       ビューレスポンス
     */
    protected function renderWithMessage($form)
    {
        return $this->render(
            'VeriTrans4G/Resource/template/admin/Order/csv_upload.twig',
            [
                'form'      => $form->createView(),
                'header'    => $this->columnHeader,
                'csvResult' => $this->csvResult,
            ]
        );
    }

    /**
     * CSV登録処理の結果を追加
     *
     * @param string  $message CSV登録結果
     * @param boolean $isOK    決済情報結果
     * @return void
     */
    protected function addCsvResult($message, $isOK = false)
    {
        $this->csvResult[] = compact('message', 'isOK');
    }

    /**
     * CSV登録処理の結果を取得　
     *
     * @return array     CSV登録処理結果
     */
    protected function getCsvResult()
    {
        return $this->csvResult;
    }

    /**
     * CSV登録処理結果の確認
     *
     * @return boolean     CSV登録処理結果が存在するかどうか
     */
    protected function hasCsvResult()
    {
        return count($this->getCsvResult()) > 0;
    }

    /**
     * 決済の更新処理
     *
     * @param  array $arrIds    注文番号
     * @return array arrResult  決済結果
     */
    public function updateListPaymentStatus($arrIds)
    {
        $arrResult = [];
        $message = 'message';
        $isOK    = 'isOK';
        foreach ($arrIds as $orderId) {
            if (!empty($arrResult[$orderId])) {
                continue;
            }
            $order = $this->em->getRepository(Order::class)->find($orderId);
            // 決済データを取得
            $orderPayment = $this->util->getOrderPayment($orderId);
            // 受注のチェック
            if (empty($order)) {
                $arrResult[$orderId][$message] = trans('vt4g_plugin.order.csv.upload.order.not.exist');
                $arrResult[$orderId][$isOK]    = false;
                continue;
            }
            // 決済のチェック
            if (empty($orderPayment)) {
                $arrResult[$orderId][$message] = sprintf(trans('vt4g_plugin.order.csv.upload.not.plugin.payment'),
                                                        $this->vt4gConst['VT4G_PLUGIN_NAME']);
                $arrResult[$orderId][$isOK]    = false;
                continue;
            }
            // 注文ステータスを取得
            $orderStatus = $order->getOrderStatus()->getId();
            // 新規受付
            $isNew = $orderStatus == OrderStatus::NEW;
            // 決済処理中
            $isPending = $orderStatus == OrderStatus::PENDING;

            // 注文ステータスが新規受付か決済処理中で決済ステータスに値がない(memo04 = '')場合であれば処理を行わない
            if (($isNew || $isPending) && empty($orderPayment->getMemo04())) {
                $arrResult[$orderId][$message] = trans('vt4g_plugin.order.csv.upload.processing');
                $arrResult[$orderId][$isOK]    = false;
                continue;
            }
            // クレジットカード決済で決済ステータスが売上なら処理を行わない
            if ($orderPayment->getMemo04() == $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']
            && $orderPayment->getMemo03() == $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']) {
                $arrResult[$orderId][$message] = sprintf(trans('vt4g_plugin.order.csv.upload.unauthoriz'),
                $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['LABEL']);
                $arrResult[$orderId][$isOK]    = false;
                continue;
            }
            // クレジットカード決済で販売種別が継続課金かつ少額与信の対象なら処理を行わない
            $subscFewCreditExists = $this->util->checkSubscFewCreditExists($orderId);
            if ($orderPayment->getMemo03() == $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']
            && $subscFewCreditExists) {
              $arrResult[$orderId][$message] = sprintf(trans('vt4g_plugin.order.csv.upload.subsc_few_credit'),
              $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['LABEL']);
              $arrResult[$orderId][$isOK]    = false;
              continue;
            }

            // 更新処理のため配列に入れる
            $payload = [];
            $payload['order'] = $this->em->getRepository(Order::class)->find($orderPayment->getOrderId());
            $payload['orderPayment'] = $orderPayment;
            $beforeUpdatePaymentStatsu = $payload['orderPayment']->getMemo04();
            $beforeUpdateOrderStatsu = $payload['order']->getOrderStatus()['id'];

            // 決済の更新処理
            $extension = $this->container->get('vt4g_plugin.service.admin.order_edit_extension');
            $arrRes = $extension->operateCapture($payload);
            // 更新後のメッセージの判断
            if (!array_key_exists($isOK, $arrRes)) {
                $arrResult[$orderId][$message] = sprintf(trans('vt4g_plugin.order.csv.upload.unauthoriz'),
                                                        $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['LABEL']);
                $arrResult[$orderId][$isOK]    = false;
            } elseif (array_key_exists($message, $arrRes)) {
                $arrResult[$orderId][$message] = trans('vt4g_plugin.payment.shopping.error').$arrRes['message'];
                $arrResult[$orderId][$isOK]    = false;
            } else {

                //注文ステータスが新規受付→入金済みへ変更した際のメッセージを表示する。
                if($beforeUpdateOrderStatsu === OrderStatus::NEW && $payload['order']->getOrderStatus()['id'] === OrderStatus::PAID){
                    $arrResult[$orderId][$message] = trans('vt4g_plugin.order.csv.update_payment_and_order_status.success');
                    $arrResult[$orderId][$isOK]    = true;
                } else {
                    $arrResult[$orderId][$message] = trans('vt4g_plugin.order.csv.upload.part.success');
                    $arrResult[$orderId][$isOK]    = true;
                }

                //入金済みのステータスが存在しない + 変更前の決済ステータスが与信の場合のメッセージを表示する。
                $orderStatus = $this->em->getRepository(OrderStatus::class)->find(OrderStatus::PAID);
                if(empty($orderStatus) && $beforeUpdatePaymentStatsu == $this->vt4gConst['VT4G_PAY_STATUS']['AUTH']['VALUE']){
                    $arrResult[$orderId][$message] = trans('vt4g_plugin.order.csv.upload.success.no_order_status');
                    $arrResult[$orderId][$isOK]    = true;
                }

                //楽天ペイの時は決済ステータスのみの更新となるため、他の決済と異なるメッセージを表示する。
                if($orderPayment->getMemo03() === (string) $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN']){
                    $arrResult[$orderId][$message] = trans('vt4g_plugin.order.csv.upload.success');
                    $arrResult[$orderId][$isOK]    = true;
                }

            }
        }
        return $arrResult;
    }
}
