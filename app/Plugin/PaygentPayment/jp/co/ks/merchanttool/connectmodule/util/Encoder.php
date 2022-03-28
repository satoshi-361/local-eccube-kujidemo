<?php
/**
 * PAYGENT B2B MODULE
 * HttpsRequestSender.php
 *
 * Copyright (C) 2007 by PAYGENT Co., Ltd.
 * All rights reserved.
 */

namespace Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\util;

use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system\PaygentB2BModuleResources;

/**
 * 文字コード制御クラス
 *
 * @author $Author: ito $
 */
class Encoder {

    /** 電文のパラメータ */
    private $params = null;

    /** エラーコード */
    private $errorCode = null;

    /** 結果メッセージ */
    private $resultMessage = null;

    /** 文字コード */
    private $encoding = null;

    /** 電文種別ID */
    private $telegramKind = null;

    /** 有効無効フラグ */
    private $isSupported = true;

    public function __construct(\Eccube\Common\EccubeConfig $eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->masterFile = PaygentB2BModuleResources::getInstance($this->eccubeConfig);
    }

    /**
     * エラーコードを取得する
     *
     * @return String errorCode
     */
    public function getErrorCode() {
        return $this->errorCode;
    }

    /**
     * 結果メッセージを取得する
     *
     * @return String resultMessage
     */
    public function getResultMessage() {
        return $this->resultMessage;
    }

    /**
     * 電文パラメータを取得する
     *
     * @return array params
     */
    public function getParams() {
        return $this->params;
    }

    /**
     * 文字コードを設定する
     *
     * @param encoding String 文字コード
     * @return boolean true：成功、false：失敗
     */
    public function setEncoding($encoding) {

        $this->encoding = $encoding;

        $encodings = array('UTF-8','Shift_JIS','EUC-JP');

        if (!in_array($this->encoding, $encodings)) {
            $this->errorCode = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__encoding_error'];
            $this->resultMessage = $this->errorCode . ": The encoding isn't supported.";
            trigger_error($this->resultMessage, E_USER_WARNING);
            $this->isSupported = false;
            return false;
        }

        return true;
    }

    /**
     * リクエストパラメータをエンコードする
     *
     * @param params array 電文パラメータ
     * @param telegramKind integer 電文種別
     * @return boolean true：成功、false：失敗
     */
    public function encode($params, $telegramKind) {

        // サポート対象外の文字コードが指定された場合は処理を中断
        if (!$this->isSupported) {
            return false;
        }

        $this->params = $params;
        $this->telegramKind = $telegramKind;

        if ($this->masterFile->isTelegramKindUtf8($this->telegramKind)) {

            // 文字コードが指定されていない場合はエラー
            if (!$this->encoding) {
                $this->errorCode = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__encoding_error'];
                $this->resultMessage = $this->errorCode . ": An encoding must be specified for the telegram.";
                trigger_error($this->resultMessage, E_USER_WARNING);
                return false;
            }

            if ($this->encoding != 'UTF-8') {
                $convertedEncoding = $this->convert($this->encoding);
                foreach($this->params as $key => $value) {
                    $this->params[$key] = mb_convert_encoding($value, 'UTF-8', $convertedEncoding);
                }
            }

        } else {

            // 文字コードが指定されている場合はエラー
            if ($this->encoding) {
                $this->errorCode = $this->eccubeConfig['paygent_payment']['paygentb2bmoduleexception__encoding_error'];
                $this->resultMessage = $this->errorCode . ": Encodings can't be specified for the telegram.";
                trigger_error($this->resultMessage, E_USER_WARNING);
                return false;
            }
        }

        return true;
    }

    /**
     * レスポンスをデコードする
     *
     * @param string String デコード前の文字列
     * @return String デコード後の文字列
     */
    public function decode($string) {

        if ($this->masterFile->isTelegramKindUtf8($this->telegramKind)) {
            if ($this->encoding != 'UTF-8') {
                $string = mb_convert_encoding($string, $this->convert($this->encoding), 'UTF-8');
            }
        }

        return $string;
    }

    /**
     * 文字コード名を変換する。「髙」などの拡張文字に対応させるため。
     *
     * @param encoding String 文字コード
     * @return String 変換後の文字列
     */
    private function convert($encoding) {

        $mappings = array(
            'Shift_JIS'=>'SJIS-win',
            'EUC-JP'=>'CP51932'
        );

        foreach ($mappings AS $key=>$value) {
            if ($encoding == $key) {
                return $value;
            }
        }

        return $encoding;
    }
}

?>
