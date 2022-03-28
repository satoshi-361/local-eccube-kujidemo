<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service;

use Plugin\VeriTrans4G\Entity\Vt4gOrderPayment;
use Plugin\VeriTrans4G\Entity\Vt4gOrderLog;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;
use Plugin\VeriTrans4G\Entity\Vt4gPlugin;
use Plugin\VeriTrans4G\Entity\Master\Vt4gSubscSaleType;
use Eccube\Entity\Payment;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * 汎用的な処理用クラス
 */
class UtilService
{
    /**
     * コンテナ
     */
    private $container;

    /**
     * エンティティーマネージャー
     */
    private $em;

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * MDK Logger
     */
    private $mdkLogger;

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
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * ゼロパディング
     *
     * @param  integer $num    対象の値
     * @param  integer $digits 桁数
     * @return string          ゼロパディングされた値
     */
    public function zeroPadding($num, $digits = 2)
    {
        return sprintf("%0{$digits}d", $num);
    }

    /**
     * 改行コードをエスケープ
     *
     * @param  string $str 対象の文字列
     * @return string      エスケープされた文字列
     */
    public function escapeNewLines($str)
    {
        return str_replace(["\r\n", "\r", "\n"], '\\n', $str);
    }

    /**
     * 注文IDから決済データを取得
     *
     * @param  integer $orderId 注文ID
     * @return object           決済データ
     */
    public function getOrderPayment($orderId)
    {
        return $this->em->getRepository(Vt4gOrderPayment::class)->find($orderId);
    }

    /**
     * 注文IDから決済データを取得
     * オブジェクトインスタンスかnullで結果を返す.
     *
     * @param  integer $orderId 注文ID
     * @return object           決済データ
     */
    public function getOrderPaymetFindOneBy($orderId)
    {
        return $this->em->getRepository(Vt4gOrderPayment::class)->findOneBy(['order_id' => $orderId]);
    }

    /**
     * 注文IDから決済ログデータを取得
     *
     * @param  integer      $orderId 注文ID
     * @return Vt4gOrderLog          決済ログデータ
     */
    public function getOrderLog($orderId)
    {
        return $this->em->getRepository(Vt4gOrderLog::class)->findBy(
            ['order_id' => $orderId],
            ['log_id' => 'ASC']
        );
    }

    /**
     * プラグインの決済方法IDリストを取得
     *
     * @param  boolean $getAll         標準の決済方法IDを含めるか
     * @param  object  $excludePayment 取得から除外する決済方法
     * @return array                   決済方法IDリスト
     */
    public function getPaymentIdList($getAll = false, $excludePayment = null)
    {
        $excludePaymentId = is_null($excludePayment)
            ? null
            : $excludePayment->getId();
        $paymentList = $getAll
            ? $this->em->getRepository(Payment::class)->findAll()
            : $this->em->getRepository(Vt4gPaymentMethod::class)->findAll();

        $idList = [];
        foreach ($paymentList as $payment) {
            $paymentId = $getAll
                ? $payment->getId()
                : $payment->getPaymentId();

            // 例外の決済方法の場合はスキップ
            if (isset($excludePaymentId) && $excludePaymentId === $paymentId) {
                continue;
            }

            $idList[] = $paymentId;
        }

        return $idList;
    }

    /**
     * 削除済みのプラグイン決済方法IDリストを取得
     *
     * @param  object $excludePayment 取得から除外する決済方法
     * @return array                  決済方法IDリスト
     */
    public function getRemovedVt4gPaymentIdList($excludePayment = null)
    {
        $paymentList = $this->em->getRepository(Payment::class)->findAll();

        $idList = [];
        foreach ($paymentList as $payment) {
            // 例外の決済方法の場合はスキップ
            if (isset($excludePayment) && $excludePayment->getId() === $payment->getId()) {
                continue;
            }

            if (strpos($payment->getMethod(), $this->vt4gConst['VT4G_REMOVED_PAYNAME_LABEL']) !== false) {
                $idList[] = $payment->getId();
            }
        }

        return $idList;
    }

    /**
     * 決済方法データを取得
     *
     * @param  integer           $paymentId 決済方法ID
     * @return Vt4gPaymentMethod            決済方法データ
     */
    public function getPaymentMethod($paymentId)
    {
        return $this->em->getRepository(Vt4gPaymentMethod::class)->find($paymentId);
    }

    /**
     * 決済方法の内部IDから決済方法データを取得
     *
     * @param  integer           $payId 決済方法の内部ID
     * @return Vt4gPaymentMethod        決済方法データ
     */
    public function getPaymentMethodByPayId($payId)
    {
        return $this->em->getRepository(Vt4gPaymentMethod::class)->findOneBy([
            'memo03' => $payId
        ]);
    }

    /**
     * 決済方法の内部IDから決済方法データをすべて取得
     *
     * @param  integer           $payId 決済方法の内部ID
     * @return Vt4gPaymentMethod        決済方法データ
     */
    public function getPaymentMethodsByPayId($payId)
    {
        return $this->em->getRepository(Vt4gPaymentMethod::class)->findBy([
            'memo03' => $payId
        ]);
    }

    /**
     * 決済方法IDから決済方法の設定データを取得
     *
     * @param  integer    $paymentId 決済方法ID
     * @return array|null            決済方法の設定データ
     */
    public function getPaymentMethodInfo($paymentId)
    {
        $paymentMethod = $this->getPaymentMethod($paymentId);

        return empty($paymentMethod)
            ? null
            : unserialize($paymentMethod->getMemo05());
    }

    /**
     * 決済方法の内部IDから決済方法の設定データを取得
     *
     * @param  integer    $payId 決済方法の内部ID
     * @return array|null        決済方法の設定データ
     */
    public function getPaymentMethodInfoByPayId($payId)
    {
        $paymentMethod = $this->getPaymentMethodByPayId($payId);

        return empty($paymentMethod)
            ? null
            : unserialize($paymentMethod->getMemo05());
    }

    /**
     * 決済方法の内部IDからカード情報登録の上限数を取得
     *
     * @param  integer    $payId 決済方法の内部ID
     * @return integer    カード情報登録の上限数
     */
    public function getCardMax($payId)
    {
        $cardsMax = [];
        $paymentMethods = $this->getPaymentMethodsByPayId($payId);
        foreach ($paymentMethods as $paymentMethod) {
            $memo05 = unserialize($paymentMethod->getMemo05());
            $cardsMax[] = (int)$memo05['cardinfo_regist_max'];
        }
        return !empty($cardsMax) ? max($cardsMax) : $this->vt4gConst['VT4G_FORM']['DEFAULT']['CARDINFO_REGIST_MAX'];
    }

    /**
     * ベリトランス決済方法データを取得
     *
     * @return array|null ベリトランス決済方法データ
     */
    public function getVt4gPaymentMethodList()
    {
        return $this->em->getRepository(Vt4gPaymentMethod::class)
            ->setConst($this->vt4gConst)
            ->getPaymentIdByPluginCode();
    }

    /**
     * 決済方法IDから決済方法の内部IDを取得
     *
     * @param  integer $paymentId 決済方法ID
     * @return string             決済方法の内部ID
     */
    public function getPayId($paymentId)
    {
        return $this->getPaymentMethod($paymentId)->getMemo03();
    }

    /**
     * 決済方法の内部IDから決済方法コードを取得
     *
     * @param  integer     $payId 決済方法の内部ID
     * @return string|null        決済方法コード
     */
    public function getPayCode($payId)
    {
        return $this->vt4gConst["VT4G_CODE_PAYTYPEID_{$payId}"] ?? null;
    }

    /**
     * 決済方法の内部IDから決済方法ラベルを取得
     *
     * @param  integer     $payId 決済方法の内部ID
     * @return string|null        決済方法ラベル
     */
    public function getPayName($payId)
    {
        return $this->vt4gConst["VT4G_PAYNAME_PAYTYPEID_{$payId}"] ?? null;
    }

    /**
     * 決済ステータスデータを取得
     *
     * @param  boolean $shouldFilter フィルタリングを行うか
     * @return array                 決済ステータスデータ
     */
    public function getPaymentStatus($shouldFilter = false)
    {
        $payStatusConst = $this->vt4gConst['VT4G_PAY_STATUS'];
        $filter = $shouldFilter
            ? [$payStatusConst['CAPTURE']['VALUE'], $payStatusConst['CANCEL']['VALUE']]
            : [];

        return array_reduce($payStatusConst, function ($carry, $item) use ($filter) {
            // フィルタリング
            if (empty($filter) || in_array($item['VALUE'], $filter, true)) {
                $carry[$item['VALUE']] = $item['LABEL'];
            }
            return $carry;
        }, []);
    }

    /**
     * 決済ステータスのラベルを取得
     *
     * @param  integer $status 決済ステータス
     * @return string          決済ステータスのラベル
     */
    public function getPaymentStatusName($status)
    {
        $paymentStatus = $this->getPaymentStatus();

        return $paymentStatus[$status] ?? '―';
    }

    /**
     * 注文データから決済操作の実行可否を取得
     *
     * @param  object $order 注文データ
     * @return array         決済操作の実行可否
     */
    public function getPaymentOperations($order)
    {
        $orderPayment        = $this->getOrderPayment($order->getId());
        $payId               = $orderPayment->getMemo03();
        $paymentStatus       = $orderPayment->getMemo04();
        $arrMemo10           = unserialize($orderPayment->getMemo10());
        $refundButtonDisplay = false;
        if (isset($arrMemo10['captureTotal'])){
            $captureTotal = $arrMemo10['captureTotal'];
            $refundButtonDisplay = $captureTotal > $order->getPaymentTotal() && $order->getPaymentTotal() > 0;
        }

        $operations = [];

        switch ($payId) {
            // クレジットカード決済
            case $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']:
                switch ($paymentStatus) {
                    // 与信
                    case $this->vt4gConst['VT4G_PAY_STATUS']['AUTH']['VALUE']:
                        $operations[$this->vt4gConst['VT4G_OPERATION_AUTH']]    = true;
                        $operations[$this->vt4gConst['VT4G_OPERATION_CAPTURE']] = true;
                        $operations[$this->vt4gConst['VT4G_OPERATION_CANCEL']]  = true;
                        break;
                    // 売上
                    case $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']:
                        $operations[$this->vt4gConst['VT4G_OPERATION_CAPTURE']] = true;
                        $operations[$this->vt4gConst['VT4G_OPERATION_CANCEL']]  = true;
                        break;
                    // 新規決済
                    case $this->vt4gConst['VT4G_PAY_STATUS']['NEWLY']['VALUE']:
                        $operations[$this->vt4gConst['VT4G_OPERATION_NEWLY']] = true;
                        break;
                }
                break;

            // コンビニ決済
            case $this->vt4gConst['VT4G_PAYTYPEID_CVS']:
                switch ($paymentStatus) {
                    // 申込
                    case $this->vt4gConst['VT4G_PAY_STATUS']['REQUEST']['VALUE']:
                        $operations[$this->vt4gConst['VT4G_OPERATION_CANCEL']] = true;
                        break;
                    // 新規決済
                    case $this->vt4gConst['VT4G_PAY_STATUS']['NEWLY']['VALUE']:
                        $operations[$this->vt4gConst['VT4G_OPERATION_NEWLY']] = true;
                        break;
                }
                break;

            // 銀行決済
            case $this->vt4gConst['VT4G_PAYTYPEID_BANK']:
                break;

            // ATM決済
            case $this->vt4gConst['VT4G_PAYTYPEID_ATM']:
                switch ($paymentStatus) {
                    // 新規決済
                    case $this->vt4gConst['VT4G_PAY_STATUS']['NEWLY']['VALUE']:
                        $operations[$this->vt4gConst['VT4G_OPERATION_NEWLY']] = true;
                        break;
                }
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_RAKUTEN']:
            case $this->vt4gConst['VT4G_PAYTYPEID_RECRUIT']:
            case $this->vt4gConst['VT4G_PAYTYPEID_LINEPAY']:
                switch ($paymentStatus) {
                    // 与信
                    case $this->vt4gConst['VT4G_PAY_STATUS']['AUTH']['VALUE']:
                        $operations[$this->vt4gConst['VT4G_OPERATION_CAPTURE']] = true;
                        $operations[$this->vt4gConst['VT4G_OPERATION_CANCEL']]  = true;
                        break;
                    // 売上
                    case $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']:
                        $operations[$this->vt4gConst['VT4G_OPERATION_CANCEL']] = true;
                        // 再売上（減額用）
                        if ($refundButtonDisplay) {
                            $operations[$this->vt4gConst['VT4G_OPERATION_REFUND']] = true;
                        }
                        break;
                }
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_UPOP']:
                switch ($paymentStatus) {
                    // 与信
                    case $this->vt4gConst['VT4G_PAY_STATUS']['AUTH']['VALUE']:
                        $operations[$this->vt4gConst['VT4G_OPERATION_CAPTURE']] = true;
                        $operations[$this->vt4gConst['VT4G_OPERATION_CANCEL']]  = true;
                        break;
                    // 売上
                    case $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']:
                        if (!$arrMemo10['refundFlg']) {
                            $operations[$this->vt4gConst['VT4G_OPERATION_CANCEL']] = true;
                        }
                        // 再売上（減額用）
                        if ($refundButtonDisplay || $order->getPaymentTotal() == 0) {
                            $operations[$this->vt4gConst['VT4G_OPERATION_REFUND']] = true;
                        }
                        $operations[$this->vt4gConst['VT4G_OPERATION_REFUND_ALL']] = true;
                        break;
                }
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_PAYPAL']:
                switch ($paymentStatus) {
                    // 与信
                    case $this->vt4gConst['VT4G_PAY_STATUS']['AUTH']['VALUE']:
                        $operations[$this->vt4gConst['VT4G_OPERATION_CAPTURE']] = true;
                        $operations[$this->vt4gConst['VT4G_OPERATION_CANCEL']]  = true;
                        break;
                    // 売上
                    case $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']:
                        // 再売上（減額用）
                        if ($refundButtonDisplay || $order->getPaymentTotal() == 0) {
                            $operations[$this->vt4gConst['VT4G_OPERATION_REFUND']] = true;
                        }
                        $operations[$this->vt4gConst['VT4G_OPERATION_REFUND_ALL']] = true;
                        break;
                }
                break;
            case $this->vt4gConst['VT4G_PAYTYPEID_ALIPAY']:
                if ($paymentStatus == $this->vt4gConst['VT4G_PAY_STATUS']['CAPTURE']['VALUE']) {
                    $operations[$this->vt4gConst['VT4G_OPERATION_CANCEL']] = true;
                    // 再売上（減額用）
                    if ($refundButtonDisplay) {
                        $operations[$this->vt4gConst['VT4G_OPERATION_REFUND']] = true;
                    }
                }
                break;
        }

        return $operations;
    }

    /**
     * 決済設定 処理区分の取得
     *
     * @param  integer $paymentMethod 決済方法データ
     * @return boolean                与信+売上: true / 与信のみ: false
     */
    public function getCapture($paymentMethod)
    {
        $memo05 = unserialize($paymentMethod->getMemo05());

        return $memo05['withCapture'] === 1;
    }

    /**
     * VeriTrans4Gプラグインの設定データを取得
     *
     * @return array|null プラグイン設定データ
     */
    public function getPluginSetting()
    {
        $subData = $this->em->getRepository(Vt4gPlugin::class)->getSubData($this->vt4gConst['VT4G_CODE']);

        return $subData
            ? unserialize($subData)
            : null;
    }

    /**
     * VeriTrans4Gプラグイン設定画面で有効になっている決済方法の内部IDを取得
     *
     * @return array 有効になっている決済方法の内部ID
     */
    public function getEnablePayIdList()
    {
        $payIdList = [];
        $subData = $this->getPluginSetting();
        if (!empty($subData) && array_key_exists('enable_payment_type', $subData)) {
            $payIdList = $subData['enable_payment_type'];
        }

        return $payIdList;
    }

    /**
     * 注文データから注文がベリトランス決済か判定
     *
     * @param  object  $order 注文データ
     * @return boolean        ベリトランス決済かどうか
     */
    public function isVt4gPayment($order)
    {
        $paymentId = $order->getPayment()->getId();

        return !empty($paymentId) && !empty($this->getPaymentMethod($paymentId));
    }

    /**
     * URL・パスを生成
     *
     * @param  string  $route         ルート名
     * @param  array   $parameters    クエリパラメータ生成用データ
     * @param  integer $referenceType 生成する文字列の種類
     * @return string                 生成したURL・パス
     */
    public function generateUrl($route, $parameters = [], $referenceType = UrlGeneratorInterface::ABSOLUTE_URL)
    {
        return $this->container->get('router')->generate($route, $parameters, $referenceType);
    }

    /**
     * ルート名を指定してリダイレクト
     *
     * @param  string  $route      ルート名
     * @param  array   $parameters パラメータ
     * @param  integer $status     リダイレクト時のHTTPステータスコード
     * @return object              リダイレクトレスポンス
     */
    public function redirectToRoute($route, $parameters = [], $status = 302)
    {
        return new RedirectResponse($this->generateUrl($route, $parameters), $status);
    }

    /**
     * MDKトークン取得処理用のjsファイルのパスを取得
     *
     * @return string ファイルパス
     */
    public function getTokenJsPath()
    {
        return $this->vt4gConst['VT4G_PLUGIN_PATH'].$this->vt4gConst['VT4G_PLUGIN_TOKEN_JS_FILENAME'].'?date='.date('YmdHis');
    }

    /**
     * システム時刻に引数日数を加算した日付を指定したフォーマットで返す
     *
     * @param integer $days 日数
     * @param string  $format 日付フォーマット
     * @return string
     */
    public function getAddDateFormat($days = 0, $format = 'Y/m/d')
    {
        return date($format, strtotime(sprintf('+ %d day', $days)));
    }

    /**
     * 日付フォーマットをY/m/d H:i:sに変換
     *
     * @param string $yyyymmddhhmiss
     * @return string 日付
     */
    public function toDate($yyyymmddhhmiss)
    {
        if (empty($yyyymmddhhmiss)){
            return '';
        }

        $yyyy = "0000";
        $mm   = "00";
        $dd   = "00";
        $hh   = "00";
        $mi   = "00";
        $ss   = "00";
        if (strlen($yyyymmddhhmiss) >= 4) {
            $yyyy = substr($yyyymmddhhmiss, 0, 4);
        }
        if (strlen($yyyymmddhhmiss) >= 6) {
            $mm = substr($yyyymmddhhmiss, 4, 2);
        }
        if (strlen($yyyymmddhhmiss) >= 8) {
            $dd = substr($yyyymmddhhmiss, 6, 2);
        }
        if (strlen($yyyymmddhhmiss) == 8){
            return sprintf("%04d/%02d/%02d", $yyyy, $mm, $dd);
        }
        if (strlen($yyyymmddhhmiss) >= 10) {
            $hh = substr($yyyymmddhhmiss, 8, 2);
        }
        if (strlen($yyyymmddhhmiss) >= 12) {
            $mi = substr($yyyymmddhhmiss, 10, 2);
        }
        if (strlen($yyyymmddhhmiss) >= 14) {
            $ss = substr($yyyymmddhhmiss, 12, 2);
        }
        return sprintf("%04d/%02d/%02d %02d:%02d:%02d",
            $yyyy, $mm, $dd, $hh, $mi, $ss
        );
    }

    /**
     * 年を除いた日時フォーマットをm/d H:i:sに変換
     *
     * @param  string $mmddhhmiss 年を除いた日時
     * @return string             m/d H:i:sの日時
     */
    public function toDateMMDD($mmddhhmiss)
    {
        if (empty($mmddhhmiss)){
            return '';
        }

        $mm   = "00";
        $dd   = "00";
        $hh   = "00";
        $mi   = "00";
        $ss   = "00";
        if (strlen($mmddhhmiss) >= 2) {
            $mm = substr($mmddhhmiss, 0, 2);
        }
        if (strlen($mmddhhmiss) >= 4) {
            $dd = substr($mmddhhmiss, 2, 2);
        }
        if (strlen($mmddhhmiss) == 4){
            return sprintf("%02d/%02d",$mm, $dd);
        }

        if (strlen($mmddhhmiss) >= 6) {
            $hh = substr($mmddhhmiss, 4, 2);
        }
        if (strlen($mmddhhmiss) >= 8) {
            $mi = substr($mmddhhmiss, 6, 2);
        }
        if (strlen($mmddhhmiss) >= 10) {
            $ss = substr($mmddhhmiss, 8, 2);
        }
        return sprintf("%02d/%02d %02d:%02d:%02d",
            $mm, $dd, $hh, $mi, $ss
            );
    }

    /**
     * ローディング画像の取得
     *
     * @return string 画像パス
     */
    public function getLoadingImage()
    {
        return $this->vt4gConst['VT4G_PLUGIN_PATH'].$this->vt4gConst['VT4G_PLUGIN_LOADING_IMAGE'];
    }

    /**
     * コンビニ名取得
     *
     * @param integer $code コンビニコード
     * @return string
     */
    public function getConveniNameByCode($code)
    {
        $conveniMap = array_column($this->vt4gConst['VT4G_CONVENI'], 'LABEL', 'CODE');
        return $conveniMap[$code];
    }

    /**
     * 認証情報の検証
     *
     * @param  string  $authInfo 認証情報の文字列
     * @return boolean           検証結果がOKの場合はtrue、NGの場合はfalse
     */
    public function checkAuthInfo($authInfo)
    {
        $keys = preg_split("/-/", "$authInfo");
        $auth = true;

        if (sizeof($keys) != 3 ) {
            $auth = false;

        } else {
            // base64エンコード用クラス
            $cipher = new \TGMDK_Cipher();
            $merchant_cc_id = $cipher->base64Dec($keys[0]);
            $now = $cipher->base64Dec($keys[1]);
            $received_hash = $cipher->base64Dec($keys[2]);
            $conf  = \TGMDK_Config::getInstance();
            $array = $conf->getTransactionParameters();
            $merchant_secret_key = $array[\TGMDK_Config::MERCHANT_SECRET_KEY]; // マーチャントパスワード

            // ハッシュ生成
            $hash = \TGMDK_Util::get_hash_256($merchant_cc_id . $now . $merchant_secret_key);
            if (strcmp($hash,  $received_hash) != 0 ) {
                $auth = false;
            }
        }
        return $auth;
    }

    /**
     * 認証情報の検証
     *
     * @param  array   $request リクエストパラメータ
     * @return boolean          検証結果がOKの場合はtrue、NGの場合はfalse
     */
    public function checkVAuthInfo($request)
    {
        $conf = \TGMDK_Config::getInstance();
        $array = $conf->getConnectionParameters();
        $merchant_cc_id = $array[\TGMDK_Config::MERCHANT_CC_ID];
        $merchant_pw = $array[\TGMDK_Config::MERCHANT_SECRET_KEY];
        $charset = "UTF-8";
        $auth = true;

        $check_result = \TGMDK_AuthHashUtil::checkAuthHash(@$request, $merchant_cc_id, $merchant_pw, $charset);
        if (!isset($check_result) || $check_result == false) {
            $auth = false;
        }

        return $auth;
    }

    /**
     * 注文された商品が継続課金かつ少額与信の販売種別か確認する.
     * plg_vt4g_order_paymentのmemo10に設定される注文時の少額与信有無を参照する.
     * 継続課金販売種別マスタは参照していないことに注意.
     *
     * @param  string $orderId 注文ID
     * @return boolean 決済操作の実行可否
     */
    public function checkSubscFewCreditExists($orderId)
    {

        $subscFewCreditExists = false;
        $orderPayment = $this->getOrderPaymetFindOneBy($orderId);

        if (!is_null($orderPayment)) {
            $memo10 = $orderPayment->getMemo10();

            if (!is_null($memo10)) {
                $memo10Array = unserialize($memo10);

                // 注文時に少額与信であるか
                if (isset($memo10Array['useFewCredit']) && $memo10Array['useFewCredit'] === true) {
                    $subscFewCreditExists = true;
                }
            }
        }

        return $subscFewCreditExists;
    }

    /**
     * 継続課金商品の注文を判別
     *
     * @param  object $order 注文データ
     * @return Vt4gSubscSaleType|null      継続課金対象の場合は販売種別ID、対象外の場合はnullを返す
     */
    public function checkSubscriptionOrder($order)
    {
        // 注文商品の販売種別を取得
        $orderSaleTypes = $order->getSaleTypes();
        // この注文の販売種別
        $curSaleTypeId = isset($orderSaleTypes[0]) ? $orderSaleTypes[0]->getId() : null;
        // 継続課金対象の場合は販売種別ID、対象外の場合はnullを返す
        return $this->em->getRepository(Vt4gSubscSaleType::class)->findOneBy(['sale_type_id' => $curSaleTypeId]);
    }

    /**
     * 継続課金注文の際に少額与信対象を判別
     *
     * @param  Vt4gSubscSaleType $subscSsaleTypeEntity 継続課金販売種別
     * @return boolean       継続課金対象の場合はtrue
     */
    public function checkFewCreditSaleType($subscSsaleTypeEntity)
    {
        return isset($subscSsaleTypeEntity) ? !empty($subscSsaleTypeEntity->getFewCreditFlg()) : false;
    }

    // 決済依頼ステータスリスト
    public function getPayReqStatusList()
    {
        $request_status_list = [];
        foreach ($this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS'] as $k => $v) {
            $request_status_list[$v] = $this->vt4gConst['VTG4_PAYMENT_REQUEST']['REQUEST_STATUS_NAME'][$k];
        }
        return $request_status_list;
    }

    /**
     * OrderItemから商品IDを取得する
     *
     * @param  int $order_item_id
     *
     * @return int
     */
    public function getProductIdByOrderItem($order_item_id)
    {
        $sql = "SELECT p.id product_id FROM dtb_product p INNER JOIN dtb_order_item oi ON p.id=oi.product_id WHERE oi.id=:order_item_id";
        $stmt = $this->em->getConnection()->prepare($sql);
        $stmt->execute(['order_item_id' => $order_item_id]);
        $result = $stmt->fetchAll();
        return !empty($result[0])?$result[0]['product_id']:null;
    }
}
