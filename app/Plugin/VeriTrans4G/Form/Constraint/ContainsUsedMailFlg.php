<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * ご利用状況メールチェックボックス用制約クラス
 *
 * @Annotation
 */
class ContainsUsedMailFlg extends Constraint
{
    public $message = 'if dummy_mode_flg is 0,used_mail_flg is not blank .';

    public function validatedBy()
    {
        return \get_class($this).'Validator';
    }
}