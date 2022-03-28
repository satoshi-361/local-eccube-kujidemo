<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Service;

use Eccube\Entity\BaseInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ベリトランス会員ID処理クラス
 */
class Vt4gAccountIdService
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
     * MDK Logger
     */
    protected $mdkLogger;


    /**
     * コンストラクタ
     *
     * @param  ContainerInterface $container
     * @return void
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->util = $container->get('vt4g_plugin.service.util');
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->vt4gConst = $this->container->getParameter('vt4g_plugin.const');
    }

    /**
     * ベリトランス会員IDに登録済みのカード情報を取得
     *
     * @param  string $accountId ベリトランス会員ID
     * @return array             登録済みカード情報
     */
    public function getAccountCards($accountId)
    {
        $mdkRequest = new \CardInfoGetRequestDto();
        $mdkRequest->setAccountId($accountId);

        $this->mdkLogger->info(trans('vt4g_plugin.account.card.get'));

        $mdkTransaction = new \TGMDK_Transaction();
        $mdkResponse = $mdkTransaction->execute($mdkRequest);

        // レスポンス検証
        if (!isset($mdkResponse)) {
            // システムエラー
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.mdk.error'));
            return [];
        }

        // 異常終了レスポンスの場合
        if ($mdkResponse->getMStatus() === $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['NG']) {
            $this->mdkLogger->fatal(trans('vt4g_plugin.shopping.credit.fatal.mdk'));
            $this->mdkLogger->fatal(print_r($mdkResponse, true));
            return [];
        }

        // レスポンスからカード情報を取得
        $cardInfoList = $mdkResponse->getPayNowIdResponse()->getAccount()->getCardInfo();

        // カード情報が存在しない場合
        if (empty($cardInfoList)) {
            return [];
        }

        // 連想配列に変換
        $cards = array_map(function ($cardInfo) {
            return [
                'cardId'               => $cardInfo->getCardId(),
                'cardNumber'           => preg_replace('/\*+/', '***', $cardInfo->getCardNumber()),
                'registeredCardName'   => $cardInfo->getCardholderName(),
                'expire'               => $cardInfo->getCardExpire(),
                'isDefault'            => $cardInfo->getDefaultCard()
            ];
        }, $cardInfoList);

        // ソート
        usort($cards, function ($card1, $card2) {
            // どちらも標準カードではない場合
            if (!($card1['isDefault'] || $card2['isDefault'])) {
                // 有効期限 YYMMを比較条件として使用
                $comparison1 = intval(substr($card1['expire'], -2).substr($card1['expire'], 0, 2));
                $comparison2 = intval(substr($card2['expire'], -2).substr($card2['expire'], 0, 2));

                // 有効期限が近い順
                return ($comparison1 < $comparison2) ? -1 : 1;
            } else {
                return $card1['isDefault'] ? -1 : 1;
            }

            return 0;
        });

        return $cards;
    }

    /**
     * メッセージを付与した登録済みのカード情報を取得
     * @param  string $accountId ベリトランス会員ID
     * @return array             登録済みカード情報
     */
    public function getAccountCardsWithMsg($accountId)
    {
        $cards = $this->getAccountCards($accountId);

        $date = new \DateTime();
        $currentYM = $date->format('ym');

        foreach ($cards as $key => $card) {
            $expireYM = intval(substr($card['expire'], -2).substr($card['expire'], 0, 2));
            $cards[$key]['alertMsg'] = $currentYM > $expireYM ? trans('vt4g_plugin.account.card.expired') : '';
        }

        return $cards;
    }

    /**
     * ベリトランス会員IDに登録済みのカード情報を削除
     * @param  string $accountId   ベリトランス会員ID
     * @param  string $cardId      カードID
     * @return object $mdkResponse 削除レスポンスデータ
     */
    public function deleteAccountCard($accountId, $cardId)
    {
        $mdkRequest = new \CardInfoDeleteRequestDto();
        $mdkRequest->setAccountId($accountId);
        $mdkRequest->setCardId($cardId);

        $this->mdkLogger->info(trans('vt4g_plugin.account.card.del'));

        $mdkTransaction = new \TGMDK_Transaction();
        $mdkResponse = $mdkTransaction->execute($mdkRequest);

        // レスポンス検証
        if (!isset($mdkResponse)) {
            // システムエラー
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.mdk.error'));
            return null;
        }

        if ($mdkResponse->getMStatus() === $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $this->mdkLogger->info(trans('vt4g_plugin.account.card.del.mdk.success'));
        } else {
            $this->mdkLogger->fatal(trans('vt4g_plugin.account.card.del.mdk.failed'));
            $this->mdkLogger->fatal(print_r($mdkResponse, true));
        }

        return $mdkResponse;
    }

    /**
     * ベリトランス会員IDの削除リクエストを実行
     * @param  string  $accountId ベリトランス会員ID
     * @return boolean true:削除成功/false:削除失敗
     */
    public function deleteVt4gAccountId($accountId)
    {
        $mdkRequest = new \AccountDeleteRequestDto();
        $mdkRequest->setAccountId($accountId);
        $mdkRequest->setDeleteCardInfo(1);

        $this->mdkLogger->info(trans('vt4g_plugin.account.del'));

        $mdkTransaction = new \TGMDK_Transaction();
        $mdkResponse = $mdkTransaction->execute($mdkRequest);

        // レスポンス検証
        if (!isset($mdkResponse)) {
            // システムエラー
            $this->mdkLogger->fatal(trans('vt4g_plugin.payment.shopping.mdk.error'));
            return false;
        }

        if ($mdkResponse->getMStatus() === $this->vt4gConst['VT4G_RESPONSE']['MSTATUS']['OK']) {
            $this->mdkLogger->info(trans('vt4g_plugin.account.del.mdk.success'));
            return true;
        } else {
            $this->mdkLogger->fatal(trans('vt4g_plugin.account.del.mdk.failed'));
            $this->mdkLogger->fatal(print_r($mdkResponse, true));

            $content = $this->container->get('twig')->render(
                "VeriTrans4G/Resource/template/default/Mail/vt4g_withdraw_error.twig",
                [
                    'accountId' => $accountId,
                    'errorCode' => $mdkResponse->getVResultCode(),
                    'errorMessage' => $mdkResponse->getMerrMsg(),
                ],
                'text/html'
                );
            $this->mdkLogger->debug(trans('vt4g_plugin.payment.recv.show.error_mail') .LF. $content);

            // 基本情報取得
            $baseInfo = $this->em->getRepository(BaseInfo::class)->get();

            // メール送信クラス生成
            $message = (new \Swift_Message())
            ->setSubject($this->vt4gConst['VT4G_SERVICE_NAME'] . $this->vt4gConst['VT4G_WITHDRAW_ERROR_MAIL_SUBJECT'])
            ->setFrom([$baseInfo->getEmail03() => $baseInfo->getShopName()])
            ->setTo([$baseInfo->getEmail01()])
            ->setBcc($baseInfo->getEmail01())
            ->setReplyTo($baseInfo->getEmail04())
            ->setReturnPath($baseInfo->getEmail04())
            ->setBody($content);

            $this->container->get('mailer')->send($message);

            $this->mdkLogger->info(trans('vt4g_plugin.account.del.send.error_mail'));
            return false;
        }
    }

}
