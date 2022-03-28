<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller;

use Eccube\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * 入金(結果)通知コントローラー
 */
class PaymentRecvController extends AbstractController
{

    /**
     * コンテナ
     */
    protected $container;

    /**
     * VT用固定値配列
     */
    protected $vt4gConst;


    /**
     * コンストラクタ
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * 入金(結果)通知受信時の処理
     * @Route("/shopping/vt4g_payment_recv", name="vt4g_plugin_shopping_payment_recv")
     * @param Request $request
     */
    public function index(Request $request)
    {
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $mdkLogger = $this->container->get('vt4g_plugin.service.vt4g_mdk')->getMdkLogger();
        $mdkLogger->info(trans('vt4g_plugin.payment.recv.start'));

        $paymentRecv = $this->container->get('vt4g_plugin.service.payment_recv');

        // POST値判定
        if ($request->getMethod() !== 'POST') {
            $mdkLogger->warn(trans('vt4g_plugin.payment.recv.non.post'));
            return $this->makeResponse(Response::HTTP_OK);
        }

        // ヘッダーチェック
        if(!$paymentRecv->checkHeader($request->headers->all())) {
            return $this->makeResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // データ取得
        list($arrHead, $arrRecord) = $paymentRecv->getRecord($request->request->all());
        if (empty($arrHead) || empty($arrRecord)){
            $mdkLogger->warn(trans('vt4g_plugin.payment.recv.non.data'));
            return $this->makeResponse(Response::HTTP_OK);
        }

        $mdkLogger->info(sprintf(
                            trans('vt4g_plugin.payment.recv.header.info'),
                            $arrHead['numberOfNotify'],
                            $arrHead['pushTime'],
                            $arrHead['pushId']
                        ));

        // 各レコードの解析と結果情報の反映
        $paymentRecv->saveRecvData($arrRecord);

        if (!empty($paymentRecv->errorMailMsg)){
            $paymentRecv->sendErrorMail($arrHead);
        }

        if (!empty($paymentRecv->rakutenMailMsg)) {
            $paymentRecv->sendRakutenReqMail($arrHead);
        }

        $mdkLogger->info(trans('vt4g_plugin.payment.recv.complete'));
        return $this->makeResponse(Response::HTTP_OK);
    }

    /**
     * 処理終了時のレスポンスを作成します。
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function makeResponse($status)
    {
        return new Response(
            '',
            $status,
            array('Content-Type' => 'text/plain; charset=utf-8')
            );
    }

}
