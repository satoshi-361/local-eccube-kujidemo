<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */

namespace Plugin\VeriTrans4G\Service;

use Eccube\Event\EventArgs;
use Eccube\Service\MailService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * メールのメッセージ編集クラス
 */
class MailMessageService
{

    /**
     * コンテナ
     */
    protected $container;

    /**
     * メールサービス
     */
    protected $mailService;

    /**
     * VT用固定値配列
     */
    protected $vt4gConst;

    /**
     * コンストラクタ
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container, MailService $mailService)
    {
        $this->container = $container;
        $this->vt4gConst = $this->container->getParameter('vt4g_plugin.const');
        $this->mailService = $mailService;

    }

    /**
     * 注文完了メール送信前の処理です。<br>
     * メール本文に決済情報を追加します。
     * @param EventArgs $event
     */
    public function onSendOrderMailBefore(EventArgs $event)
    {
        $util = $this->container->get('vt4g_plugin.service.util');
        $Order = $event->getArgument('Order');

        $OrderPayment = $util->getOrderPayment($Order->getId());

        if (isset($OrderPayment)) {

            $memo06 = $OrderPayment->getMemo06();
            $memo06 = unserialize($memo06);

            $param = ['arrOther' => $memo06];
            $template = $this->vt4gConst['VT4G_CODE'].'/Resource/template/default/Mail/vt4g_order_complete.twig';
            $engine = $this->container->get('twig');
            $vtMessage = $engine->render($template, $param, null);

            $message = $event->getArgument('message');
            $MailTemplate = $event->getArgument('MailTemplate');
            $orderMassage_org = $Order->getMessage();

            $MailService = $this->mailService;
            $htmlFileName = $MailService->getHtmlTemplate($MailTemplate->getFileName());

            // HTML形式テンプレートを使用する場合
            if (!is_null($htmlFileName)) {
                // 注文完了メールに表示するメッセージの改行コードをbrタグに変換して再設定
                $orderMassage = str_replace(["\r\n", "\r", "\n"], "<br/>", $orderMassage_org.$vtMessage);
                $Order->setMessage($orderMassage);

                $htmlBody = $engine->render($htmlFileName, compact('Order'));

                // HTML形式で使われるbodyを再設定
                $beforeBody = $message->getChildren();
                $message->detach($beforeBody[0]);
                $message->addPart(str_replace(["&lt;br/&gt;"], "<br/>", $htmlBody), 'text/html');
            }

            // テキスト形式用に設定
            $Order->setMessage($orderMassage_org.$vtMessage);
            $body = $engine->render($MailTemplate->getFileName(), compact('Order'));
            $message->setBody($body);

            // Orderのmessageを元に戻す
            $Order->setMessage($orderMassage_org);
        }
    }

    /**
     * 管理画面メール通知(注文完了メール)初期処理後の処理です。<br>
     * 問い合わせの下に決済情報を表示するため、messageに決済情報をセットします。
     * @param EventArgs $event
     */
    public function onAdminOrderMailInitAfter(EventArgs $event)
    {
        $util = $this->container->get('vt4g_plugin.service.util');
        $Order = $event->getArgument('Order');

        $OrderPayment = $util->getOrderPayment($Order->getId());

        if (isset($OrderPayment)) {
            $memo06 = $OrderPayment->getMemo06();
            $memo06 = unserialize($memo06);

            $engine = $this->container->get('twig');
            $param = ['arrOther' => $memo06];
            $template = $this->vt4gConst['VT4G_CODE'].'/Resource/template/default/Mail/vt4g_order_complete.twig';

            $message = $engine->render($template, $param, null);
            $orderMassage_org = $Order->getMessage();

            // HTMLエンティティをデコードして設定
            $Order->setMessage($orderMassage_org.$message);
        }
    }

}
