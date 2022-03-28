<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Service\Admin;

use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 支払方法設定画面クラス
 */
class SettingPaymentService
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
     * ユーティリティ
     */
    private $util;

    /**
     * コンストラクタ
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $this->container->getParameter('vt4g_plugin.const');
        $this->util = $container->get('vt4g_plugin.service.util');
    }

    /**
     * 支払方法設定画面下部にベリトランス4G設定項目を追加します。
     * @param TemplateEvent $event
     */
    public function onRenderBefore(TemplateEvent $event)
    {
        // URLにある支払方法IDからベリトランス支払方法情報を取得
        $eventParam = $event->getParameters();

        if (empty($eventParam['payment_id'])) {
            return;
        }

        $Vt4gPayment = $this->em->getRepository(Vt4gPaymentMethod::class)->get($eventParam['payment_id']);

        if (isset($Vt4gPayment)) {
            // 利用するテンプレートのセット
            $payId  = $Vt4gPayment->getMemo03();
            $payCode  = $this->vt4gConst['VT4G_CODE_PAYTYPEID_'.$payId];
            $twig = '@VeriTrans4G/admin/Setting/Shop/payment_'.strtolower($payCode).'.twig';

            // cssの読み込みを追加
            $css = '@VeriTrans4G/default/css/'.$this->vt4gConst['VT4G_PLUGIN_CSS_FILENAME'];
            $event->addAsset($css);

            $payName = $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payId];

            $extension = compact(
                'payName'
            );
            $event->setParameter('vt4g', $extension);

            // addSnippetのリターンにあるform(FormView)で値のセットが可能だった。
            $FormView = $event->addSnippet($twig);
            $viewParams = $FormView->getParameters();

            // フォームに値をセット
            $form = $viewParams['form'];
            $memo05 = $Vt4gPayment->getMemo05();
            $function = "set{$payCode}Data";
            $this->$function($form, $memo05);
        }
    }

    /**
     * 支払方法設定画面の内容をplg_vt4g_payment_methodに登録します。
     * @param EventArgs $event
     */
    public function onEditCompleteAfter(EventArgs $event)
    {
        // URLにある支払方法IDからベリトランス支払方法情報を取得
        $Payment = $event->getArgument('Payment');

        if (empty($Payment->getId())) {
            return;
        }

        $Vt4gPayment = $this->em->getRepository(Vt4gPaymentMethod::class)->get($Payment->getId());

        if (isset($Vt4gPayment)) {
            $formData = $event->getArgument('form')->all();

            $payId  = $Vt4gPayment->getMemo03();
            $payCode  = $this->vt4gConst['VT4G_CODE_PAYTYPEID_'.$payId];

            // plg_vt4g_payment_methodに登録
            $function = "save{$payCode}Data";
            $this->$function($Vt4gPayment, $formData);
            $this->mdkLogger->info(sprintf(
                trans('vt4g_plugin.admin.payment.save.vt4gpaymethod.success'),
                $Payment->getId(),
                $this->vt4gConst['VT4G_PAYNAME_PAYTYPEID_'.$payId]
                ));
        }

    }

    /**
     * クレジット支払の設定項目をセットします。
     * @param object $form
     * @param string $memo05 シリアライズされた設定値
     * @return void
     */
    public function setCreditData($form, $memo05)
    {
        if (isset($memo05)) {
            $data = unserialize($memo05);
            $form['withCapture']->vars['value']         = $data['withCapture'];
            $form['credit_pay_methods']->vars['value']  = $data['credit_pay_methods'];
            $form['security_flg']->vars['value']        = $data['security_flg'];
            $form['mpi_flg']->vars['value']             = $data['mpi_flg'];
            $form['secure_second_flg']->vars['value']   = empty($form['secure_second_flg']->vars['value']) ? $this->vt4gConst['VT4G_FORM']['DEFAULT']['SECURE_SECOND_FLG'] : $data['secure_second_flg'];
            $form['mpi_option']->vars['value']          = $data['mpi_option'];
            $form['one_click_flg']->vars['value']       = $data['one_click_flg'];
            $form['veritrans_id_prefix']->vars['value'] = $data['veritrans_id_prefix'];
            $form['order_mail_title1']->vars['value']   = $data['order_mail_title1'];
            $form['order_mail_body1']->vars['value']    = $this->util->escapeNewLines($data['order_mail_body1']);
            $form['cardinfo_regist_default']->vars['value'] = $data['cardinfo_regist_default'] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['CARDINFO_REGIST_DEFAULT'];
            $form['cardinfo_regist_max']->vars['value'] = $data['cardinfo_regist_max'] ?? $this->vt4gConst['VT4G_FORM']['DEFAULT']['CARDINFO_REGIST_MAX'];
        }
    }

    /**
     * コンビニ支払の設定項目をセットします。
     * @param object $form
     * @param string $memo05 シリアライズされた設定値
     * @return void
     */
    public function setCVSData($form, $memo05)
    {
        if (isset($memo05)) {
            $data = unserialize($memo05);
            $form['conveni']->vars['value']           = $data['conveni'];
            $form['payment_term_day']->vars['value']  = $data['payment_term_day'];
            $form['free1']->vars['value']             = $data['free1'];
            $form['free2']->vars['value']             = $data['free2'];
            $form['order_mail_title1']->vars['value'] = $data['order_mail_title1'];
            $form['order_mail_body1']->vars['value']  = $this->util->escapeNewLines($data['order_mail_body1']);
        }
    }

    /**
     * ネットバンク支払の設定項目をセットします。
     * @param object(FormView) $form
     * @param string(serialize) $memo05
     */
    public function setBankData($form, $memo05)
    {
        if (isset($memo05)) {
            $data = unserialize($memo05);
            $form['payment_term_day']->vars['value']  = $data['payment_term_day'];
            $form['contents']->vars['value']          = $data['contents'];
            $form['contents_kana']->vars['value']     = $data['contents_kana'];
            $form['mailTiming']->vars['value']        = $data['mailTiming'];
            $form['order_mail_title1']->vars['value'] = $data['order_mail_title1'];
            $form['order_mail_body1']->vars['value']  = $this->util->escapeNewLines($data['order_mail_body1']);
        }
    }

    /**
     * ATM支払の設定項目をセットします。
     * @param object(FormView) $form
     * @param string(serialize) $memo05
     */
    public function setATMData($form, $memo05)
    {
        if (isset($memo05)) {
            $data = unserialize($memo05);
            $form['payment_term_day']->vars['value']  = $data['payment_term_day'];
            $form['contents']->vars['value']          = $data['contents'];
            $form['contents_kana']->vars['value']     = $data['contents_kana'];
            $form['order_mail_title1']->vars['value'] = $data['order_mail_title1'];
            $form['order_mail_body1']->vars['value']  = $this->util->escapeNewLines($data['order_mail_body1']);
        }
    }

    /**
     * 銀聯ネット決済の設定項目をセットします。
     * @param object(FormView) $form
     * @param string(serialize) $memo05
     */
    public function setUPOPData($form, $memo05)
    {
        if (isset($memo05)) {
            $data = unserialize($memo05);
            $form['withCapture']->vars['value']       = $data['withCapture'];
            $form['order_mail_title1']->vars['value'] = $data['order_mail_title1'];
            $form['order_mail_body1']->vars['value']  = $this->util->escapeNewLines($data['order_mail_body1']);
        }
    }

    /**
     * Alipay決済の設定項目をセットします。
     * @param object(FormView) $form
     * @param string(serialize) $memo05
     */
    public function setAlipayData($form, $memo05)
    {
        if (isset($memo05)) {
            $data = unserialize($memo05);
            $form['commodity_name']->vars['value']    = $data['commodity_name'];
            $form['refund_reason']->vars['value']     = $data['refund_reason'];
            $form['order_mail_title1']->vars['value'] = $data['order_mail_title1'];
            $form['order_mail_body1']->vars['value']  = $this->util->escapeNewLines($data['order_mail_body1']);
        }
    }

    /**
     * 楽天ペイの設定項目をセットします。
     * @param object(FormView) $form
     * @param string(serialize) $memo05
     */
    public function setRakutenData($form, $memo05)
    {
        if (isset($memo05)) {
            $data = unserialize($memo05);
            $form['withCapture']->vars['value']        = $data['withCapture'];
            $form['item_name']->vars['value']          = $data['item_name'];
            $form['result_mail_target']->vars['value'] = $data['result_mail_target'];
            $form['order_mail_title1']->vars['value']  = $data['order_mail_title1'];
            $form['order_mail_body1']->vars['value']   = $this->util->escapeNewLines($data['order_mail_body1']);
        }
    }

    /**
     * キャリア決済の設定項目をセットします。
     * @param object(FormView) $form
     * @param string(serialize) $memo05
     */
    public function setCarrierData($form, $memo05)
    {
        if (isset($memo05)) {
            $data = unserialize($memo05);
            $form['withCapture']->vars['value']        = $data['withCapture'];
            $form['item_type']->vars['value']          = $data['item_type'];
            $form['result_mail_target']->vars['value'] = $data['result_mail_target'];
            $form['order_mail_title1']->vars['value']  = $data['order_mail_title1'];
            $form['order_mail_body1']->vars['value']   = $this->util->escapeNewLines($data['order_mail_body1']);
        }
    }

    /**
     * リクルートかんたん支払いの設定項目をセットします。
     * @param object(FormView) $form
     * @param string(serialize) $memo05
     */
    public function setRecruitData($form, $memo05)
    {
        if (isset($memo05)) {
            $data = unserialize($memo05);
            $form['withCapture']->vars['value']       = $data['withCapture'];
            $form['item_name']->vars['value']         = $data['item_name'];
            $form['order_mail_title1']->vars['value'] = $data['order_mail_title1'];
            $form['order_mail_body1']->vars['value']  = $this->util->escapeNewLines($data['order_mail_body1']);
        }
    }

    /**
     * LINE Payの設定項目をセットします。
     * @param object(FormView) $form
     * @param string(serialize) $memo05
     */
    public function setLINEPayData($form, $memo05)
    {
        if (isset($memo05)) {
            $data = unserialize($memo05);
            $form['withCapture']->vars['value']       = $data['withCapture'];
            $form['item_name']->vars['value']         = $data['item_name'];
            $form['order_mail_title1']->vars['value'] = $data['order_mail_title1'];
            $form['order_mail_body1']->vars['value']  = $this->util->escapeNewLines($data['order_mail_body1']);
        }
    }

    /**
     * PayPalの設定項目をセットします。
     * @param object(FormView) $form
     * @param string(serialize) $memo05
     */
    public function setPayPalData($form, $memo05)
    {
        if (isset($memo05)) {
            $data = unserialize($memo05);
            $form['withCapture']->vars['value']       = $data['withCapture'];
            $form['order_description']->vars['value'] = $data['order_description'];
            $form['order_mail_title1']->vars['value'] = $data['order_mail_title1'];
            $form['order_mail_body1']->vars['value']  = $this->util->escapeNewLines($data['order_mail_body1']);
        }
    }

    /**
     * クレジット支払の設定項目をplg_vt4g_payment_methodに登録します。
     * @param Vt4gPaymentMethod $Vt4gPayment
     * @param array $formData
     * @return void
     */
    public function saveCreditData($Vt4gPayment, $formData)
    {
        if (isset($formData)) {
            $memo05 = serialize([
                'withCapture'         => $formData['withCapture']->getData(),
                'credit_pay_methods'  => $formData['credit_pay_methods']->getData(),
                'security_flg'        => $formData['security_flg']->getData(),
                'mpi_flg'             => $formData['mpi_flg']->getData(),
                'secure_second_flg'   => $formData['secure_second_flg']->getData(),
                'mpi_option'          => $formData['mpi_option']->getData(),
                'one_click_flg'       => $formData['one_click_flg']->getData(),
                'veritrans_id_prefix' => $formData['veritrans_id_prefix']->getData(),
                'order_mail_title1'   => $formData['order_mail_title1']->getData(),
                'order_mail_body1'    => $formData['order_mail_body1']->getData(),
                'cardinfo_regist_default' => $formData['cardinfo_regist_default']->getData(),
                'cardinfo_regist_max' => $formData['cardinfo_regist_max']->getData(),
            ]);
            $Vt4gPayment->setMemo05($memo05);
            $Vt4gPayment->setUpdateDate(new \DateTime());
            $this->em->persist($Vt4gPayment);
            $this->em->flush();
        }
    }

    /**
     * コンビニ支払の設定項目をplg_vt4g_payment_methodに登録します。
     * @param Vt4gPaymentMethod $Vt4gPayment
     * @param array $formData
     * @return void
     */
    public function saveCVSData($Vt4gPayment, $formData)
    {
        if(isset($formData)){
            $memo05 = serialize([
                'conveni'           => $formData['conveni']->getData() ,
                'payment_term_day'  => $formData['payment_term_day']->getData() ,
                'free1'             => $formData['free1']->getData() ,
                'free2'             => $formData['free2']->getData() ,
                'order_mail_title1' => $formData['order_mail_title1']->getData() ,
                'order_mail_body1'  => $formData['order_mail_body1']->getData(),
            ]);

            $Vt4gPayment->setMemo05($memo05);
            $Vt4gPayment->setUpdateDate(new \DateTime());
            $this->em->persist($Vt4gPayment);
            $this->em->flush();
        }
    }

    /**
     * ネットバンク支払の設定項目をplg_vt4g_payment_methodに登録します。
     * @param Vt4gPaymentMethod $Vt4gPayment
     * @param array $formData
     */
    public function saveBankData($Vt4gPayment, $formData)
    {
        if(isset($formData)){
            $memo05 = serialize([
                        'payment_term_day'  => $formData['payment_term_day']->getData(),
                        'contents'          => $formData['contents']->getData(),
                        'contents_kana'     => $formData['contents_kana']->getData(),
                        'mailTiming'        => $formData['mailTiming']->getData(),
                        'order_mail_title1' => $formData['order_mail_title1']->getData(),
                        'order_mail_body1'  => $formData['order_mail_body1']->getData(),
                        ]);
            $Vt4gPayment->setMemo05($memo05);
            $Vt4gPayment->setUpdateDate(new \DateTime());
            $this->em->persist($Vt4gPayment);
            $this->em->flush();
        }
    }

    /**
     * ATM支払の設定項目をplg_vt4g_payment_methodに登録します。
     * @param Vt4gPaymentMethod $Vt4gPayment
     * @param array $formData
     */
    public function saveATMData($Vt4gPayment, $formData)
    {
        if(isset($formData)){
            $memo05 = serialize([
                        'payment_term_day'  => $formData['payment_term_day']->getData(),
                        'contents'          => $formData['contents']->getData(),
                        'contents_kana'     => $formData['contents_kana']->getData(),
                        'order_mail_title1' => $formData['order_mail_title1']->getData(),
                        'order_mail_body1'  => $formData['order_mail_body1']->getData(),
                        ]);
            $Vt4gPayment->setMemo05($memo05);
            $Vt4gPayment->setUpdateDate(new \DateTime());
            $this->em->persist($Vt4gPayment);
            $this->em->flush();
        }
    }

    /**
     * 銀聯ネット決済の設定項目をplg_vt4g_payment_methodに登録します。
     * @param Vt4gPaymentMethod $Vt4gPayment
     * @param array $formData
     */
    public function saveUPOPData($Vt4gPayment, $formData)
    {
        if(isset($formData)){
            $memo05 = serialize([
                'withCapture'       => $formData['withCapture']->getData(),
                'order_mail_title1' => $formData['order_mail_title1']->getData(),
                'order_mail_body1'  => $formData['order_mail_body1']->getData(),
            ]);
            $Vt4gPayment->setMemo05($memo05);
            $Vt4gPayment->setUpdateDate(new \DateTime());
            $this->em->persist($Vt4gPayment);
            $this->em->flush();
        }
    }

    /**
     * Alipay決済の設定項目をplg_vt4g_payment_methodに登録します。
     * @param Vt4gPaymentMethod $Vt4gPayment
     * @param array $formData
     */
    public function saveAlipayData($Vt4gPayment, $formData)
    {
        if(isset($formData)){
            $memo05 = serialize([
                'commodity_name'    => $formData['commodity_name']->getData(),
                'refund_reason'     => $formData['refund_reason']->getData(),
                'order_mail_title1' => $formData['order_mail_title1']->getData(),
                'order_mail_body1'  => $formData['order_mail_body1']->getData(),
            ]);
            $Vt4gPayment->setMemo05($memo05);
            $Vt4gPayment->setUpdateDate(new \DateTime());
            $this->em->persist($Vt4gPayment);
            $this->em->flush();
        }
    }

    /**
     * 楽天ペイの設定項目をplg_vt4g_payment_methodに登録します。
     * @param Vt4gPaymentMethod $Vt4gPayment
     * @param array $formData
     */
    public function saveRakutenData($Vt4gPayment, $formData)
    {
        if(isset($formData)){
            $memo05 = serialize([
                'withCapture'        => $formData['withCapture']->getData(),
                'item_name'          => $formData['item_name']->getData(),
                'result_mail_target' => $formData['result_mail_target']->getData(),
                'order_mail_title1'  => $formData['order_mail_title1']->getData(),
                'order_mail_body1'   => $formData['order_mail_body1']->getData(),
            ]);
            $Vt4gPayment->setMemo05($memo05);
            $Vt4gPayment->setUpdateDate(new \DateTime());
            $this->em->persist($Vt4gPayment);
            $this->em->flush();
        }
    }

    /**
     * キャリアの設定項目をplg_vt4g_payment_methodに登録します。
     * @param Vt4gPaymentMethod $Vt4gPayment
     * @param array $formData
     */
    public function saveCarrierData($Vt4gPayment, $formData)
    {
        if(isset($formData)){
            $memo05 = serialize([
                'withCapture'        => $formData['withCapture']->getData(),
                'item_type'          => $formData['item_type']->getData(),
                'result_mail_target' => $formData['result_mail_target']->getData(),
                'order_mail_title1'  => $formData['order_mail_title1']->getData(),
                'order_mail_body1'   => $formData['order_mail_body1']->getData(),
            ]);
            $Vt4gPayment->setMemo05($memo05);
            $Vt4gPayment->setUpdateDate(new \DateTime());
            $this->em->persist($Vt4gPayment);
            $this->em->flush();
        }
    }

    /**
     * リクルートかんたん支払いの設定項目をplg_vt4g_payment_methodに登録します。
     * @param Vt4gPaymentMethod $Vt4gPayment
     * @param array $formData
     */
    public function saveRecruitData($Vt4gPayment, $formData)
    {
        if(isset($formData)){
            $memo05 = serialize([
                'withCapture'       => $formData['withCapture']->getData(),
                'item_name'         => $formData['item_name']->getData(),
                'order_mail_title1' => $formData['order_mail_title1']->getData(),
                'order_mail_body1'  => $formData['order_mail_body1']->getData(),
            ]);
            $Vt4gPayment->setMemo05($memo05);
            $Vt4gPayment->setUpdateDate(new \DateTime());
            $this->em->persist($Vt4gPayment);
            $this->em->flush();
        }
    }

    /**
     * LINE Payの設定項目をplg_vt4g_payment_methodに登録します。
     * @param Vt4gPaymentMethod $Vt4gPayment
     * @param array $formData
     */
    public function saveLINEPayData($Vt4gPayment, $formData)
    {
        if(isset($formData)){
            $memo05 = serialize([
                'withCapture'       => $formData['withCapture']->getData(),
                'item_name'         => $formData['item_name']->getData(),
                'order_mail_title1' => $formData['order_mail_title1']->getData(),
                'order_mail_body1'  => $formData['order_mail_body1']->getData(),
            ]);
            $Vt4gPayment->setMemo05($memo05);
            $Vt4gPayment->setUpdateDate(new \DateTime());
            $this->em->persist($Vt4gPayment);
            $this->em->flush();
        }
    }

    /**
     * PAYPALの設定項目をplg_vt4g_payment_methodに登録します。
     * @param Vt4gPaymentMethod $Vt4gPayment
     * @param array $formData
     */
    public function savePayPalData($Vt4gPayment, $formData)
    {
        if(isset($formData)){
            $memo05 = serialize([
                'withCapture'       => $formData['withCapture']->getData(),
                'order_description' => $formData['order_description']->getData(),
                'order_mail_title1' => $formData['order_mail_title1']->getData(),
                'order_mail_body1'  => $formData['order_mail_body1']->getData(),
            ]);
            $Vt4gPayment->setMemo05($memo05);
            $Vt4gPayment->setUpdateDate(new \DateTime());
            $this->em->persist($Vt4gPayment);
            $this->em->flush();
        }
    }
}
