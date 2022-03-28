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

namespace Plugin\PaygentPayment\Service\Credit;

/**
 * ペイジェント管理画面用のクレジット決済のインスタンスを取得をするInstanceクラス
 */
class CreditOperationFactory {

    /**
     * @var CreditOperationCommitService
     */
    private $creditOperationCommitService;
    /**
     * @var CreditOperationCancelService
     */
    private $creditOperationCancelService;
    /**
     * @var CreditOperationChangeService
     */
    private $creditOperationChangeService;

    /**
     * コンストラクタ
     * @param CreditOperationCommitService $creditOperationCommitService
     * @param CreditOperationCancelService $creditOperationCancelService
     * @param CreditOperationChangeService $creditOperationChangeService
     */
    public function __construct(
        CreditOperationCommitService $creditOperationCommitService,
        CreditOperationCancelService $creditOperationCancelService,
        CreditOperationChangeService $creditOperationChangeService
    ) {
        $this->creditOperationCommitService = $creditOperationCommitService;
        $this->creditOperationCancelService = $creditOperationCancelService;
        $this->creditOperationChangeService = $creditOperationChangeService;
    }

    /**
     * ペイジェント管理画面表示用の各決済インスタンス取得
     * @param string $paymentType 決済種別
     * @return ペイジェント各決済のインスタンス
     */
    public function getInstance($paymentType)
    {
        switch ($paymentType) {
            case 'card_commit':
                return $this->creditOperationCommitService;
                break;
            case 'auth_cancel':
            case 'card_commit_cancel':
                return $this->creditOperationCancelService;
                break;
            case 'change_auth':
            case 'change_commit':
                return $this->creditOperationChangeService;
                break;
            default:
                return null;
                break;
        }
    }
}
