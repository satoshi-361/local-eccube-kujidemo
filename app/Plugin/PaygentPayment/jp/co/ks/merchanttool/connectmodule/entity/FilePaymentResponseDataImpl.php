<?php
/**
 * PAYGENT B2B MODULE
 * FilePaymentResponseDataImpl.php
 *
 * Copyright (C) 2010 by PAYGENT Co., Ltd.
 * All rights reserved.
 */

namespace Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\entity;

use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\CSVTokenizer;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\CSVWriter;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\StringUtil;

/**
 * ファイル決済系応答電文処理クラス
 *
 * @version $Revision: 15878 $
 * @author $Author: orimoto $
 */

class FilePaymentResponseDataImpl extends ResponseData {

	/** 処理結果 */
	var $resultStatus;

	/** レスポンスコード */
	var $responseCode;

	/** レスポンス詳細 */
	var $responseDetail;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    public function __construct(EccubeConfig $eccubeConfig) {
        $this->eccubeConfig = $eccubeConfig;
    }

	/**
     * ファイル決済の場合は値を含むパースは不可。
     * 常にExceptionをthrowする。
	 *
	 * @param data
	 */
	function parse($body) {
	    trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__file_payment_error']
				. ": parse is not supported.", E_USER_WARNING);
	    return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__file_payment_error'];
	}

	/**
     * data を分解 リザルト情報のみ、変数に設定。
	 *
	 * @param body
	 * @return mixed TRUE:成功、他：エラーコード
	 */
	function parseResultOnly($body) {

	    $csvTknzr = new CSVTokenizer($this->eccubeConfig, $this->eccubeConfig['paygent_payment']['csvtokenizer__def_separator'],
			$this->eccubeConfig['paygent_payment']['csvtokenizer__no_item_envelope']);
		$line = "";

		// リザルト情報の初期化
		$this->resultStatus = "";
		$this->responseCode = "";
		$this->responseDetail = "";

		$lines = explode($this->eccubeConfig['paygent_payment']['filepaymentresponsedataimpl__line_separator'], $body);
		foreach($lines as $i => $line) {
			$lineItem = $csvTknzr->parseCSVData($line);

			if (0 < count($lineItem)) {
				if ($lineItem[$this->eccubeConfig['paygent_payment']['filepaymentresponsedataimpl__line_record_division']]
						== $this->eccubeConfig['paygent_payment']['filepaymentresponsedataimpl__lineno_header']) {
					// ヘッダー部の行の場合
					if ($this->eccubeConfig['paygent_payment']['filepaymentresponsedataimpl__line_header_result'] < count($lineItem)) {
						// 処理結果を設定
						$this->resultStatus = $lineItem[$this->eccubeConfig['paygent_payment']['filepaymentresponsedataimpl__line_header_result']];
					}
					if ($this->eccubeConfig['paygent_payment']['filepaymentresponsedataimpl__line_header_response_code'] < count($lineItem)) {
						// レスポンスコードを設定
						$this->responseCode = $lineItem[$this->eccubeConfig['paygent_payment']['filepaymentresponsedataimpl__line_header_response_code']];
					}
					if ($this->eccubeConfig['paygent_payment']['filepaymentresponsedataimpl__line_header_response_detail'] < count($lineItem)) {
						// レスポンス詳細を設定
						$this->responseDetail = $lineItem[$this->eccubeConfig['paygent_payment']['filepaymentresponsedataimpl__line_header_response_detail']];
					}

					// ヘッダーのみの解析で終了
					break;
				}
			}
		}

		if (StringUtil::isEmpty($this->resultStatus)) {
			// 処理結果が 空文字 もしくは null の場合
			trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__ks_connect_error']
				. ": resultStatus is Nothing.", E_USER_WARNING);
			return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleconnectexception__ks_connect_error'];
		}

		return true;

	}

	/**
     * 次のデータを取得。
	 *
	 * @return Map
	 */
	function resNext() {
		return null;
	}

	/**
     * 次のデータが存在するか判定。
     *
	 * @return boolean true=存在する false=存在しない
	 */
	function hasResNext() {
		return false;
	}

	/**
	 * resultStatus を取得
	 *
	 * @return String
	 */
	function getResultStatus() {
		return $this->resultStatus;
	}

	/**
	 * responseCode を取得
	 *
	 * @return String
	 */
	function getResponseCode() {
		return $this->responseCode;
	}

	/**
	 * responseDetail を取得
	 *
	 * @return String
	 */
	function getResponseDetail() {
		return $this->responseDetail;
	}

	/**
	 * CSV を作成
	 *
	 * @param resBody
	 * @param resultCsv String
	 * @return boolean true：成功、他：エラーコード
	 */
	function writeCSV($body, $resultCsv) {
		$rb = false;

		// CSV を 1行ずつ出力
		$csvWriter = new CSVWriter($this->eccubeConfig, $resultCsv);
		if ($csvWriter->open() === false) {
			// ファイルオープンエラー
			trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__csv_output_error']
				. ": Failed to open CSV file.", E_USER_WARNING);
			return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__csv_output_error'];
		}

		$lines = explode($this->eccubeConfig['paygent_payment']['filepaymentresponsedataimpl__line_separator'], $body);

		foreach($lines as $i => $line) {
			if(StringUtil::isEmpty($line)) {
				continue;
			}
			if (!$csvWriter->writeOneLine($line)) {
				// 書き込めなかった場合
				trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__csv_output_error']
					. ": Failed to write to CSV file.", E_USER_WARNING);
				return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__csv_output_error'];
			}
		}

		$csvWriter->close();

		$rb = true;

		return $rb;
	}



}

?>