<?php
/**
 * PAYGENT B2B MODULE
 * ReferenceResponseDataImpl.php
 *
 * Copyright (C) 2007 by PAYGENT Co., Ltd.
 * All rights reserved.
 */

namespace Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\entity;

use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\CSVTokenizer;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\CSVWriter;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util\StringUtil;

/**
 * 照会系応答電文処理クラス
 *
 * @version $Revision: 15878 $
 * @author $Author: orimoto $
 */

class ReferenceResponseDataImpl extends ResponseData {
	/** 処理結果 */
	var $resultStatus;

	/** レスポンスコード */
	var $responseCode;

	/** レスポンス詳細 */
	var $responseDetail;

	/** データヘッダー */
	var $dataHeader;

	/** データ */
	var $data;

	/** 現在のIndex */
	var $currentIndex;

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
	function ReferenceResponseDataImpl() {
		$this->dataHeader = [];
		$this->data = [];
		$this->currentIndex = 0;
	}

	/**
	 * data を分解
	 *
	 * @param data
	 * @return mixed TRUE:成功、他：エラーコード
	 */
	function parse($body) {

		$csvTknzr = new CSVTokenizer($this->eccubeConfig, $this->eccubeConfig['paygent_payment']['csvtokenizer__def_separator'],
			$this->eccubeConfig['paygent_payment']['csvtokenizer__def_item_envelope']);

		// 保持データを初期化
		$this->data = [];

		// 現在位置を初期化
		$this->currentIndex = 0;

		// リザルト情報の初期化
		$this->resultStatus = "";
		$this->responseCode = "";
		$this->responseDetail = "";

		$lines = explode($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_separator'], $body);
		foreach($lines as $i => $line) {
			$lineItem = $csvTknzr->parseCSVData($line);

			if (0 < count($lineItem)) {
				if ($lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_record_division']]
						== $this->eccubeConfig['paygent_payment']['responsedata__lineno_header']) {
					// ヘッダー部の行の場合
					if ($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_result'] < count($lineItem)) {
						// 処理結果を設定
						$this->resultStatus = $lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_result']];
					}
					if ($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_response_code'] < count($lineItem)) {
						// レスポンスコードを設定
						$this->responseCode = $lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_response_code']];
					}
					if ($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_response_detail'] < count($lineItem)) {
						// レスポンス詳細を設定
						$this->responseDetail = $lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_response_detail']];
					}
				} else if ($lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_record_division']]
						== $this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__lineno_data_header']) {
					// データヘッダー部の行の場合
					$this->dataHeader = [];

					for ($i = 1; $i < count($lineItem); $i++) {
						// データヘッダーを設定（レコード区分は除く）
						$this->dataHeader[] = $lineItem[$i];
					}
				} else if ($lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_record_division']]
						== $this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__lineno_data']) {
					// データ部の行の場合
					// データヘッダー部が既に展開済みである事を想定
					$map = [];

					if (count($this->dataHeader) == (count($lineItem) - 1)) {
						// データヘッダー数と、データ項目数（レコード区分除く）は一致
						for ($i = 1; $i < count($lineItem); $i++) {
							// 対応するデータヘッダーを Key に、Mapへ設定
							$map[$this->dataHeader[$i - 1]] = $lineItem[$i];
						}
					} else {
						// データヘッダー数と、データ項目数が一致しない場合
						$sb = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__other_error'] . ": ";
						$sb .= "Not Mutch DataHeaderCount=";
						$sb .= "" . count($this->dataHeader);
						$sb .= " DataItemCount:";
						$sb .= "" . (count($lineItem) - 1);
						trigger_error($sb, E_USER_WARNING);
						return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__other_error'];
					}

					if (0 < count($map)) {
						// Map が設定されている場合
						$this->data[] = $map;
					}
				} else if ($lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_record_division']]
						== $this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__lineno_trailer']) {
					// トレーラー部の行の場合
					if ($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_trailer_data_count'] < count($lineItem)) {
						// データサイズ
					}
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
	 * data を分解 リザルト情報のみ、変数に設定
	 *
	 * @param body
	 * @return mixed TRUE:成功、他：エラーコード
	 */
	function parseResultOnly($body) {

		$csvTknzr = new CSVTokenizer($this->eccubeConfig, $this->eccubeConfig['paygent_payment']['csvtokenizer__def_separator'],
			$this->eccubeConfig['paygent_payment']['csvtokenizer__def_item_envelope']);
		$line = "";

		// 保持データを初期化
		$this->data = [];

		// 現在位置を初期化
		$this->currentIndex = 0;

		// リザルト情報の初期化
		$this->resultStatus = "";
		$this->responseCode = "";
		$this->responseDetail = "";

		$lines = explode($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_separator'], $body);
		foreach($lines as $i => $line) {
			$lineItem = $csvTknzr->parseCSVData($line);

			if (0 < count($lineItem)) {
				if ($lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_record_division']]
						== $this->eccubeConfig['paygent_payment']['responsedata__lineno_header']) {
					// ヘッダー部の行の場合
					if ($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_result'] < count($lineItem)) {
						// 処理結果を設定
						$this->resultStatus = $lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_result']];
					}
					if ($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_response_code'] < count($lineItem)) {
						// レスポンスコードを設定
						$this->responseCode = $lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_response_code']];
					}
					if ($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_response_detail'] < count($lineItem)) {
						// レスポンス詳細を設定
						$this->responseDetail = $lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_header_response_detail']];
					}
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
	 * 次のデータを取得
	 *
	 * @return Map
	 */
	function resNext() {
		$map = null;

		if ($this->hasResNext()) {

			$map = $this->data[$this->currentIndex];

			$this->currentIndex++;
		}

		return $map;
	}

	/**
	 * 次のデータが存在するか判定
	 *
	 * @return boolean true=存在する false=存在しない
	 */
	function hasResNext() {
		$rb = false;

		if ($this->currentIndex < count($this->data)) {
			$rb = true;
		}

		return $rb;
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
	 * データ件数を取得
	 *
	 * @param data InputStream
	 * @return int -1:エラー
	 */
	function getDataCount($body) {
		$ri = 0;
		$strCnt = null;

		$csvTknzr = new CSVTokenizer($this->eccubeConfig, $this->eccubeConfig['paygent_payment']['csvtokenizer__def_separator'],
			$this->eccubeConfig['paygent_payment']['csvtokenizer__def_item_envelope']);
		$line = "";

		$lines = explode($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_separator'], $body);
		foreach($lines as $i => $line) {
			$lineItem = $csvTknzr->parseCSVData($line);

			if (0 < count($lineItem)) {
				if ($lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_record_division']]
						== $this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__lineno_trailer']) {
					// トレーラー部の行の場合
					if ($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_trailer_data_count'] < count($lineItem)) {
						// データ件数を取得 whileから抜ける
						if (StringUtil::isNumeric($lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_trailer_data_count']])) {
							$strCnt = $lineItem[$this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_trailer_data_count']];
						}
						break;
					}
				}
			}
		}

		if ($strCnt != null && StringUtil::isNumeric($strCnt)) {
			$ri = intval($strCnt);
		} else {
			return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__other_error'];		//エラー
		}

		return $ri;
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
			// ファイルオーブンエラー
			trigger_error($this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__csv_output_error']
				. ": Failed to open CSV file.", E_USER_WARNING);
			return $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__csv_output_error'];
		}

		$lines = explode($this->eccubeConfig['paygent_payment']['referenceresponsedataimpl__line_separator'], $body);
		foreach($lines as $i => $line) {
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