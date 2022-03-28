<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin\Product;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\SaleType;
use Plugin\VeriTrans4G\Entity\Master\Vt4gSubscSaleType;
use Plugin\VeriTrans4G\Form\Type\Admin\SubscSalesType;
use Plugin\VeriTrans4G\Repository\Master\Vt4gSubscSaleTypeRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


/**
 * 継続課金用販売種別コントローラー
 *
 */
class SubscSalestypeController extends AbstractController
{
    /**
     * コンテナ
     */
    protected $container;

    /**
     * 汎用処理用ユーティリティ
     */
    protected $util;

    /**
     * MDK Logger
     */
    protected $mdkLogger;

    /**
     * @var Vt4gSubscSaleTypeRepository
     */
    protected $saletypeRepository;

    /**
     * コンストラクタ
     * @param ContainerInterface $container
     * @param Vt4gSubscSaleTypeRepository $saletypeRepository
     */
    public function __construct(ContainerInterface $container, Vt4gSubscSaleTypeRepository $saletypeRepository)
    {
        $this->container = $container;
        $mdkService = $this->container->get('vt4g_plugin.service.vt4g_mdk');
        $mdkService->checkMdk();
        $this->mdkLogger = $mdkService->getMdkLogger();
        $this->saletypeRepository = $saletypeRepository;
    }

    /**
     * 継続課金用販売種別一覧表示と変更処理
     *
     * @Route("/%eccube_admin_route%/product/vt4g_subsc_salestype", name="vt4g_admin_subsc_salestype")
     * @Route("/%eccube_admin_route%/product/vt4g_subsc_salestype/{id}/edit", requirements={"id" = "\d+"}, name="vt4g_admin_subsc_salestype_edit")
     * @Template("@admin/Product/subsc_saletype.twig")
     * @param Request $request
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function index(Request $request)
    {
        $saleType = new Vt4gSubscSaleType();

        /**
         * 新規登録用フォーム
         * ユーザから送られたデータをフォームオブジェクトに書き込む.
         **/
        // Typeベースでフォーム項目を作成
        $builder = $this->formFactory->createBuilder(SubscSalesType::class);
        // フォーム作成
        $form = $builder->getForm();

        /**
         * 一覧編集用フォーム
         */
        // 一覧データ取得
        $saleTypes = $this->saletypeRepository->getList();

        // 補足　一件処理
        $forms = [];
        foreach ($saleTypes as $EditSaleType) {
            $id = $EditSaleType->getSaleTypeId();
            $forms[$id] = $this
                ->formFactory
                ->createNamed('sales_type_'.$id, SubscSalesType::class, $EditSaleType);
        }

        // 登録あるいは更新
        if ('POST' === $request->getMethod()) {
            /*
             * 登録処理
             */
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {

                try {
                  // フォーム入力データ取得
                  $data = $form->getData();

                  // 継続課金販売種別マスタエンティティ(idはリポジトリで設定)
                  $subscSalesType = new Vt4gSubscSaleType();
                  $subscSalesType->setName($data['name']);
                  $subscSalesType->setFewCreditFlg($data['few_credit_flg']);
                  $subscSalesType->setDiscriminatorType('subscSaleType');

                  // 登録処理
                  $saleTypeId = $this->saletypeRepository->save($subscSalesType);

                  // 完了通知
                  $this->addSuccess('admin.common.save_complete', 'admin');
                  $this->mdkLogger->info(
                      trans('vt4g_plugin.admin.subsc_sales_type.save.complete')
                      .sprintf(trans('vt4g_plugin.admin.payment_request.sale_type'),$saleTypeId)
                  );

                } catch (\Exception $e) {
                    $message = trans('vt4g_plugin.admin.subsc_sales_type.db.save.error');
                    $this->mdkLogger->error(trans('vt4g_plugin.admin.subsc_sales_type.db.save.error'));
                    $this->mdkLogger->error($e->getMessage());
                    $this->mdkLogger->error($e->getTraceAsString());

                    $this->addError($message, 'admin');
                }

                return $this->redirectToRoute('vt4g_admin_subsc_salestype');
            }

            /*
             * 編集処理
             */
            foreach ($forms as $editForm) {
                $editForm->handleRequest($request);
                if ($editForm->isSubmitted() && $editForm->isValid()) {

                    $this->saletypeRepository->update($editForm->getData());

                    // 完了通知
                    $this->addSuccess('admin.common.save_complete', 'admin');
                    $this->mdkLogger->info(
                        trans('vt4g_plugin.admin.subsc_sales_type.save.complete')
                        .sprintf(trans('vt4g_plugin.admin.payment_request.sale_type'),$editForm->getData()->getSaleTypeId())
                    );

                    return $this->redirectToRoute('vt4g_admin_subsc_salestype');
                }
            }
        }

        $formViews = [];
        foreach ($forms as $key => $value) {
            $formViews[$key] = $value->createView();
        }

        return $this->render(
            'VeriTrans4G/Resource/template/admin/Product/subsc_salestype.twig',
            [
                'form' => $form->createView(),
                'SaleType' => $saleType,
                'SaleTypes' => $saleTypes,
                'forms' => $formViews,
            ]
        );
    }

    /**
     * 継続課金用販売種別削除処理
     *
     * @Route("/%eccube_admin_route%/product/vt4g_subsc_salestype/{id}/delete", requirements={"id" = "\d+"}, name="vt4g_admin_subsc_salestype_delete", methods={"DELETE"})
     * @ParamConverter("saleType", class="Eccube\Entity\Master\SaleType")
     *
     */
    public function delete(Request $request, SaleType $saleType)
    {
        $this->isTokenValid();

        try {
            $this->saletypeRepository->delete($saleType);

            // 完了通知
            $this->addSuccess('admin.common.delete_complete', 'admin');
            $this->mdkLogger->info(
                trans('vt4g_plugin.admin.subsc_sales_type.del.complete')
                .sprintf(trans('vt4g_plugin.admin.payment_request.sale_type'),$saleType->getId())
            );

        } catch (\Exception $e) {

            $message = trans('admin.common.delete_error_foreign_key', ['%name%' => '販売種別マスタデータの'. $saleType->getName()]);
            $this->mdkLogger->error($message);
            $this->addError($message, 'admin');

        }

        return $this->redirectToRoute('vt4g_admin_subsc_salestype');
    }

}
