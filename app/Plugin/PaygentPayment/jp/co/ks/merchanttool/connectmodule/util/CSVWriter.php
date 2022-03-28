<?php
/**
 * PAYGENT B2B MODULE
 * CSVWriter.php
 *
 * Copyright (C) 2007 by PAYGENT Co., Ltd.
 * All rights reserved.
 */

/**
 * CSVWriter CSV形式でファイルを出力する。
 * 使用方法：<br />
 * <pre><code>
 * // writerオブジェクトを作る。デフォルトはShift_JISエンコード
 * CSVWriter writer = null;
 * try {
 *     writer = new CSVWriter(
 *         "c:\\temp\\test.txt", CSVWriter.ENCODING_SJIS);
 *     writer.open();
 *     List list = new ArrayList();
 *     list.add("1");
 *     list.add("abc");
 *     list.add("");
 *     list.add(",");
 *     list.add("にほんご");
 *     writer.writeOneLine(list);
 *     list.remove(0);
 *     list.add(0, "2");
 *     writer.writeOneLine(list);
 * } finally {
 *     writer.close();
 * }
 * </code></pre>
 * @version $Revision: 15878 $
 * @author $Author: orimoto $
 */
namespace Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util;

use Eccube\Common\EccubeConfig;

class CSVWriter {

    var $csvFile;
    var $filePath;
    var $encoding;
    var $envelop;
    var $newLine;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    public function __construct(EccubeConfig $eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
    	$this->newLine = $this->eccubeConfig['paygent_payment']['csvwriter__windows_newline'];
    }

    /**
     * コンストラクタ。エンコード及び項目データ囲み文字の指定を行いWriterを作成する。
     * @param filePath ファイルパス
     * @param encoding ファイルのエンコード
     * @param envelop 項目データ囲み文字
     */
    function CSVWriter($filePath, $encoding = "", $envelop = "") {
        if ($encoding == ""){
            $encoding = $this->eccubeConfig['paygent_payment']['csvwriter__encoding_ms932'];
        }
        if ($envelop == ""){
            $envelop = $this->eccubeConfig['paygent_payment']['csvtokenizer__def_item_envelope'];
        }
        $this->filePath = $filePath;
        $this->encoding = $encoding;
        $this->envelop = $envelop;
    }

    /**
     * 出力ファイルを開く。
     * ファイル出力が可能な状態にする。
      * @return boolean TRUE:成功、FALSE：失敗
     */
    function open() {

        $this->csvFile = fopen($this->filePath, "w");
        if ($this->csvFile == false) {
			$this->csvFile = null;
			trigger_error("cannot open file " . $this->filePath . " to write", E_USER_NOTICE);
			return false;
        }

        // チェックエンコーディング
        if (mb_convert_encoding("エンコード", $this->encoding) === false){
			trigger_error("Unsupported Encoding " . $this->encoding . ".", E_USER_NOTICE);
			return false;
        }
        return true;
    }

    /**
     * 出力ファイルを閉じる。
     * 再度ファイルを作成する場合はOpenから行うこと。
     */
    function close() {
        if ($this->csvFile != null) {
            fclose($this->csvFile);
            $this->csvFile = null;
        }
    }

    /**
     * 改行コードを設定する。未設定の場合、\nで出力する。
     * @param newLine 改行コードの文字列
     */
    function setNewLine($newLine) {
        $this->newLine = $newLine;
    }

    /**
     * ファイルを一行分書き込む。末尾に改行コードを追加する。
     * Listの場合、Listの中身をCSV形式の一行に変換し、出力を行う。
     * @param line 一行分の文字列(String)或いは配列(array)
     * @return 書き込めたらtrue。
     */
    function writeOneLine($line) {

		if (is_string($line)) {
	        if ($this->csvFile == null) {
	            trigger_error("File not open.", E_USER_NOTICE);
	            return false;
	        }
	        $encLine = $line;

	        if (fwrite($this->csvFile, $line) === false) {
	        	trigger_error("File can not write.", E_USER_NOTICE);
	            return false;
	        }
	        fwrite($this->csvFile, $this->newLine);
	        flush($this->csvFile);
	        return true;
		}
		else if (is_array($line)) {
	        $strLine = "";

	        // List to CSVString
	        $bFirstLine = true;
	        foreach($line as $i => $data) {
				if ($bFirstLine) {
					$bFirstLine = false;
				} else {
					$strLine .= ",";
				}

	            if ($this->envelop != $this->eccubeConfig['paygent_payment']['csvtokenizer__no_item_envelope']) {
	                $strLine .= $this->envelop;
	            }
	            $strLine .= $this->cnvKnmString($data);
	            if ($this->envelop != $this->eccubeConfig['paygent_payment']['csvtokenizer__no_item_envelope']) {
	                $strLine .= $this->envelop;
	            }
	        }

	        return $this->writeOneLine($strLine);
		}
    }

    /**
     * 文字列中にカンマが存在する場合は""で囲む。
     * 文字列中にダブルクォーテーションが存在する場合はダブルクォーテーションでエスケープし、
     * ダブルクォーテーションで囲む。
     * @param str 変換対象文字列
     * @return 変換結果文字列
     */
    function cnvKnmString($str) {
        if ($str == null) {
            return null;
        }
        $flg = false;
        $buf = "";
        for ($i = 0; $i < strlen($str); $i++) {
            if ($str[$i] == $this->envelop) {
                $buf .= $this->envelop;
                $flg = true;
            }
            if ($str[$i] == $this->eccubeConfig['paygent_payment']['csvtokenizer__def_separator']) {
                $flg = true;
            }
            $buf .= $str[$i];
        }
        return $buf;
    }
}

?>