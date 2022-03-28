<?php
/**
 * PAYGENT B2B MODULE
 * PaygentB2BModuleConnectException.php
 *
 * Copyright (C) 2007 by PAYGENT Co., Ltd.
 * All rights reserved.
 */

/*
 * 接続モジュール　接続エラー用Exception
 *
 * @version $Revision: 15878 $
 * @author $Author: orimoto $
 */
namespace Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\exception;

 class PaygentB2BModuleConnectException {

	/** エラーコード */
	var $errorCode = "";

	/**
	 * コンストラクタ
	 *
	 * @param errorCode String
	 * @param msg String
	 */
	public function __construct($errCode, $msg = null) {
		$this->errorCode = $errCode;
	}

	/**
	 * エラーコードを返す
	 *
	 * @return String errorCode
	 */
	function getErrorCode() {
		return $this->errorCode;
	}

	/**
	 * メッセージを返す
	 *
	 * @return String code=message
	 */
    function getLocalizedMessage() {
    }

 }

?>
