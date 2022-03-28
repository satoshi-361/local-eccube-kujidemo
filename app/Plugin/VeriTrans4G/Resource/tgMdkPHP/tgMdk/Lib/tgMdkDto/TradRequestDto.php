<?php
/**
 * Trad拡張パラメータセット<br>
 *
 * @author Veritrans, Inc.
 */
class TradRequestDto extends OptionParams {

    /**
     * 拡張パラメータ名称<br>
     * 半角英数字<br>
     */
    private $optionName = "trad";

    /**
     * 広告サイズコード<br>
     * 半角英数字<br>
     */
    private $scaleCode;

    /**
     * 拡張パラメータ名を取得する<br>
     *
     * @return string 拡張パラメータ名<br>
     */
    public function getOptionName() {
        return $this->optionName;
    }

    /**
     * 文字コード<br>
     * 半角英数字<br>
     */
    private $charsetCode;

    /**
     * 文字コードを取得する<br>
     *
     * @return string 広告サイズコード<br>
     */
    public function getCharsetCode() {
        return $this->charsetCode;
    }

    /**
     * 広告サイズコードを取得する<br>
     *
     * @return string 広告サイズコード<br>
     */
    public function getScaleCode() {
        return $this->scaleCode;
    }

    /**
     * 広告サイズコードを設定する<br>
     *
     * @param string $scaleCode 広告サイズコード<br>
     */
    public function setScaleCode($scaleCode) {
        $this->scaleCode = $scaleCode;
    }

    /**
     * 文字コードを設定する<br>
     *
     * @param string $charsetCode 文字コード<br>
     */
    public function setCharsetCode($charsetCode) {
        $this->charsetCode = $charsetCode;
    }
}
?>
