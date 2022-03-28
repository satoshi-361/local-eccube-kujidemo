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
 * ベリトランス会員IDコントローラー
 *
 */
class Vt4gAccountIdController extends AbstractController
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
     * 汎用処理用ユーティリティ
     */
    protected $util;

    /**
     * コンストラクタ
     * @param ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
        $this->util = $container->get('vt4g_plugin.service.util');
    }

    /**
     * マイページのベリトランス会員ID画面アクセス時の処理
     * @Route("/mypage/vt4g_account_id", name="mypage_vt4g_account_id")
     * @param  Request $request     リクエストデータ
     * @return object               ビューレスポンス|レダイレクトレスポンス
     */
    public function mypage(Request $request)
    {
        $customer = $this->getUser();

        // ベリトランス会員IDが未登録の会員が直接アクセスしてきたら404ページを表示する
        if (empty($customer->vt4g_account_id)) {
            $engine = $this->container->get('templating');
            $content = $engine->render('error.twig', [
                'error_title'   => trans('exception.error_title_not_found'),
                'error_message' => trans('exception.error_message_not_found'),
            ]);

            return new Response($content);
        }

        // 登録済みカード情報を取得
        $account = $this->container->get('vt4g_plugin.service.vt4g_account_id');
        $accountCards = $account->getAccountCardsWithMsg($customer->vt4g_account_id);

        $cardMax = $this->util->getCardMax($this->vt4gConst['VT4G_PAYTYPEID_CREDIT']);

        $msg = '';
        if ($this->container->get('session')->has('vt4g_account_id_card_del_msg')) {
            $msg = $this->container->get('session')->get('vt4g_account_id_card_del_msg');
            $this->container->get('session')->remove('vt4g_account_id_card_del_msg');
        }

        return $this->render(
            'VeriTrans4G/Resource/template/default/Mypage/vt4g_account_id.twig',
            [
                'Customer'     => $customer,     // ナビの下のようこそ欄で使う
                'accountCards' => $accountCards,
                'msg'          => $msg,
                'cardMax'     => $cardMax,
            ]
        );
    }

    /**
     * ベリトランス会員IDに登録されたカード情報の削除リクエスト処理
     * @Route("/vt4g_account_id/{cardId}/card_delete", name="vt4g_account_id_card_delete")
     * @param  Request $request     リクエストデータ
     * @param  string  $id          カードID
     * @return object               ビューレスポンス|レダイレクトレスポンス
     */
    public function cardDelete(Request $request, $cardId)
    {
        $this->isTokenValid();

        $customer = $this->getUser();

        // カード情報削除リクエスト実行
        $account = $this->container->get('vt4g_plugin.service.vt4g_account_id');
        $mdkResponse = $account->deleteAccountCard($customer->vt4g_account_id, $cardId);

        // レスポンス異常の場合はエラーページを表示
        if (empty($mdkResponse)) {
            $engine = $this->container->get('templating');
            $content = $engine->render('error.twig', [
                'error_title'   => trans('exception.error_title_can_not_access'),
                'error_message' => trans('vt4g_plugin.account.error.message'),
            ]);

            return new Response($content);
        }

        if ($mdkResponse->getMStatus() === $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $msg = trans('vt4g_plugin.account.card.del.success');
        } else {
            $msg  = trans('vt4g_plugin.account.card.del.failed')."<br>";
            $msg .= $mdkResponse->getMerrMsg().'['.$mdkResponse->getVResultCode().']';
        }

        $this->container->get('session')->set('vt4g_account_id_card_del_msg',$msg);

        return $this->redirectToRoute('mypage_vt4g_account_id');
    }

}
