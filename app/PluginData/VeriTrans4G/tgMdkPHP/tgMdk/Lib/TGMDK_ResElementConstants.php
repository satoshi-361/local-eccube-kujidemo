<?php
if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__)) die('Permission denied.');

if (!defined('MDK_LIB_DIR')) require_once('../3GPSMDK.php');

/**
 *
 * TradUrl へアクセスする定数を保持するクラス
 *
 * @category    Veritrans
 * @package     Lib
 * @copyright   VeriTrans Inc.
 * @access  public
 */
class TGMDK_ResElementConstants {

    /** TradのURLの取得用文字列 */
    const QUERY_TRAD_URL = "/result/optionResults/url";

}
