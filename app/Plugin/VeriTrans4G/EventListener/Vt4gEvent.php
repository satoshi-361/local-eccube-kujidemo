<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\EventListener;

use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * ベリトランス4Gプラグインのイベントクラス
 * @author develop
 *
 */
class Vt4gEvent implements EventSubscriberInterface
{
    /**
     * コンテナ
     */
    private $container;


    /**
     * コンストラクタ
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * イベントを登録します。
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            '@admin/Setting/Shop/payment_edit.twig' => 'settingShopPaymentRenderBefore',
            '@admin/Setting/Shop/payment.twig' => 'settingShopPaymentAddRegistBtnRenderBefore',
            '@admin/Order/index.twig' => 'orderIndexRenderBefore',
            '@admin/Order/edit.twig' => 'orderEditRenderBefore',
            'Mypage/index.twig' => 'myPageNaviRenderBefore',
            'Mypage/history.twig' => 'myPageNaviRenderBefore',
            'Mypage/favorite.twig' => 'myPageNaviRenderBefore',
            'Mypage/change.twig' => 'myPageNaviRenderBefore',
            'Mypage/change_complete.twig' => 'myPageNaviRenderBefore',
            'Mypage/delivery.twig' => 'myPageNaviRenderBefore',
            'Mypage/delivery_edit.twig' => 'myPageNaviRenderBefore',
            'Mypage/withdraw.twig' => 'myPageNaviRenderBefore',
            'VeriTrans4G/Resource/template/default/Mypage/vt4g_account_id.twig' => 'myPageNaviRenderBefore',
            'VeriTrans4G/Resource/template/default/Mypage/vt4g_subsc_payment.twig' => 'myPageNaviRenderBefore',
            'VeriTrans4G/Resource/template/default/Mypage/vt4g_subsc_payment_history.twig' => 'myPageNaviRenderBefore',
            EccubeEvents::ADMIN_SETTING_SHOP_PAYMENT_EDIT_COMPLETE => 'settingShopPaymentEditComplete',
            EccubeEvents::MAIL_ORDER => 'sendOrderMailBefore',
            EccubeEvents::ADMIN_ORDER_MAIL_INDEX_INITIALIZE => 'adminOrderMailInitAfter',
            EccubeEvents::ADMIN_ORDER_EDIT_INDEX_COMPLETE => 'adminOrderEditIndexComplete',
            'Shopping/index.twig' => 'shoppingIndexRenderBefore',
            'Shopping/confirm.twig' => 'shoppingConfirmRenderBefore',
            EccubeEvents::FRONT_MYPAGE_WITHDRAW_INDEX_COMPLETE => 'frontMypageWithdrawComplete',
            EccubeEvents::ADMIN_CUSTOMER_DELETE_COMPLETE => 'adminCustomerDeleteComplete',
            EccubeEvents::ADMIN_CUSTOMER_EDIT_INDEX_COMPLETE => 'adminCustomerEditIndexComplete',
            '@admin/Customer/edit.twig' => 'adminCustomerEditRenderBefore',
            EccubeEvents::ADMIN_CUSTOMER_CSV_EXPORT => 'adminCustomerCsvExport',
            EccubeEvents::FRONT_MYPAGE_MYPAGE_INDEX_SEARCH => 'frontMypageIndexSearch',
            EccubeEvents::ADMIN_PRODUCT_EDIT_COMPLETE => 'adminProductEditComplete',
            EccubeEvents::ADMIN_PRODUCT_DELETE_COMPLETE => 'adminProductDeleteComplete',
            EccubeEvents::ADMIN_PRODUCT_COPY_COMPLETE => 'adminProductCopyComplete',
            '@admin/Product/product_class.twig' => 'adminProductClassRenderBefore',
            '@admin/Product/product.twig' => 'adminProductRenderBefore',
            'Cart/index.twig' => 'cartRenderBefore',
            EccubeEvents::ADMIN_ORDER_CSV_EXPORT_ORDER => 'adminOrderCsvExportOrder',
        ];
    }

    /**
     * 支払方法設定編集画面表示前のイベント
     * @param  TemplateEvent $event
     * @return void
     */
    public function settingShopPaymentRenderBefore(TemplateEvent $event)
    {
        $this->container->get('vt4g_plugin.service.admin.setting_shop_payment')->onRenderBefore($event);
    }

    /**
     * 支払方法一覧画面表示前のイベント(新規登録ボタン追加)
     * @param  TemplateEvent $event
     * @return void
     */
    public function settingShopPaymentAddRegistBtnRenderBefore(TemplateEvent $event)
    {
        $this->container->get('vt4g_plugin.service.admin.setting_shop_payment_add_regist_btn')->onRenderBefore($event);
    }

    /**
     * 支払方法設定(dtb_payment)登録後のイベント
     * @param  EventArgs $event
     * @return void
     */
    public function settingShopPaymentEditComplete(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.admin.setting_shop_payment')->onEditCompleteAfter($event);
    }

    /**
     * 注文完了メール送信前のイベント
     * @param  EventArgs $event
     * @return void
     */
    public function sendOrderMailBefore(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.mail_message')->onSendOrderMailBefore($event);
    }

    /**
     * 管理画面メール通知(注文完了メール)初期処理後のイベント
     * @param  EventArgs $event
     * @return void
     */
    public function adminOrderMailInitAfter(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.mail_message')->onAdminOrderMailInitAfter($event);
    }

    /**
     * 受注管理一覧画面表示前のイベント
     *
     * @param  TemplateEvent $event
     * @return void
     */
    public function orderIndexRenderBefore(TemplateEvent $event)
    {
        $this->container->get('vt4g_plugin.service.admin.order_index_extension')->onRenderBefore($event);
    }

    /**
     * 受注管理詳細画面表示前のイベント
     *
     * @param  TemplateEvent $event
     * @return void
     */
    public function orderEditRenderBefore(TemplateEvent $event)
    {
        $this->container->get('vt4g_plugin.service.admin.order_edit_extension')->onRenderBefore($event);
    }

    /**
     * 受注管理詳細画面更新時のイベント
     *
     * @param  EventArgs $event
     * @return void
     */
    public function adminOrderEditIndexComplete(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.admin.order_edit_extension')->onEditComplete($event);
    }

    /**
     * マイページ画面表示前のイベント
     * @param  TemplateEvent $event
     * @return void
     */
    public function myPageNaviRenderBefore(TemplateEvent $event)
    {
        $this->container->get('vt4g_plugin.service.front.mypage_extension')->onRenderBefore($event);
    }

    /**
     * ご注文手続き画面表示前のイベント
     * @param  TemplateEvent $event
     * @return void
     */
    public function shoppingIndexRenderBefore(TemplateEvent $event)
    {
        $this->container->get('vt4g_plugin.service.shopping_index_extension')->onRenderBefore($event);
    }

    /**
     * 注文確認画面表示前のイベント
     * @param  TemplateEvent $event
     * @return void
     */
    public function shoppingConfirmRenderBefore(TemplateEvent $event)
    {
        $this->container->get('vt4g_plugin.service.shopping.confirm')->onRenderBefore($event);
    }

    /**
     * マイページ→退会手続き完了後のイベント
     * @param  TemplateEvent $event
     * @return void
     */
    public function frontMypageWithdrawComplete(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.front.mypage.withdraw_extension')->onWithdrawComplete($event);
    }

    /**
     * 管理画面→会員一覧→削除完了後のイベント
     * @param  TemplateEvent $event
     * @return void
     */
    public function adminCustomerDeleteComplete(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.admin.customer_index_extension')->onDeleteComplete($event);
    }

    /**
     * 管理画面→会員登録→登録完了後のイベント
     * @param  TemplateEvent $event
     * @return void
     */
    public function adminCustomerEditIndexComplete(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.admin.customer_edit_extension')->onEditIndexComplete($event);
    }

    /**
     * 管理画面→会員登録画面表示前のイベント
     * @param  TemplateEvent $event
     * @return void
     */
    public function adminCustomerEditRenderBefore(TemplateEvent $event)
    {
        $this->container->get('vt4g_plugin.service.admin.customer_edit_extension')->onRenderBefore($event);
    }

    /**
     * 会員一覧CSVダウンロードのイベント
     * @param  EventArgs $event
     * @return void
     */
    public function adminCustomerCsvExport(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.admin.customer_csv_export_extension')->onCustomerCsvExport($event);
    }

    /**
     * マイページ注文履歴一覧情報取得時のイベント
     * @param  EventArgs $event
     * @return void
     */
    public function frontMypageIndexSearch(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.front.mypage_extension')->onMypageIndexSearch($event);
    }

    /**
     * 商品登録 登録完了後のイベント
     * @param  EventArgs $event
     * @return void
     */
    public function adminProductEditComplete(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.admin.product.subsc_extension')->onAdminProductEditComplete($event);
    }

    /**
     * 商品登録　削除完了後のイベント
     * @param  EventArgs $event
     * @return void
     */
    public function adminProductDeleteComplete(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.admin.product.subsc_extension')->onAdminProductDeleteComplete($event);
    }

    /**
     * 商品一覧　商品情報コピー完了後のイベント
     * @param  EventArgs $event
     * @return void
     */
    public function adminProductCopyComplete(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.admin.product.subsc_extension')->onAdminProductCopyComplete($event);
    }

    /**
     * 商品規格登録　商品規格登録完了後のイベント
     * @param  TemplateEvent $event
     * @return void
     */
    public function adminProductClassRenderBefore(TemplateEvent $event)
    {
        $this->container->get('vt4g_plugin.service.admin.product.subsc_extension')->onAdminProductClassSaveComplete($event);
    }

    /**
     * 商品登録　商品登録画面表示前のイベント
     * @param  TemplateEvent $event
     * @return void
     */
    public function adminProductRenderBefore(TemplateEvent $event)
    {
        $event->addSnippet('@VeriTrans4G/admin/Product/product_order_comp_mail_info.twig');
        $this->container->get('vt4g_plugin.service.admin.product.subsc_extension')->onAdminProductRenderBefore($event);
    }

    /**
     * カート　表示前のイベント
     * @param  TemplateEvent $event
     * @return void
     */
    public function cartRenderBefore(TemplateEvent $event)
    {
        $event->addSnippet('@VeriTrans4G/default/Cart/subsc_product_duplicate_warn.twig');
        $this->container->get('vt4g_plugin.service.cart.index_extension')->onCartRenderBefore($event);
    }

    /**
     * 受注CSVダウンロードのイベント
     * @param  EventArgs $event
     * @return void
     */
    public function adminOrderCsvExportOrder(EventArgs $event)
    {
        $this->container->get('vt4g_plugin.service.admin.customer_csv_export_extension')->adminOrderCsvExportOrder($event);
    }

}
