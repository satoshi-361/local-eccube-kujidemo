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

namespace Plugin\PaygentPayment\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\PaygentPayment\Form\Type\Admin\ConfigType;
use Plugin\PaygentPayment\Service\ConfigService;
use Plugin\PaygentPayment\Service\CacheConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Plugin\PaygentPayment\Repository\ConfigRepository;
use Plugin\PaygentPayment\Service\PaygentBaseService;

/**
 * ペイジェント決済プラグインのモジュール設定を行うためのクラス。
 */
class ConfigController extends AbstractController
{
    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var PaygentBaseService
     */
    protected $paygentBaseService;

    /**
     * コンストラクタ
     * @param CacheConfig $config
     * @param ConfigService $configService
     * @param PaygentBaseService $paygentBaseService
     */
    public function __construct(
        ConfigRepository $configRepository,
        ConfigService $configService,
        PaygentBaseService $paygentBaseService
        ) {
        $this->configService = $configService;
        $this->configRepository = $configRepository;
        $this->paygentBaseService = $paygentBaseService;
    }

    /**
     * モジュール設定画面の画面表示クラス
     * @Route("/%eccube_admin_route%/paygent_payment/config", name="paygent_payment_admin_config")
     * @Template("@PaygentPayment/admin/config.twig")
     */
    public function index(Request $request)
    {
        $config = $this->configRepository->get();

        $params = [];

        if ('POST' === $request->getMethod()) {
            $postParam = $request->request->get("config");
            if (isset($postParam['settlement_division'])) {
                $params['connectType'] = $postParam['settlement_division'];
            }
            if (isset($postParam['paygent_payment_method'])) {
                $params['arrPaymentStatus'] = $postParam["paygent_payment_method"];
            }
            if (isset($postParam['module_credit_config']['credit_3d'])) {
                $params['credit3d'] = $postParam['module_credit_config']["credit_3d"];
            }
        }

        $form = $this->createForm(ConfigType::class, $config, $params);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 入力項目 値取得
            $config = $form->getData();

            // 接続試験チェック電文送信
            $ret = $this->configService->inquiry($config);

            // 接続試験に成功したら、設定値の更新
            if ($ret['resultStatus'] === $this->eccubeConfig['paygent_payment']['result_success']) {
                $this->configService->updatePaygentConfigInfo($config);
                $this->configService->updatePaymentInfo($config);
                $this->configService->updatePaymentPage($config);

                $this->addSuccess(trans('paygent_payment.admin.config.save.message'), 'admin');

                return $this->redirectToRoute('paygent_payment_admin_config');
            } else {
                $this->addError(trans('paygent_payment.admin.config.save_error.message', [
                                        '%responseCode%' => $ret["responseCode"], 
                                        '%responseDetail%' => $ret["responseDetail"],
                                    ]), 'admin');
            }
        }

        $arrPaymentClass = $this->getArrPaymentClass();
        $arrSettlementDivisionClass = $this->getSettlementDivisionClass();

        $response = $this->paygentBaseService->setDefaultHeader(new Response());

        $arrReturn = [
            'form' => $form->createView(),
            'arrPaymentClass' => array_flip($arrPaymentClass),
            'arrSettlementDivisionClass' => array_flip($arrSettlementDivisionClass),
        ];

        return $this->render('@PaygentPayment/admin/config.twig', $arrReturn, $response);
    }

    function getArrPaymentClass(){
        return [
            'credit' => $this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_credit'],
            'conveni' => $this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_conveni_num'],
            'atm' => $this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_atm'],
            'bank' => $this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_bank'],
            'career' => $this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_career'],
            'paidy' => $this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_paidy'],
        ];
    }

    function getSettlementDivisionClass(){
        return [
            'module-type' => $this->eccubeConfig['paygent_payment']['settlement_id']['module'],
            'link-type' => $this->eccubeConfig['paygent_payment']['settlement_id']['link'],
        ];
    }
}