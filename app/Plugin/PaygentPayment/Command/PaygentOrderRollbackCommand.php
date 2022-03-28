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

namespace Plugin\PaygentPayment\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Plugin\PaygentPayment\Service\PaygentOrderRollbackService;

class PaygentOrderRollbackCommand extends Command
{
    protected static $defaultName = 'eccube:paygentOrder:rollback';

    /**
     * @var PaygentOrderRollbackService
     */
    protected $paygentOrderRollbackService;

    /**
     * コンストラクタ
     * @param PaygentOrderRollbackService $paygentOrderRollbackService
     */
    public function __construct(
        PaygentOrderRollbackService $paygentOrderRollbackService
    ) {
        parent::__construct();
        $this->paygentOrderRollbackService = $paygentOrderRollbackService;
    }

    protected function configure()
    {
        $this->setDescription('Execute paygent order rollback.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        logs('paygent_payment')->info("Begin paygent order rollback process.");

        // 決済処理中ステータス受注の取得
        $arrOrder = $this->paygentOrderRollbackService->getPendingOrder();

        // 取得した受注のロールバック処理
        foreach($arrOrder as $order){
            $this->paygentOrderRollbackService->rollbackPaygentOrder($order);
        }

        logs('paygent_payment')->info("End paygent order rollback process.");
    }
}
