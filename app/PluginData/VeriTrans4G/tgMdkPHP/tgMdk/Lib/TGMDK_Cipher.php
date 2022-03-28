<?php
if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__)) die('Permission denied.');

if (!defined('MDK_LIB_DIR')) require_once('../3GPSMDK.php');

/**
 *
 * パラメータ暗号化クラス
 *
 * @category    Veritrans
 * @package     Lib
 * @copyright   VeriTrans Inc.
 * @access  public
 * @author  VeriTrans Inc.
 */
class TGMDK_Cipher {

    /** キーストアファイルパス */
    private $storeFile = null;

    /** キーストアファイル操作時のパスワード */
    private $storePass = null;

    /** トラストストアファイルパス */
    private $trustFile = null;

    /**
     * コンストラクタ。
     * コンフィグファイルからデータを取得して当クラスを使用できる状態にする。
     * @throws TGMDK_Exception
     */
    public function __construct() {
        $conf = TGMDK_Config::getInstance();
        $array = $conf->getCipherParameters();

        if (isset($array[TGMDK_Config::PRIVATE_CERT_FILE])) {
            $this->storeFile = $array[TGMDK_Config::PRIVATE_CERT_FILE];
        }
        if (isset($array[TGMDK_Config::PRIVATE_CERT_PASSWORD])) {
            $this->storePass = $array[TGMDK_Config::PRIVATE_CERT_PASSWORD];
        }
        if (isset($array[TGMDK_Config::TRUST_CERT_FILE])) {
            $this->trustFile = $array[TGMDK_Config::TRUST_CERT_FILE];
        }
    }

    /**
     * デストラクタ。
     */
    public function  __destruct() {
    }

    /**
     * Base64エンコードを行う。<br>
     * エンコード後の文字列を"/"を"-"に、"+"を"_"に、"="を"*"に置換して返却する。
     *
     * @access public
     * @param string $data エンコードする文字列
     * @return string エンコードを行った結果の文字列
     */
    public function base64Enc($data) {
        $data = base64_encode($data);
        $data = str_replace("/", "-", $data);
        $data = str_replace("+", "_", $data);
        $data = str_replace("=", "*", $data);

        return $data;
    }

    /**
     * Base64デコードを行う。<br>
     * base64Encの逆。<br>
     * デコード前の文字列を"-"を"/"に、"_"を"+"に、"*"を"="に置換してからデコードする。
     *
     * @access public
     * @param string $data デコードする文字列
     * @return mixed|string デコードを行った結果の文字列
     */
    public function base64Dec($data) {
        $data = str_replace("*", "=", $data);
        $data = str_replace("_", "+", $data);
        $data = str_replace("-", "/", $data);
        $data = base64_decode($data);

        return $data;
    }

}
