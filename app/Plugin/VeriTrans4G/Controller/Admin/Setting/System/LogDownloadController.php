<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin\Setting\System;

use Eccube\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Plugin\VeriTrans4G\Form\Type\Admin\LogDownloadType;


/**
 * ログダウンロードコントローラー
 *
 */
class LogDownloadController extends AbstractController
{

    /**
     * コンテナ
     */
    protected $container;

    /**
     * コンストラクタ
     * @param ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * ログダウンロード画面アクセス時の処理
     * @Route("/%eccube_admin_route%/setting/system/vt4g_log_download", name="vt4g_admin_log_download")
     * @param  Request $request     リクエストデータ
     * @return object               ビューレスポンス|レダイレクトレスポンス
     */
    public function index(Request $request)
    {
        $form = $this->formFactory->createBuilder(LogDownloadType::class)->getForm();
        $form->handleRequest($request);

        if ('POST' === $request->getMethod() && $form->isSubmitted() && $form->isValid()) {
            if (!empty($form['log']->getData())) {
                $selectedLog = $this->getLogDirPath() . '/' . $form['log']->getData();

                header('Content-Type: text/plain');
                header('Content-Length: '. filesize($selectedLog));
                header('Content-Disposition: attachment; filename="' . basename($selectedLog) . '"');

                while (ob_get_level()) { ob_end_clean(); }
                readfile($selectedLog);
                return new Response(
                    '',
                    Response::HTTP_OK,
                    ['Content-Type' => 'text/plain; charset=utf-8']
                );
            }
        }
        return $this->render(
            'VeriTrans4G/Resource/template/admin/Setting/System/log_download.twig',
            [
                'form'      => $form->createView(),
            ]
        );
    }

    /**
     * ログが保管されているディレクトリのパスを取得
     * @return string ログのディレクトリのパス
     */
    public function getLogDirPath()
    {
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();

        $mdkLogDir = '';
        $mdkLog4phpProp = $this->container->getParameter('plugin_data_realdir').'/VeriTrans4G/tgMdkPHP/tgMdk/log4php.properties';

        $arrPropertiesFile = file($mdkLog4phpProp);
        if ($arrPropertiesFile === false) {
            return $mdkLogDir;
        }

        foreach ($arrPropertiesFile as $val) {
            if (strpos($val,"log4php.appender.R1.File") !== false) {
                $mdkLogFile = explode("=", $val)[1];
                $mdkLogDirArr = explode("/", $mdkLogFile);
                array_pop($mdkLogDirArr);
                $mdkLogDir = implode("/", $mdkLogDirArr);
            }
        }
        return $mdkLogDir;
    }
}
