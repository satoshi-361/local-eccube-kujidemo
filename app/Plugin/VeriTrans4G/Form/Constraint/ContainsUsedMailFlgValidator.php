<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Form\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * ご利用状況メールチェックボックス用バリデーション
 *
 * @Annotation
 */
class ContainsUsedMailFlgValidator extends ConstraintValidator
{
    /**
     * 本番モード指定時にメール送信にチェックがない場合はエラーとします。
     */
    public function validate($value, Constraint $constraint)
    {
        $dummy_mode_flg = $_POST['plugin_config']['dummy_mode_flg'];
        if ( $dummy_mode_flg == '0' && empty($value)) {
            if (is_array($value) && empty($value)) {
                $value = null;
            }
            $this->context->buildViolation('vt4g_plugin.validate.used.mail.flg.not.blank')
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->addViolation();
        }
    }

}
