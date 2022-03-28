<?php
/**
 * PAYGENT B2B MODULE
 * PaygentB2BModuleResources.php
 *
 * Copyright (C) 2007 by PAYGENT Co., Ltd.
 * All rights reserved.
 */

/*
 * プロパティファイル読込、値保持クラス
 *
 * @version $Revision: 15878 $
 * @author $Author: orimoto $
 */
namespace Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system;

use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\StringUtil;

 class PaygentB2BModuleResources {

	/** クライアント証明書ファイルパス */
	var $clientFilePath = "";

    /** クライアント証明書未使用設定 */
    var $notUseClientCert = "";

	/** CA証明書ファイルパス */
	var $caFilePath = "";

    /** CA証明書未使用設定 */
    var $notUseCaCert = "";

	/** Proxyサーバ名 */
	var $proxyServerName = "";

	/** ProxyIPアドレス */
	var $proxyServerIp = "";

	/** Proxyポート番号 */
	var $proxyServerPort = 0;

	/** デフォルトID */
	var $defaultId = "";

	/** デフォルトパスワード */
	var $defaultPassword = "";

	/** タイムアウト値 */
	var $timeout = 0;

	/** ログ出力先 */
	var $logOutputPath = "";

	/** 照会MAX件数 */
	var $selectMaxCnt = 0;

	/** 設定ファイル（プロパティ） */
	var $propConnect = null;

	/** UTF-8の電文種別リスト */
	private $telegramKindUtf8s = null;

	/** 照会系電文種別リスト */
	var $telegramKindRefs = null;

	/** デバッグオプション */
	var $debugFlg = 0;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    public function __construct(EccubeConfig $eccubeConfig) {
        $this->eccubeConfig = $eccubeConfig;
    }

	/**
	 * コンストラクタ
	 */
	function PaygentB2BModuleResources() {
	}

	/**
	 * PaygentB2BModuleResources を取得
	 *
	 * @return PaygentB2BModuleResources　失敗の場合、エラーコード
	 */
	static function &getInstance($eccubeConfig) {
		static $resourceInstance = null;

		if (isset($resourceInstance) == false
			|| $resourceInstance == null
			|| is_object($resourceInstance) != true) {

			$resourceInstance = new PaygentB2BModuleResources($eccubeConfig);
			$rslt = $resourceInstance->readProperties();
			if ($rslt === true) {
			} else {
				$resourceInstance = $rslt;
			}
		}

		return $resourceInstance;
	}

	/**
	 * クライアント証明書ファイルパスを取得。
	 *
	 * @return clientFilePath
	 */
	function getClientFilePath() {
		return $this->clientFilePath;
	}

    /**
     * クライアント証明書未使用設定を取得。
     *
     * @return notUseClientCert
     */
    function getNotUseClientCert() {
        return $this->notUseClientCert;
    }

	/**
	 * CA証明書ファイルパスを取得。
	 *
	 * @return caFilePath
	 */
	function getCaFilePath() {
		return $this->caFilePath;
	}

    /**
     * CA証明書未使用設定を取得。
     *
     * @return notUseCaCert
     */
    function getNotUseCaCert() {
        return $this->notUseCaCert;
    }

	/**
	 * Proxyサーバ名を取得。
	 *
	 * @return proxyServerName
	 */
	function getProxyServerName() {
		return $this->proxyServerName;
	}

	/**
	 * ProxyIPアドレスを取得。
	 *
	 * @return proxyServerIp
	 */
	function getProxyServerIp() {
		return $this->proxyServerIp;
	}

	/**
	 * Proxyポート番号を取得。
	 *
	 * @return proxyServerPort
	 */
	function getProxyServerPort() {
		return $this->proxyServerPort;
	}

	/**
	 * デフォルトIDを取得。
	 *
	 * @return defaultId
	 */
	function getDefaultId() {
		return $this->defaultId;
	}

	/**
	 * デフォルトパスワードを取得。
	 *
	 * @return defaultPassword
	 */
	function getDefaultPassword() {
		return $this->defaultPassword;
	}

	/**
	 * タイムアウト値を取得。
	 *
	 * @return timeout
	 */
	function getTimeout() {
		return $this->timeout;
	}

	/**
	 * ログ出力先を取得。
	 *
	 * @return logOutputPath
	 */
	function getLogOutputPath() {
		return $this->logOutputPath;
	}

	/**
	 * 照会MAX件数を取得。
	 *
	 * @return selectMaxCnt
	 */
	function getSelectMaxCnt() {
		return $this->selectMaxCnt;
	}

	/**
	 * 接続先URLを取得。
	 *
	 * @param telegramKind
	 * @return FALSE: 失敗(PaygentB2BModuleConnectException::TEREGRAM_PARAM_OUTSIDE_ERROR)、成功:取得した URL
	 */
	function getUrl($telegramKind) {
		$rs = null;
		$sKey = null;

		// プロパティチェック
		if ($this->propConnect == null) {
			trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_outside_error']
				. ": HTTP request contains unexpected value.", E_USER_WARNING);
			return false;
		}

		// 引数チェック
		if (StringUtil::isEmpty($telegramKind)) {
			trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_outside_error']
				. ": HTTP request contains unexpected value.", E_USER_WARNING);
			return false;
		}

		// 全桁数でプロパティからURLを取得
		$sKey = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__url_comm'] . $telegramKind;
		if (array_key_exists($sKey, $this->propConnect)) {
			$rs = $this->propConnect[$sKey];
		}

		// 全桁数で取得できた場合、その値を戻す
		if (!StringUtil::isEmpty($rs)) {
			return $rs;
		}

		// 先頭２桁でプロパティからURLを取得
		if (strlen($telegramKind) > $this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__telegram_kind_first_chars']) {
			$sKey = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__url_comm']
				. substr($telegramKind, 0, $this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__telegram_kind_first_chars']);
		} else {
			// 全桁数となり、エラーとする
			trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_outside_error']
				. ": HTTP request contains unexpected value.", E_USER_WARNING);
			return false;
		}
		if (array_key_exists($sKey, $this->propConnect)) {
			$rs = $this->propConnect[$sKey];
		}

		// 全桁数と先頭２桁で取得できなかった場合、エラーを戻す
		if (StringUtil::isEmpty($rs)) {
			trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__teregram_param_outside_error']
				. ": HTTP request contains unexpected value.", E_USER_WARNING);
			return false;
		}

		return $rs;
	}

	/**
	 * デバッグオプションを取得。
	 *
	 * @return debugFlg
	 */
	function getDebugFlg() {
		return $this->debugFlg;
	}

	/**
	 * PropertiesFile の値を取得し、設定。
	 *
	 * @return mixed 成功：TRUE、他：エラーコード
	 */
	function readProperties() {

		// Properties File Read
		$prop = null;

		// PluginDataディレクトリ
		$paygentPaymentPluginData = $this->eccubeConfig['plugin_data_realdir'].'/'.$this->eccubeConfig['paygent_payment']['paygent_payment_code'];

		$prop = PaygentB2BModuleResources::parseJavaProperty($paygentPaymentPluginData."/".$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__properties_file_name']);
		if ($prop === false) {
			// Properties File 読込エラー
			trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__resource_file_not_found_error']
				. ": Properties file doesn't exist.", E_USER_WARNING);
			return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__resource_file_not_found_error'];
		}

		// 必須項目エラーチェック
		if (!($this->isPropertiesIndispensableItem($prop)
			&& $this->isPropertiesSetData($prop)
			&& $this->isPropertieSetInt($prop))
			|| $this->isURLNull($prop)) {
			// 必須項目エラー
			$propConnect = null;
			trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__resource_file_required_error']
				. ": Properties file contains inappropriate value.", E_USER_WARNING);
			return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__resource_file_required_error'];
		}
		$this->propConnect = $prop;

		// クライアント証明書ファイルパス
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__client_file_path'], $prop)
				&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__client_file_path']]))) {
			$this->clientFilePath = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__client_file_path']];
		}

        // クライアント証明書未使用設定
        if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_client_cert'], $prop)
                && !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_client_cert']]))) {
            $this->notUseClientCert = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_client_cert']];
        }

		// CA証明書ファイルパス
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__ca_file_path'], $prop)
				&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__ca_file_path']]))) {
			$this->caFilePath = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__ca_file_path']];
		}

        // CA証明書未使用設定
        if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_ca_cert'], $prop)
                && !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_ca_cert']]))) {
            $this->notUseCaCert = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_ca_cert']];
        }

		// Proxyサーバ名
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__proxy_server_name'], $prop)
				&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__proxy_server_name']]))) {
			$this->proxyServerName = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__proxy_server_name']];
		}

		// ProxyIPアドレス
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__proxy_server_ip'], $prop)
				&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__proxy_server_ip']]))) {
			$this->proxyServerIp = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__proxy_server_ip']];
		}

		// Proxyポート番号
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__proxy_server_port'], $prop)
				&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__proxy_server_port']]))) {
			if (StringUtil::isNumeric($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__proxy_server_port']])) {
				$this->proxyServerPort = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__proxy_server_port']];
			} else {
				// 設定値エラー
				trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__resource_file_required_error']
					. ": Properties file contains inappropriate value.", E_USER_WARNING);
				return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__resource_file_required_error'];
			}
		}

		// デフォルトID
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__default_id'], $prop)
				&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__default_id']]))) {
			$this->defaultId = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__default_id']];
		}

		// デフォルトパスワード
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__default_password'], $prop)
				&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__default_password']]))) {
			$this->defaultPassword = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__default_password']];
		}

		// タイムアウト値
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__timeout_value'], $prop)
				&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__timeout_value']]))) {
			$this->timeout = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__timeout_value']];
		}

		// ログ出力先
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__log_output_path'], $prop)
				&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__log_output_path']]))) {
			$this->logOutputPath = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__log_output_path']];
		}

		// 照会MAX件数
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__select_max_cnt'], $prop)
				&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__select_max_cnt']]))) {
			$this->selectMaxCnt = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__select_max_cnt']];
		}

		// UTF-8の電文種別リスト
		$keyUtf8 = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__telegram_kind_utf8'];
		if (array_key_exists($keyUtf8, $prop) && !StringUtil::isEmpty($prop[$keyUtf8])) {
			$this->telegramKindUtf8s = $this->split($prop[$keyUtf8], $this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__telegram_kind_separator']);
		}

		// 照会電文種別リスト
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__telegram_kind_refs'], $prop)
				&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__telegram_kind_refs']]))) {
			$telegramKindRef = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__telegram_kind_refs']];
			$this->telegramKindRefs = $this->split($telegramKindRef, $this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__telegram_kind_separator']);
		}
		if ($this->telegramKindRefs == null) {
			$this->telegramKindRefs = [];
		}

		// デバッグオプション
		if (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__debug_flg'], $prop)
			&& !(StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__debug_flg']]))) {
			$this->debugFlg = $prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__debug_flg']];
		}

		return true;
	}

	/**
	 * Properties 必須項目チェック
	 *
	 * @param Properties
	 * @return boolean true=必須項目有り false=必須項目無し
	 */
	function isPropertiesIndispensableItem($prop) {
		$rb = false;

		if (((array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__client_file_path'], $prop) || array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_client_cert'], $prop))
				&& (array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__ca_file_path'], $prop) || array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_ca_cert'], $prop))
				&& array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__timeout_value'], $prop)
				&& array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__log_output_path'], $prop)
				&& array_key_exists($this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__select_max_cnt'], $prop))) {
			// 必須項目有り
			$rb = true;
		}

		return $rb;
	}

	/**
	 * Properties データ設定チェック
	 *
	 * @param prop Properties
	 * @return boolean true=データ未設定項目無し false=データ未設定項目有り
	 */
	function isPropertiesSetData($prop) {
		$rb = true;

		if (((!isset($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__client_file_path']]) || StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__client_file_path']]))
		        && (!isset($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_client_cert']]) || StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_client_cert']])))
			    || ((!isset($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__ca_file_path']]) || StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__ca_file_path']]))
			    && (!isset($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_ca_cert']]) || StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__not_use_ca_cert']])))
				|| StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__timeout_value']])
				|| StringUtil::isEmpty($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__select_max_cnt']])) {
			// 必須項目未設定エラー
			$rb = false;
		}

		return $rb;
	}

	/**
	 * Properties 数値チェック
	 *
	 * @param prop Properties
	 * @return boolean true=数値設定 false=数値未設定
	 */
	function isPropertieSetInt($prop) {
		$rb = false;

		if (StringUtil::isNumeric($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__timeout_value']])
				&& StringUtil::isNumeric($prop[$this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__select_max_cnt']])) {
			// 数値設定
			$rb = true;
		}

		return $rb;
	}

	/**
	 * 接続先URLはヌルかどうかのチェック
	 *
	 */
	function isURLNull($prop) {
		$rb = false;
		if (!is_array($prop)) {
			return true;
		}

		foreach($prop as $key => $value) {

			if (strpos($key, $this->eccubeConfig['paygent_payment']['paygentb2bmoduleresources__url_comm']) === 0) {
				if (isset($value) == false
					|| strlen(trim($value)) == 0) {
					$rb = true;
					break;
				}
			}
		}
		return $rb;
	}

	/**
	 * 指定された区切り文字で文字列を分割し、トリムする
	 *
	 * @param str 文字列
	 * @param separator 区切り文字
	 * @return リスト
	 */
	function split($str, $separator) {
		$list = [];

		if ($str == null) {
			return $list;
		}

		if ($separator == null || strlen($separator) == 0) {
			if (!StringUtil::isEmpty(trim($str))) {
				$list[] = trim($str);
			}
			return $list;
		}

		$arr = explode($separator, $str);
		for ($i=0; $arr && $i < sizeof($arr); $i++) {
			if (!StringUtil::isEmpty(trim($arr[$i]))) {
				$list[] = trim($arr[$i]);
			}
		}

		return $list;
	}

	/**
	 * UTF-8対象の電文かどうかを返す
	 * @param telegramKind 電文種別
	 * @return true=UTF-8対象 false=UTF-8対象でない
	 */
	function isTelegramKindUtf8($telegramKind) {
	    if ($this->telegramKindUtf8s == null) {
	        return false;
	    }
	    if (in_array($telegramKind, $this->telegramKindUtf8s)) {
	        return true;
	    }
	    return false;
	}

	/**
	 * 照会電文チェック
	 * @param telegramKind 電文種別
	 * @return true=照会電文 false=照会電文以外
	 */
	function isTelegramKindRef($telegramKind) {
		$bRet = false;

		if ($this->telegramKindRefs == null) {
			return $bRet;
		}
		$bRet = in_array($telegramKind, $this->telegramKindRefs);
		return $bRet;
	}

 	/**
 	 * Javaフォーマットのプロパティファイルから値を取得して
 	 * 配列に入れて返す
 	 *
 	 * @param fileName プロパティファイル名
 	 * @param commentChar コメント用文字
 	 * @return FALSE: 失敗、他:KEY=VALUE形式の配列,
 	 */
 	function parseJavaProperty($fileName, $commentChar = "#") {

		$properties = [];

		$lines = @file($fileName, FILE_USE_INCLUDE_PATH | FILE_IGNORE_NEW_LINES);
 		if ($lines === false) {
			// Properties File 読込エラー
			return $lines;
 		}

 		foreach ($lines as $i => $line) {
 			$lineData = trim($line);

 			$index = strpos($lineData, '\r');
 			if (!($index === false)) {
 				$lineData = trim(substr($lineData, 0, $index));
 			}
 			$index = strpos($lineData, '\n');
 			if (!($index === false)) {
 				$lineData = trim(substr($lineData, 0, $index));
 			}

 			if (strlen($lineData) <= 0) {
 				continue;
 			}
 			$firstChar = substr($lineData, 0, strlen($commentChar));

 			if ($firstChar == $commentChar) {
 				continue;
 			}

			$quotationIndex = strpos($lineData, '=');
			if ($quotationIndex <= 0) {
				continue;
			}

			$key = trim(substr($lineData, 0, $quotationIndex));
			$value = null;
			if (strlen($lineData) > $quotationIndex) {
				$value = trim(substr($lineData, $quotationIndex + 1));
			}
			$properties[$key] = $value;
 		}

 		return $properties;
 	}

 }
?>
