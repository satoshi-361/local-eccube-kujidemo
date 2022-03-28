<?php
if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__)) die('Permission denied.');

if (!defined('MDK_LIB_DIR')) require_once('../3GPSMDK.php');
//require_once(LOG4PHP_DIR . DS . 'LoggerManager.php');

// PHP5.3対応 ログ基底クラスが変更になった
require_once(LOG4PHP_DIR . DS . 'Logger.php');
Logger::configure(LOG4PHP_CONFIGURATION);

/**
 * TGMDK_Logger ログ出力クラス
 *
 * @category    Veritrans
 * @package     Lib
 * @copyright   VeriTrans Inc.
 */
final class TGMDK_Logger {
// Defines
    /** パッケージ名 */
    const PACKAGE_NAME = 'jp.co.veritrans.3GpsMdk';
    /** error_log 出力時のプレフィックス */
    const ERR_LOG_PREFIX = "[MDK_LOG_ERR]";

    // Data members
    /** ロガー保持変数 */
    private $logger;
    /** 当クラスのインスタンスを保持する変数 */
    private static $instance = NULL;


    /**
     * コンストラクタ（getInstanceのみインスタンス化が可能）
     *
     * @access private
     */
    private function __construct() {
        $work = Logger::getLogger(self::PACKAGE_NAME);
        $this->logger = $work;
    }

    /**
     * デストラクタ
     *
     * @access public
     */
    public function __destruct() {
        Logger::shutdown();
    }

    /**
     * 静的なインスタンスを返す
     *
     * @access public
     * @static
     * @return TGMDK_Logger ロガー
     */
    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new TGMDK_Logger();
        }
        return self::$instance;
    }

    /**
     * ロガークラスを取得する。
     *
     * @access private
     * @return Logger ロガークラス
     */
    private function getLogger() { return $this->logger; }


    /**
     * ログ出力設定の Debug レベルの有効/無効 判定
     *
     * @access public
     * @return boolean 有効な場合 true
     */
    public function isDebugEnabled() {
        return $this->getLogger()->isDebugEnabled();
    }

    /**
     * ログ出力設定の Info レベルの有効/無効 判定
     *
     * @access public
     * @return boolean 有効な場合 true
     */
    public function isInfoEnabled() {
        return $this->getLogger()->isInfoEnabled();
    }

    /**
     * ログ出力設定の Warn レベルの有効/無効 判定
     *
     * @access public
     * @return boolean 有効な場合 true
     */
    public function isWarnEnabled() {
        return $this->logger->isEnabledFor(LoggerLevel::getLevelWarn());
    }

    /**
     * ログ出力設定の Error レベルの有効/無効 判定
     *
     * @access public
     * @return boolean 有効な場合 true
     */
    public function isErrorEnabled() {
        return $this->logger->isEnabledFor(LoggerLevel::getLevelError());
    }

    /**
     * ログ出力設定の Fatal レベルの有効/無効 判定
     *
     * @access public
     * @return boolean 有効な場合 true
     */
    public function isFatalEnabled() {
        return $this->logger->isEnabledFor(LoggerLevel::getLevelFatal());
    }

    /**
     * 接続しているマシンのIPアドレスを保持する。
     *
     * @access private
     */
    private function push() {
        $remote = getenv("REMOTE_ADDR");
        if (empty($remote)) {
            $remote = "unknown";
        }
        LoggerNDC::push($remote);
    }

    /**
     * LoggerNDCクラスのpopを呼び出す
     *
     * @access private
     */
    private function pop() {
        LoggerNDC::pop();
    }

    /**
     *
     * Debugレベルでログを出力
     *
     * @access public
     * @param mixed $message: ログ出力するメッセージ
     * @param mixed $throwable: ログに出力するExceptionオブジェクト(stack traceを含む)
     */
    public function debug($message, $throwable=NULL) {
        $this->push();
        try {
            $this->getLogger()->debug($message, $throwable);
        } catch (Exception $e) {
            error_log(self::ERR_LOG_PREFIX . $message);
        }
        $this->pop();
    }

    /**
     *
     * Infoレベルでログを出力
     *
     * @access public
     * @param mixed $message: ログ出力するメッセージ
     * @param mixed $throwable: ログに出力するExceptionオブジェクト(stack traceを含む)
     */
    public function info($message, $throwable=NULL) {
        $this->push();
        try {
            $this->getLogger()->info($message, $throwable);
        } catch (Exception $e) {
            error_log(self::ERR_LOG_PREFIX . $message);
        }
        $this->pop();
    }

    /**
     *
     * Warnレベルでログを出力
     *
     * @access public
     * @param mixed $message: ログ出力するメッセージ
     * @param mixed $throwable: ログに出力するExceptionオブジェクト(stack traceを含む)
     */
    public function warn($message, $throwable=NULL) {
        $this->push();
        try {
            $this->getLogger()->warn($message, $throwable);
        } catch (Exception $e) {
            error_log(self::ERR_LOG_PREFIX . $message);
        }
        $this->pop();
    }

    /**
     *
     * Errorレベルでログを出力
     *
     * @access public
     * @param mixed $message: ログ出力するメッセージ
     * @param mixed $throwable: ログに出力するExceptionオブジェクト(stack traceを含む)
     */
    public function error($message, $throwable=NULL) {
        $this->push();
        try {
            $this->getLogger()->error($message, $throwable);
        } catch (Exception $e) {
            error_log(self::ERR_LOG_PREFIX . $message);
        }
        $this->pop();
    }

    /**
     *
     * Fatalレベルでログを出力
     *
     * @access public
     * @param mixed $message: ログ出力するメッセージ
     * @param mixed $throwable: ログに出力するExceptionオブジェクト(stack traceを含む)
     */
    public function fatal($message, $throwable=NULL) {
        $this->push();
        try {
            $this->getLogger()->fatal($message, $throwable);
        } catch (Exception $e) {
            error_log(self::ERR_LOG_PREFIX . $message);
        }
        $this->pop();
    }
}
