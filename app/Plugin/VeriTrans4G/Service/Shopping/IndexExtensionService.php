<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Service\Shopping;

use Eccube\Event\TemplateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;
use Plugin\VeriTrans4G\Entity\Master\Vt4gSubscSaleType;
use Symfony\Component\Form\FormError;

/**
 * ご注文手続き画面 拡張用クラス
 */
class IndexExtensionService
{
    /**
     * コンテナ
     */
    protected $container;

    /**
     * エンティティーマネージャー
     */
    protected $em;

    /**
     * 汎用処理用サービス
     */
    protected $util;

    /**
     * VT用固定値配列
     */
    protected $vt4gConst;

    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->em        = $container->get('doctrine.orm.entity_manager');
        $this->util      = $container->get('vt4g_plugin.service.util');
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * ご注文手続き画面 レンダリング時のイベントリスナ
     * @param  TemplateEvent $event イベントデータ
     * @return void
     */
    public function onRenderBefore(TemplateEvent $event)
    {
        $excludePaymentIdList = [];
        //plg_vt4g_payment_methodを取得
        $vt4gPaymentMethods = $this->em->getRepository(Vt4gPaymentMethod::class)->findAll();

        if (!empty($vt4gPaymentMethods)) {
            // 注文商品の販売種別を取得
            $orderSaleTypes = $event->getParameter('Order')->getSaleTypes();
            $curSaleTypeId = isset($orderSaleTypes[0]) ? $orderSaleTypes[0]->getId() : null;
            // $subscSaleTypeがnullでなかったら継続課金商品
            $subscSaleType = $this->em->getRepository(Vt4gSubscSaleType::class)->findOneBy(['sale_type_id' => $curSaleTypeId]);

            //plg_vt4g_pluginのサブデータから有効になっている決済内部IDを取得
            $enablePayIdList = $this->util->getEnablePayIdList();

            // 継続課金対象の注文
            if (isset($subscSaleType)) {
                // 注文の顧客マスタ情報を取得
                $customer = $event->getParameter('Order')->getCustomer();
                // 顧客マスタあり（ベリトランス会員）、かつ、決済内部IDにクレジットカード決済が含まれていたらクレジットカード決済のみ有効
                if (in_array($this->vt4gConst['VT4G_PAYTYPEID_CREDIT'], $enablePayIdList) && isset($customer)) {
                    $paymethodCredits = $this->em->getRepository(Vt4gPaymentMethod::class)->findBy(['memo03' => $this->vt4gConst['VT4G_PAYTYPEID_CREDIT']]);

                    foreach ($paymethodCredits as $paymethodCredit) {
                        $memo5Array = !is_null($paymethodCredit->getMemo05()) ? unserialize($paymethodCredit->getMemo05()) : [];
                        $oneClickFlg = !empty($memo5Array['one_click_flg']) ? $memo5Array['one_click_flg'] : null;

                        if (is_null($oneClickFlg) || $oneClickFlg != $this->vt4gConst['VT4G_CREDIT_ONE_CLICK']['VERITRANS_ID']) {
                            $excludePaymentIdList[] = $paymethodCredit->getPaymentId();
                        } else {
                            $enablePayIdList = [$this->vt4gConst['VT4G_PAYTYPEID_CREDIT']];
                        }
                    }
                } else {
                    // 決済手段なし
                    $enablePayIdList = [];
                }
            }

            //有効な決済内部IDリストに存在しないplg_vt4g_payment_methodのpayment_idを対象外リストに追加
            foreach ($vt4gPaymentMethods as $vt4gPaymentMethod) {
                if (!in_array($vt4gPaymentMethod->getMemo03(), $enablePayIdList)) {
                    $excludePaymentIdList[] = $vt4gPaymentMethod->getPaymentId();
                }
            }
            // 継続課金注文の場合
            if (isset($subscSaleType)) {
                // plg_vt4g_payment_methodに含まれないdtb_paymentの支払方法も対象外リストに追加
                $notExistsVt4gPaymentMethodList = $this->em->getRepository(Vt4gPaymentMethod::class)->getNotExistsVt4gPaymentMethodList();
                foreach ($notExistsVt4gPaymentMethodList as $notExistsVt4gPaymentMethod) {
                    $excludePaymentIdList[] = $notExistsVt4gPaymentMethod['id'];
                }
            }

            //お支払方法のFormViewから対象外リストにあるpayment_idを削除
            $form = $event->getParameter('form');
            $paymentFormViews = $form['Payment'];
            foreach ($paymentFormViews as $key => $paymentFormView){
                if (in_array($paymentFormView->vars['value'],$excludePaymentIdList)) {
                    $paymentFormViews->offsetUnset($key);
                }
            }

            // 有効な支払い方法がない場合
            if ($paymentFormViews->count() == 0) {
                // 「支払い情報を選択」する旨のエラーとなるのでそのメッセージを除去
                $form['Payment']->vars['errors'] = [];
                $form['Payment']->vars['errors'] = [new FormError(trans('front.shopping.payment_method_not_fount'))];
            } else {
                // 有効な支払方法がある場合は1件目の支払方法を選択済みとする
                foreach ($paymentFormViews as $key => $paymentFormView){
                    $paymentFormView->vars['checked'] = true;
                    break;
                }
            }

            $event->setParameter('form', $form);

        }
    }
}
