<?php
if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__)) die('Permission denied.');

if (!defined('MDK_LIB_DIR')) require_once('../3GPSMDK.php');

/**
 *
 * PHP版Trad用XMLパーサクラス
 *
 * ノード情報が半角英数で始まっている時、
 * 途中で日本語が出て来るとノードが分割される現象に対応
 *
 * @category    Veritrans
 * @package     Lib
 * @copyright   VeriTrans Inc.
 * @access  public
 * @author  VeriTrans Inc.
 */
class TGMDK_MaskSaxHandler {

    /** XMLパーサ */
    private $xml_parser;

    /** ノードの値を保持 */
    private $node_data = "";

    /** マスク化されたXMLの格納場所 */
    private $mask_xml_string = "";

    /** マスク項目 */
    private $mask_item = null;

    /**
     * デストラクタ。
     */
    public function  __destruct() {
        if (!is_null($this->xml_parser)) {
            xml_parser_free($this->xml_parser);
        }
    }

    /**
     * XMLを設定して
     *
     * @access  public
     * @param string $xml_str XML文字列
     * @return string
     */
    public function get_mask_xml($xml_str) {
        // 入力チェック
        if ($xml_str == "" || is_null($xml_str)) {
            return $this->mask_xml_string;
        }

        // マスク項目を取得する
        $conf = TGMDK_Config::getInstance();
        $response_item = $conf->getResponseDtoParameters();
        $mask_list = preg_split("/,/", strtoupper($response_item[TGMDK_Config::MASK_ITEM]));

        foreach ($mask_list as $value) {
            $this->mask_item[$value] = "1";
        }

        preg_match("/\<\?[xml|XML]+[^>]*>/", $xml_str, $matches);
        if (!is_null($matches) && 0 < count($matches)) {
            $this->mask_xml_string .= $matches[0] . "\n";
        }

        $this->xml_parser = xml_parser_create("UTF-8");

        // 大文字変換を行わない
        xml_parser_set_option($this->xml_parser, XML_OPTION_CASE_FOLDING, false);

        //start および end 要素のハンドラを設定する
        xml_set_element_handler($this->xml_parser, Array("TGMDK_MaskSaxHandler", "start_element"), Array("TGMDK_MaskSaxHandler", "end_element"));

        //文字データハンドラを設定する
        xml_set_character_data_handler($this->xml_parser, Array("TGMDK_MaskSaxHandler", "characters"));

        xml_parse($this->xml_parser, $xml_str, TRUE);

        return $this->mask_xml_string;
    }

    /**
     * エレメント開始時の処理。
     *
     * @access  public
     * @param resource $parser パーサ
     * @param string $name タグ名
     * @param array $attributes 属性
     */
    public function start_element($parser, $name, $attributes) {
        $this->mask_xml_string .= "<" . $name;
        foreach ($attributes as $key => $value) {
            $this->mask_xml_string .= " " . $key . "=\"" . $value . "\"";
        }
        $this->mask_xml_string .= ">";
    }

    /**
     * エレメント終了時の処理。
     *
     * @access  public
     * @param resource $parser パーサ
     * @param string $name タグ名
     */
    public function end_element($parser, $name) {
        // ノードの値を追加
        if (empty($this->mask_item[strtoupper($name)])) {
        // マスク対象でない場合
            $this->mask_xml_string .= $this->node_data;
        } else {
        // マスク対象の場合
            $this->mask_xml_string .= preg_replace("/./", "*", $this->node_data);
        }

        // 閉じタグの追加
        $this->mask_xml_string .= "</" . $name . ">\n";

        // ノード情報のクリア
        $this->node_data = "";
    }

    /**
     * 値の処理。
     *
     * @access  public
     * @param resource $parser パーサ
     * @param string $data 値
     */
    public function characters($parser, $data) {
        $data = trim($data);
        $tmp = TGMDK_Util::deleteRN($data);
        if (!empty($tmp)) {
            $this->node_data = $this->node_data . htmlspecialchars($data, ENT_QUOTES, "UTF-8");
        }
    }
}
