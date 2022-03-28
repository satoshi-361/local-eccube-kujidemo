<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Form\Type\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Plugin\VeriTrans4G\Controller\Admin\Setting\System\LogDownloadController;

/**
 * システム設定 ログダウンロード選択フォーム
 */
class LogDownloadType extends AbstractType
{

    /**
     * コンテナ
     */
    protected $container;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface   $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * フォーム生成
     *
     * @param  FormBuilderInterface $builder フォームビルダー
     * @param  array                $options フォーム生成に使用するデータ
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $logChoices = $this->getLogChoices();

        $builder
            ->add('log', ChoiceType::class, [
                'choices'     => $logChoices,
                'empty_data' => false,
            ]);
    }

    /**
     * ログの選択肢を取得
     *
     * @return array ログファイル
     */
    private function getLogChoices()
    {
        $logDownloadController = new LogDownloadController($this->container);
        $mdkLogDir = $logDownloadController->getLogDirPath();
        $mdkLogs = [];
        foreach(glob($mdkLogDir.'/*mdk*log*') as $mdkLog) {
            $mdkLogArr = explode("/", $mdkLog);
            $mdkLogFileName = array_pop($mdkLogArr);
            $mdkLogs[$mdkLogFileName] = $mdkLogFileName;
        }
        return $mdkLogs;
    }
}
