<?php
/*
 * Copyright (c) 2018 VeriTrans Inc., a Digital Garage company. All rights reserved.
 * http://www.veritrans.co.jp/
 */
namespace Plugin\VeriTrans4G\Controller\Admin\Product;

use Eccube\Controller\AbstractController;
use Plugin\VeriTrans4G\Repository\Master\Vt4gSubscSaleTypeRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 *
 * 継続課金商品判別コントローラー
 *
 */
class ChangeSaleTypeController extends AbstractController
{
    /**
     * @var Vt4gSubscSaleTypeRepository
     */
    protected $subscSaleTypeRepository;

    /**
     * コンストラクタ
     *
     * @param Vt4gSubscSaleTypeRepository $subscSaleTypeRepository
     */
    public function __construct(Vt4gSubscSaleTypeRepository $subscSaleTypeRepository)
    {
        $this->subscSaleTypeRepository = $subscSaleTypeRepository;
    }

    /**
     * 販売種別変更時に継続間商品であるかを判定
     * @Route("/%eccube_admin_route%/product/vtg4_change_sale_type", name="vtg4_admin_product_chang_Sale_Type")
     * @param Request $request
     * @return JsonResponse
     */
    public function changeSaleType(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }

        // 商品規格が登録されている場合は画面から販売種別が送れない
        $saleType = $request->get('saleType');

        // 画面の販売種別が存在する場合
        if (isset($saleType)) {

            // 画面から送られてきた販売種別が継続課金販売種別マスタに存在する販売種別か確認
            $subscSaleType = $this->subscSaleTypeRepository->findOneBy(['sale_type_id' => $saleType]);

            if (isset($subscSaleType)) {
            // 画面の販売種別が継続課金用の場合

            return $this->json([
                    'result' => true,
                ]);
            }

        } else {
            // 画面の販売種別が存在しない（商品規格登録ずみ）

            // 商品規格の販売種別が継続課金用か確認
            // レンダリング前処理でhiddenに商品IDを設定しておき、ajaxリクエストの際に詰め直す
            $productId = $request->get('productIdForAjax');

            if (!is_null($productId)) {
                $subscSaleTypeArray = $this->subscSaleTypeRepository->existsSubscSaleTypeByProductId($productId);

                if (count($subscSaleTypeArray) > 0) {
                    // 商品規格に継続課金用の販売種別存在する
                    return $this->json([
                        'result' => true,
                    ]);
                }
            }

        }

        return $this->json([
            'result' =>  false,
        ]);
    }

}
