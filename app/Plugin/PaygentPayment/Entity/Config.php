<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright (c) 2006 PAYGENT Co.,Ltd. All rights reserved.
 *
 * https://www.paygent.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Plugin\PaygentPayment\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Config
 *
 * @ORM\Table(name="plg_paygent_payment_config")
 * @ORM\Entity(repositoryClass="Plugin\PaygentPayment\Repository\ConfigRepository")
 */
class Config
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * マーチャントID
     * @var string
     *
     * @ORM\Column(name="merchant_id", type="string", length=255, nullable=true)
     */
    private $merchant_id;

    /**
     * 接続ID
     * @var string
     *
     * @ORM\Column(name="connect_id", type="string", length=255, nullable=true)
     */
    private $connect_id;

    /**
     * 接続パスワード
     * @var string
     *
     * @ORM\Column(name="connect_password", type="string", length=255, nullable=true)
     */
    private $connect_password;

    /**
     * 差分通知ハッシュ値生成キー
     * @var string
     *
     * @ORM\Column(name="notice_hash_key", type="string", length=255, nullable=true)
     */
    private $notice_hash_key;

    /**
     * リンクタイプハッシュ値生成キー
     * @var string
     *
     * @ORM\Column(name="hash_key", type="string", length=255, nullable=true)
     */
    private $hash_key;

    /**
     * 利用決済
     * @var array
     *
     * @ORM\Column(name="paygent_payment_method", type="array")
     */
    private $paygent_payment_method;

    /**
     * リンクタイプリクエスト先URL
     * @var string
     *
     * @ORM\Column(name="link_url", type="string", length=1024, nullable=true)
     */
    private $link_url;

    /**
     * リンク型 カード支払区分
     * @var int
     *
     * @ORM\Column(name="card_class", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $card_class;

    /**
     * リンク型 カード確認番号
     * @var int
     *
     * @ORM\Column(name="card_conf", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $card_conf;

    /**
     * リンク型 カード情報お預かり機能
     * @var int
     *
     * @ORM\Column(name="stock_card", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $stock_card;

    /**
     * 支払期限日
     * @var int
     *
     * @ORM\Column(name="link_payment_term", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $link_payment_term;

    /**
     * 店舗名(全角)
     * @var string
     *
     * @ORM\Column(name="merchant_name", type="string", length=32, nullable=true)
     */
    private $merchant_name;

    /**
     * コピーライト(半角英数)
     * @var string
     *
     * @ORM\Column(name="link_copy_right", type="string", length=128, nullable=true)
     */
    private $link_copy_right;

    /**
     * 自由メモ欄(全角)
     * @var string
     *
     * @ORM\Column(name="link_free_memo", type="string", length=128, nullable=true)
     */
    private $link_free_memo;

    /**
     * 決済処理中の注文の取消期間
     * @var string
     *
     * @ORM\Column(name="rollback_target_term", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $rollback_target_term;

    /**
     * 決済完了後戻りURL
     * @var string
     *
     * @ORM\Column(name="return_url", type="string", length=1024, nullable=true)
     */
    private $return_url;

    /**
     * システム種別
     * @var int
     *
     * @ORM\Column(name="settlement_division", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $settlement_division;

    /**
     * モジュール型  支払い回数
     * @var array
     *
     * @ORM\Column(name="payment_division", type="array", nullable=true)
     */
    private $payment_division;

    /**
     * モジュール型 セキュリティコード
     * @var int
     *
     * @ORM\Column(name="security_code", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $security_code;

    /**
     * モジュール型 3Dセキュア
     * @var int
     *
     * @ORM\Column(name="credit_3d", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $credit_3d;

    /**
     * モジュール型 3Dセキュア結果受付ハッシュ鍵
     * @var string
     *
     * @ORM\Column(name="credit_3d_hash_key", type="string", length=255, nullable=true)
     */
    private $credit_3d_hash_key;

    /**
     * モジュール型 カード情報お預かり機能
     * @var int
     *
     * @ORM\Column(name="module_stock_card", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $module_stock_card;

    /**
     * モジュール型 トークン接続先
     * @var int
     *
     * @ORM\Column(name="token_env", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $token_env;

    /**
     * モジュール型 トークン生成鍵
     * @var string
     *
     * @ORM\Column(name="token_key", type="string", length=255, nullable=true)
     */
    private $token_key;

    /**
     * コンビニ決済 支払期限日
     * @var string
     *
     * @ORM\Column(name="conveni_limit_date_num", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $conveni_limit_date_num;

    /**
     * ATM決済 支払期限日
     * @var string
     *
     * @ORM\Column(name="atm_limit_date", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $atm_limit_date;

    /**
     * ATM決済 店舗名(カナ)
     * @var string
     *
     * @ORM\Column(name="payment_detail", type="string", length=32, nullable=true)
     */
    private $payment_detail;

    /**
     * 銀行ネット決済 支払期限日
     * @var string
     *
     * @ORM\Column(name="asp_payment_term", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $asp_payment_term;

    /**
     * 銀行ネット決済 店舗名(全角)
     * @var string
     *
     * @ORM\Column(name="claim_kanji", type="string", length=32, nullable=true)
     */
    private $claim_kanji;

    /**
     * 銀行ネット決済 店舗名(カナ)
     * @var string
     *
     * @ORM\Column(name="claim_kana", type="string", length=32, nullable=true)
     */
    private $claim_kana;

    /**
     * 銀行ネット決済 コピーライト(半角英数)
     * @var string
     *
     * @ORM\Column(name="copy_right", type="string", length=128, nullable=true)
     */
    private $copy_right;

    /**
     * 銀行ネット決済 自由メモ欄(全角)
     * @var string
     *
     * @ORM\Column(name="free_memo", type="string", length=128, nullable=true)
     */
    private $free_memo;

    /**
     * 携帯キャリア決済 利用決済
     * @var array
     *
     * @ORM\Column(name="career_division", type="array", nullable=true)
     */
    private $career_division;

    /**
     * Paidy決済 パブリックキー
     * @var string
     *
     * @ORM\Column(name="api_key", type="text", nullable=true)
     */
    private $api_key;

    /**
     * Paidy決済 ロゴURL
     * @var string
     *
     * @ORM\Column(name="logo_url", type="string", length=1024, nullable=true)
     */
    private $logo_url;

    /**
     * Paidy決済 店舗名(全角)
     * @var string
     *
     * @ORM\Column(name="paidy_store_name", type="string", length=32, nullable=true)
     */
    private $paidy_store_name;

    /**
     * 加盟店名(半角英数字記号)
     *
     * @var string
     *
     * @ORM\Column(name="credit_3d_merchant_name", type="string", length=25, nullable=true)
     */
    private $credit_3d_merchant_name;

    /**
     * モジュール型 有効性チェック
     * @var int
     *
     * @ORM\Column(name="card_valid_check", type="integer", options={"unsigned":true}, nullable=true)
     */
    private $card_valid_check;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * マーチャントID
     * @return string
     */
    public function getMerchantId()
    {
        return $this->merchant_id;
    }

    /**
     * マーチャントID
     * @param string $merchant_id
     *
     * @return $this;
     */
    public function setMerchantId($merchant_id)
    {
        $this->merchant_id = $merchant_id;

        return $this;
    }

    /**
     * 接続ID
     * @return string
     */
    public function getConnectId()
    {
        return $this->connect_id;
    }

    /**
     * 接続ID
     * @param string $connect_id
     *
     * @return $this
     */
    public function setConnectId($connect_id)
    {
        $this->connect_id = $connect_id;

        return $this;
    }

    /**
     * 接続パスワード
     * @return string
     */
    public function getConnectPassword()
    {
        return $this->connect_password;
    }

    /**
     * 接続パスワード
     * @param string $connect_password
     *
     * @return $this
     */
    public function setConnectPassword($connect_password)
    {
        $this->connect_password = $connect_password;

        return $this;
    }

    /**
     * 差分通知ハッシュ値生成キー
     * @return string
     */
    public function getNoticeHashKey()
    {
        return $this->notice_hash_key;
    }

    /**
     * 差分通知ハッシュ値生成キー
     * @param string $notice_hash_key
     *
     * @return $this
     */
    public function setNoticeHashKey($notice_hash_key)
    {
        $this->notice_hash_key = $notice_hash_key;

        return $this;
    }

    /**
     * リンクタイプハッシュ値生成キー
     * @return string
     */
    public function getHashKey()
    {
        return $this->hash_key;
    }

    /**
     * リンクタイプハッシュ値生成キー
     * @param string $hash_key
     *
     * @return $this
     */
    public function setHashKey($hash_key)
    {
        $this->hash_key = $hash_key;

        return $this;
    }

    /**
     * 利用決済
     * @return array
     */
    public function getPaygentPaymentMethod()
    {
        return $this->paygent_payment_method;
    }

    /**
     * 利用決済
     * @param array $paygent_payment_method
     *
     * @return $this
     */
    public function setPaygentPaymentMethod($paygent_payment_method)
    {
        $this->paygent_payment_method = $paygent_payment_method;

        return $this;
    }

    /**
     * リンクタイプリクエスト先URL
     * @return string
     */
    public function getLinkUrl()
    {
        return $this->link_url;
    }

    /**
     * リンクタイプリクエスト先URL
     * @param string $link_url
     *
     * @return $this
     */
    public function setLinkUrl($link_url)
    {
        $this->link_url = $link_url;

        return $this;
    }

    /**
     * カード支払区分
     * @return string
     */
    public function getCardClass()
    {
        return $this->card_class;
    }

    /**
     * カード支払区分
     * @param string $card_class
     *
     * @return $this
     */
    public function setCardClass($card_class)
    {
        $this->card_class = $card_class;

        return $this;
    }

    /**
     * カード確認番号
     * @return string
     */
    public function getCardConf()
    {
        return $this->card_conf;
    }

    /**
     * カード確認番号
     * @param string $card_conf
     *
     * @return $this
     */
    public function setCardConf($card_conf)
    {
        $this->card_conf = $card_conf;

        return $this;
    }

    /**
     * カード情報お預かり機能
     * @return string
     */
    public function getStockCard()
    {
        return $this->stock_card;
    }

    /**
     * カード情報お預かり機能
     * @param string $stock_card
     *
     * @return $this
     */
    public function setStockCard($stock_card)
    {
        $this->stock_card = $stock_card;

        return $this;
    }

    /**
     * 支払期限日
     * @return string
     */
    public function getLinkPaymentTerm()
    {
        return $this->link_payment_term;
    }

    /**
     * 支払期限日
     * @param string $link_payment_term
     *
     * @return $this
     */
    public function setLinkPaymentTerm($link_payment_term)
    {
        $this->link_payment_term = $link_payment_term;

        return $this;
    }

    /**
     * 店舗名(全角)
     * @return string
     */
    public function getMerchantName()
    {
        return $this->merchant_name;
    }

    /**
     * 店舗名(全角)
     * @param string $merchant_name
     *
     * @return $this
     */
    public function setMerchantName($merchant_name)
    {
        $this->merchant_name = $merchant_name;

        return $this;
    }

    /**
     * コピーライト(半角英数)
     * @return string
     */
    public function getLinkCopyRight()
    {
        return $this->link_copy_right;
    }

    /**
     * コピーライト(半角英数)
     * @param string $link_copy_right
     *
     * @return $this
     */
    public function setLinkCopyRight($link_copy_right)
    {
        $this->link_copy_right = $link_copy_right;

        return $this;
    }

    /**
     * 自由メモ欄(全角)
     * @return string
     */
    public function getLinkFreeMemo()
    {
        return $this->link_free_memo;
    }

    /**
     * 自由メモ欄(全角)
     * @param string $link_free_memo
     *
     * @return $this
     */
    public function setLinkFreeMemo($link_free_memo)
    {
        $this->link_free_memo = $link_free_memo;

        return $this;
    }

    /**
     * 決済処理中の注文の取消期間
     * @return string
     */
    public function getRollbackTargetTerm()
    {
        return $this->rollback_target_term;
    }

    /**
     * 決済処理中の注文の取消期間
     * @param string $rollback_target_term
     *
     * @return $this
     */
    public function setRollbackTargetTerm($rollback_target_term)
    {
        $this->rollback_target_term = $rollback_target_term;

        return $this;
    }

    /**
     * 決済完了後戻りURL
     * @return string
     */
    public function getReturnUrl()
    {
        return $this->return_url;
    }

    /**
     * 決済完了後戻りURL
     * @param string $return_url
     * 
     * @return $this
     */
    public function setReturnUrl($return_url)
    {
        $this->return_url = $return_url;

        return $this;
    }

    /**
     * システム種別
     * @return string
     */
    public function getSettlementDivision()
    {
        return $this->settlement_division;
    }

    /**
     * システム種別
     * @param string $settlement_division
     *
     * @return $this
     */
    public function setSettlementDivision($settlement_division)
    {
        $this->settlement_division = $settlement_division;

        return $this;
    }

    /**
     * 支払い回数
     * @return array
     */
    public function getPaymentDivision()
    {
        return $this->payment_division;
    }

    /**
     * 支払い回数
     * @param array $payment_division
     *
     * @return $this
     */
    public function setPaymentDivision($payment_division)
    {
        $this->payment_division = $payment_division;

        return $this;
    }

    /**
     * セキュリティコード
     * @return string
     */
    public function getSecurityCode()
    {
        return $this->security_code;
    }

    /**
     * セキュリティコード
     * @param string $security_code
     *
     * @return $this
     */
    public function setSecurityCode($security_code)
    {
        $this->security_code = $security_code;

        return $this;
    }

    /**
     * 3Dセキュア
     * @return string
     */
    public function getCredit3d()
    {
        return $this->credit_3d;
    }

    /**
     * 3Dセキュア
     * @param string $credit_3d
     *
     * @return $this
     */
    public function setCredit3d($credit_3d)
    {
        $this->credit_3d = $credit_3d;

        return $this;
    }

    /**
     * 3Dセキュア結果受付ハッシュ鍵
     * @return string
     */
    public function getCredit3dHashKey()
    {
        return $this->credit_3d_hash_key;
    }

    /**
     * 3Dセキュア結果受付ハッシュ鍵
     * @param string $credit_3d_hash_key
     *
     * @return $this
     */
    public function setCredit3dHashKey($credit_3d_hash_key)
    {
        $this->credit_3d_hash_key = $credit_3d_hash_key;

        return $this;
    }

    /**
     * モジュール型 カード情報お預かり機能
     * @return string
     */
    public function getModuleStockCard()
    {
        return $this->module_stock_card;
    }

    /**
     * モジュール型 カード情報お預かり機能
     * @param string $module_stock_card
     *
     * @return $this
     */
    public function setModuleStockCard($module_stock_card)
    {
        $this->module_stock_card = $module_stock_card;

        return $this;
    }

    /**
     * モジュール型 トークン接続先
     * @return string
     */
    public function getTokenEnv()
    {
        return $this->token_env;
    }

    /**
     * モジュール型 トークン接続先
     * @param string $token_env
     *
     * @return $this
     */
    public function setTokenEnv($token_env)
    {
        $this->token_env = $token_env;

        return $this;
    }

    /**
     * モジュール型 トークン生成鍵
     * @return string
     */
    public function getTokenKey()
    {
        return $this->token_key;
    }

    /**
     * モジュール型 トークン生成鍵
     * @param string $token_key
     *
     * @return $this
     */
    public function setTokenKey($token_key)
    {
        $this->token_key = $token_key;

        return $this;
    }

    /**
     * コンビニ決済 支払期限日
     * @return string
     */
    public function getConveniLimitDateNum()
    {
        return $this->conveni_limit_date_num;
    }

    /**
     * コンビニ決済 支払期限日
     * @param string $conveni_limit_date_num
     *
     * @return $this
     */
    public function setConveniLimitDateNum($conveni_limit_date_num)
    {
        $this->conveni_limit_date_num = $conveni_limit_date_num;

        return $this;
    }

    /**
     * ATM決済 支払期限日
     * @return string
     */
    public function getAtmLimitDate()
    {
        return $this->atm_limit_date;
    }

    /**
     * ATM決済 支払期限日
     * @param string $atm_limit_date
     *
     * @return $this
     */
    public function setAtmLimitDate($atm_limit_date)
    {
        $this->atm_limit_date = $atm_limit_date;

        return $this;
    }

    /**
     * ATM決済 店舗名(カナ)
     * @return string
     */
    public function getPaymentDetail()
    {
        return $this->payment_detail;
    }

    /**
     * ATM決済 店舗名(カナ)
     * @param string $payment_detail
     *
     * @return $this
     */
    public function setPaymentDetail($payment_detail)
    {
        $this->payment_detail = $payment_detail;

        return $this;
    }

    /**
     * 銀行ネット決済 支払期限日
     * @return string
     */
    public function getAspPaymentTerm()
    {
        return $this->asp_payment_term;
    }

    /**
     * 銀行ネット決済 支払期限日
     * @param string $asp_payment_term
     *
     * @return $this
     */
    public function setAspPaymentTerm($asp_payment_term)
    {
        $this->asp_payment_term = $asp_payment_term;

        return $this;
    }

    /**
     * 銀行ネット決済 店舗名(全角)
     * @return string
     */
    public function getClaimKanji()
    {
        return $this->claim_kanji;
    }

    /**
     * 銀行ネット決済 店舗名(全角)
     * @param string $claim_kanji
     *
     * @return $this
     */
    public function setClaimKanji($claim_kanji)
    {
        $this->claim_kanji = $claim_kanji;

        return $this;
    }

    /**
     * 銀行ネット決済 店舗名(カナ)
     * @return string
     */
    public function getClaimKana()
    {
        return $this->claim_kana;
    }

    /**
     * 銀行ネット決済 店舗名(カナ)
     * @param string $claim_kana
     *
     * @return $this
     */
    public function setClaimKana($claim_kana)
    {
        $this->claim_kana = $claim_kana;

        return $this;
    }

    /**
     * 銀行ネット決済 コピーライト(半角英数)
     * @return string
     */
    public function getCopyRight()
    {
        return $this->copy_right;
    }

    /**
     * 銀行ネット決済 コピーライト(半角英数)
     * @param string $copy_right
     *
     * @return $this
     */
    public function setCopyRight($copy_right)
    {
        $this->copy_right = $copy_right;

        return $this;
    }

    /**
     * 銀行ネット決済 自由メモ欄(全角)
     * @return string
     */
    public function getFreeMemo()
    {
        return $this->free_memo;
    }

    /**
     * 銀行ネット決済 自由メモ欄(全角)
     * @param string $free_memo
     *
     * @return $this
     */
    public function setFreeMemo($free_memo)
    {
        $this->free_memo = $free_memo;

        return $this;
    }

    /**
     * 携帯キャリア決済 利用決済
     * @return array
     */
    public function getCareerDivision()
    {
        return $this->career_division;
    }

    /**
     * 携帯キャリア決済 利用決済
     * @param string $career_division
     *
     * @return $this
     */
    public function setCareerDivision($career_division)
    {
        $this->career_division = $career_division;

        return $this;
    }

    /**
     * Paidy決済 パブリックキー
     * @return string
     */
    public function getApiKey()
    {
        return $this->api_key;
    }

    /**
     * Paidy決済 パブリックキー
     * @param string $logo_url
     *
     * @return $this
     */
    public function setApikey($api_key)
    {
        $this->api_key = $api_key;

        return $this;
    }

    /**
     * Paidy決済 ロゴURL
     * @return string
     */
    public function getLogoUrl()
    {
        return $this->logo_url;
    }

    /**
     * Paidy決済 ロゴURL
     * @param string $logo_url
     *
     * @return $this
     */
    public function setLogoUrl($logo_url)
    {
        $this->logo_url = $logo_url;

        return $this;
    }

    /**
     * Paidy決済 店舗名(全角)
     * @return string
     */
    public function getPaidyStoreName()
    {
        return $this->paidy_store_name;
    }

    /**
     * Paidy決済 店舗名(全角)
     * @param string $paidy_store_name
     *
     * @return $this
     */
    public function setPaidyStoreName($paidy_store_name)
    {
        $this->paidy_store_name = $paidy_store_name;

        return $this;
    }

    /**
     * 加盟店名(半角英数字記号)
     * @return string
     */
    public function getCredit3dMerchantName()
    {
        return $this->credit_3d_merchant_name;
    }

    /**
     * 加盟店名(半角英数字記号)
     * @param string $credit_3d_merchant_name
     *
     * @return $this
     */
    public function setCredit3dMerchantName($credit_3d_merchant_name)
    {
        $this->credit_3d_merchant_name = $credit_3d_merchant_name;

        return $this;
    }

    /**
     * 有効性チェック
     * @return string
     */
    public function getCardValidCheck()
    {
        return $this->card_valid_check;
    }

    /**
     * 有効性チェック
     * @param string $card_valid_check
     *
     * @return $this
     */
    public function setCardValidCheck($card_valid_check)
    {
        $this->card_valid_check = $card_valid_check;

        return $this;
    }
}
