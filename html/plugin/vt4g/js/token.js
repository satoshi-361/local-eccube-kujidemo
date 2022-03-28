/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

// 名前空間を設定
window.VeriTrans4G = window.VeriTrans4G || {};

// MDKトークン取得用 APIリクエスト
VeriTrans4G.fetchMdkToken = function() {
    VeriTrans4G.resetError();

    // 入力チェック
    if (!VeriTrans4G.validate()) {
        VeriTrans4G.isProcessing = false;
        return false;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', VeriTrans4G.tokenApiUrl, true);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('Content-Type', 'application/json; charset=utf-8');
    xhr.onloadend = function() {
        VeriTrans4G.handleFetchMdkToken(JSON.parse(xhr.response));
    };
    // 通信エラー
    xhr.ontimeout = function() {
        VeriTrans4G.isProcessing = false;
        alert("通信エラーが発生しました。(timeout)");
        return false;
    };
    xhr.onabort = function() {
        VeriTrans4G.isProcessing = false;
        alert("通信エラーが発生しました。(abort)");
        return false;
    };

    xhr.send(VeriTrans4G.getRequestParams());
};

// APIリクエスト用パラメータを生成
VeriTrans4G.getRequestParams = function() {
    var cardNumber   = document.querySelector('input[name="payment_credit[card_no]"]').value;
    var expireMonth  = document.querySelector('select[name="payment_credit[expiry_month]"]').value;
    var expireYear   = document.querySelector('select[name="payment_credit[expiry_year]"]').value;

    var params = {
        token_api_key: VeriTrans4G.tokenApiKey,
        card_number: cardNumber,
        card_expire: expireMonth+'/'+expireYear,
        lang: 'ja'
    };
    if (VeriTrans4G.securityFlg) {
        var securityCode = document.querySelector('input[name="payment_credit[security_code]"]').value;
        params.security_code = securityCode;
    }

    return JSON.stringify(params);
};

// MDKトークン取得成功時
VeriTrans4G.handleFetchMdkToken = function(response) {
    if (response.status === 'success') {
        const formElm = document.querySelector('#vt4g_form_credit');
        formElm.querySelector('input[name="token_id"]').value = response.token;
        formElm.querySelector('input[name="token_expire_date"]').value=response.token_expire_date;

        // 入力内容のクリア
        VeriTrans4G.resetForm();

        // フォームを送信
        formElm.submit();
        // EC-CUBE側で定義されているオーバーレイ表示 実行
        window.loadingOverlay();
    } else {
        // エラーメッセージ表示
        var message = response.message.indexOf( 'ディジットチェックエラーです。' ) !== -1
            ? '入力されたカード情報に誤りがあります。'
            : '【'+response.code+'】'+response.message
        VeriTrans4G.setError(message);
    }
};

// エラーメッセージを表示
VeriTrans4G.setError = function(message) {

    document.querySelector('#vt4g_form_credit_error').innerHTML = message;
    if(message !== ''){
        document.getElementById('vt4g_form_credit').scrollIntoView(true);
    }
};

// エラーメッセージをクリア
VeriTrans4G.resetError = function() {
    VeriTrans4G.setError('');
};

// 入力されているクレジットカード情報をクリア
VeriTrans4G.resetForm = function() {
    var fields = Array.prototype.slice.call(document.querySelectorAll('[name^="payment_credit"]'),0);

    fields.forEach(function(field) {
        if (field.name != 'payment_credit[_token]'
            && field.name != 'payment_credit[payment_type]'
            && field.name != 'payment_credit[cardinfo_regist]'
            && field.name != 'payment_credit[cardinfo_retrade]'
            && field.name != 'payment_credit[card_name]'
        ) {
            field.value = /select/i.test(field.nodeName)
                ? field.querySelector('option').getAttribute('value')
                : '';
            VeriTrans4G.hideErrorMessage(field);
        }
    });
};

// 必須チェック
VeriTrans4G.validateNotBlank = function(key, name, message, formKey) {
    if (!formKey) {
        formKey = 'payment_credit';
    }

    var field = document.querySelector('[name="'+formKey+'['+key+']"]');
    var value = field.value;
    var control = /select/i.test(field.nodeName)
        ? '選択'
        : '入力';

    if (value == '' || value == null) {
        VeriTrans4G.showErrorMessage(field, (message || '※ '+name+'が'+control+'されていません。'));
        return false;
    }

    return true;
};

// 正規表現チェック
VeriTrans4G.validateRegex = function(key, name, regex, message, formKey) {
    var field = document.querySelector('[name="'+formKey+'['+key+']"]');
    var value = field.value;

    if (new RegExp(regex).test(value)) {
        VeriTrans4G.showErrorMessage(field, (message || '※ '+name+'の入力書式が不正です。'));
        return false;
    }

    return true;
};

// 文字数チェック
VeriTrans4G.validateRange = function(key, name, min, max, formKey, message) {
    var field = document.querySelector('[name="'+formKey+'['+key+']"]');
    var value = field.value;

    var isValid = true;

    if (min != null) {
        isValid = min <= value.length;
    }
    if (isValid && max != null) {
        isValid = value.length <= max;
    }

    var defaultMessage = (min != null && max != null && min === max)
        ? '※ '+name+'は'+min+'桁で入力してください。'
        : '※ '+name+'が'+(min != null ? min+'桁' : '')+'〜'+(max != null ? max+'桁' : '')+'の範囲ではありません。';

    if (!isValid) {
        VeriTrans4G.showErrorMessage(field, (message || defaultMessage));
        return false;
    }

    return true;
};

// カード番号入力チェック
VeriTrans4G.validateCardNumber = function() {
    var key = 'card_no';
    var formKey = 'payment_credit';
    var name = 'クレジットカード番号';

    var field = document.querySelector('input[name="payment_credit['+key+']"]');
    var value = field.value;
    var minLength = field.getAttribute('minlength');
    var maxLength = field.getAttribute('maxlength');

    VeriTrans4G.hideErrorMessage(field);

    // 必須チェック & 形式チェック & 桁数チェック
    return VeriTrans4G.validateNotBlank(key, name) &&
        VeriTrans4G.validateRegex(key, name, '[^0-9]', '※ '+name+'に数字以外の文字が含まれています。', formKey) &&
        VeriTrans4G.validateRange(key, name, minLength, maxLength, formKey);
};

// カード有効期限チェック
VeriTrans4G.validateExpiration = function() {
    var validLength = 2;
    var formKey = 'payment_credit';
    var monthKey = 'expiry_month';
    var monthName = 'カード有効期限(月)';
    var yearKey = 'expiry_year';
    var yearName = 'カード有効期限(年)';

    var monthField = document.querySelector('select[name="payment_credit['+monthKey+']"]');
    var yearField = document.querySelector('select[name="payment_credit['+yearKey+']"]');

    VeriTrans4G.hideErrorMessage(monthField);
    VeriTrans4G.hideErrorMessage(yearField);

    // 必須チェック & 形式チェック & 桁数チェック
    var isValidMonth = VeriTrans4G.validateNotBlank(monthKey, monthName) &&
        VeriTrans4G.validateRegex(monthKey, monthName, '[^0-9]', '※ '+monthName+'に数字以外の文字が含まれています。', formKey) &&
        VeriTrans4G.validateRange(monthKey, monthName, validLength, validLength, formKey);

    var isValidYear = VeriTrans4G.validateNotBlank(yearKey, yearName) &&
        VeriTrans4G.validateRegex(yearKey, yearName, '[^0-9]', '※ '+yearName+'に数字以外の文字が含まれています。', formKey) &&
        VeriTrans4G.validateRange(yearKey, yearName, validLength, validLength, formKey);

    return isValidMonth && isValidYear && VeriTrans4G.validateExpirationDate(yearField, monthField);
};

// カード有効期限 日付チェック
VeriTrans4G.validateExpirationDate = function(yearField, monthField) {
    // フォームの入力値は西暦の末尾2桁のため先頭2桁と結合
    var year = '20'+yearField.value;
    // Date関数で1月が'0'から始まるため-1
    var month = monthField.value - 1;
    // 日付の入力はないため1日とする
    var day = '01';

    var date = new Date(year, month, day);
    if (!(date.getFullYear() == year && date.getMonth() == month && date.getDate() == day)) {
        VeriTrans4G.showErrorMessage(yearField, '※ 不正な年月です。');
        return false;
    }

    return true;
};

// カード名義人名チェック
VeriTrans4G.validateOwner = function() {

    var nameKey = 'card_name';
    var formKey = 'payment_credit';
    var name = 'カード名義人名';

    var nameField = document.querySelector('input[name="payment_credit['+nameKey+']"]');
    var minLength = nameField.getAttribute('minlength');
    var maxLength = nameField.getAttribute('maxlength');

    VeriTrans4G.hideErrorMessage(nameField);

    var isValidName = VeriTrans4G.validateNotBlank(nameKey, name) &&
        VeriTrans4G.validateRegex(nameKey, name, '[^a-zA-Z ]', '※ '+name+'は半角英字(半角スペースのみ許可)で入力してください', formKey) &&
        VeriTrans4G.validateRange(nameKey, name, minLength, maxLength, formKey);

    return isValidName;
};

// 支払い方法チェック
VeriTrans4G.validatePaymentType = function() {
    var key = 'payment_type';
    var name = 'お支払い方法';

    var field = document.querySelector('select[name="payment_credit['+key+']"]');
    var value = field.value;

    VeriTrans4G.hideErrorMessage(field);

    // 必須チェック
    return VeriTrans4G.validateNotBlank(key, name);
};

// セキュリティコードチェック
VeriTrans4G.validateSecurityCode = function() {
    var key = 'security_code';
    var formKey = 'payment_credit';
    var name = 'セキュリティコード';

    var field = document.querySelector('input[name="payment_credit['+key+']"]');
    var value = field.value;
    var minLength = field.getAttribute('minlength');
    var maxLength = field.getAttribute('maxLength');

    VeriTrans4G.hideErrorMessage(field);

    // 必須チェック & 形式チェック & 桁数チェック
    return VeriTrans4G.validateNotBlank(key, name) &&
        VeriTrans4G.validateRegex(key, name, '[^0-9]', '※ '+name+'に数字以外の文字が含まれています。', formKey) &&
        VeriTrans4G.validateRange(key, name, minLength, maxLength, formKey);
};

// カード情報登録チェック
VeriTrans4G.validateCardinfoRegist = function() {
    var key = 'cardinfo_regist';
    var fields = document.querySelectorAll('input[name="payment_credit['+key+']"]');
    if (fields.length == 0) {
        return true;
    }

    var field = fields[0];
    var flg = false;

    for(var i = 0; i < fields.length; i++) {
        if(fields[i].checked) {
            flg = true;
        }
    }

    $(field).parent().parent().removeClass('error');
    $(field).parent().siblings('.ec-errorMessage').remove();
    // 必須チェック
    if(!flg) {
        $(field).parent().parent().addClass('error').append('<p class="ec-errorMessage">※ カード情報登録が選択されていません。</p>');
    }

    // 継続課金の場合はカード情報登録「登録する」必須
    var subscOrderFlgField = document.querySelectorAll('input[name="subsc_order_flg"]');

    var flg2 = (subscOrderFlgField[0].value == 1 && fields[1].checked) ? false : true;
    if (!flg2) {
        $(field).parent().parent().addClass('error').append('<p class="ec-errorMessage">※ この注文の決済はカード情報の登録が必要です。</p>');
    }

    return (flg && flg2) ? true : false;
};

// カード情報登録チェック(再取引)
VeriTrans4G.validateCardinfoRetrade = function() {
    var key = 'cardinfo_retrade';
    var fields = document.querySelectorAll('input[name="payment_credit['+key+']"]');
    if (fields.length == 0) {
        return true;
    }

    var field = fields[0];
    var flg = false;

    for(var i = 0; i < fields.length; i++) {
        if(fields[i].checked) {
            flg = true;
        }
    }

    $(field).parent().parent().removeClass('error');
    $(field).parent().siblings('.ec-errorMessage').remove();
    // 必須チェック
    if(!flg) {
        $(field).parent().parent().addClass('error').append('<p class="ec-errorMessage">※ かんたん決済が選択されていません。</p>');
    }

    return (flg) ? true : false;
};

// 入力チェック
VeriTrans4G.validate = function() {
    var isValid = true;

    // クレジットカード番号チェック
    if (!VeriTrans4G.validateCardNumber()) {
        isValid = false;
    }

    // カード有効期限チェック
    if (!VeriTrans4G.validateExpiration()) {
        isValid = false;
    }

    // カード名義人名チェック
    if (!VeriTrans4G.validateOwner()) {
        isValid = false;
    }

    // お支払い方法チェック
    if (!VeriTrans4G.validatePaymentType()) {
        isValid = false;
    }

    // セキュリティコードチェック(セキュリティコード認証が有効な場合のみ)
    if (VeriTrans4G.securityFlg && !VeriTrans4G.validateSecurityCode()) {
        isValid = false;
    }

    // カード情報登録チェック
    if (!VeriTrans4G.validateCardinfoRegist()) {
        isValid = false;
    }

    // カード情報登録チェック(再取引)
    if (!VeriTrans4G.validateCardinfoRetrade()) {
        isValid = false;
    }

    return isValid;
};

// エラーメッセージ表示
VeriTrans4G.showErrorMessage = function(field, message) {
    $(field).parent().addClass('error').append('<p class="ec-errorMessage">'+message+'</p>');
};

// エラーメッセージ非表示
VeriTrans4G.hideErrorMessage = function(field) {
    $(field).parent().removeClass('error');
    $(field).siblings('.ec-errorMessage').remove();
};