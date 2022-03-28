<?php
if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__)) die('Permission denied.');

if (!defined('MDK_LIB_DIR')) require_once('../3GPSMDK.php');

/**
 * TGMDK_ErrorMessage 管理クラス
 *
 * エラーメッセージを取得し管理するクラス
 *
 * @category    Veritrans
 * @package     Lib
 * @copyright   VeriTrans Inc.
 */
class TGMDK_ErrorMessage {

    // define const
    /** エラー文字列が見つからない場合に返す文字列 */
    const UNKNOWN = "-01";

    // fields
    /** エラー情報を保持する変数 */
    private static $m_error = null;
    /** エラーメッセージを保持する変数 */
    private $m_msg = null;


    /**
     * コンストラクタ。
     *
     * @access private
     * @param null|string $msg_filename メッセージプロパティファイルパス
     * @throws Exception
     */
    private function __construct($msg_filename=null) {
        if ($msg_filename == null) {
            $msg_filename = MDK_LIB_DIR . DS . "errormessage.properties";
        }
        if (!is_readable($msg_filename)) {
            // メッセージファイルが読み取れない場合は、例外を返す
            // TGMDK_Exception は、このクラスに依存しているため
            // 組み込みの Exception を返す
            throw new Exception("Error: properties file does not exist.\n" . $msg_filename . "\n");
        }
        $this->m_msg = parse_ini_file($msg_filename, FALSE);
    }

    /**
     *
     *  TGMDK_ErrorMessageクラスの静的なインスタンスを取得する
     *
     * @access public
     * @static
     * @param string $msg_filename メッセージプロパティファイルパス
     * @return mixed TGMDK_ErrorMessageクラスのインスタンス
     */
    public static function getInstance($msg_filename=null) {
        if (self::$m_error == null) {
            self::$m_error = new TGMDK_ErrorMessage($msg_filename);
        }
        return self::$m_error;
    }

    /**
     *
     * エラーメッセージを取得する
     *
     * @access public
     * @param mixed $messageId メッセージID
     * @return mixed|string 取得したメッセージ
     */
    public function getMessage($messageId) {

        if (!array_key_exists($messageId, $this->m_msg)) {
            return self::UNKNOWN;
        }

        $retVal = $this->m_msg[$messageId];

        if (func_num_args() > 1) {  //  可変個引数が存在する場合引数の型をチェックする

            $pattern = array();
            $replacement = array();
            $idx = 0;
            
            $args = func_get_args();
            array_shift($args); //  先頭要素（$messageId）を削除
            
            foreach ($args as $key => $val) {
                if ($val instanceof Exception) {
                    //  例外クラス（および、その派生クラス）なら
                    //  スタックトレースを取得し連結します
                    $retVal .= PHP_EOL . $val;
//                    $retVal .= PHP_EOL . $val->getTraceAsString();

                } elseif (is_array($val)) {
                    //  配列ならそのまま文字列として連結します
                    $retVal .= PHP_EOL . implode(PHP_EOL, $val);

                } else {
                    ++$idx;
                    //  配列以外は、メッセージパラメータに設定する値として保存する
                    $regex = "/#" . $idx . "/";     // 正規表現を作成 /#1/, /#2/, /#3/...
                    array_push($pattern, $regex);
                    array_push($replacement, $val);
                }
            }

            // #1, #2..を置き換える
            $retVal = preg_replace($pattern, $replacement, $retVal);
        }

        return $retVal;
    }

    /**
     * 静的なインスタンスを破棄する（単体テスト用）
     *
     * @access public
     * @static
     */
     public static function __reset() {
         self::$m_error = NULL;
     }
}
