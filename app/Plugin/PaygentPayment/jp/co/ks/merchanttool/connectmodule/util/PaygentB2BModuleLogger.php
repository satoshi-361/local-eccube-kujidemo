<?php
/**
 * PAYGENT B2B MODULE
 * PaygentB2BModuleLogger.php
 *
 * Copyright (C) 2007 by PAYGENT Co., Ltd.
 * All rights reserved.
 */
namespace Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util;

use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system\PaygentB2BModuleResources;

/**
 * 接続モジュール用 Logger クラス
 *
 * @version $Revision: 15878 $
 * @author $Author: orimoto $
 */

class PaygentB2BModuleLogger {

	/** FileAppender 保持 */
	var $filename = null;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

	public function __construct(EccubeConfig $eccubeConfig)
	{
        $this->eccubeConfig = $eccubeConfig;
		$this->PaygentB2BModuleLogger();
	}

	/**
	 * コンストラクタ
	 */
	function PaygentB2BModuleLogger() {
		$inst = PaygentB2BModuleResources::getInstance($this->eccubeConfig);
		if (is_object($inst) &&
			!StringUtil::isEmpty($inst->getLogOutputPath())) {
			$this->filename = $inst->getLogOutputPath();
		}
	}

	/**
	 * PaygentB2BModuleLogger を取得
	 *
	 * @return PaygentB2BModuleLogger
	 */
	static function &getInstance($eccubeConfig) {
		static $logInstance = null;
		if (isset($logInstance) == false
			|| $logInstance == null
			|| is_object($logInstance) != true) {

			$logInstance = new PaygentB2BModuleLogger($eccubeConfig);
		}
		return $logInstance;
	}

	/**
	 * デバッグログを出力
	 *
	 * @param className String ログの出力元クラス名 出力元を識別
	 * @param message Object ログメッセージ
	 */
	function debug($className, $message) {
		if(is_null($this->filename) == false && $this->filename != "") {
			if(! $handle = fopen( $this->filename, 'a')) {
				// ファイルが開けない
				trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__other_error']. ":File doesn't open.(".$this->filename.").", E_USER_WARNING);
				return;
			}
			if(! fwrite($handle, $this->outputMsg($message, $className))) {
				// ファイルに書き込めない
				trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__other_error']. ":It is not possible to write it in the file(".$this->filename.").", E_USER_WARNING);
				return;
			}
			fclose($handle);
		}
	}

	/**
	 * 出力メッセージを整形する
	 *
	 * @param message ログメッセージ
	 * @param className クラス名
	 * @return 整形後のメッセージ
	 */
	function outputMsg($message, $className) {
		return date("Y/m/d H:i:s")." $className ".$message."\n";
	}
}

?>
