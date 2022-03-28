<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Service\Admin;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Csv;
use Eccube\Entity\Layout;
use Eccube\Entity\Page;
use Eccube\Entity\PageLayout;
use Eccube\Entity\Payment;
use Eccube\Entity\Master\CsvType;
use Eccube\Common\Constant;
use Eccube\Service\PluginService;
use Plugin\VeriTrans4G\Entity\Vt4gOrderPayment;
use Plugin\VeriTrans4G\Entity\Vt4gPlugin;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;
use Plugin\VeriTrans4G\Service\Vt4gMdkService;


/**
 * プラグイン設定画面クラス
 */
class PluginConfigService
{

    /**
     * コンテナ
     */
    private $container;

    /**
     * エンティティーマネージャー
     */
    private $em;

    /**
     * VT用固定値配列
     */
    private $vt4gConst;

    /**
     * MDK Logger
     */
    private $mdkLogger;

    /**
     * コンストラクタ
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $mdkService = new Vt4gMdkService($this->container);
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $this->container->getParameter('vt4g_plugin.const');
    }

    /**
     * 設定画面の情報を登録します。
     * @param array $formData
     */
    public function savePaymentData(array $formData)
    {
        // 反映前のベリトランス決済のdtb_payment.idを取得
        $Vt4gPaymentMethod = $this->em->getRepository(Vt4gPaymentMethod::class);
        $Vt4gPaymentMethod->setConst($this->vt4gConst);
        $listId = $Vt4gPaymentMethod->getPaymentIdByPluginCode();
        $installedPaymentId = array();

        foreach ($formData['enable_payment_type'] as $paymentTypeId) {
            // 指定された支払方法内部IDを持つレコードを複数取得
            $Payments = $this->em->getRepository(Vt4gPaymentMethod::class)
            ->getPaymentByTypeId($paymentTypeId);

            //  取得出来た場合、dtb_payment,plg_vt4g_payment_methodに取得分を追加
            if (!empty($Payments)) {
                foreach ($Payments as $Payment) {
                    $Payment = $this->savePayment($paymentTypeId, $Payment);
                    $this->saveVt4gPaymentMethod($paymentTypeId, $Payment);
                    $installedPaymentId[] = $Payment->getId();
                }
            //  取得出来ない場合(VT用決済の初登録時)、dtb_payment,plg_vt4g_payment_methodに新規で1つ追加
            } else {
                $Payment = NULL;
                $Payment = $this->savePayment($paymentTypeId, $Payment);
                $this->saveVt4gPaymentMethod($paymentTypeId, $Payment);
                $installedPaymentId[] = $Payment->getId();
            }

        }

        if (!empty($listId)) {
            foreach ((array)$listId as $paymentId) {
                if (!in_array($paymentId["id"], $installedPaymentId)) {
                    $this->unvisiblePayment($paymentId["id"]);
                }
            }
        }

        // plg_vt4g_pluginの更新とご利用状況メール送信
        $this->saveVt4gPlugin($formData);

        // ページ・ページレイアウトの登録
        $this->registerPageLayout($this->vt4gConst['VT4G_PAYMENT']['URL']);
        $this->registerPageLayout($this->vt4gConst['VT4G_MYPAGE']['URL']);
        $this->registerPageLayout($this->vt4gConst['VT4G_RAKUTEN_BUTTON']['URL']);
        $this->registerPageLayout($this->vt4gConst['VT4G_MYPAGE_SUBSC_PAYMENT']['URL']);
        $this->registerPageLayout($this->vt4gConst['VT4G_MYPAGE_SUBSC_PAYMENT_HISTORY']['URL']);

        // CSV項目の登録
        $this->registerCustomerCsv($this->vt4gConst['VT4G_DTB_CSV']['CUSTOMER']);
        $this->registerOrderCsv($this->vt4gConst['VT4G_DTB_CSV']['ORDER']);
    }

    /**
     * サブデータを取得します。
     *
     * @return array|null
     */
    public function getSubData()
    {
        $ret = $this->em->getRepository(Vt4gPlugin::class)
                ->getSubData($this->vt4gConst['VT4G_CODE']);

        if (!empty($ret)) {
            return unserialize($ret);
        }
        return null;
    }

    /**
     * plg_vt4g_pluginにサブデータを登録します。<br />
     * 本番モードかつマーチャントCCIDの変更または
     * 送信済みマーチャントCCIDが空(ご利用状況メール未送信)の場合はメール送信も行います。
     *
     * @param array $formData
     */
    public function saveVt4gPlugin(array $formData)
    {
        $vt4gPlugin = $this->em->getRepository(Vt4gPlugin::class)
                        ->findOneBy(array('plugin_code' => $this->vt4gConst['VT4G_CODE']));

        $subData = $vt4gPlugin->getSubData();
        $formData['send_mail_date'] = null;
        if (!empty($subData)) {
            $subData = unserialize($subData);
            $formData['send_mail_date'] = $subData['send_mail_date'];
        }
        $sendMerchantCcid = $subData['send_merchant_ccid'] ?? [];
        if ($formData['dummy_mode_flg'] == 0
            && !in_array($formData['merchant_ccid'], $sendMerchantCcid)
        ) {
            $isSubmit = true;
            foreach ($this->vt4gConst['VT4G_MERCHANT_CCID']['EXCLUDE'] as $excludeId) {
                // merchant_ccid 末尾n桁を取得
                $substrMerchantId = substr($formData['merchant_ccid'], -strlen($excludeId));
                // テスト用merchant_ccidでメール送信をしない
                if ($substrMerchantId == $excludeId) {
                    $isSubmit = false;
                    break;
                }
            }
            if ($isSubmit) {
                $this->sendUsedMail($formData['merchant_ccid']);
                $formData['send_mail_date'] = date('Y/m/d');
                // 現在と変更前のマーチャントCCIDをログに保存
                array_unshift($sendMerchantCcid, $formData['merchant_ccid']);
                $formData['send_merchant_ccid'] = $sendMerchantCcid;
            }
        }

        $subData = serialize($formData);
        if (!is_null($vt4gPlugin)) {
            $vt4gPlugin->setSubData($subData);
            $this->em->persist($vt4gPlugin);
            $this->em->flush($vt4gPlugin);

            $this->mdkLogger->info(trans('vt4g_plugin.admin.config.save.subdata.success'));
        }
    }

    /**
     * dtb_paymentの追加または更新を行います。
     *
     * @param int $paymentTypeId 支払方法内部ID
     * @param Payment $Payment 支払方法
     * @return Payment $Payment データ登録後の支払方法
     */
    public function savePayment(int $paymentTypeId, $Payment)
    {

        $name = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$paymentTypeId] ?? '設定なし';

        if (is_null($Payment)) {
            $registeredPayment = $this->em->getRepository(Payment::class)
                                    ->findOneBy([], ['sort_no' => 'DESC']);

            $sortNo = $registeredPayment ? $registeredPayment->getSortNo() + 1 : 1;
            $Payment = new Payment();
            $Payment->setSortNo($sortNo);
            $Payment->setCharge(0);
            $Payment->setMethod($name);
            $Payment->setRuleMin($this->vt4gConst['RULE_MIN_PAYTYPEID_'.$paymentTypeId]);
            $Payment->setRuleMax($this->vt4gConst['RULE_MAX_PAYTYPEID_'.$paymentTypeId]);
        }

        $Payment->setVisible(true);
        $Payment->setMethodClass('Plugin\\VeriTrans4G\\Service\Method\\'.$this->vt4gConst['VT4G_METHOD_COMMON']);

        $this->em->persist($Payment);
        $this->em->flush();

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.config.save.payment.success'),$Payment->getId(),$name));

        return $Payment;

    }

    /**
     * plg_vt4g_payment_methodの追加または更新を行います。
     *
     * @param int $paymentTypeId 支払方法内部ID
     * @param Payment $Payment 支払方法
     */
    public function saveVt4gPaymentMethod(int $paymentTypeId, Payment $Payment)
    {
        $id = $Payment->getId();

        $Vt4gPayment = $this->em->getRepository(Vt4gPaymentMethod::class)->get($id);

        if (is_null($Vt4gPayment)) {
            $Vt4gPayment = new Vt4gPaymentMethod();
            $Vt4gPayment->setCreateDate(new \DateTime());
        }

        $name = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$paymentTypeId] ?? '設定なし';

        $Vt4gPayment->setPayment($Payment);
        $Vt4gPayment->setPaymentId($id);
        $Vt4gPayment->setPaymentMethod($name);
        $Vt4gPayment->setDelFlg(0);
        $Vt4gPayment->setUpdateDate(new \DateTime());
        $Vt4gPayment->setMemo03($paymentTypeId);
        $Vt4gPayment->setPluginCode($this->vt4gConst['VT4G_CODE']);
        $this->em->persist($Vt4gPayment);
        $this->em->flush();

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.config.save.vt4gpaymethod.success'),$id,$name));
    }

    /**
     * dtb_paymentの表示フラグをオフにします。
     *
     * @param int $id
     */
    public function unvisiblePayment(int $id)
    {
        $Payment = $this->em->getRepository(Payment::class)->find($id);
        $Payment->setVisible(false);
        $this->em->persist($Payment);
        $this->em->flush();

        $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.config.unvisible.paymet.success'),$id));
    }

    /**
     *ご利用状況メール情報を取得します。
     *
     * @return array $usedMail
     */
    public function getUsedMailData()
    {
        $baseInfo = $this->em->getRepository(BaseInfo::class)->get();

        $vt4gPlugin = $this->em->getRepository(Vt4gPlugin::class)
        ->findOneBy(array('plugin_code' => $this->vt4gConst['VT4G_CODE']));

        $subData = $vt4gPlugin->getSubData();
        if (!empty($subData)) {
            $subData = unserialize($subData);
        }

        return [
            'to' => $this->vt4gConst['VT4G_USED_MAIL_TO'],
            'cc' => $baseInfo->getEmail01(),
            'send_date' => !empty($subData['send_mail_date'])
                            ? $subData['send_mail_date']
                            : null,
        ];
    }

    /**
     * プラグイン利用状況メールを送信します。
     *
     * @param string $merchant_ccid
     */
    public function sendUsedMail(string $merchant_ccid)
    {
        $baseInfo = $this->em->getRepository(BaseInfo::class)->get();

        $mailer = $this->container->get('mailer');
        $render = $this->container->get('twig');

        $pluginService = $this->container->get(PluginService::class);
        $pluginDir = $pluginService->calcPluginDir($this->vt4gConst['VT4G_CODE']);
        $config = $pluginService->readConfig($pluginDir);

        $message = (new \Swift_Message())
        ->setSubject($this->vt4gConst['VT4G_USED_MAIL_SUBJECT'])
        ->setFrom([$baseInfo->getEmail01() => $baseInfo->getShopName()])
        ->setTo([$this->vt4gConst['VT4G_USED_MAIL_TO']])
        ->setBcc($baseInfo->getEmail01())
        ->setReplyTo($baseInfo->getEmail03())
        ->setReturnPath($baseInfo->getEmail04())
        ->setBody(
            $render->render(
                "VeriTrans4G/Resource/template/mail/used_mail.twig",
                [
                    'shop_name' => $baseInfo->getShopName(),
                    'eccube_version' => Constant::VERSION,
                    'module_version' => $config['version'],
                    'merchant_ccid' => $merchant_ccid
                ],
            'text/html'
            ));

        $failures = '';
        $cnt = $mailer->send($message, $failures);
        if ($cnt <> 0) {
            $this->mdkLogger->info(trans('vt4g_plugin.admin.config.used.mail.success'));
        } else {
            $this->mdkLogger->info(trans('vt4g_plugin.admin.config.used.mail.failed').$failures);
        }
    }

    /**
     * 3GPSMDK.propertiesの設定値を書き換えます。
     * @param  array $formData
     */
    public function setMdkProperties(array $formData)
    {
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkPath = $this->container->getParameter('plugin_data_realdir').'/VeriTrans4G/tgMdkPHP';
        $mdkPropPath = $mdkPath.'/tgMdk/3GPSMDK.properties';

        $mdkService->set3GPSMDKProperties($mdkPath, $mdkPropPath, $formData);
    }

    /**
     * ページ・ページレイアウトの追加
     * @param  array $urlConst  ページ追加用配列
     * @param  bool  $forUpdate プラグインアップデート用処理フラグ
     * @return void
     */
    public function registerPageLayout(array $urlConst, bool $forUpdate = false)
    {
        $url = $urlConst['INDEX']['NAME'];

        // ページ設定
        $page = $this->em->getRepository(Page::class)->findOneBy(compact('url'));
        // 存在しない場合は新規作成
        if (is_null($page)) {
            $page = new Page;
        }

        $page->setName($urlConst['INDEX']['LABEL']);
        $page->setUrl($url);
        $page->setMetaRobots('noindex');
        $page->setFileName($urlConst['INDEX']['TEMPLATE']);
        $page->setEditType(Page::EDIT_TYPE_DEFAULT);

        $this->em->persist($page);
        $this->em->flush();

        // ページレイアウト設定
        $pageLayoutRepository = $this->em->getRepository(PageLayout::class);
        $pageLayout = $pageLayoutRepository->findOneBy([
            'page_id' => $page->getId()
        ]);
        // 存在しない場合は新規作成
        if (is_null($pageLayout)) {
            $pageLayout = new PageLayout;
            // 存在するレコードで一番大きいソート番号を取得
            $lastSortNo = $pageLayoutRepository->findOneBy([], ['sort_no' => 'desc'])->getSortNo();
            // ソート番号は新規作成時のみ設定
            $pageLayout->setSortNo($lastSortNo+1);
        }
        // 下層ページ用レイアウトを取得
        $layout = $this->em->getRepository(Layout::class)->find(Layout::DEFAULT_LAYOUT_UNDERLAYER_PAGE);

        $pageLayout->setPage($page);
        $pageLayout->setPageId($page->getId());
        $pageLayout->setLayout($layout);
        $pageLayout->setLayoutId($layout->getId());

        $this->em->persist($pageLayout);
        $this->em->flush();

        // プラグイン無効化状態でアップデートするとメッセージ定義が読み込めないのでアップデート時はスキップする
        if(!$forUpdate) {
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.config.save.pagedata'),$urlConst['INDEX']['LABEL']));
        }
    }

    /**
     * dtb_csvへ会員CSV情報を登録
     * @param  array $csvConst  dtb_csv追加用配列
     * @param  bool  $forUpdate プラグインアップデート用処理フラグ
     * @return void
     */
    public function registerCustomerCsv(array $csvConst, bool $forUpdate = false)
    {
        $csv = $this->em->getRepository(Csv::class)->findOneBy(
            [
                'CsvType' => CsvType::CSV_TYPE_CUSTOMER,
                'field_name' => $csvConst['VT4G_ACCOUNT_ID']['FIELD_NAME']
            ]);

        if(is_null($csv)) {
            $lastSortNoCsv = $this->em->getRepository(Csv::class)->findOneBy(
                ['CsvType' => CsvType::CSV_TYPE_CUSTOMER],
                ['sort_no' => 'DESC']
            );

            $csv = new Csv();
            $csv->setCsvType($lastSortNoCsv->getCsvType())
                ->setEntityName($lastSortNoCsv->getEntityName())
                ->setDispName($csvConst['VT4G_ACCOUNT_ID']['DISP_NAME'])
                ->setFieldName($csvConst['VT4G_ACCOUNT_ID']['FIELD_NAME'])
                ->setEnabled(true)
                ->setSortNo($lastSortNoCsv->getSortNo() + 1);
        }

        $this->em->persist($csv);
        $this->em->flush($csv);

        if(!$forUpdate) {
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.config.save.csvdata'), $csvConst['VT4G_ACCOUNT_ID']['FIELD_NAME']));
        }
    }


    /**
     * dtb_csvへ受注CSV情報を登録
     * @param  array $csvConst  dtb_csv追加用配列
     * @param  bool  $forUpdate プラグインアップデート用処理フラグ
     * @return void
     */
    public function registerOrderCsv(array $csvConst, bool $forUpdate = false)
    {
        $csv = $this->em->getRepository(Csv::class)->findOneBy(
            [
                'CsvType' => CsvType::CSV_TYPE_ORDER,
                'field_name' => $csvConst['VT4G_PAYMENT_STATUS']['FIELD_NAME']
            ]);

        if(is_null($csv)) {
            $lastSortNoCsv = $this->em->getRepository(Csv::class)->findOneBy(
                ['CsvType' => CsvType::CSV_TYPE_ORDER],
                ['sort_no' => 'DESC']
                );

            $csv = new Csv();
            $csv->setCsvType($lastSortNoCsv->getCsvType())
            ->setEntityName(Vt4gOrderPayment::class)
            ->setDispName($csvConst['VT4G_PAYMENT_STATUS']['DISP_NAME'])
            ->setFieldName($csvConst['VT4G_PAYMENT_STATUS']['FIELD_NAME'])
            ->setEnabled(true)
            ->setSortNo($lastSortNoCsv->getSortNo() + 1);
        }

        $this->em->persist($csv);
        $this->em->flush($csv);

        if(!$forUpdate) {
            $this->mdkLogger->info(sprintf(trans('vt4g_plugin.admin.config.save.csvdata'), $csvConst['VT4G_PAYMENT_STATUS']['FIELD_NAME']));
        }
    }


}
