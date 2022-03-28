<?php
/**
 * 決済サービスタイプ：MPI、コマンド名：認可の要求Dtoクラス<br>
 *
 * @author Veritrans, Inc.
 *
 */
class MpiAuthorizeRequestDto extends AbstractPaymentCreditRequestDto
{

    /**
     * 決済サービスタイプ<br>
     * 半角英数字<br>
     * 必須項目、固定値<br>
     */
    private $SERVICE_TYPE = "mpi";

    /**
     * 決済サービスコマンド<br>
     * 半角英数字<br>
     * 必須項目、固定値<br>
     */
    private $SERVICE_COMMAND = "Authorize";

    /**
     * サービスオプションタイプ<br>
     * 半角英数字<br/>
     * 最大桁数：100<br/>
     * "mpi-none" ： MPI 単体サービス<br/>
     * "mpi-complete" ： 完全認証<br/>
     * "mpi-company" ： 通常認証（カード会社リスク負担）<br/>
     * "mpi-merchant" ： 通常認証（カード会社、加盟店リスク負担）<br/>
     */
    private $serviceOptionType;

    /**
     * 取引ID<br>
     * 半角英数字<br/>
     * 最大桁数：100<br/>
     * - マーチャント側で取引を一意に表す注文管理IDを指定します。<br/>
     * - 申込処理ごとに一意である必要があります。<br/>
     * - 半角英数字、“-”（ハイフン）、“_”（アンダースコア）も使用可能です。<br/>
     */
    private $orderId;

    /**
     * 決済金額<br>
     * 半角数字<br/>
     * 最大桁数：8<br/>
     * 決済金額を指定します。<br/>
     * 1 以上かつ 99999999 以下である必要があります。<br/>
     */
    private $amount;

    /**
     * カード番号<br>
     * 半角数字、記号<br/>
     * 最大桁数：26<br/>
     * クレジットカード番号を指定します。<br/>
     * 記号はハイフン、ブランク、ピリオドが使用可能<br/>
     *  （ハイフンを含んでも含まなくても同様に処理が可能）<br/>
     */
    private $cardNumber;

    /**
     * カード有効期限<br>
     * 半角数字、記号<br/>
     * 最大桁数：5<br/>
     * クレジットカードの有効期限を指定します。<br/>
     * MM/YY （月 + "/" + 年）の形式<br/>
     */
    private $cardExpire;

    /**
     * カード接続センター<br>
     * 半角英数字<br/>
     * 最大桁数：5<br/>
     * カード接続センターを指定します。<br/>
     * "jcn"： Jcn接続<br/>
     * "cafis"： CAFIS接続<br/>
     * ※ 未指定の場合は、デフォルトの接続センターを検証<br/>
     */
    private $cardCenter;

    /**
     * 仕向け先コード<br>
     * 半角英数字<br/>
     * 最大桁数：2<br/>
     * 仕向け先カード会社コードを指定します。<br/>
     * （店舗が加盟店契約をしているカード会社）<br/>
     * ※ 最終的に決済を行うカード発行会社ではなく、決済要求電文が最初に仕向けられる加盟店管理会社となります。<br/>
     */
    private $acquirerCode;

    /**
     * 支払種別<br>
     * 半角英数字<br/>
     * 最大桁数：138<br/>
     * JPOを指定します。<br/>
     * "10"<br/>
     * "21"+"支払詳細"<br/>
     * "22"+"支払詳細"<br/>
     * "23"+"支払詳細"<br/>
     * "24"+"支払詳細"<br/>
     * "25"+"支払詳細"<br/>
     * "31"+"支払詳細"<br/>
     * "32"+"支払詳細"<br/>
     * "33"+"支払詳細"<br/>
     * "34"+"支払詳細"<br/>
     * "61"+"支払詳細"<br/>
     * "62"+"支払詳細"<br/>
     * "63"+"支払詳細"<br/>
     * "69"+"支払詳細"<br/>
     * ※ 未指定の場合は、10（一括払い）<br/>
     */
    private $jpo;

    /**
     * 売上フラグ<br>
     * 半角英数字<br/>
     * 最大桁数：5<br/>
     * 売上フラグを指定します。（任意指定）<br/>
     * "true"： 与信・売上<br/>
     * "false"： 与信のみ<br/>
     * ※ 未指定の場合は、false:与信のみ<br/>
     */
    private $withCapture;

    /**
     * 売上日<br>
     * 半角数字<br/>
     * 最大桁数：8<br/>
     * 売上日を指定します。<br/>
     * YYYYMMDD形式<br/>
     */
    private $salesDay;

    /**
     * 商品コード<br>
     * 半角数字<br/>
     * 最大桁数：7<br/>
     * 商品コードを指定します。<br/>
     */
    private $itemCode;

    /**
     * セキュリティコード<br>
     * 半角数字<br/>
     * 最大桁数：4<br/>
     * セキュリティコードを指定します。<br/>
     */
    private $securityCode;

    /**
     * 誕生日<br>
     * 半角数字<br/>
     * 最大桁数：4<br/>
     * 誕生日を指定します。<br/>
     * カード接続センターがsln以外の場合は利用できません。<br/>
     */
    private $birthday;

    /**
     * 電話番号<br>
     * 半角数字<br/>
     * 最大桁数：4<br/>
     * 電話番号を指定します。<br/>
     * カード接続センターがsln以外の場合は利用できません。<br/>
     */
    private $tel;

    /**
     * 名前（名）カナ<br>
     * 半角カナ<br/>
     * 最大桁数：15<br/>
     * 名前（名）カナ を指定します。<br/>
     * 半角カナ（ｱ～ﾝ）、半濁点が使用できます。<br/>
     * カード接続センターがsln以外の場合は利用できません。<br/>
     */
    private $firstKanaName;

    /**
     * 名前（姓）カナ<br>
     * 半角カナ<br/>
     * 最大桁数：15<br/>
     * 名前（姓）カナ を指定します。<br/>
     * 半角カナ（ｱ～ﾝ）、半濁点が使用できます。<br/>
     * カード接続センターがsln以外の場合は利用できません。<br/>
     */
    private $lastKanaName;

    /**
     * 通貨単位<br>
     * 英字<br/>
     * 最大桁数：3<br/>
     * 通貨単位を指定します。<br/>
     */
    private $currencyUnit;

    /**
     * リダイレクションURI<br>
     * 半角英数字<br/>
     * 最大桁数：1024<br/>
     * 検証結果を返すURIを指定します。<br/>
     * ※ 未指定の場合は、マスタに登録されたURIを用います。<br/>
     */
    private $redirectionUri;

    /**
     * HTTPアクセプト<br>
     * 文字列<br/>
     * 最大桁数：-<br/>
     * コンシューマのブラウザ情報でアプリケーションサーバから取得して設定します。<br/>
     * ※ 3Dセキュア 2.0の場合は必須です。<br/>
     */
    private $httpAccept;

    /**
     * HTTPユーザエージェント<br>
     * 文字列<br/>
     * 最大桁数：-<br/>
     * コンシューマのブラウザ情報でアプリケーションサーバから取得して設定します。<br/>
     * ※ 3Dセキュア 2.0の場合は必須です。<br/>
     */
    private $httpUserAgent;

    /**
     * 初回請求年月<br>
     * 半角数字<br/>
     * 最大桁数：4<br/>
     * 初回請求年月を指定します。<br/>
     * YYMM（年月）の形式<br/>
     */
    private $firstPayment;

    /**
     * ボーナス初回年月<br>
     * 半角数字<br/>
     * 最大桁数：4<br/>
     * ボーナス初回年月を指定します。<br/>
     * YYMM（年月）の形式<br/>
     */
    private $bonusFirstPayment;

    /**
     * 決済金額（多通貨）<br>
     * 半角数字、小数点<br/>
     * 最大桁数：9<br/>
     * 決済金額（多通貨）を指定します。<br/>
     * 1 以上かつ 99999999 以下である必要があります。<br/>
     */
    private $mcAmount;

    /**
     * プッシュURL<br>
     * URL<br/>
     * 最大桁数：256<br/>
     * プッシュURLを指定します。<br/>
     * ※ 未指定の場合は、マスタに登録された値を使用します。<br/>
     */
    private $pushUrl;

    /**
     * 端末種別<br>
     * 半角数字<br/>
     * 最大桁数：1<br/>
     *  0:PC<br/>
     *  1:mobile<br/>
     * ※ 未指定の場合は、0:PC<br/>
     */
    private $browserDeviceCategory;

    /**
     * 詳細パラメータ連携フラグ<br>
     * 半角数字<br/>
     * 最大桁数：1<br/>
     *  0:パラメータ連携しない<br/>
     *  1:パラメータ連携する<br/>
     *  2:パラメータ連携しない(GET)<br/>
     * ※ 未指定の場合は、マスタに登録された値を使用します。<br/>
     */
    private $verifyResultLink;

    /**
     * 仮登録フラグ<br>
     * 半角数字<br/>
     * 最大桁数：1<br/>
     *  0:仮登録しない<br/>
     *  1:仮登録する<br/>
     */
    private $tempRegistration;

    /**
     * 不正検知評価取引情報<br>
     * -<br/>
     * 最大桁数：-<br/>
     * 不正検知取引情報<br/>
     */
    private $fraudDetectionRequest;

    /**
     * 不正検知実施フラグ<br>
     * 半角英数字<br/>
     * 最大桁数：5<br/>
     * 不正検知実施フラグを指定します。<br/>
     * "true"： 実施する<br/>
     * "false"： 実施しない<br/>
     */
    private $withFraudDetection;

    /**
     * 本人認証有効期限<br>
     * 半角数字<br/>
     * 最大桁数：3<br/>
     * 消費者が本人認証処理を完了するまでの有効期限（分単位）を指定します。<br/>
     * 1 以上かつ 999 以下である必要があります。<br/>
     * ※ 未指定の場合は、有効期限のチェックを行いません。<br/>
     */
    private $verifyTimeout;

    /**
     * デバイスチャネル<br>
     * 半角数字<br/>
     * 最大桁数：2<br/>
     * デバイスチャネルを指定します。<br/>
     *  01:アプリベース<br/>
     *  02:ブラウザベース<br/>
     *  03:3DSリクエスター（3RI）<br/>
     * ※ 未指定の場合は、マスタに登録された値を使用します。<br/>
     *     マスタにも値が設定されていない場合は、3Dセキュア 1.0.2として扱います。<br/>
     */
    private $deviceChannel;

    /**
     * アカウントタイプ<br>
     * 半角数字<br/>
     * 最大桁数：2<br/>
     * アカウントの種類を指定します。<br/>
     *  01:該当なし<br/>
     *  02:クレジット<br/>
     *  03:デビット<br/>
     */
    private $accountType;

    /**
     * 認証要求タイプ<br>
     * 半角数字<br/>
     * 最大桁数：2<br/>
     * 認証要求のタイプを指定します。<br/>
     *  01:支払い<br/>
     *  02:リカーリング<br/>
     *  03:分割<br/>
     *  04:カード追加<br/>
     *  05:有効性確認<br/>
     *  06:カード保有者確認<br/>
     *  07:請求確認<br/>
     * ※ 未指定の場合は、01:支払い<br/>
     */
    private $authenticationIndicator;

    /**
     * メッセージカテゴリ<br>
     * 半角数字<br/>
     * 最大桁数：2<br/>
     * メッセージのカテゴリを指定します。<br/>
     *  01:PA（支払い認証）<br/>
     *  02:NPA（支払い無しの認証）<br/>
     * ※ サービスオプションタイプがmpi-complete、mpi-company、mpi-merchantの場合は、"01"のみ指定可能です。<br/>
     */
    private $messageCategory;

    /**
     * カード保有者名<br>
     * 半角英数字記号<br/>
     * 最大桁数：45<br/>
     * カード保有者名を指定します。2桁以上。<br/>
     * ※ 3Dセキュア 2.0の場合は必須です。<br/>
     */
    private $cardholderName;

    /**
     * カード保有者メールアドレス<br>
     * 半角英数字記号<br/>
     * 最大桁数：254<br/>
     * カード保有者メールアドレスを指定します。<br/>
     */
    private $cardholderEmail;

    /**
     * カード保有者自宅電話番号国コード<br>
     * 半角数字<br/>
     * 最大桁数：3<br/>
     * カード保有者自宅電話番号の国コードを指定します。<br/>
     */
    private $cardholderHomePhoneCountry;

    /**
     * カード保有者自宅電話番号<br>
     * 半角数字<br/>
     * 最大桁数：15<br/>
     * カード保有者自宅電話番号を指定します。<br/>
     */
    private $cardholderHomePhoneNumber;

    /**
     * カード保有者携帯電話番号国コード<br>
     * 半角数字<br/>
     * 最大桁数：3<br/>
     * カード保有者携帯電話番号の国コードを指定します。<br/>
     */
    private $cardholderMobilePhoneCountry;

    /**
     * カード保有者携帯電話番号<br>
     * 半角数字<br/>
     * 最大桁数：15<br/>
     * カード保有者携帯電話番号を指定します。<br/>
     */
    private $cardholderMobilePhoneNumber;

    /**
     * カード保有者勤務先電話番号国コード<br>
     * 半角数字<br/>
     * 最大桁数：3<br/>
     * カード保有者勤務先電話番号の国コードを指定します。<br/>
     */
    private $cardholderWorkPhoneCountry;

    /**
     * カード保有者勤務先電話番号<br>
     * 半角数字<br/>
     * 最大桁数：15<br/>
     * カード保有者勤務先電話番号を指定します。<br/>
     */
    private $cardholderWorkPhoneNumber;

    /**
     * 請求先住所_市区町村<br>
     * 文字列<br/>
     * 最大桁数：50<br/>
     * 請求先住所の市区町村を指定します。<br/>
     */
    private $billingAddressCity;

    /**
     * 請求先住所_国<br>
     * 半角数字<br/>
     * 最大桁数：3<br/>
     * 請求先住所の国を指定します。<br/>
     * ISO 3166-1 numericで定義されているコードを設定します。<br/>
     */
    private $billingAddressCountry;

    /**
     * 請求先住所1<br>
     * 文字列<br/>
     * 最大桁数：50<br/>
     * 請求先住所1を指定します。<br/>
     */
    private $billingAddressLine1;

    /**
     * 請求先住所2<br>
     * 文字列<br/>
     * 最大桁数：50<br/>
     * 請求先住所2を指定します。<br/>
     */
    private $billingAddressLine2;

    /**
     * 請求先住所3<br>
     * 文字列<br/>
     * 最大桁数：50<br/>
     * 請求先住所3を指定します。<br/>
     */
    private $billingAddressLine3;

    /**
     * 請求先郵便番号<br>
     * 半角数字<br/>
     * 最大桁数：16<br/>
     * 請求先郵便番号を指定します。<br/>
     */
    private $billingPostalCode;

    /**
     * 請求先住所_都道府県<br>
     * 半角英数字<br/>
     * 最大桁数：3<br/>
     * 請求先住所の都道府県を指定します。<br/>
     * ISO 3166-2で定義されているコードを設定します。<br/>
     */
    private $billingAddressState;

    /**
     * 配送先住所_市区町村<br>
     * 文字列<br/>
     * 最大桁数：50<br/>
     * 配送先住所の市区町村を指定します。<br/>
     */
    private $shippingAddressCity;

    /**
     * 配送先住所_国<br>
     * 半角数字<br/>
     * 最大桁数：3<br/>
     * 配送先住所の国を指定します。<br/>
     * ISO 3166-1 numericで定義されているコードを設定します。<br/>
     */
    private $shippingAddressCountry;

    /**
     * 配送先住所1<br>
     * 文字列<br/>
     * 最大桁数：50<br/>
     * 配送先住所1を指定します。<br/>
     */
    private $shippingAddressLine1;

    /**
     * 配送先住所2<br>
     * 文字列<br/>
     * 最大桁数：50<br/>
     * 配送先住所2を指定します。<br/>
     */
    private $shippingAddressLine2;

    /**
     * 配送先住所3<br>
     * 文字列<br/>
     * 最大桁数：50<br/>
     * 配送先住所3を指定します。<br/>
     */
    private $shippingAddressLine3;

    /**
     * 配送先郵便番号<br>
     * 半角数字<br/>
     * 最大桁数：16<br/>
     * 配送先郵便番号を指定します。<br/>
     */
    private $shippingPostalCode;

    /**
     * 配送先住所_都道府県<br>
     * 半角英数字<br/>
     * 最大桁数：3<br/>
     * 配送先住所の都道府県を指定します。<br/>
     * ISO 3166-2で定義されているコードを設定します。<br/>
     */
    private $shippingAddressState;

    /**
     * 消費者IPアドレス<br>
     * 半角英数字記号<br/>
     * 最大桁数：45<br/>
     * コンシューマのブラウザ情報でアプリケーションサーバから取得して設定します。<br/>
     * 半角英数字以外に、".(ドット)"が使用可能です。<br/>
     */
    private $customerIp;

    /**
     * チャレンジ認証フラグ<br>
     * 半角英数字<br/>
     * 最大桁数：5<br/>
     * チャレンジ認証を行うかどうかを指定します。（任意指定）<br/>
     * "true"： チャレンジ認証を行う<br/>
     * "false"： チャレンジ認証を行わない<br/>
     * ※ 未指定の場合は、true： チャレンジ認証を行う<br/>
     */
    private $withChallenge;


    /**
     * ログ用文字列(マスク済み)<br>
     * 半角英数字<br>
     */
    private $maskedLog;


    /**
     * 決済サービスタイプを取得する<br>
     * @return string 決済サービスタイプ<br>
     */
    public function getServiceType() {
        return $this->SERVICE_TYPE;
    }

    /**
     * 決済サービスコマンドを取得する<br>
     * @return string 決済サービスコマンド<br>
     */
    public function getServiceCommand() {
        return $this->SERVICE_COMMAND;
    }

    /**
     * サービスオプションタイプを取得する<br>
     * @return string サービスオプションタイプ<br>
     */
    public function getServiceOptionType() {
        return $this->serviceOptionType;
    }

    /**
     * サービスオプションタイプを設定する<br>
     * @param string $serviceOptionType サービスオプションタイプ<br>
     */
    public function setServiceOptionType($serviceOptionType) {
        $this->serviceOptionType = $serviceOptionType;
    }

    /**
     * 取引IDを取得する<br>
     * @return string 取引ID<br>
     */
    public function getOrderId() {
        return $this->orderId;
    }

    /**
     * 取引IDを設定する<br>
     * @param string $orderId 取引ID<br>
     */
    public function setOrderId($orderId) {
        $this->orderId = $orderId;
    }

    /**
     * 決済金額を取得する<br>
     * @return string 決済金額<br>
     */
    public function getAmount() {
        return $this->amount;
    }

    /**
     * 決済金額を設定する<br>
     * @param string $amount 決済金額<br>
     */
    public function setAmount($amount) {
        $this->amount = $amount;
    }

    /**
     * カード番号を取得する<br>
     * @return string カード番号<br>
     */
    public function getCardNumber() {
        return $this->cardNumber;
    }

    /**
     * カード番号を設定する<br>
     * @param string $cardNumber カード番号<br>
     */
    public function setCardNumber($cardNumber) {
        $this->cardNumber = $cardNumber;
    }

    /**
     * カード有効期限を取得する<br>
     * @return string カード有効期限<br>
     */
    public function getCardExpire() {
        return $this->cardExpire;
    }

    /**
     * カード有効期限を設定する<br>
     * @param string $cardExpire カード有効期限<br>
     */
    public function setCardExpire($cardExpire) {
        $this->cardExpire = $cardExpire;
    }

    /**
     * カード接続センターを取得する<br>
     * @return string カード接続センター<br>
     */
    public function getCardCenter() {
        return $this->cardCenter;
    }

    /**
     * カード接続センターを設定する<br>
     * @param string $cardCenter カード接続センター<br>
     */
    public function setCardCenter($cardCenter) {
        $this->cardCenter = $cardCenter;
    }

    /**
     * 仕向け先コードを取得する<br>
     * @return string 仕向け先コード<br>
     */
    public function getAcquirerCode() {
        return $this->acquirerCode;
    }

    /**
     * 仕向け先コードを設定する<br>
     * @param string $acquirerCode 仕向け先コード<br>
     */
    public function setAcquirerCode($acquirerCode) {
        $this->acquirerCode = $acquirerCode;
    }

    /**
     * 支払種別を取得する<br>
     * @return string 支払種別<br>
     */
    public function getJpo() {
        return $this->jpo;
    }

    /**
     * 支払種別を設定する<br>
     * @param string $jpo 支払種別<br>
     */
    public function setJpo($jpo) {
        $this->jpo = $jpo;
    }

    /**
     * 売上フラグを取得する<br>
     * @return string 売上フラグ<br>
     */
    public function getWithCapture() {
        return $this->withCapture;
    }

    /**
     * 売上フラグを設定する<br>
     * @param string $withCapture 売上フラグ<br>
     */
    public function setWithCapture($withCapture) {
        $this->withCapture = $withCapture;
    }

    /**
     * 売上日を取得する<br>
     * @return string 売上日<br>
     */
    public function getSalesDay() {
        return $this->salesDay;
    }

    /**
     * 売上日を設定する<br>
     * @param string $salesDay 売上日<br>
     */
    public function setSalesDay($salesDay) {
        $this->salesDay = $salesDay;
    }

    /**
     * 商品コードを取得する<br>
     * @return string 商品コード<br>
     */
    public function getItemCode() {
        return $this->itemCode;
    }

    /**
     * 商品コードを設定する<br>
     * @param string $itemCode 商品コード<br>
     */
    public function setItemCode($itemCode) {
        $this->itemCode = $itemCode;
    }

    /**
     * セキュリティコードを取得する<br>
     * @return string セキュリティコード<br>
     */
    public function getSecurityCode() {
        return $this->securityCode;
    }

    /**
     * セキュリティコードを設定する<br>
     * @param string $securityCode セキュリティコード<br>
     */
    public function setSecurityCode($securityCode) {
        $this->securityCode = $securityCode;
    }

    /**
     * 誕生日を取得する<br>
     * @return string 誕生日<br>
     */
    public function getBirthday() {
        return $this->birthday;
    }

    /**
     * 誕生日を設定する<br>
     * @param string $birthday 誕生日<br>
     */
    public function setBirthday($birthday) {
        $this->birthday = $birthday;
    }

    /**
     * 電話番号を取得する<br>
     * @return string 電話番号<br>
     */
    public function getTel() {
        return $this->tel;
    }

    /**
     * 電話番号を設定する<br>
     * @param string $tel 電話番号<br>
     */
    public function setTel($tel) {
        $this->tel = $tel;
    }

    /**
     * 名前（名）カナを取得する<br>
     * @return string 名前（名）カナ<br>
     */
    public function getFirstKanaName() {
        return $this->firstKanaName;
    }

    /**
     * 名前（名）カナを設定する<br>
     * @param string $firstKanaName 名前（名）カナ<br>
     */
    public function setFirstKanaName($firstKanaName) {
        $this->firstKanaName = $firstKanaName;
    }

    /**
     * 名前（姓）カナを取得する<br>
     * @return string 名前（姓）カナ<br>
     */
    public function getLastKanaName() {
        return $this->lastKanaName;
    }

    /**
     * 名前（姓）カナを設定する<br>
     * @param string $lastKanaName 名前（姓）カナ<br>
     */
    public function setLastKanaName($lastKanaName) {
        $this->lastKanaName = $lastKanaName;
    }

    /**
     * 通貨単位を取得する<br>
     * @return string 通貨単位<br>
     */
    public function getCurrencyUnit() {
        return $this->currencyUnit;
    }

    /**
     * 通貨単位を設定する<br>
     * @param string $currencyUnit 通貨単位<br>
     */
    public function setCurrencyUnit($currencyUnit) {
        $this->currencyUnit = $currencyUnit;
    }

    /**
     * リダイレクションURIを取得する<br>
     * @return string リダイレクションURI<br>
     */
    public function getRedirectionUri() {
        return $this->redirectionUri;
    }

    /**
     * リダイレクションURIを設定する<br>
     * @param string $redirectionUri リダイレクションURI<br>
     */
    public function setRedirectionUri($redirectionUri) {
        $this->redirectionUri = $redirectionUri;
    }

    /**
     * HTTPアクセプトを取得する<br>
     * @return string HTTPアクセプト<br>
     */
    public function getHttpAccept() {
        return $this->httpAccept;
    }

    /**
     * HTTPアクセプトを設定する<br>
     * @param string $httpAccept HTTPアクセプト<br>
     */
    public function setHttpAccept($httpAccept) {
        $this->httpAccept = $httpAccept;
    }

    /**
     * HTTPユーザエージェントを取得する<br>
     * @return string HTTPユーザエージェント<br>
     */
    public function getHttpUserAgent() {
        return $this->httpUserAgent;
    }

    /**
     * HTTPユーザエージェントを設定する<br>
     * @param string $httpUserAgent HTTPユーザエージェント<br>
     */
    public function setHttpUserAgent($httpUserAgent) {
        $this->httpUserAgent = $httpUserAgent;
    }

    /**
     * 初回請求年月を取得する<br>
     * @return string 初回請求年月<br>
     */
    public function getFirstPayment() {
        return $this->firstPayment;
    }

    /**
     * 初回請求年月を設定する<br>
     * @param string $firstPayment 初回請求年月<br>
     */
    public function setFirstPayment($firstPayment) {
        $this->firstPayment = $firstPayment;
    }

    /**
     * ボーナス初回年月を取得する<br>
     * @return string ボーナス初回年月<br>
     */
    public function getBonusFirstPayment() {
        return $this->bonusFirstPayment;
    }

    /**
     * ボーナス初回年月を設定する<br>
     * @param string $bonusFirstPayment ボーナス初回年月<br>
     */
    public function setBonusFirstPayment($bonusFirstPayment) {
        $this->bonusFirstPayment = $bonusFirstPayment;
    }

    /**
     * 決済金額（多通貨）を取得する<br>
     * @return string 決済金額（多通貨）<br>
     */
    public function getMcAmount() {
        return $this->mcAmount;
    }

    /**
     * 決済金額（多通貨）を設定する<br>
     * @param string $mcAmount 決済金額（多通貨）<br>
     */
    public function setMcAmount($mcAmount) {
        $this->mcAmount = $mcAmount;
    }

    /**
     * プッシュURLを取得する<br>
     * @return string プッシュURL<br>
     */
    public function getPushUrl() {
        return $this->pushUrl;
    }

    /**
     * プッシュURLを設定する<br>
     * @param string $pushUrl プッシュURL<br>
     */
    public function setPushUrl($pushUrl) {
        $this->pushUrl = $pushUrl;
    }

    /**
     * 端末種別を取得する<br>
     * @return string 端末種別<br>
     */
    public function getBrowserDeviceCategory() {
        return $this->browserDeviceCategory;
    }

    /**
     * 端末種別を設定する<br>
     * @param string $browserDeviceCategory 端末種別<br>
     */
    public function setBrowserDeviceCategory($browserDeviceCategory) {
        $this->browserDeviceCategory = $browserDeviceCategory;
    }

    /**
     * 詳細パラメータ連携フラグを取得する<br>
     * @return string 詳細パラメータ連携フラグ<br>
     */
    public function getVerifyResultLink() {
        return $this->verifyResultLink;
    }

    /**
     * 詳細パラメータ連携フラグを設定する<br>
     * @param string $verifyResultLink 詳細パラメータ連携フラグ<br>
     */
    public function setVerifyResultLink($verifyResultLink) {
        $this->verifyResultLink = $verifyResultLink;
    }

    /**
     * 仮登録フラグを取得する<br>
     * @return string 仮登録フラグ<br>
     */
    public function getTempRegistration() {
        return $this->tempRegistration;
    }

    /**
     * 仮登録フラグを設定する<br>
     * @param string $tempRegistration 仮登録フラグ<br>
     */
    public function setTempRegistration($tempRegistration) {
        $this->tempRegistration = $tempRegistration;
    }

    /**
     * 不正検知評価取引情報を取得する<br>
     * @return FraudDetectionRequestDto 不正検知評価取引情報<br>
     */
    public function getFraudDetectionRequest() {
        return $this->fraudDetectionRequest;
    }

    /**
     * 不正検知評価取引情報を設定する<br>
     * @param FraudDetectionRequestDto $fraudDetectionRequest 不正検知評価取引情報<br>
     */
    public function setFraudDetectionRequest($fraudDetectionRequest) {
        $this->fraudDetectionRequest = $fraudDetectionRequest;
    }

    /**
     * 不正検知実施フラグを取得する<br>
     * @return string 不正検知実施フラグ<br>
     */
    public function getWithFraudDetection() {
        return $this->withFraudDetection;
    }

    /**
     * 不正検知実施フラグを設定する<br>
     * @param string $withFraudDetection 不正検知実施フラグ<br>
     */
    public function setWithFraudDetection($withFraudDetection) {
        $this->withFraudDetection = $withFraudDetection;
    }

    /**
     * 本人認証有効期限を取得する<br>
     * @return string 本人認証有効期限<br>
     */
    public function getVerifyTimeout() {
        return $this->verifyTimeout;
    }

    /**
     * 本人認証有効期限を設定する<br>
     * @param string $verifyTimeout 本人認証有効期限<br>
     */
    public function setVerifyTimeout($verifyTimeout) {
        $this->verifyTimeout = $verifyTimeout;
    }

    /**
     * デバイスチャネルを取得する<br>
     * @return string デバイスチャネル<br>
     */
    public function getDeviceChannel() {
        return $this->deviceChannel;
    }

    /**
     * デバイスチャネルを設定する<br>
     * @param string $deviceChannel デバイスチャネル<br>
     */
    public function setDeviceChannel($deviceChannel) {
        $this->deviceChannel = $deviceChannel;
    }

    /**
     * アカウントタイプを取得する<br>
     * @return string アカウントタイプ<br>
     */
    public function getAccountType() {
        return $this->accountType;
    }

    /**
     * アカウントタイプを設定する<br>
     * @param string $accountType アカウントタイプ<br>
     */
    public function setAccountType($accountType) {
        $this->accountType = $accountType;
    }

    /**
     * 認証要求タイプを取得する<br>
     * @return string 認証要求タイプ<br>
     */
    public function getAuthenticationIndicator() {
        return $this->authenticationIndicator;
    }

    /**
     * 認証要求タイプを設定する<br>
     * @param string $authenticationIndicator 認証要求タイプ<br>
     */
    public function setAuthenticationIndicator($authenticationIndicator) {
        $this->authenticationIndicator = $authenticationIndicator;
    }

    /**
     * メッセージカテゴリを取得する<br>
     * @return string メッセージカテゴリ<br>
     */
    public function getMessageCategory() {
        return $this->messageCategory;
    }

    /**
     * メッセージカテゴリを設定する<br>
     * @param string $messageCategory メッセージカテゴリ<br>
     */
    public function setMessageCategory($messageCategory) {
        $this->messageCategory = $messageCategory;
    }

    /**
     * カード保有者名を取得する<br>
     * @return string カード保有者名<br>
     */
    public function getCardholderName() {
        return $this->cardholderName;
    }

    /**
     * カード保有者名を設定する<br>
     * @param string $cardholderName カード保有者名<br>
     */
    public function setCardholderName($cardholderName) {
        $this->cardholderName = $cardholderName;
    }

    /**
     * カード保有者メールアドレスを取得する<br>
     * @return string カード保有者メールアドレス<br>
     */
    public function getCardholderEmail() {
        return $this->cardholderEmail;
    }

    /**
     * カード保有者メールアドレスを設定する<br>
     * @param string $cardholderEmail カード保有者メールアドレス<br>
     */
    public function setCardholderEmail($cardholderEmail) {
        $this->cardholderEmail = $cardholderEmail;
    }

    /**
     * カード保有者自宅電話番号国コードを取得する<br>
     * @return string カード保有者自宅電話番号国コード<br>
     */
    public function getCardholderHomePhoneCountry() {
        return $this->cardholderHomePhoneCountry;
    }

    /**
     * カード保有者自宅電話番号国コードを設定する<br>
     * @param string $cardholderHomePhoneCountry カード保有者自宅電話番号国コード<br>
     */
    public function setCardholderHomePhoneCountry($cardholderHomePhoneCountry) {
        $this->cardholderHomePhoneCountry = $cardholderHomePhoneCountry;
    }

    /**
     * カード保有者自宅電話番号を取得する<br>
     * @return string カード保有者自宅電話番号<br>
     */
    public function getCardholderHomePhoneNumber() {
        return $this->cardholderHomePhoneNumber;
    }

    /**
     * カード保有者自宅電話番号を設定する<br>
     * @param string $cardholderHomePhoneNumber カード保有者自宅電話番号<br>
     */
    public function setCardholderHomePhoneNumber($cardholderHomePhoneNumber) {
        $this->cardholderHomePhoneNumber = $cardholderHomePhoneNumber;
    }

    /**
     * カード保有者携帯電話番号国コードを取得する<br>
     * @return string カード保有者携帯電話番号国コード<br>
     */
    public function getCardholderMobilePhoneCountry() {
        return $this->cardholderMobilePhoneCountry;
    }

    /**
     * カード保有者携帯電話番号国コードを設定する<br>
     * @param string $cardholderMobilePhoneCountry カード保有者携帯電話番号国コード<br>
     */
    public function setCardholderMobilePhoneCountry($cardholderMobilePhoneCountry) {
        $this->cardholderMobilePhoneCountry = $cardholderMobilePhoneCountry;
    }

    /**
     * カード保有者携帯電話番号を取得する<br>
     * @return string カード保有者携帯電話番号<br>
     */
    public function getCardholderMobilePhoneNumber() {
        return $this->cardholderMobilePhoneNumber;
    }

    /**
     * カード保有者携帯電話番号を設定する<br>
     * @param string $cardholderMobilePhoneNumber カード保有者携帯電話番号<br>
     */
    public function setCardholderMobilePhoneNumber($cardholderMobilePhoneNumber) {
        $this->cardholderMobilePhoneNumber = $cardholderMobilePhoneNumber;
    }

    /**
     * カード保有者勤務先電話番号国コードを取得する<br>
     * @return string カード保有者勤務先電話番号国コード<br>
     */
    public function getCardholderWorkPhoneCountry() {
        return $this->cardholderWorkPhoneCountry;
    }

    /**
     * カード保有者勤務先電話番号国コードを設定する<br>
     * @param string $cardholderWorkPhoneCountry カード保有者勤務先電話番号国コード<br>
     */
    public function setCardholderWorkPhoneCountry($cardholderWorkPhoneCountry) {
        $this->cardholderWorkPhoneCountry = $cardholderWorkPhoneCountry;
    }

    /**
     * カード保有者勤務先電話番号を取得する<br>
     * @return string カード保有者勤務先電話番号<br>
     */
    public function getCardholderWorkPhoneNumber() {
        return $this->cardholderWorkPhoneNumber;
    }

    /**
     * カード保有者勤務先電話番号を設定する<br>
     * @param string $cardholderWorkPhoneNumber カード保有者勤務先電話番号<br>
     */
    public function setCardholderWorkPhoneNumber($cardholderWorkPhoneNumber) {
        $this->cardholderWorkPhoneNumber = $cardholderWorkPhoneNumber;
    }

    /**
     * 請求先住所_市区町村を取得する<br>
     * @return string 請求先住所_市区町村<br>
     */
    public function getBillingAddressCity() {
        return $this->billingAddressCity;
    }

    /**
     * 請求先住所_市区町村を設定する<br>
     * @param string $billingAddressCity 請求先住所_市区町村<br>
     */
    public function setBillingAddressCity($billingAddressCity) {
        $this->billingAddressCity = $billingAddressCity;
    }

    /**
     * 請求先住所_国を取得する<br>
     * @return string 請求先住所_国<br>
     */
    public function getBillingAddressCountry() {
        return $this->billingAddressCountry;
    }

    /**
     * 請求先住所_国を設定する<br>
     * @param string $billingAddressCountry 請求先住所_国<br>
     */
    public function setBillingAddressCountry($billingAddressCountry) {
        $this->billingAddressCountry = $billingAddressCountry;
    }

    /**
     * 請求先住所1を取得する<br>
     * @return string 請求先住所1<br>
     */
    public function getBillingAddressLine1() {
        return $this->billingAddressLine1;
    }

    /**
     * 請求先住所1を設定する<br>
     * @param string $billingAddressLine1 請求先住所1<br>
     */
    public function setBillingAddressLine1($billingAddressLine1) {
        $this->billingAddressLine1 = $billingAddressLine1;
    }

    /**
     * 請求先住所2を取得する<br>
     * @return string 請求先住所2<br>
     */
    public function getBillingAddressLine2() {
        return $this->billingAddressLine2;
    }

    /**
     * 請求先住所2を設定する<br>
     * @param string $billingAddressLine2 請求先住所2<br>
     */
    public function setBillingAddressLine2($billingAddressLine2) {
        $this->billingAddressLine2 = $billingAddressLine2;
    }

    /**
     * 請求先住所3を取得する<br>
     * @return string 請求先住所3<br>
     */
    public function getBillingAddressLine3() {
        return $this->billingAddressLine3;
    }

    /**
     * 請求先住所3を設定する<br>
     * @param string $billingAddressLine3 請求先住所3<br>
     */
    public function setBillingAddressLine3($billingAddressLine3) {
        $this->billingAddressLine3 = $billingAddressLine3;
    }

    /**
     * 請求先郵便番号を取得する<br>
     * @return string 請求先郵便番号<br>
     */
    public function getBillingPostalCode() {
        return $this->billingPostalCode;
    }

    /**
     * 請求先郵便番号を設定する<br>
     * @param string $billingPostalCode 請求先郵便番号<br>
     */
    public function setBillingPostalCode($billingPostalCode) {
        $this->billingPostalCode = $billingPostalCode;
    }

    /**
     * 請求先住所_都道府県を取得する<br>
     * @return string 請求先住所_都道府県<br>
     */
    public function getBillingAddressState() {
        return $this->billingAddressState;
    }

    /**
     * 請求先住所_都道府県を設定する<br>
     * @param string $billingAddressState 請求先住所_都道府県<br>
     */
    public function setBillingAddressState($billingAddressState) {
        $this->billingAddressState = $billingAddressState;
    }

    /**
     * 配送先住所_市区町村を取得する<br>
     * @return string 配送先住所_市区町村<br>
     */
    public function getShippingAddressCity() {
        return $this->shippingAddressCity;
    }

    /**
     * 配送先住所_市区町村を設定する<br>
     * @param string $shippingAddressCity 配送先住所_市区町村<br>
     */
    public function setShippingAddressCity($shippingAddressCity) {
        $this->shippingAddressCity = $shippingAddressCity;
    }

    /**
     * 配送先住所_国を取得する<br>
     * @return string 配送先住所_国<br>
     */
    public function getShippingAddressCountry() {
        return $this->shippingAddressCountry;
    }

    /**
     * 配送先住所_国を設定する<br>
     * @param string $shippingAddressCountry 配送先住所_国<br>
     */
    public function setShippingAddressCountry($shippingAddressCountry) {
        $this->shippingAddressCountry = $shippingAddressCountry;
    }

    /**
     * 配送先住所1を取得する<br>
     * @return string 配送先住所1<br>
     */
    public function getShippingAddressLine1() {
        return $this->shippingAddressLine1;
    }

    /**
     * 配送先住所1を設定する<br>
     * @param string $shippingAddressLine1 配送先住所1<br>
     */
    public function setShippingAddressLine1($shippingAddressLine1) {
        $this->shippingAddressLine1 = $shippingAddressLine1;
    }

    /**
     * 配送先住所2を取得する<br>
     * @return string 配送先住所2<br>
     */
    public function getShippingAddressLine2() {
        return $this->shippingAddressLine2;
    }

    /**
     * 配送先住所2を設定する<br>
     * @param string $shippingAddressLine2 配送先住所2<br>
     */
    public function setShippingAddressLine2($shippingAddressLine2) {
        $this->shippingAddressLine2 = $shippingAddressLine2;
    }

    /**
     * 配送先住所3を取得する<br>
     * @return string 配送先住所3<br>
     */
    public function getShippingAddressLine3() {
        return $this->shippingAddressLine3;
    }

    /**
     * 配送先住所3を設定する<br>
     * @param string $shippingAddressLine3 配送先住所3<br>
     */
    public function setShippingAddressLine3($shippingAddressLine3) {
        $this->shippingAddressLine3 = $shippingAddressLine3;
    }

    /**
     * 配送先郵便番号を取得する<br>
     * @return string 配送先郵便番号<br>
     */
    public function getShippingPostalCode() {
        return $this->shippingPostalCode;
    }

    /**
     * 配送先郵便番号を設定する<br>
     * @param string $shippingPostalCode 配送先郵便番号<br>
     */
    public function setShippingPostalCode($shippingPostalCode) {
        $this->shippingPostalCode = $shippingPostalCode;
    }

    /**
     * 配送先住所_都道府県を取得する<br>
     * @return string 配送先住所_都道府県<br>
     */
    public function getShippingAddressState() {
        return $this->shippingAddressState;
    }

    /**
     * 配送先住所_都道府県を設定する<br>
     * @param string $shippingAddressState 配送先住所_都道府県<br>
     */
    public function setShippingAddressState($shippingAddressState) {
        $this->shippingAddressState = $shippingAddressState;
    }

    /**
     * 消費者IPアドレスを取得する<br>
     * @return string 消費者IPアドレス<br>
     */
    public function getCustomerIp() {
        return $this->customerIp;
    }

    /**
     * 消費者IPアドレスを設定する<br>
     * @param string $customerIp 消費者IPアドレス<br>
     */
    public function setCustomerIp($customerIp) {
        $this->customerIp = $customerIp;
    }

    /**
     * チャレンジ認証フラグを取得する<br>
     * @return string チャレンジ認証フラグ<br>
     */
    public function getWithChallenge() {
        return $this->withChallenge;
    }

    /**
     * チャレンジ認証フラグを設定する<br>
     * @param string $withChallenge チャレンジ認証フラグ<br>
     */
    public function setWithChallenge($withChallenge) {
        $this->withChallenge = $withChallenge;
    }


    /**
     * ログ用文字列(マスク済み)を設定する<br>
     * @param string $maskedLog ログ用文字列(マスク済み)<br>
     */
    public function _setMaskedLog($maskedLog) {
        $this->maskedLog = $maskedLog;
    }

    /**
     * ログ用文字列(マスク済み)を取得する<br>
     * @return string ログ用文字列(マスク済み)<br>
     */
    public function __toString() {
        return (string)$this->maskedLog;
    }


    /**
     * 拡張パラメータ<br>
     * 並列処理用の拡張パラメータを保持する。
     */
    private $optionParams;

    /**
     * 拡張パラメータリストを取得する<br>
     * @return OptionParams 拡張パラメータリスト<br>
     */
    public function getOptionParams()
    {
        return $this->optionParams;
    }

    /**
     * 拡張パラメータリストを設定する<br>
     * @param OptionParams $optionParams 拡張パラメータリスト<br>
     */
    public function setOptionParams($optionParams)
    {
        $this->optionParams = $optionParams;
    }

}
?>
