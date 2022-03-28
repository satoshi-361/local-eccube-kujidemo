<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin\Content\Page\Preview\Vt4gPayment;

use Eccube\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * 楽天ボタンプレビュー画面コントローラー
 *
 */
class RakutenButtonController extends AbstractController
{
    /**
     * 楽天ボタンプレビュー画面アクセス時の処理
     * @Route("/%eccube_admin_route%/content/page/preview/vt4g_payment/rakuten_button", name="vt4g_admin_preview_rakuten_button")
     */
    public function index()
    {
        return $this->render('VeriTrans4G/Resource/template/admin/Content/Page/Preview/Vt4gPayment/rakuten_button.twig');
    }
}