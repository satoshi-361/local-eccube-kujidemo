<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin\Customer;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Customer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * 会員管理登録画面 カード情報追加
 */
class EditController extends AbstractController
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
     * VT用固定値配列
     */
    protected $vt4gConst;

    /**
     * コンストラクタ
     * @param ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $container->getParameter('vt4g_plugin.const');
    }

    /**
     * ベリトランス会員IDに登録されたカード情報の削除リクエスト処理
     * @Route("/%eccube_admin_route%/customer/{customerId}/card_delete/{cardId}", name="vt4g_admin_customer_card_delete")
     * @param  Request $request     リクエストデータ
     * @param  string  $customerId  会員ID
     * @param  string  $cardId      カードID
     * @return object               ビューレスポンス|レダイレクトレスポンス
     */
    public function cardDelete(Request $request, $customerId, $cardId)
    {
        $this->isTokenValid();

        $customer = $this->em->getRepository(Customer::class)->find($customerId);

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
            $this->addSuccess('vt4g_plugin.account.card.del.success', 'admin');
        } else {
            $message = trans('vt4g_plugin.account.card.del.failed');
            $this->addError($message, 'admin');
        }

        return $this->redirectToRoute('admin_customer_edit', [
            "id" => $customerId
        ]);
    }

}
