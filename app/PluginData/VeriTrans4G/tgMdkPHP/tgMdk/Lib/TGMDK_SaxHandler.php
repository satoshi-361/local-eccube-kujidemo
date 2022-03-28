<?php
if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__)) die('Permission denied.');

if (!defined('MDK_LIB_DIR')) require_once('../3GPSMDK.php');

/**
 *
 * PHP版Trad用XMLパーサクラス。
 *
 * ノード情報が半角英数で始まっている時、
 * 途中で日本語が出て来るとノードが分割されてしまう現象に対応。
 *
 * @category    Veritrans
 * @package     Lib
 * @copyright   VeriTrans Inc.
 * @access  public
 * @author VeriTrans Inc.
 */
class TGMDK_SaxHandler {

    /** XMLパーサ */
    private $xml_parser;

    /** キーとして設定される文字列 */
    private $keyString = null;

    /** ノードの値を保持 */
    private $node_data = null;

    /** キーとしての配列 */
    private $key_data_list = Array();

    /** 値としての配列 */
    private $val_data_list = Array();

    /**
     * デストラクタ。
     */
    public function  __destruct() {
        xml_parser_free($this->xml_parser);
    }

    /**
     * XMLを設定して
     *
     * @access  public
     * @param string $xml_str XML文字列
     */
    public function set_xml($xml_str) {
        $this->xml_parser = xml_parser_create("UTF-8");

        // 大文字変換を行わない
        xml_parser_set_option($this->xml_parser, XML_OPTION_CASE_FOLDING, false);

        //start および end 要素のハンドラを設定する
        xml_set_element_handler($this->xml_parser, Array("TGMDK_SaxHandler", "start_element"), Array("TGMDK_SaxHandler", "end_element"));

        //文字データハンドラを設定する
        xml_set_character_data_handler($this->xml_parser, Array("TGMDK_SaxHandler", "characters"));

        xml_parse($this->xml_parser, $xml_str, TRUE);
    }

    /**
     * エレメント開始時の処理。
     *
     * @access  public
     * @param resource $parser パーサ
     * @param string $name タグ名
     * @param string $attributes 属性
     */
    public function start_element($parser, $name, $attributes) {
        $this->keyString .= "/" . $name;
    }

    /**
     * エレメント終了時の処理。
     *
     * @access  public
     * @param resource $parser パーサ
     * @param string $name タグ名
     */
    public function end_element($parser, $name) {
        $trim_data = $this->node_data;
        if ($trim_data != "") {
            $this->key_data_list[] = $this->keyString;
            $this->val_data_list[] = $trim_data;
        }

        // ノード情報のクリア
        $this->node_data = "";

        $pos = strrpos($this->keyString, ("/" . $name));

        if ($pos === FALSE) {
            return;
        }
        $this->keyString = substr($this->keyString, 0, $pos);
    }

    /**
     * 値の処理。
     *
     * @access  public
     * @param resource $parser パーサ
     * @param string $data 値
     */
    public function characters($parser, $data) {
        $this->node_data = $this->node_data . ltrim($data);
    }

    /**
     * キーとしての配列を取得する。
     *
     * @return array キーを配列化したもの
     */
    public function get_key_data_list() {
        return $this->key_data_list;
    }

    /**
     * 値としての配列を取得する。
     *
     * @return array 値を配列化したもの
     */
    public function get_val_data_list() {
        return $this->val_data_list;
    }
}
