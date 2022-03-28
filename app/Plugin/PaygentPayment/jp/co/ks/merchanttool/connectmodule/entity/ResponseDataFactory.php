<?php
/**
 * PAYGENT B2B MODULE
 * ResponseDataFactory.php
 *
 * Copyright (C) 2007 by PAYGENT Co., Ltd.
 * All rights reserved.
 */

namespace Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\entity;

use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\jp\co\ks\merchanttool\connectmodule\system\PaygentB2BModuleResources;

/**
 * 応答電文処理用オブジェクト作成クラス
 *
 * @version $Revision: 15878 $
 * @author $Author: orimoto $
 */
class ResponseDataFactory {

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    public function __construct(EccubeConfig $eccubeConfig) {
        $this->eccubeConfig = $eccubeConfig;
    }

	/**
	 * ResponseData を作成
	 *
	 * @param kind
	 * @return ResponseData
	 */
	public function create($kind) {
		$resData = null;
		$masterFile = null;

		$masterFile = PaygentB2BModuleResources::getInstance($this->eccubeConfig);

		// Create ResponseData
		if ($this->eccubeConfig['paygent_payment']['paygentb2bmodule__telegram_kind_file_payment_res'] == $kind) {
			// ファイル決済結果照会の場合
			$resData = new FilePaymentResponseDataImpl($this->eccubeConfig);
		} elseif ($masterFile->isTelegramKindRef($kind)) {
			// 照会の場合
			$resData = new ReferenceResponseDataImpl($this->eccubeConfig);
		} else {
			// 照会以外の場合
			$resData = new PaymentResponseDataImpl($this->eccubeConfig);
		}

		return $resData;
	}

}

?>