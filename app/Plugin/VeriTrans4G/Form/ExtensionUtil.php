<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form;

use Plugin\VeriTrans4G\Entity\Vt4gPaymentMethod;

/**
 * 支払設定画面用フォームの処理用クラス
 */
class ExtensionUtil
{
    /**
     * 決済方法データを取得
     *
     * @param  object           $builder フォームビルダー
     * @param  object           $em      エンティティーマネージャー
     * @return object|null               決済方法データ
     */
    public static function getPaymentMethod($builder, $em)
    {
        $id = $builder->getData()->getId();
        return !empty($id)
            ? $em->getRepository(Vt4gPaymentMethod::class)->get($id)
            : null;
    }

    /**
     * 決済方法データ(getMemo03)が決済方法IDと一致するか
     *
     * @param  object  $Vt4gPaymentMethod 決済方法データ
     * @param  integer $paymentId         決済方法ID
     * @return bool                       true 一致 or false 不一致
     */
    public static function hasPaymentId($Vt4gPaymentMethod, $paymentId)
    {
        return method_exists($Vt4gPaymentMethod, 'getMemo03') && $Vt4gPaymentMethod->getMemo03() == $paymentId
            ? true
            : false;
    }

    /**
     * 決済方法の設定データを取得
     *
     * @param  object  $Vt4gPaymentMethod 決済方法データ
     * @return array|null                 決済方法の設定データ
     */
    public static function getSaveData($Vt4gPaymentMethod)
    {
        $memo05 = $Vt4gPaymentMethod->getMemo05();
        return !empty($memo05)
            ? unserialize($memo05)
            : null;
    }
}
