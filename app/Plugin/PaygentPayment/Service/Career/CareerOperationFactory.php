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

namespace Plugin\PaygentPayment\Service\Career;

/**
 * 携帯キャリア決済受注管理操作の各操作のインスタンスを取得をするInstanceクラス
 */
class CareerOperationFactory {

    /**
     * @var CareerOperationCommitService
     */
    private $careerOperationCommitService;
    /**
     * @var CareerOperationCancelService
     */
    private $careerOperationCancelService;
    /**
     * @var CareerOperationChangeService
     */
    private $careerOperationChangeService;

    /**
     * コンストラクタ
     * @param CareerOperationCommitService $careerOperationCommitService
     * @param CareerOperationCancelService $careerOperationCancelService
     * @param CareerOperationChangeService $careerOperationChangeService
     */
    public function __construct(
        CareerOperationCommitService $careerOperationCommitService,
        CareerOperationCancelService $careerOperationCancelService,
        CareerOperationChangeService $careerOperationChangeService
    ) {
        $this->careerOperationCommitService = $careerOperationCommitService;
        $this->careerOperationCancelService = $careerOperationCancelService;
        $this->careerOperationChangeService = $careerOperationChangeService;
    }

    /**
     * 携帯キャリア決済受注操作の各操作インスタンス取得
     * @param string $paygentType 決済種別
     * @return ペイジェント各決済のインスタンス
     */
    public function getInstance($paygentType)
    {
        switch ($paygentType) {
            case 'career_commit':
                return $this->careerOperationCommitService;
                break;
            case 'career_commit_cancel':
                return $this->careerOperationCancelService;
                break;
            case 'change_career_auth':
                return $this->careerOperationChangeService;
                break;
            default:
                return null;
                break;
        }
    }
}
