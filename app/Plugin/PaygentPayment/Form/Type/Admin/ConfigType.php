<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright (c) 2006 PAYGENT Co.,Ltd. All rights reserved.
 *
 * https://www.paygent.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Plugin\PaygentPayment\Form\Type\Admin;

use Eccube\Common\EccubeConfig;
use Plugin\PaygentPayment\Entity\Config;
use Plugin\PaygentPayment\Form\Type\Admin\LinkCreditConfigType;
use Plugin\PaygentPayment\Form\Type\Admin\ModuleAtmConfigType;
use Plugin\PaygentPayment\Form\Type\Admin\ModuleBankConfigType;
use Plugin\PaygentPayment\Form\Type\Admin\ModuleCareerConfigType;
use Plugin\PaygentPayment\Form\Type\Admin\ModuleConveniConfigType;
use Plugin\PaygentPayment\Form\Type\Admin\ModuleCreditConfigType;
use Plugin\PaygentPayment\Form\Type\Admin\ModulePaidyConfigType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormEvent;

class ConfigType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * ConfigType constructor.
     * 
     * @param EccubeConfig $eccubeConfig
     * @param ValidatorInterface $validator
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        ValidatorInterface $validator
        ) {
            $this->eccubeConfig = $eccubeConfig;
            $this->validator = $validator;
    }
    /**
     * ConfigType buildForm.
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $arrPaymentStatus = $options["arrPaymentStatus"];
        $connectType = $options['connectType'];
        $isCredit3d = $options['credit3d'];

        $arrSettlementsKeys = array_keys($this->getSettlements());
        if (!in_array((int)$connectType, $arrSettlementsKeys, true)) {
            $connectType = null;
        }

        $builder = $this->configBuildForm($builder);

        $builder = $this->moduleTypeConfigBuildForm($builder, $connectType, $arrPaymentStatus, $isCredit3d);
        $builder = $this->linkTypeConfigBuildForm($builder, $connectType, $arrPaymentStatus);

        return $builder;
    }

    public function configBuildForm($builder)
    {
        $arrPayments = array_flip($this->getPayments());
        $arrSettlements = array_flip($this->getSettlements());

        $builder->add('settlement_division', ChoiceType::class, [
            'choices' => $arrSettlements,
            'expanded' => true,
            'constraints' => [
                    new NotBlank(),
            ],
        ]);
        $builder->add('merchant_id', TextType::class, [
            'constraints' => [
                new NotBlank(['message' => '※ マーチャントIDが入力されていません。']),
                new Regex([
                    'pattern' => "/^[0-9]+$/",
                    'message' => '※ マーチャントIDは数字で入力してください。'
                ]),
                new Length([
                    'max' => $this->eccubeConfig['eccube_int_len'],
                    'maxMessage' => '※ マーチャントIDは' . $this->eccubeConfig['eccube_int_len'] . '字以下で入力してください。',
                    ]),
            ],
        ]);
        $builder->add('connect_id', TextType::class, [
            'constraints' => [
                new NotBlank(['message' => '※ 接続IDが入力されていません。']),
                new Regex([
                    'pattern' => "/^[A-z0-9]+$/",
                    'message' => '※ 接続IDは英数字で入力してください。'
                ]),
                new Length([
                    'max' => 32,
                    'maxMessage' => '※ 接続IDは32字以下で入力してください。',
                    ]),
            ],
        ]);
        $builder->add('connect_password', PasswordType::class, [
            'constraints' => [
                new NotBlank(['message' => '※ 接続パスワードが入力されていません。']),
                new Regex([
                    'pattern' => "/^[A-z0-9]+$/",
                    'message' => '※ 接続パスワードは英数字で入力してください。'
                ]),
                new Length([
                    'max' => $this->eccubeConfig['eccube_password_max_len'],
                    'maxMessage' => '※ 接続パスワードは' . $this->eccubeConfig['eccube_password_max_len'] . '字以下で入力してください。',
                    ]),
            ],
        ]);
        $builder->add('notice_hash_key', TextType::class, [
            'constraints' => [
                new NotBlank(),
                new Regex([
                    'pattern' => "/^[A-z0-9]+$/",
                    'message' => '※ 差分通知ハッシュ値生成キーは英数字で入力してください。'
                ]),
                new Length([
                    'max' => 16,
                    'maxMessage' => '※ 差分通知ハッシュ値生成キーは16字以下で入力してください。',
                    ]),
            ],
        ]);
        $builder->add('paygent_payment_method', ChoiceType::class, [
            'choices' => $arrPayments,
            'expanded' => true,
            'multiple' => true,
            'constraints' => [
                new NotBlank(['message' => '※ 利用決済が入力されていません。']),
            ],
        ]);

        $builder = $this->rollbackConfigBuildForm($builder);

        return $builder;
    }

    public function linkTypeConfigBuildForm($builder, $connectType, $arrPaymentStatus)
    {
        $isLink = $connectType == $this->eccubeConfig['paygent_payment']['settlement_id']['link'];

        $isCredit = in_array($this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_credit'], $arrPaymentStatus);

        $builder->add('link_config', LinkConfigType::class, ["isLink" => $isLink]);
        $builder->add('link_credit_config', LinkCreditConfigType::class, ["isLink" => $isLink, "isCredit" => $isCredit]);

        return $builder;
    }

    public function moduleTypeConfigBuildForm($builder, $connectType, $arrPaymentStatus, $isCredit3d = false)
    {
        $isModule = $connectType == $this->eccubeConfig['paygent_payment']['settlement_id']['module'];

        $isCredit = in_array($this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_credit'], $arrPaymentStatus);
        $isConveni = in_array($this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_conveni_num'], $arrPaymentStatus);
        $isAtm = in_array($this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_atm'], $arrPaymentStatus);
        $isBank = in_array($this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_bank'], $arrPaymentStatus);
        $isCareer = in_array($this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_career'], $arrPaymentStatus);
        $isPaidy = in_array($this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_paidy'], $arrPaymentStatus);

        $builder->add('module_credit_config', ModuleCreditConfigType::class, ["isModule" => $isModule, "isCredit3d" => $isCredit3d, "isCredit" => $isCredit]);
        $builder->add('module_conveni_config', ModuleConveniConfigType::class, ["isModule" => $isModule, "isConveni" => $isConveni]);
        $builder->add('module_atm_config', ModuleAtmConfigType::class, ["isModule" => $isModule, "isAtm" => $isAtm]);
        $builder->add('module_bank_config', ModuleBankConfigType::class, ["isModule" => $isModule, "isBank" => $isBank]);
        $builder->add('module_career_config', ModuleCareerConfigType::class, ["isModule" => $isModule, "isCareer" => $isCareer]);
        $builder->add('module_paidy_config', ModulePaidyConfigType::class, ["isModule" => $isModule, "isPaidy" => $isPaidy]);
        // 子フォームだと親がsubmitされる前に実行されるためConfigTypeで実施
        $builder->addEventListener(FormEvents::POST_SUBMIT, [$this, 'checkAdditionalValidation']);

        return $builder;
    }

    public function rollbackConfigBuildForm($builder)
    {
        $builder->add('rollback_target_term', TextType::class, [
            'attr' => [
                'style' => 'width:50px',
                'maxlength' => '2',
            ],
            'constraints' => [
                new NotBlank(['message' => '※ 決済処理中の注文の取消期間が入力されていません。']),
                new Regex([
                    'pattern' => "/^[0-9]+$/",
                    'message' => '※ 決済処理中の注文の取消期間は数字で入力してください。'
                ]),
                new Length(['max' => $this->eccubeConfig['eccube_int_len']]),
            ],
        ]);
        $builder->addEventListener(FormEvents::POST_SUBMIT, function ($event) {
            $form = $event->getForm();
            $data = $form->getData();

            $connectType = $data->getSettlementDivision();
            $arrPaymentStatus = isset($_POST['config']["paygent_payment_method"]) ? $_POST['config']["paygent_payment_method"] : [];

            $rollbackTargetTerm = $data->getRollbackTargetTerm();

            $isModule = $connectType == $this->eccubeConfig['paygent_payment']['settlement_id']['module'];
            $isLink = $connectType == $this->eccubeConfig['paygent_payment']['settlement_id']['link'];

            $isBank = in_array($this->eccubeConfig['paygent_payment']['payment_type_id']['pay_paygent_bank'], $arrPaymentStatus);

            if (isset($rollbackTargetTerm)) {
                $errors = $this->validator->validate($rollbackTargetTerm, [
                    new GreaterThanOrEqual([
                        'value' => '0',
                        'message' => '※ 決済処理中の注文の取消期間は0以上の数値を入力して下さい。',
                    ]),
                ]);

                if ($errors->count() > 0) {
                    foreach ($errors as $error) {
                        $form['rollback_target_term']->addError(new FormError($error->getMessage()));
                    }
                }

                $message = null;
                $value = null;

                if ($isLink) {
                    $message = "※ 支払期限前の注文が取り消されるため、支払期限日より小さい日数は入力できません。";
                    $value = $data->getLinkPaymentTerm();
                } elseif ($isModule && $isBank) {
                    $message = "※ 支払期限前の注文が取り消されるため、銀行ネット決済の支払期限日より小さい日数は入力できません。";
                    $value = $data->getAspPaymentTerm();
                }

                if ($message && $value) {
                    $errors = $this->validator->validate($rollbackTargetTerm, [
                        new GreaterThanOrEqual([
                            'value' => $value,
                            'message' => $message,
                        ]),
                    ]);

                    if ($errors->count() > 0) {
                        foreach ($errors as $error) {
                            $form['rollback_target_term']->addError(new FormError($error->getMessage()));
                        }
                    }
                }
            }
        });

        return $builder;
    }

    /**
     * 無効な設定項目に値を設定する
     *
     * @param $builder
     * @param boolean $isValidItem 有効な設定項目か
     * @return $builder
     */
    protected function setDataForInvalidItem($builder, $isValidItem)
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function(FormEvent $event) use ($isValidItem) {
            $form = $event->getForm();
            $requestData = $event->getData();

            // リクエストに設定されていない項目はnullで保存されるため設定する
            // 利用決済にチェックを入れていない決済設定ではHTMLを編集してinput要素のdisabledを外すとバリデーションが実行されずに保存できるため元のデータで上書きする
            // 決済内の無効設定に関しては各決済のクラスで実施する
            foreach ($form->all() as $key => $child) {
                // 無効な決済設定項目の時更新前の値(初期値)を設定する
                if (!$isValidItem) {
                    $requestData[$key] = $child->getData();
                }
            }

            $event->setData($requestData);
        });

        return $builder;
    }

    /**
     * ConfigType configureOptions.
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'connectType' => null,
            'credit3d' => false,
            'arrPaymentStatus' => [],
        ]);
    }

    /**
     * 決済モジュールで利用出来る決済方式の名前一覧を取得する
     *
     * @return array 支払方法
     */
    private function getPayments()
    {
        $payments = [];

        foreach ($this->eccubeConfig['paygent_payment']['payment_type_id'] as $payName => $payId) {
            $payments[$payId] = $this->eccubeConfig['paygent_payment']['payment_type_names'][$payName];
        }

        return $payments;
    }

    /**
     * 決済モジュールで利用出来るシステム種別の名前一覧を取得する
     *
     * @return array システム種別
     */
    private function getSettlements()
    {
        $settlements = [];

        foreach ($this->eccubeConfig['paygent_payment']['settlement_id'] as $settlementName => $settlementId) {
            $settlements[$settlementId] = $this->eccubeConfig['paygent_payment']['settlement_names'][$settlementName];
        }

        return $settlements;
    }

    /**
     * null→空配列 変換のTransformer作成
     * @return CallbackTransformer
     */
    protected function getTransformer()
    {
        $transformer = new CallbackTransformer(
            function ($array) {
                if (is_array($array) == false) {
                    $array = [];
                }
                return $array;
            },
            function ($array) {
                return $array;
            }
        );

        return $transformer;
    }

    public function checkAdditionalValidation(FormEvent $event) {
        $form = $event->getForm();
        $config = $form->getData();

        $cardValidCheck = $config->getCardValidCheck();
        $moduleStockCard = $config->getModuleStockCard();
        // カードお預かり機能が要かつ有効性チェックでエラーがない場合必須チェックを行う
        if ($moduleStockCard && $form['module_credit_config']['card_valid_check']->getErrors()->count() == 0) {
            $errors = $this->validator->validate($cardValidCheck, [
                new NotBlank([
                    'message' => '※ カード有効性チェックが選択されていません。',
                ]),
            ]);

            if ($errors->count() > 0) {
                foreach ($errors as $error) {
                    $form['module_credit_config']['card_valid_check']->addError(new FormError($error->getMessage()));
                }
            }
        }
    }
}
