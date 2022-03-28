<?php
if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__)) die('Permission denied.');

if (!defined('MDK_LIB_DIR')) require_once('../3GPSMDK.php');
/*
 * マーチャントが設定した要求DTOよりJSON文字列を作成するクラス
 */
class TGMDK_JSONQuery {

    /** N=V要素結合文字 */
    const PARAM_UNITE_CHAR = '&';
    /** N=V要素結合文字エスケープ文字列 */
    const PARAM_UNITE_CHAR_ESCAPE = '\&';
    /** ダブルクオート */
    const DQUOTE_CHAR = "\"";
    /** ダブルクオートエスケープ文字列 */
    const DQUOTE_CHAR_ESCAPE = "\\\"";
    /** N、V結合文字 */
    const NV_UNITE_CHAR = "=";
    /** BaseDto名 */
    const BASE_DTO_NAME = "MdkBaseDto";
    /** サービス固有要素前置詞 */
    const EXPARAM_PREPOSIT = "exparam";
    /** NAME部階層区切り文字 */
    const N_SEP = ".";
    /** サービスタイプのフィールド名 */
    const FIELD_NAME_SERVICE_TYPE = "SERVICE_TYPE";
    /** サービスコマンドのフィールド名 */
    const FIELD_NAME_SERVICE_COMMAND = "SERVICE_COMMAND";
    /** サーチパラメータのフィールド名 */
    const FIELD_NAME_SEARCH_PARAM = "SearchParameter";
    /** サーチの範囲指定フィールド名 */
    const FIELD_NAME_SEARCH_RANGE = "SearchRange";

    /** TGMDK_Configファイルの読み込み */
    private $conf;

    /** JSON文字 */
    private $jsonParame;

    /** ログ出力用文字列 */
    private $maskedLogString;
    /** サービスタイプ */
    private $serviceType;
    /** サービスコマンド */
    private $serviceCommand;

    /**
     * JSON文字列を取得する
     * @return string|bool
     */
    public function getJsonParam() {
        return $this->jsonParame;
    }

    /**
     * ログ出力用文字列を取得する
     * @return string
     */
    public function getMaskedLogString() {
        return $this->maskedLogString;
    }

    /**
     * サービスタイプを取得する
     */
    public function getServiceType() {
        return $this->serviceType;
    }

    /**
     * サービスコマンドを取得する
     */
    public function getServiceCommand() {
        return $this->serviceCommand;
    }

    /**
     * コンストラクタ。
     * コンフィグファイルからデータを取得して当クラスを使用できる状態にする。
     * @access public
     */
    public function __construct() {
        $myConf = TGMDK_Config::getInstance();
        $this->conf = $myConf->getRequestDtoParameters();
        $this->conf = array_merge($this->conf, $myConf->getResponseDtoParameters());
        $this->conf = array_merge($this->conf, $myConf->getEnvironmentParameters());
    }

    /**
     * リクエストDTOをJSON文字列に変換する
     * @param MdkBaseDto $requestDto リクエストDTO
     */
    public function createJsonParameter($requestDto, $className) {
        $arrayData = (array)$requestDto;
        $maskedArrayData = (array)$requestDto;
        if (is_array($arrayData)) {
            foreach ($arrayData as $key => $value) {
                $orgKey = (string)$key;
                $workKey = str_replace($className, "", $orgKey);
                $replacekey = preg_replace('/\W/', '', $workKey);
                if (is_object($value)) {
                    $arrayData[$replacekey] = $this->createJsonSubObject($value, get_class($value), false);
                    $maskedArrayData[$replacekey] = $this->createJsonSubObject($value, get_class($value), true);
                } elseif (is_array($value)) {
                    $arrayData[$replacekey] = $this->createJsonSubObject($value, $className, false);
                    $maskedArrayData[$replacekey] = $this->createJsonSubObject($value, $className, true);
                } else {
                    if (!is_null($value)) {
                        if (strpos($replacekey, self::FIELD_NAME_SERVICE_TYPE) !== FALSE) {
                            $this->serviceType = $value;
                        } else if (strpos($replacekey, self::FIELD_NAME_SERVICE_COMMAND) !== FALSE) {
                            $this->serviceCommand = $value;
                        } else {
                            $param = $this->encConv($value);
                            $arrayData[$replacekey] = $param;
                            $maskedArrayData[$replacekey] = TGMDK_Util::maskValue($replacekey, $param);
                        }
                    }
                }
                if ($orgKey != $replacekey) {
                    unset($arrayData[$orgKey]);
                    unset($maskedArrayData[$orgKey]);
                }
            }
        }
        $arrayData = array_filter($arrayData, array($this, 'shouldParseToJson'));
        $maskedArrayData = array_filter($maskedArrayData, array($this, 'shouldParseToJson'));
        // JSON文字列
        $this->jsonParame = json_encode($arrayData);
        // マスク化したJSON文字列
        $maskedJsonParam = $this->unicode_encode(json_encode($maskedArrayData));
        // ログ出力用文字列(マーチャントが指定する文字エンコードに変換する)
        $this->maskedLogString = $this->encConvLogString($maskedJsonParam);
    }

    /**
     * Unicodeエスケープされた文字列をUTF-8文字列に戻す。
     * @param string $str
     * @return mixed
     */
    private function unicode_encode($str) {
        return preg_replace_callback("/\\\\u([0-9a-zA-Z]{4})/", array($this, "encode_callback"), $str);
    }

    /**
     * Unicodeエスケープされた文字列をUTF-8文字列に置換する。
     * @param array $matches
     * @return mixed
     */
    private function encode_callback($matches) {
        return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UTF-16");
    }

     /**
     * サブオブジェクトを解析する。
     * @param array $subObject サブオブジェクト
     * @param string $className クラス名
     * @param bool $is_mask サブオブジェクト
     * @return array|null
     */
    private function createJsonSubObject($subObject, $className, $is_mask) {
        $arrayData = (array)$subObject;
        foreach ($arrayData as $key => $value) {
            $orgKey = (string)$key;

            // SearchRangeがついているものは削除
            $wk = str_replace(self::FIELD_NAME_SEARCH_RANGE, "", $orgKey);
            // SearchParameterを後ろにつけてクラスが存在するか判定
            if (!class_exists($className)) {
                $wcn = $className. self::FIELD_NAME_SEARCH_PARAM;
                if (class_exists($wcn)) {
                    $className = $wcn;
                }
            }
            $workKey = preg_replace('/'.$className.'/', '', $wk,1);
            $replacekey = preg_replace('/\W/', '', $workKey);
            if (is_object($value)) {
                $arrayData[$replacekey] = $this->createJsonSubObject($value, get_class($value), $is_mask);
            } elseif (is_array($value)) {
                $arrayData[$replacekey] = $this->createJsonSubObject($value, $className, $is_mask);
            } else {
                if (!is_null($value)) {
                    $param = $this->encConv($value);
                    if ($is_mask) {
                        $arrayData[$replacekey] = TGMDK_Util::maskValue($replacekey, $param);
                    } else {
                        $arrayData[$replacekey] = $param;
                    }
                }
            }
            if ($orgKey != $replacekey) {
                unset($arrayData[$orgKey]);
            }
            if (isset($replacekey) && array_key_exists($replacekey, $arrayData)) {
                if (is_null($arrayData[$replacekey])) {
                    unset($arrayData[$replacekey]);
                }
            }
        }

        // カラ文字除去
        $arrayData = array_filter($arrayData, array($this, 'shouldParseToJson'));
        // サブオブジェクトの要素が0の場合nullを返す
        if (count($arrayData) == 0) {
            return null;
        }

        return $arrayData;
    }

    /**
     * 設定値のエンコードをMDKで使用するUTF-8のエンコードに変更する<br>
     * 指定されていない場合はUTF-8として扱う<br>
     * @param string $value 設定値
     * @return string エスケープ処理後の設定値
     */
    private function encConv($value){
        // DTOの文字エンコードを取得
        $dto_enc = $this->conf[TGMDK_Config::DTO_ENCODE];

        // 指定されている場合は指定のエンコードからUTF-8に変換
        if (0 < strlen($dto_enc) && "UTF-8" != strtoupper($dto_enc)) {
            return mb_convert_encoding($value, "UTF-8", $dto_enc);
        } else {
            return $value;
        }
    }

    /**
     * ログ出力用文字列について<br>
     * MDKで使用されている文字のUTF-8エンコードをマーチャントが指定するエンコードに変更する<br>
     * 指定されていない場合はUTF-8として扱う<br>
     *
     * @access private
     * @param mixed $value 設定値
     * @return string エスケープ処理後の設定値
     */
    private function encConvLogString($value) {
        // DTOの文字エンコードを取得
        $dto_enc = $this->conf[TGMDK_Config::DTO_ENCODE];

        // 指定されている場合は指定のエンコードに変換
        if (0 < strlen($dto_enc) && "UTF-8" != strtoupper($dto_enc)) {
        // エンコードが指定されている場合
            return mb_convert_encoding($value, $dto_enc, "UTF-8");
        } else {
            return $value;
        }
    }

    /**
     * JSONにパースすべきかどうかを判定する
     * <p>createJsonParameter または createJsonSubObject で使用</p>
     *
     * @access private
     * @param mixed $value 判定する値
     * @return bool 判定結果
     */
    private function shouldParseToJson($value) {
        return $value !== '' && isset($value) && $value !== array();
    }
}
?>
