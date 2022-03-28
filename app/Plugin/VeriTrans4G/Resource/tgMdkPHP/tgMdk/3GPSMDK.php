<?php
/******************************************************************************
 * 3GPSMDK.php - MDK Library main file.
 *
 * @category    Veritrans
 * @package     Lib
 * @copyright   VeriTrans Inc.
 ******************************************************************************/

if (defined('3GPSMDK_INCLUDED')) {
    $GLOBALS['3GPSMDK_COUNT_OF_INCLUDE']++;
    return;
} else {
    $GLOBALS['3GPSMDK_COUNT_OF_INCLUDE'] = 1;
    define('3GPSMDK_INCLUDED', TRUE);
}

// デフォルトタイムゾーンの設定
// php.iniにdate.timezoneが設定されていない場合のみ
$date_timezone = ini_get("date.timezone");
if (empty($date_timezone)) {
    date_default_timezone_set("Asia/Tokyo");
}

if (!defined('DS'))             define('DS', DIRECTORY_SEPARATOR);                       //  DS ディレクトリセパレータ省略形
if (!defined('LF'))             define('LF', PHP_EOL);                                   //  OS依存の改行

if (!defined('MDK_DIR'))        define('MDK_DIR', dirname(__FILE__));                    //  MDKインストールディレクトリ
if (!defined('MDK_LIB_DIR'))    define('MDK_LIB_DIR', MDK_DIR . DS . "Lib");             //  MDK/Libディレクトリ
if (!defined('MDK_DTO_DIR'))    define('MDK_DTO_DIR', MDK_LIB_DIR . DS . "tgMdkDto");    //  MDK/Lib/tgMdkDtoディレクトリ


/******************************************************************************
 *  log4phpが参照する定数
 ******************************************************************************/
//  LOG4PHP_DIR
if (!defined('LOG4PHP_DIR')) { define('LOG4PHP_DIR', MDK_LIB_DIR .DS. "log4php"); }

//  LOG4PHP_CONFIGURATION
if (!defined('LOG4PHP_CONFIGURATION')) { define('LOG4PHP_CONFIGURATION', MDK_DIR .DS. "log4php.properties"); }


/******************************************************************************
 *  各ディレクトリの実在チェック
 ******************************************************************************/
$dh = opendir(MDK_LIB_DIR) or die(MDK_LIB_DIR . " is not a valid directory.");
@closedir($dh);

$dh = opendir(MDK_DTO_DIR) or die(MDK_DTO_DIR . " is not a valid directory.");
@closedir($dh);

$dh = opendir(LOG4PHP_DIR) or die(LOG4PHP_DIR . " is not a valid directory.");
@closedir($dh);


/******************************************************************************
 *  クラスをロードする
 ******************************************************************************/
require_once(MDK_LIB_DIR . DS . 'TGMDK_ErrorMessage.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_Logger.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_Exception.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_AuthHashUtil.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_Config.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_Cipher.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_Connection.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_ConnectionServlet.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_ErrorHandler.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_MaskSaxHandler.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_ContentHandler.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_MerchantUtility.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_Util.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_MerchantSettingContext.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_NVQuery.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_ResElementConstants.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_ResElementProcessor.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_SaxHandler.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_Transaction.php');
require_once(MDK_LIB_DIR . DS . 'TGMDK_JSONQuery.php');

require_once(MDK_DTO_DIR . DS . 'MdkBaseDto.php');
require_once(MDK_DTO_DIR . DS . 'PayNowIdConstants.php');
require_once(MDK_DTO_DIR . DS . 'AbstractPayNowIdRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AbstractPaymentRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AbstractPaymentCreditRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AbstractPaymentVirtualaccRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AbstractPayNowIdResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'OptionParams.php');
require_once(MDK_DTO_DIR . DS . 'SearchRange.php');
require_once(MDK_DTO_DIR . DS . 'TradRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'TransactionInfo.php');
require_once(MDK_DTO_DIR . DS . 'TransactionInfos.php');
require_once(MDK_DTO_DIR . DS . 'ProperOrderInfo.php');
require_once(MDK_DTO_DIR . DS . 'ProperTransactionInfo.php');
require_once(MDK_DTO_DIR . DS . 'CommonSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'AlipaySearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'BankSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'CardSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'CarrierSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'CvsSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'EmSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'PaypalSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'SaisonSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'MpiSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'RakutenSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'RecruitSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'LinepaySearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'MasterpassSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'TenpaySearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'CvspaySearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpaySearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'PaypaySearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'AlipayxSearchParameter.php'); 
require_once(MDK_DTO_DIR . DS . 'AmazonpaySearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'MerpaySearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'EposSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'SearchParameters.php');
require_once(MDK_DTO_DIR . DS . 'OrderInfo.php');
require_once(MDK_DTO_DIR . DS . 'OrderInfos.php');
require_once(MDK_DTO_DIR . DS . 'TransactionApi.php');
require_once(MDK_DTO_DIR . DS . 'TransactionApis.php');
require_once(MDK_DTO_DIR . DS . 'TransactionCard.php');
require_once(MDK_DTO_DIR . DS . 'TransactionCards.php');
require_once(MDK_DTO_DIR . DS . 'BankAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'BankAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CardAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CardAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CardCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CardCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CardCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CardCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CardEmvAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CardEmvAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CardReAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CardReAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CardRetryRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CardRetryResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierReAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierReAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierTerminateRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierTerminateResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierUpdateAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CarrierUpdateAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CvsAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CvsAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CvsCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CvsCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'EmAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'EmAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'EmCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'EmCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'EmRefundRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'EmRefundResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'EmReAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'EmReAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'EmRemoveRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'EmRemoveResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypalAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypalAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypalCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypalCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypalCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypalCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypalRefundRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypalRefundResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MpiAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MpiAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MpiGetResultRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MpiGetResultResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MpiReAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MpiReAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'SaisonAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'SaisonAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'SaisonCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'SaisonCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'SaisonCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'SaisonCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'SearchRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'SearchResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'UpopAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'UpopAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'UpopCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'UpopCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'UpopCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'UpopCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'UpopRefundRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'UpopRefundResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'UpopSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'AlipayAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AlipayAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AlipayConfirmRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AlipayConfirmResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AlipayCreateIdentityCodeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AlipayCreateIdentityCodeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AlipayRefundRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AlipayRefundResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AlipaySearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'BankFinancialInstInfo.php');
require_once(MDK_DTO_DIR . DS . 'MasterInfo.php');
require_once(MDK_DTO_DIR . DS . 'MasterInfos.php');
require_once(MDK_DTO_DIR . DS . 'Masters.php');
require_once(MDK_DTO_DIR . DS . 'OricoscAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'OricoscAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'OricoscSearchParameter.php');
require_once(MDK_DTO_DIR . DS . 'Account.php');
require_once(MDK_DTO_DIR . DS . 'RecurringUpdateResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'RecurringUpdateRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'RecurringGetResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'RecurringGetRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'RecurringDeleteResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'RecurringDeleteRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'RecurringAddResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'RecurringAddRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'ChargeUpdateResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'ChargeUpdateRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'ChargeDeleteResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'ChargeDeleteRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'ChargeAddResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'ChargeAddRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CardInfoUpdateResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CardInfoUpdateRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CardInfoGetResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CardInfoGetRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CardInfoDeleteResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CardInfoDeleteRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CardInfoAddResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CardInfoAddRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountUpdateResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountUpdateRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountRestoreResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountRestoreRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountDeleteResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountDeleteRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountAddResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountAddRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountLinkResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountLinkRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountTokenResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountTokenRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountCopyResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AccountCopyRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'RecurringChargeParam.php');
require_once(MDK_DTO_DIR . DS . 'RecurringCharge.php');
require_once(MDK_DTO_DIR . DS . 'PayNowIdResponse.php');
require_once(MDK_DTO_DIR . DS . 'PayNowIdParam.php');
require_once(MDK_DTO_DIR . DS . 'OptionResults.php');
require_once(MDK_DTO_DIR . DS . 'ChargeParam.php');
require_once(MDK_DTO_DIR . DS . 'CardParam.php');
require_once(MDK_DTO_DIR . DS . 'OrderParam.php');
require_once(MDK_DTO_DIR . DS . 'CardInfo.php');
require_once(MDK_DTO_DIR . DS . 'AccountParam.php');
require_once(MDK_DTO_DIR . DS . 'AccountInfo.php');
require_once(MDK_DTO_DIR . DS . 'AccountBasicParam.php');
require_once(MDK_DTO_DIR . DS . 'AccountBasic.php');
require_once(MDK_DTO_DIR . DS . 'RakutenAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'RakutenAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'RakutenCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'RakutenCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'RakutenCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'RakutenCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'RecruitAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'RecruitAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'RecruitCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'RecruitCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'RecruitCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'RecruitCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'RecruitExtendAuthRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'RecruitExtendAuthResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'LinepayAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'LinepayAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'LinepayCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'LinepayCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'LinepayCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'LinepayCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MasterpassAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MasterpassAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MasterpassCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MasterpassCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MasterpassCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MasterpassCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MasterpassLoginRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MasterpassLoginResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccDepositEntryRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccDepositEntryResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccDepositReverseRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccDepositReverseResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccReconcileRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccReconcileResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccUndoReconcileRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'VirtualaccUndoReconcileResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'BankAccountAddRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'BankAccountAddResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'BankAccountDeleteRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'BankAccountDeleteResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'BankAccountInfo.php');
require_once(MDK_DTO_DIR . DS . 'BankAccountParam.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionDeviceDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionHeaderDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionUserIdentityCookieDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionExternalRiskResultDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionContactDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionUserAccountDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionLastActionTimesDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionSessionDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionTotalDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionTransactionDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionCashValueDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionMethodCardDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionAuthorizationDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionOrderDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionLineItemDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionShipmentDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionCostDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionCbResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionCbAuditTrailDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionCbWarningDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionAgResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'FraudDetectionRdResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'TenpayAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'TenpayAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'TenpayConfirmRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'TenpayConfirmResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'TenpayRefundRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'TenpayRefundResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CvspayAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CvspayAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'CvspayCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'CvspayCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayConfirmRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayConfirmResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayContactDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayCorrectAuthRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayCorrectAuthResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayDeliveryDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayDetailDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayErrorDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayGetInvoiceDataRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayGetInvoiceDataResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'ScoreatpayHoldReasonDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayRefundRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayRefundResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayReAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayReAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayTerminateRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'PaypayTerminateResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AlipayxAuthorizeRequestDto.php'); 
require_once(MDK_DTO_DIR . DS . 'AlipayxAuthorizeResponseDto.php'); 
require_once(MDK_DTO_DIR . DS . 'AlipayxConfirmRequestDto.php'); 
require_once(MDK_DTO_DIR . DS . 'AlipayxConfirmResponseDto.php'); 
require_once(MDK_DTO_DIR . DS . 'AlipayxRefundRequestDto.php'); 
require_once(MDK_DTO_DIR . DS . 'AlipayxRefundResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayGetAddressRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayGetAddressResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayReAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayReAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayRefundRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayRefundResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayTerminateRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayTerminateResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayUpdateAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayUpdateAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayUpdateConsentRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'AmazonpayUpdateConsentResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayCaptureResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayRefundRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayRefundResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayReAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayReAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayTerminateRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'MerpayTerminateResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'EposAuthorizeRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'EposAuthorizeResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'EposCancelRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'EposCancelResponseDto.php');
require_once(MDK_DTO_DIR . DS . 'EposCaptureRequestDto.php');
require_once(MDK_DTO_DIR . DS . 'EposCaptureResponseDto.php');

////  各クラスをロード
//foreach ($lib_includes as $incfile) {
//    require_once(MDK_LIB_DIR . DS . $incfile);
//}
//
////  各DTOクラスをロード
//foreach ($dto_includes as $incfile) {
//    require_once(MDK_DTO_DIR . DS . $incfile);
//}

?>
