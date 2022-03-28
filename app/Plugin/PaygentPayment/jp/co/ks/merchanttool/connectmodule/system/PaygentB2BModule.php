<?php
/**
 * PAYGENT B2B MODULE
 * PaygentB2BModule.php
 *
 * Copyright (C) 2007 by PAYGENT Co., Ltd.
 * All rights reserved.
 */
namespace Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system;

use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\Encoder;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\entity\ResponseDataFactory;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\HttpsRequestSender;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\PaygentB2BModuleLogger;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\StringUtil;

/**
 * 接続モジュール メイン処理用クラス
 *
 * @version $Revision: 28058 $
 * @author $Author: ito $
 */
class PaygentB2BModule {

    /**
     * カナ変換用 要求電文 POSTパラメータ名
     */
    var $REPLACE_KANA_PARAM = ["customer_family_name_kana", "customer_name_kana",
            "payment_detail_kana", "claim_kana", "receipt_name_kana"];

    /** クライアント証明書ファイルパス */
    var $clientFilePath;

    /** CA証明書ファイルパス */
    var $caFilePath;

    /** Proxyサーバ名 */
    var $proxyServerName;

    /** ProxyIPアドレス */
    var $proxyServerIp;

    /** Proxyポート番号 */
    var $proxyServerPort;

    /** デフォルトID */
    var $defaultId;

    /** デフォルトパスワード */
    var $defaultPassword;

    /** タイムアウト値 */
    var $timeout;

    /** 結果CSVファイル名 */
    var $resultCsv;

    /** 照会MAX件数 */
    var $selectMaxCnt;

    /** ファイル決済用送信ファイルパス */
    var $sendFilePath;

    /** 電文種別ID */
    var $telegramKind;

    /** PropertiesFile 値保持 */
    var $masterFile;

    /** 引数保持 */
    var $telegramParam = [];

    /** 通信処理 */
    var $sender;

    /** 処理結果 */
    var $responseData;

    /** Logger */
    var $logger = null;

    /** デバッグオプション */
    var $debugFlg;

    /** 処理結果メッセージ */
    var $resultMessage = '';

    /** 文字コード制御 */
    var $encoder = null;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    public function __construct(EccubeConfig $eccubeConfig) {
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * コンストラクタ
     *
     * @return なし 成功しているかのチェックは
     */
    function PaygentB2BModule() {

        // 変数初期化
        $this->telegramParam = [];

    }

    /**
     * クラスを初期化処理
     * @return mixed true:成功、他：エラーコード
     */
    function init() {
        // 設定値を取得
        $this->masterFile = PaygentB2BModuleResources::getInstance($this->eccubeConfig);

        // Logger を取得
        $this->logger = PaygentB2BModuleLogger::getInstance($this->eccubeConfig);
        if ($this->masterFile == null
            || strcasecmp(get_class($this->masterFile), "Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system\PaygentB2BModuleResources") != 0) {
            // エラーコード
            return $this->masterFile;
        }

        if ($this->logger == null
            || strcasecmp(get_class($this->logger), "Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\PaygentB2BModuleLogger") != 0) {
            // エラーコード
            return $this->logger;
        }

        $this->encoder = new Encoder($this->eccubeConfig);

        // 設定値をセット
        $this->clientFilePath = $this->masterFile->getClientFilePath();
        $this->notUseClientCert = $this->masterFile->getNotUseClientCert();
        $this->caFilePath = $this->masterFile->getCaFilePath();
        $this->notUseCaCert = $this->masterFile->getNotUseCaCert();
        $this->proxyServerName = $this->masterFile->getProxyServerName();
        $this->proxyServerIp = $this->masterFile->getProxyServerIp();
        $this->proxyServerPort = $this->masterFile->getProxyServerPort();
        $this->defaultId = $this->masterFile->getDefaultId();
        $this->defaultPassword = $this->masterFile->getDefaultPassword();
        $this->timeout = $this->masterFile->getTimeout();
        $this->selectMaxCnt = $this->masterFile->getSelectMaxCnt();
        $this->debugFlg = $this->masterFile->getDebugFlg();

        return true;
    }

    /**
     * デフォルトIDを設定
     *
     * @param defaultId String
     */
    function setDefaultId($defaultId) {
        $this->defaultId = $defaultId;
    }

    /**
     * デフォルトIDを取得
     *
     * @return String defaultId
     */
    function getDefaultId() {
        return $this->defaultId;
    }

    /**
     * デフォルトパスワードを設定
     *
     * @param defaultPassword String
     */
    function setDefaultPassword($defaultPassword) {
        $this->defaultPassword = $defaultPassword;
    }

    /**
     * デフォルトパスワードを取得
     *
     * @return String defaultPassword
     */
    function getDefaultPassword() {
        return $this->defaultPassword;
    }

    /**
     * タイムアウト値を設定
     *
     * @param timeout int
     */
    function setTimeout($timeout) {
        $this->timeout = $timeout;
    }

    /**
     * タイムアウト値を取得
     *
     * @return int timeout
     */
    function getTimeout() {
        return $this->timeout;
    }

    /**
     * 結果CSVファイル名を設定
     *
     * @param resultCsv String
     */
    function setResultCsv($resultCsv) {
        $this->resultCsv = $resultCsv;
    }

    /**
     * 結果CSVファイル名を取得
     *
     * @return String resultCsv
     */
    function getResultCsv() {
        return $this->resultCsv;
    }

    /**
     * 照会MAX件数を設定
     *
     * @param selectMaxCnt int
     */
    function setSelectMaxCnt($selectMaxCnt) {
        $this->selectMaxCnt = $selectMaxCnt;
    }

    /**
     * 照会MAX件数を取得
     *
     * @return String selectMaxCnt
     */
    function getSelectMaxCnt() {
        return $this->selectMaxCnt;
    }

    /**
     * ファイル決済用送信ファイルパス
     *
     * @param sendFilePath String
     */
    function setSendFilePath($sendFilePath) {
        $this->sendFilePath = $sendFilePath;
    }

    /**
     * ファイル決済用送信ファイルパス
     *
     * @return String sendFilePath
     */
    function getSendFilePath() {
        return $this->sendFilePath;
    }

    /**
     * 処理結果メッセージ
     *
     * @retunr resultMessage String
     */
    function getResultMessage() {
        return $this->resultMessage;
    }

    /**
     * 引数を設定
     *
     * @param key String
     * @param valuet String
     */
    function reqPut($key, $value) {
        $tempVal = $value;

        if ($tempVal === null) {
            // Value 値の null 設定は認めない
            $tempVal = "";
        }
        $this->telegramParam[$key] = $tempVal;
    }

    /**
     * 引数を取得
     *
     * @param key Stirng
     * @return String value
     */
    function reqGet($key) {
        return $this->telegramParam[$key];
    }

    /**
     * 文字コードを設定する
     *
     * @param encoding String 文字コード
     * @return エラーコード(異常時のみ)
     */
    function setEncoding($encoding) {
        if (!$this->encoder->setEncoding($encoding)) {
            $this->resultMessage = $this->encoder->getResultMessage();
            return $this->encoder->getErrorCode();
        }
    }

    /**
     * 照会処理を実行
     *
     * @return String true：成功、他:エラーコード、
     */
    function post() {

        $rslt = "";

        // 電文種別ID を取得
        $this->telegramKind = "";


        $PAYGENTB2BMODULE__TELEGRAM_KIND_KEY = $this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_kind_key'];
        if (array_key_exists($PAYGENTB2BMODULE__TELEGRAM_KIND_KEY, $this->telegramParam)) {
            $this->telegramKind = $this->telegramParam[$PAYGENTB2BMODULE__TELEGRAM_KIND_KEY];
        }

        // 要求電文パラメータ未設定値の設定
        $this->setTelegramParamUnsetting();

        // Post時エラーチェック
        $rslt = $this->postErrorCheck();
        if (!($rslt === true)) {
            // エラーコード
            return 	$rslt;
        }

        // 取引ファイル設定
        $this->convertFileData();

        // URL取得
        $url = $this->masterFile->getUrl($this->telegramKind);
        if ($url === false) {
            $PAYGENTB2BMODULECONNECTEXCEPTION__TEREGRAM_PARAM_OUTSIDE_ERROR = $this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_kind_key'];
            $this->resultMessage = $PAYGENTB2BMODULECONNECTEXCEPTION__TEREGRAM_PARAM_OUTSIDE_ERROR
                . ": HTTP request contains unexpected value.";
            return $PAYGENTB2BMODULECONNECTEXCEPTION__TEREGRAM_PARAM_OUTSIDE_ERROR;
        }

        // HttpsRequestSender取得
        $this->sender = new HttpsRequestSender($url, $this->eccubeConfig);

        // クライアント証明書パス設定
        $this->sender->setClientCertificatePath($this->clientFilePath);

        // クライアント証明書未使用設定
        $this->sender->setNotUseClientCert($this->notUseClientCert);

        // CA証明書パス設定
        $this->sender->setCaCertificatePath($this->caFilePath);

        // CA証明書未使用設定
        $this->sender->setNotUseCaCert($this->notUseCaCert);

        // タイムアウト設定
        $this->sender->setTimeout($this->timeout);

        // Proxy接続タイムアウト設定
        $this->sender->setProxyConnectTimeout($this->timeout);

        // Proxy伝送タイムアウト設定
        $this->sender->setProxyCommunicateTimeout($this->timeout);

        if ($this->isProxyDataSet()) {
            if (!StringUtil::isEmpty($this->proxyServerIp)) {
                $this->sender->setProxyInfo($this->proxyServerIp, $this->proxyServerPort);
            } else if (!StringUtil::isEmpty($this->proxyServerName)) {
                $this->sender->setProxyInfo($this->proxyServerName, $this->proxyServerPort);
            }
        }

        // カナ変換処理
        $this->replaceTelegramKana();

        // 電文長チェック
        $this->validateTelegramLengthCheck();

        // 文字コード変換
        if (!$this->encoder->encode($this->telegramParam, $this->telegramKind)) {
            $this->resultMessage = $this->encoder->getResultMessage();
            return $this->encoder->getErrorCode();
        }
        $this->telegramParam = $this->encoder->getParams();

        // Post
        $rslt =	$this->sender->postRequestBody($this->telegramParam, $this->debugFlg);
        if (!($rslt === true)) {
            $this->resultMessage = $this->sender->getResultMessage();
            // エラーコード
            return $rslt;
        }

        // Get Response
        $resBody = $this->sender->getResponseBody();

        // 文字コードを戻す
        $resBody = $this->encoder->decode($resBody);

        // Create ResponseData
        $objResponseDataFactory = new ResponseDataFactory($this->eccubeConfig);
        $this->responseData = $objResponseDataFactory->create($this->telegramKind);

        // Parse Stream
        if ($this->isParseProcess()) {
            $rslt = $this->responseData->parse($resBody);
        } else {
            $rslt = $this->responseData->parseResultOnly($resBody);
        }
        $this->resultMessage = $this->getResponseCode().': '.$this->getResponseDetail() ;

        // エラー時

        if (!($rslt === true)) {
            return $rslt;
        }

        // CSV File出力判定
        if ($this->isCSVOutput()) {
            // CSV File 出力
            if (strcasecmp(get_class($this->responseData), "ReferenceResponseDataImpl") == 0) {

                $rslt = $this->responseData->writeCSV($resBody, $this->resultCsv);
                if (!($rslt === true)) {
                    // CSV File Output Error
                    return $rslt;
                }
            }
        } elseif ($this->isFilePaymentOutput()) {
            // ファイル決済結果ファイル出力
            if (strcasecmp(get_class($this->responseData), "FilePaymentResponseDataImpl") == 0) {

                $rslt = $this->responseData->writeCSV($resBody, $this->resultCsv);
                if (!($rslt === true)) {
                    // CSV File Output Error
                    return $rslt;
                }
            }
        }

        return true;
    }

    /**
     * 処理結果を返す
     *
     * @return Map；ない場合、NULL
     */
    function resNext() {
        if ($this->responseData == null) {
            return null;
        }
        return $this->responseData->resNext();
    }

    /**
     * 処理結果が存在するか判定
     *
     * @return boolean
     */
    function hasResNext() {
        if ($this->responseData == null) {
            return false;
        }

        return $this->responseData->hasResNext();
    }

    /**
     * 処理結果を取得
     *
     * @return String 処理結果；ない場合、NULL
     */
    function getResultStatus() {
        if ($this->responseData == null) {
            return null;
        }
        return $this->responseData->getResultStatus();
    }

    /**
     * レスポンスコードを取得
     *
     * @return String レスポンスコード；ない場合、NULL
     */
    function getResponseCode() {
        if ($this->responseData == null) {

            return null;
        }
        return $this->responseData->getResponseCode();
    }

    /**
     * レスポンス詳細を取得
     *
     * @return String レスポンス詳細；ない場合、NULL
     */
    function getResponseDetail() {
        if ($this->responseData == null) {
            return null;
        }
        return $this->responseData->getResponseDetail();
    }

    /**
     * 要求電文パラメータ未設定値の設定
     */
    function setTelegramParamUnsetting() {
        // 接続ID
        if (!array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmodule__connect_id_key'], $this->telegramParam)) {
            // 接続ID が未設定の場合、デフォルトID を設定
            $this->telegramParam[$this->eccubeConfig['paygent_payment']['paygentb2bmodule__connect_id_key']] = $this->defaultId;
        }

        // 接続パスワード
        if (!array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmodule__connect_password_key'], $this->telegramParam)) {
            // 接続パスワードが未設定の場合、デフォルトパスワード を設定
            $this->telegramParam[$this->eccubeConfig['paygent_payment']['paygentb2bmodule__connect_password_key']] = $this->defaultPassword;
        }

        // 最大検索数
        if ($this->telegramKind != null) {
            if ($this->masterFile->isTelegramKindRef($this->telegramKind)) {
                // 決済情報照会の場合
                if (!array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmodule__limit_count_key'], $this->telegramParam)) {
                    // 最大検索数が未設定の場合、照会MAX件数を設定
                    $this->telegramParam[$this->eccubeConfig['paygent_payment']['paygentb2bmodule__limit_count_key']] =
                        $this->selectMaxCnt;
                }
            }
        }
    }

    /**
     * Post時エラーチェック
     *
     * @return mixed エラーなしの場合：TRUE、他：エラーコード
     */
    function postErrorCheck() {
        // パラメータ必須チェック
        if (!$this->isModuleParamCheck()) {
            // モジュールパラメータエラー
            $this->resultMessage = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__module_param_required_error']
                    . ": Error in indespensable HTTP request value.";
            trigger_error($this->resultMessage, E_USER_WARNING);
            return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__module_param_required_error'];
        }

        if (!$this->isTeregramParamCheck()) {
            // 電文要求パラメータエラー
            $this->resultMessage = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_outside_error']
                    . ": HTTP request contains unexpected value.";
            trigger_error($this->resultMessage, E_USER_WARNING);
            return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_outside_error'];
        }

        if (!$this->isResultCSV()) {
            // 結果CSVファイル名設定エラー
            $this->resultMessage = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__response_type_error']
                    . ": CVS file name error.";
            trigger_error($this->resultMessage, E_USER_WARNING);
            return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__response_type_error'];
        }

        if (!$this->isTeregramParamKeyNullCheck()) {
            // 電文要求Key null エラー
            $this->resultMessage = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_required_error']
                    . ": HTTP request key must be null.";
            trigger_error($this->resultMessage, E_USER_WARNING);
            return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_required_error'];
        }

        if (!$this->isTelegramParamKeyLenCheck()) {
            // 電文要求Key長エラー
            $this->resultMessage = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_required_error']
                    . ": HTTP request key must be shorter than "
                    . $this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_key_length'] . " bytes.";
            trigger_error($this->resultMessage, E_USER_WARNING);
            return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_required_error'];
        }

        if (!$this->isTelegramParamValueLenCheck()) {
            // 電文要求Value長エラー
            $this->resultMessage = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_required_error']
                    . ": HTTP request value must be shorter than "
                    . $this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_value_length'] . " bytes.";
            trigger_error($this->resultMessage, E_USER_WARNING);
            return $this->eccubeConfig['paygent_payment']['PAYGENTB2BMODULECONNECTEXCEPTION__TEREGRAM_PARAM_REQUIRED_ERROR'];
        }

        return true;
    }

    /**
     * ファイルパスに指定されたCSVファイルの内容をdataパラメータに設定する
     *
     */
    function convertFileData()  {

        // 取引ファイル
        if (!isset($this->telegramParam[$this->eccubeConfig['paygent_payment']['paygentb2bmodule__data_key']])
                    && !StringUtil::isEmpty($this->getSendFilePath())) {
            // key:dataの内容が空でファイルパスの指定がある場合はファイル内容をdataに設定

            // ファイルの存在確認
            if (!file_exists($this->getSendFilePath())) {
                // ファイル存在エラー
                trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__file_payment_error']
                        . ": Send file not found. ", E_USER_WARNING);
                return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_required_error'];
            }

            // ファイル内容の取得
            $fileData = file_get_contents($this->getSendFilePath());

            // ファイル内容をdataパラメータに設定
            $this->telegramParam[$this->eccubeConfig['paygent_payment']['paygentb2bmodule__data_key']] = $fileData;

        }

    }


    /**
     * モジュールパラメータチェック
     *
     * @return boolean true=NotError false=Error
     */
    function isModuleParamCheck() {
        $rb = false;
        // 必須エラーチェック
        if ((0 < $this->timeout) && (0 < $this->selectMaxCnt)) {
            $rb = true;
        }

        return $rb;
    }

    /**
     * 電文要求パラメータチェック
     *
     * @return boolean true=NotError false=Error
     */
    function isTeregramParamCheck() {
        $rb = false;

        // 電文種別ID エラーチェック
        if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_kind_key'], $this->telegramParam)) {
            if (!StringUtil::isEmpty(
                    $this->telegramParam[$this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_kind_key']])) {
                $rb = true;
            }
        }

        return $rb;
    }

    /**
     * 結果CSVファイル名設定チェック
     *
     * @return boolean true=NotError false=Error
     */
    function isResultCSV() {
        $rb = true;

        // 結果CSVファイル名設定エラーチェック
        if (!$this->masterFile->isTelegramKindRef($this->telegramKind)
                && $this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_kind_file_payment_res'] != $this->telegramKind
                && !StringUtil::isEmpty($this->resultCsv)) {
            $rb = false;
        } elseif ($this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_kind_file_payment_res'] == $this->telegramKind
                && StringUtil::isEmpty($this->resultCsv)) {
            $rb = false;
        }

        return $rb;
    }

    /**
     * 電文要求パラメータ Key Null チェック
     *
     * @return boolean true=NotError false=Error
     */
    function isTeregramParamKeyNullCheck() {
        $rb = true;

        // Key null チェック
        if (array_key_exists(null, $this->telegramParam)) {
                $rb = false;
        }

        return $rb;
    }

    /**
     * 電文要求パラメータ Key 長チェック
     *
     * @return boolean true=NoError false=Error
     */
    function isTelegramParamKeyLenCheck() {
        $rb = true;

        foreach($this->telegramParam as $keys => $values) {
            if (!StringUtil::isEmpty($keys)) {
                if (strlen($keys) > $this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_key_length']) {
                    $rb = false;
                    break;
                }
            }
        }

        return $rb;
    }

    /**
     * 電文要求パラメータ Value 長チェック
     *
     * @return boolean true=NoError false=Error
     */
    function isTelegramParamValueLenCheck() {
        $rb = true;

        foreach($this->telegramParam as $keys => $values) {
            // 取引ファイル内容はチェック対象外
            if ($this->eccubeConfig['paygent_payment']['paygentb2bmodule__data_key'] != $keys && !StringUtil::isEmpty($values)) {
                if (strlen($values) > $this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_value_length']) {
                    $rb = false;
                    break;
                }
            }
        }

        return $rb;
    }

    /**
     * 電文要求パラメータ 総POSTサイズチェック
     *
     * @return boolean true=NoError false=Error
     */
    function validateTelegramLengthCheck() {
        $telegramLength = $this->sender->getTelegramLength($this->telegramParam);

        // ファイル決済判定
        if (isset($this->telegramParam[$this->eccubeConfig['paygent_payment']['paygentb2bmodule__data_key']])) {
            // ファイル決済
            if ($this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_length_file'] < $telegramLength) {
                return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_required_error'];
            }
        } else {
            // ファイル決済以外
            if ($this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_length'] < $telegramLength) {
                return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_required_error'];
            }
        }

    }

    /**
     * Proxy 設定判定
     *
     * @return boolean true=Set false=NotSet
     */
    function isProxyDataSet() {
        $rb = false;

        if (!(StringUtil::isEmpty($this->proxyServerIp) && StringUtil
                ::isEmpty($this->proxyServerName))
                && 0 < $this->proxyServerPort) {
            // Proxy 設定済の場合
            $rb = true;
        }

        return $rb;
    }

    /**
     * Parse 処理判定
     *
     * @param InputStream
     * @return boolean true=parse false=ResultOnly
     */
    function isParseProcess() {
        $rb = true;

        // Parse 処理実施判定
        if (strcasecmp(get_class($this->responseData), "ReferenceResponseDataImpl") == 0) {
            // ReferenceResponseDataImpl の場合のみ、CSV出力可否から実施判定
            if (!StringUtil::isEmpty($this->resultCsv)) {
                $rb = false;
            }
        } elseif (strcasecmp(get_class($this->responseData), "FilePaymentResponseDataImpl") == 0) {
            // ファイル決済は常にResultのみ
            $rb = false;
        }

        return $rb;
    }

    /**
     * CSV 出力判定
     *
     * @return boolean true=CSV Output false=Non
     */
    function isCSVOutput() {
        $rb = false;

        if ($this->masterFile->isTelegramKindRef($this->telegramKind)
                && !StringUtil::isEmpty($this->resultCsv)) {
            // 電文種別が照会 且つ 結果CSVファイル名 が設定済の場合
            if ($this->getResultStatus() == $this->eccubeConfig['paygent_payment']['paygentb2bmodule__result_status_error']) {
                // 処理結果が異常の場合
                if ($this->getResponseCode() == $this->eccubeConfig['paygent_payment']['paygentb2bmodule__response_code_9003']) {
                    // レスポンスコードが 9003 の場合
                    $rb = true;
                }
            } else {
                // 処理結果が正常の場合
                $rb = true;
            }
        }

        return $rb;
    }

    /**
     * ファイル決済結果ファイル 出力判定
     *
     * @return boolean true=CSV Output false=Non
     */
    function isFilePaymentOutput() {
        if ($this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_kind_file_payment_res'] == $this->telegramKind
                && !StringUtil::isEmpty($this->resultCsv)) {
            return true;
        }
        return false;
    }

    /**
     * 電文要求パラメータ 半角カナ 置換処理
     */
    function replaceTelegramKana() {

        foreach($this->telegramParam as $keys => $values) {
            if (in_array(strtolower($keys), $this->REPLACE_KANA_PARAM)) {
                $this->telegramParam[$keys] =
                    StringUtil::convertKatakanaZenToHan($values);
            }
        }
    }

}

?>
