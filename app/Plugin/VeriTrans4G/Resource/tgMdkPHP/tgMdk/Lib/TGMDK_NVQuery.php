<?php
if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__)) die('Permission denied.');

if (!defined('MDK_LIB_DIR')) require_once('../3GPSMDK.php');

/**
 *
 * マーチャントが設定した要求DtoよりN=V文字列を作成するクラス<br>
 *
 * @category    Veritrans
 * @package     Lib
 * @copyright   VeriTrans Inc.
 * @access  public
 * @author VeriTrans Inc.
 */
class TGMDK_NVQuery {

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
    /** マスク文字列 */
    const MASKED_VALUE = "******";
    /** BaseDto名 */
    const BASE_DTO_NAME = "MdkBaseDto";
    /** サービス固有要素前置詞 */
    const EXPARAM_PREPOSIT = "exparam";
    /** 特殊マスク項目：カード番号 */
    const ITEM_CARDNUMBER = "CARDNUMBER";
    /** NAME部階層区切り文字 */
    const N_SEP = ".";


    /** TGMDK_Configファイルの読み込み */
    private $conf;

    /**
     * N=V連結文字<br>
     */
    private $nameValue;

    /**
     * マスクされたN=V連結文字<br>
     */
    private $maskedNameValue;

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
     * N=V連結文字を取得する<br>
     * @return mixed N=V連結文字<br>
     * @access public
     */
    public function getNameValue(){
        return $this->nameValue;
    }

    /**
     * マスクされたN=V連結文字を取得する<br>
     * @return mixed マスクされたN=V連結文字<br>
     * @access public
     */
    public function getMaskedNameValue(){
        return $this->maskedNameValue;
    }

    /**
     * 決済コマンド要求Dtoに設定されたパラメータを取得しN=V文字列を生成。
     * 全てのパラメータを連結したN=V連結文字列を作成する。
     *
     * @access public
     * @param MdkBaseDto $requestDto 決済コマンド要求Dto
     */
    public function getNVofRequestDto($requestDto){

        //N=Vリスト(項目："name","value")
        $nvList = Array();

        //N=Vリストを取得する。
        $nvList = $this->getNVofDto($nvList, $requestDto,"");

        //N=V文字列
        $nVString = "";
        //マスクN=V文字列
        $maskedNVString = "";
        //N=Vリスト分、N=V文字列とマスクN=V文字列を連結する。
        foreach ($nvList as $nv => $rec) {
            $encValue = $this->encConv($rec['value']);
            $nVString .= self::PARAM_UNITE_CHAR . $rec['name'] . self::NV_UNITE_CHAR . $this->valueEscape($encValue);
            $maskedNVString .= $rec['name'] . " : " . $this->maskValue($rec['name'], $encValue) . "   ";

        }
        //N=V連結文字へ格納
        $this->nameValue = substr($nVString, 1) ;
        //マスクされたN=V連結文字へ格納
        $this->maskedNameValue =  $maskedNVString ;
        return;
    }

    /**
     * 設定値のエスケープ処理を行う
     *
     * @access public
     * @param string $value 設定値
     * @return mixed|string エスケープ処理後の設定値
     */
    private function valueEscape($value){

        $return_string="";
        //[&]⇒[\&]
        $return_string = str_replace(self::DQUOTE_CHAR,self::DQUOTE_CHAR_ESCAPE,$value);
        //["]⇒[\"]
        $return_string = str_replace(self::PARAM_UNITE_CHAR,self::PARAM_UNITE_CHAR_ESCAPE,$return_string);
        return $return_string;
    }

    /**
     * DTOに設定されている文字列のエンコードをMDKで使用するUTF-8のエンコードに変更する<br>
     * 指定されていない場合はUTF-8として扱う
     *
     * @access public
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
     * 設定値のマスク化を行う
     *
     * @access public
     * @param string $name 項目名
     * @param string $value VALUE値
     * @return string マスク処理後の設定値
     */
    private function maskValue($name, $value ){

        //設定ファイルよりマスク化項目リストを取得。
        $MASK_ITEM = $this->conf[TGMDK_Config::MASK_ITEM];
        //Name部の最終項目名を抜き出す。
        $names = explode(self::N_SEP,$name);
        $lastName = array_pop($names);
        //マスク化項目リストを全て大文字へ変換
        $MASK_ITEM_UPPER = strtoupper($MASK_ITEM);
        //カンマで区切り、配列に格納
        $maskItems = explode(",",$MASK_ITEM_UPPER);

        //項目名を大文字へ変換
        $upperName = strtoupper($lastName);
        if(in_array($upperName,$maskItems)){
            //--------------------------------------------------
            // 特殊なマスク処理は以下へIF文を追加
            //--------------------------------------------------
            //カード番号は頭４桁は表示
            if($upperName == self::ITEM_CARDNUMBER){
                return substr($value, 0, 4 ) . self::MASKED_VALUE .self::MASKED_VALUE  ;
            }
            return self::MASKED_VALUE ;
        } else {
            return $value;
        }
    }

    /**
     * Dtoに設定されたパラメータを取得しN=V文字列を生成。
     *
     * @access public
     * @param array $nvList N=Vリスト
     * @param mixed $requestDto 処理対象Dto
     * @param string $NameString Name階層（再帰呼出時に引き渡される）<br>
     * @return array 処理したN=Vを追加したN=Vリスト
     */
    public function getNVofDto($nvList, $requestDto, $NameString){

        //要求Dtoのメソッドを全て取得
        $methods = get_class_methods(get_class($requestDto));
        foreach( $methods as $method)
        {
            //N=V生成対象要素確認
            if($this->isCorrectGetter($method) == true)
            {
                //N=Vリストに対象メソッドのN=Vを追加する。
                $nvList = $this->createNameValueString($nvList,$method, $requestDto, $NameString);
            }
        }
        //追加したN=Vリストを返却
        return $nvList;
    }

    /**
     * 指定パラメータのgetterを起動し、パラメータ設定値を取り出し、N=V文字列を生成する。
     * @access private
     * @param array $nvList N=Vリスト
     * @param string $method 指定パラメータのgetter名
     * @param mixed $requestDto 処理対象Dto
     * @param string $NameString Name階層（再帰呼出時に引き渡される）
     * @return array 処理したN=Vを追加したN=Vリスト
     * @throws TGMDK_Exception
     */
    private function createNameValueString($nvList, $method, $requestDto, $NameString){

        //設定ファイルより、共通項目を取得。
        $COMMON_ITEM = $this->conf[TGMDK_Config::COMMON_ITEM];

        //共通項目を全て大文字へ変換
        $COMMON_ITEM_UPPER = strtoupper($COMMON_ITEM);
        //カンマで区切り、配列に格納
        $commonParam = explode(",",$COMMON_ITEM_UPPER);

        //処理対象フィールド名を取得
        $fieldName = strtolower(substr($method,3,1)) . substr($method, 4, strlen($method)-3);
        $upperMethodName = strtoupper($method);
        $upperFieldName = strtoupper($fieldName);

        //メソッドより設定値を取得
        $tempValue = $requestDto->$method();
        //設定値がない場合は処理を抜ける
        if (is_string($tempValue)){
            if($tempValue == ""){
                return $nvList;
            }
        } else {
            if($tempValue == null){
                return $nvList;
            }
        }
        //処理対象getterでない場合は処理を抜ける
        if(!$this->isCorrectGetter($method)){
            return $nvList;
        }

        //=================================================================
        //設定値の型により処理を分岐する。
        //条件1:設定値が文字列
        //条件2:設定値がオブジェクト
        //条件3:設定値が配列
        //  条件3-1:設定値が配列で要素が文字列
        //  条件3-2:設定値が配列で要素がDto
        //※上記条件において、Name生成をさらに分岐する。
        //  条件a:BASE_DTOを継承したオブジェクトの項目
        //　条件b:共通項目である
        //=================================================================
        //条件1:設定値が文字列
        if (is_string($tempValue)){

            //条件a:BASE_DTOを継承したオブジェクトの項目
            //条件b:共通項目である
            if(get_parent_class($requestDto) == self::BASE_DTO_NAME &&
                in_array($upperFieldName,$commonParam)
            ){
                // N=Vをそのまま追加
                $nvList[] = Array("name" => $fieldName, "value" => (string)$requestDto->$method());
                return $nvList;
            }
            //条件a:BASE_DTOを継承したオブジェクトの項目
            elseif(get_parent_class($requestDto) == self::BASE_DTO_NAME
            ){
                //Nameへ前置詞を付加し追加
                $nvList[] = Array("name" => self::EXPARAM_PREPOSIT .self::N_SEP. $fieldName, "value" => (string)$requestDto->$method());
                return $nvList;
            }
            else
            {
                //Nameへ呼び出し元のName階層を付加し追加
                $nvList[] = Array("name" => $NameString .self::N_SEP. $fieldName, "value" => (string)$requestDto->$method());
                return $nvList;
            }

        }
        //条件2:設定値がDto
        elseif (is_object($tempValue)){

            //---------------------------------------------------
            //再帰呼出しを行うために、引き渡すName階層文字列を作成
            //---------------------------------------------------
            //条件a:BASE_DTOを継承したオブジェクトの項目
            //条件b:共通項目である
            if(get_parent_class($requestDto) == self::BASE_DTO_NAME &&
                in_array($upperFieldName,$commonParam)
            ){
                $addName = $fieldName;
            }
            //条件a:BASE_DTOを継承したオブジェクトの項目
            elseif(get_parent_class($requestDto) == self::BASE_DTO_NAME )
            {
                $addName = self::EXPARAM_PREPOSIT .self::N_SEP.  $fieldName ;
            }
            else{
                if($NameString == "" ) {
                    $addName = self::EXPARAM_PREPOSIT .self::N_SEP. $fieldName ;
                }else{
                    $addName = $NameString .self::N_SEP. $fieldName;
                }

            }
            //---------------------------------------------------
            //本メソッドの再帰呼出しを行う
            //---------------------------------------------------
            $childQuery = new TGMDK_NVQuery();
            $nvList =  $childQuery->getNVofDto($nvList,$tempValue, $addName );
            return $nvList;
        }
        //条件3:設定値が配列の場合
        elseif (is_array($tempValue)){
            //条件3-1:文字列配列の場合
            if ( is_string($tempValue[0]) ) {

                //条件a:BASE_DTOを継承したオブジェクトの項目
                //条件b:共通項目である
                if(get_parent_class($requestDto) == self::BASE_DTO_NAME &&
                    in_array($upperFieldName,$commonParam)
                ){
                    for($i = 0; $i < sizeof($tempValue); ++$i)
                    {
                        // N=Vをそのまま返却
                        $nvList[] = Array("name" => $fieldName . "[" . $i ."]" , "value" => (string)$tempValue[$i]);
                    }
                }
                //条件a:BASE_DTOを継承したオブジェクトの項目
                //条件b:共通項目である
                elseif(get_parent_class($requestDto) == self::BASE_DTO_NAME )
                {
                    for($i = 0; $i < sizeof($tempValue); ++$i)
                    {
                        //Nameへ前置詞を付加し追加
                        $nvList[] = Array("name" => self::EXPARAM_PREPOSIT .self::N_SEP. $fieldName. "[" . $i ."]" , "value" => (string)$tempValue[$i]);
                    }
                }
                else{
                    for($i = 0; $i < sizeof($tempValue); ++$i)
                    {
                        //Nameへ呼び出し元のName階層を付加し追加
                        $nvList[] = Array("name" => $NameString .self::N_SEP. $fieldName. "[" . $i ."]" , "value" => (string)$tempValue[$i]);
                    }
                }
                return $nvList;
            }
            //条件3-2:Dto配列の場合
            else {
                for($i = 0; $i < sizeof($tempValue); ++$i)
                {
                    //---------------------------------------------------
                    //再帰呼出しを行うために、引き渡すName階層文字列を作成
                    //---------------------------------------------------
                    //条件a:BASE_DTOを継承したオブジェクトの項目
                    //条件b:共通項目である
                    if(get_parent_class($requestDto) == self::BASE_DTO_NAME &&
                        in_array($upperFieldName,$commonParam)
                    ){
                        $addName = $fieldName . "[" . $i . "]";
                    }
                    //条件a:BASE_DTOを継承したオブジェクトの項目
                    elseif(get_parent_class($requestDto) == self::BASE_DTO_NAME )
                    {
                        $addName = self::EXPARAM_PREPOSIT  . self::N_SEP .  $fieldName . "[" . $i . "]";
                    }
                    else{
                        $addName = $NameString . self::N_SEP . $fieldName . "[" . $i . "]";
                    }
                    //---------------------------------------------------
                    //本メソッドの再帰呼出しを行う
                    //---------------------------------------------------
                    $childQuery = new TGMDK_NVQuery();
                    $nvList = $childQuery->getNVofDto($nvList, $tempValue[$i], $addName );

                }
                return $nvList;
            }
        }
        else {
            // 文字列以外の型が設定されている場合
            throw new TGMDK_Exception(TGMDK_Exception::MA07_INVALID_DTO_VALUE_TYPE, $fieldName);
        }
    }

    /**
     * methodがgetter()であることをチェックする。<br>
     * getterメソッドの対象を抽出した上で、このメソッドを呼ぶこと<br>
     *
     * @access private
     * @param string $method チェック対象となるメソッド<br>
     * @return bool ゲッターであるか否か
     */
    private function isCorrectGetter($method){

        if (mb_ereg("^get", $method) ) {
            return true;
        }
        return false;
    }
}
