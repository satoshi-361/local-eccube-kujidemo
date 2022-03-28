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

namespace Plugin\PaygentPayment\Service\Paidy;

/**
 * ペイジェント管理画面用のクレジット決済のインスタンスを取得をするInstanceクラス
 */
class PaidyOperationFactory {

    /**
     * @var PaidyOperationCommitService
     */
    private $paidyOperationCommitService;
    /**
     * @var PaidyOperationCancelService
     */
    private $paidyOperationCancelService;
    /**
     * @var PaidyOperationChangeService
     */
    private $paidyOperationChangeService;

    /**
     * コンストラクタ
     * @param PaidyOperationCommitService $paidyOperationCommitService
     * @param PaidyOperationCancelService $paidyOperationCancelService
     * @param PaidyOperationChangeService $paidyOperationChangeService
     */
    public function __construct(
        PaidyOperationCommitService $paidyOperationCommitService,
        PaidyOperationCancelService $paidyOperationCancelService,
        PaidyOperationChangeService $paidyOperationChangeService
    ) {
        $this->paidyOperationCommitService = $paidyOperationCommitService;
        $this->paidyOperationCancelService = $paidyOperationCancelService;
        $this->paidyOperationChangeService = $paidyOperationChangeService;
    }

    /**
     * ペイジェント管理画面表示用の各決済インスタンス取得
     * @param string $paygentType 決済種別
     * @return ペイジェント各決済のインスタンス
     */
    public function getInstance($paygentType)
    {
        switch ($paygentType) {
            case 'paidy_commit':
                return $this->paidyOperationCommitService;
                break;
            case 'paidy_cancel':
                return $this->paidyOperationCancelService;
                break;
            case 'change_paidy':
                return $this->paidyOperationChangeService;
                break;
            default:
                return null;
                break;
        }
    }
}
