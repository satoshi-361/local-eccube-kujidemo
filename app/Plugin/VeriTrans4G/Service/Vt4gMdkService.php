<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Service;

use Plugin\VeriTrans4G\Entity\Vt4gPlugin;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * ベリトランス4G MDKサービス
 *
 */
class Vt4gMdkService
{
    /**
     * コンテナ
     */
    protected $container;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * PluginDataに置かれたMDKのディレクトリや設定ファイルの存在チェックを行います。
     * もし存在しない場合はResourceからコピーして設定を行います。
     *
     * @param boolean $updatePlugin プラグインアップデートフラグ(trueにするとMDKをResourceの中身に置き換え、設定ファイルを再設定します)
     * @param boolean $readRepo 3GPSMDKProperties設定時にテーブルを読み込むかどうか(trueにすると読み込みます)
     */
    public function checkMdk(bool $updatePlugin = false, bool $readRepo = true)
    {
        $fileSystem = new Filesystem();

        $pluginDataPath = $this->container->getParameter('plugin_data_realdir');
        $mdkSrcPath     = $this->container->getParameter('plugin_realdir').'/VeriTrans4G/Resource/tgMdkPHP';
        $mdkDstPath     = $pluginDataPath.'/VeriTrans4G/tgMdkPHP';
        $mdkSettingPath = $mdkDstPath.'/tgMdk/3GPSMDK.properties.setting';
        $mdkPropPath    = $mdkDstPath.'/tgMdk/3GPSMDK.properties';
        $logSettingPath = $mdkDstPath.'/tgMdk/log4php.properties.setting';
        $logPropPath    = $mdkDstPath.'/tgMdk/log4php.properties';

        if(!$fileSystem->exists($pluginDataPath.'/VeriTrans4G')) {
            $fileSystem->mkdir($pluginDataPath.'/VeriTrans4G', 0755);
        }

        if(!$fileSystem->exists($mdkDstPath) || $this->isEmptyDir($mdkDstPath)) {
            $fileSystem->mirror($mdkSrcPath, $mdkDstPath);
        } elseif ($updatePlugin) {
            $fileSystem->remove($mdkDstPath);
            $fileSystem->mirror($mdkSrcPath, $mdkDstPath);
        }

        if(!$fileSystem->exists($mdkPropPath) || $updatePlugin) {
            $fileSystem->copy($mdkSettingPath, $mdkPropPath, true);
            $this->set3GPSMDKProperties($mdkDstPath,$mdkPropPath,[],$readRepo);
        }

        if(!$fileSystem->exists($logPropPath) || $updatePlugin) {
            $fileSystem->copy($logSettingPath, $logPropPath, true);
            $this->setLog4phpProperties($this->container->getParameter('kernel.project_dir'),$logPropPath);
        }

    }

    /**
     * MDK Loggerインスタンスを取得します。
     *
     * @return \TGMDK_Logger logger MDKのロガーインスタンス
     */
    public function getMdkLogger() {
        require_once($this->container->getParameter('plugin_data_realdir') . "/VeriTrans4G/tgMdkPHP/tgMdk/3GPSMDK.php");
        return \TGMDK_Logger::getInstance();
    }

    /**
     * ディレクトリの中が空であるか？
     *
     * @param string $path 調査対象のディレクトリのパス
     * @return boolean true/false 空であればtrue、1つでもファイル、ディレクトリがあればfalse
     */
    private function isEmptyDir(string $path)
    {
        $handle = opendir($path);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
        return true;
    }

    /**
     * 3GPSMDK propertiesの設定を行います。
     *
     * @param string $eccubeDirPath EC-CUBEディレクトリのパス
     * @param string $logPropPath log4php.propertiesのパス
     * @param array $formData プラグイン設定画面入力フォームデータ
     * @param boolean $readRepo 3GPSMDKProperties設定時にテーブルを読み込むかどうか(trueにすると読み込みます)
     */
    public function set3GPSMDKProperties(string $mdkPath, string $mdkPropPath, array $formData = [], bool $readRepo = true)
    {

        $arrPropertiesFile = file($mdkPropPath);
        if ($arrPropertiesFile === false) {
            return;
        }

        if (!empty($formData)) {
            $subData = $formData;
        } else {

            if ($readRepo) {
                $em = $this->container->get('doctrine.orm.entity_manager');
                $ret = $em->getRepository(Vt4gPlugin::class)->getSubData('VeriTrans4G');
            } else {
                $ret = null;
            }

            if (!empty($ret)) {
                $subData = unserialize($ret);
            } else {
                $subData = [
                    "dummy_mode_flg" => "1",
                    "merchant_ccid" => "",
                    "merchant_pass" => "",
                ];
            }
        }

        foreach($arrPropertiesFile as $key => $val) {
            // ダミーモードフラグ
            if (strstr($val,"DUMMY_REQUEST")) {
                if($subData["dummy_mode_flg"] == 1) {
                    $arrPropertiesFile[$key] =
                    "DUMMY_REQUEST                  = " . $subData["dummy_mode_flg"] . "\n";
                } else {
                    $arrPropertiesFile[$key] =
                    "DUMMY_REQUEST                  = 0" . "\n";
                }
            }

            // SSL暗号用 CA証明書ファイル名
            if (strstr($val,"CA_CERT_FILE")) {
                $arrPropertiesFile[$key] =
                "CA_CERT_FILE                   = " . $mdkPath . "/resources/cert.pem". "\n";
            }

            // マーチャントCCID
            if (strstr($val,"MERCHANT_CC_ID")) {
                $arrPropertiesFile[$key] =
                "MERCHANT_CC_ID                 = ". $subData["merchant_ccid"] ."\n";
            }

            // マーチャントパスワード
            if (strstr($val,"MERCHANT_SECRET_KEY")) {
                $arrPropertiesFile[$key] =
                "MERCHANT_SECRET_KEY            = ". $subData["merchant_pass"] ."\n";
            }
        }
        $line = join("", $arrPropertiesFile);
        $file = fopen($mdkPropPath, "w+");
        fwrite($file, $line);

        fclose($file);

    }

    /**
     * log4php propertiesの設定を行います。
     *
     * @param string $eccubeDirPath EC-CUBEディレクトリのパス
     * @param string $logPropPath log4php.propertiesのパス
     */
    private function setLog4phpProperties(string $eccubeDirPath, string $logPropPath)
    {
        // log4php.properties の読み込み
        $arrPropertiesFile = file($logPropPath);
        if ($arrPropertiesFile === false) {
            return;
        }

        foreach ($arrPropertiesFile as $key => $val) {
            if (strstr($val,"log4php.appender.R1.File")) {
                $arrPropertiesFile[$key] =
                "log4php.appender.R1.File=" . $eccubeDirPath ."/var/log/mdk.log". PHP_EOL;
            }
        }
        $line = join("",$arrPropertiesFile);
        $file = fopen($logPropPath, "w+");
        fwrite($file, $line);
        fclose($file);
    }
}