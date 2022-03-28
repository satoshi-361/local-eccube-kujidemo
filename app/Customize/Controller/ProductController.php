<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Customize\Controller;

use Eccube\Controller\AbstractController;

use Eccube\Entity\BaseInfo;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Product;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\AddCartType;
use Eccube\Form\Type\Master\ProductListMaxType;
use Eccube\Form\Type\Master\ProductListOrderByType;
use Eccube\Form\Type\SearchProductType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CustomerFavoriteProductRepository;
use Eccube\Repository\Master\ProductListMaxRepository;
use Eccube\Repository\ProductRepository;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Knp\Component\Pager\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

use Plugin\LotteryProbability\Repository\ConfigRepository as LotteryProbabilityRepository;
use Plugin\ProductAssist\Entity\Config as ProductAssist;
use Plugin\ProductAssistConfig\Entity\Config as ProductAssistConfig;
use Plugin\PrizeShow\Entity\Config as Prize;
use Plugin\PrizeShow\Entity\PrizeList as PrizeList;
use Eccube\Entity\Order;

class ProductController extends AbstractController
{
    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var CustomerFavoriteProductRepository
     */
    protected $customerFavoriteProductRepository;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var AuthenticationUtils
     */
    protected $helper;

    /**
     * @var ProductListMaxRepository
     */
    protected $productListMaxRepository;
	
	/**
     * @var LotteryProbabilityRepository
     */
    protected $lotteryProbabilityRepository;

    private $title = '';

    /**
     * ProductController constructor.
     *
     * @param PurchaseFlow $cartPurchaseFlow
     * @param CustomerFavoriteProductRepository $customerFavoriteProductRepository
     * @param CartService $cartService
     * @param ProductRepository $productRepository
     * @param BaseInfoRepository $baseInfoRepository
     * @param AuthenticationUtils $helper
     * @param ProductListMaxRepository $productListMaxRepository
	 * @param LotteryProbabilityRepository $lotteryProbabilityRepository;
     */
    public function __construct(
        PurchaseFlow $cartPurchaseFlow,
        CustomerFavoriteProductRepository $customerFavoriteProductRepository,
        CartService $cartService,
        ProductRepository $productRepository,
        BaseInfoRepository $baseInfoRepository,
        AuthenticationUtils $helper,
        ProductListMaxRepository $productListMaxRepository,
		LotteryProbabilityRepository $lotteryProbabilityRepository
    ) {
        $this->purchaseFlow = $cartPurchaseFlow;
        $this->customerFavoriteProductRepository = $customerFavoriteProductRepository;
        $this->cartService = $cartService;
        $this->productRepository = $productRepository;
        $this->BaseInfo = $baseInfoRepository->get();
        $this->helper = $helper;
        $this->productListMaxRepository = $productListMaxRepository;
		$this->lotteryProbabilityRepository = $lotteryProbabilityRepository;
    }

    /**
     * 商品一覧画面.
     *
     * @Route("/products/list", name="product_list")
     * @Template("Product/list.twig")
     */
    public function index(Request $request, Paginator $paginator)
    {
        // Doctrine SQLFilter
        if ($this->BaseInfo->isOptionNostockHidden()) {
            $this->entityManager->getFilters()->enable('option_nostock_hidden');
        }

        // handleRequestは空のqueryの場合は無視するため
        if ($request->getMethod() === 'GET') {
            $request->query->set('pageno', $request->query->get('pageno', ''));
        }

        // searchForm
        /* @var $builder \Symfony\Component\Form\FormBuilderInterface */
        $builder = $this->formFactory->createNamedBuilder('', SearchProductType::class);

        if ($request->getMethod() === 'GET') {
            $builder->setMethod('GET');
        }

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_INDEX_INITIALIZE, $event);

        /* @var $searchForm \Symfony\Component\Form\FormInterface */
        $searchForm = $builder->getForm();

        $searchForm->handleRequest($request);

        // paginator
        $searchData = $searchForm->getData();
        $qb = $this->productRepository->getQueryBuilderBySearchData($searchData);

        $event = new EventArgs(
            [
                'searchData' => $searchData,
                'qb' => $qb,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_INDEX_SEARCH, $event);
        $searchData = $event->getArgument('searchData');

        $query = $qb->getQuery()
            ->useResultCache(true, $this->eccubeConfig['eccube_result_cache_lifetime_short']);

        /** @var SlidingPagination $pagination */
        $pagination = $paginator->paginate(
            $query,
            !empty($searchData['pageno']) ? $searchData['pageno'] : 1,
            !empty($searchData['disp_number']) ? $searchData['disp_number']->getId() : $this->productListMaxRepository->findOneBy([], ['sort_no' => 'ASC'])->getId()
        );

        $ids = [];
        foreach ($pagination as $Product) {
            $ids[] = $Product->getId();
        }
        $ProductsAndClassCategories = $this->productRepository->findProductsWithSortedClassCategories($ids, 'p.id');

        // addCart form
        $forms = [];
        foreach ($pagination as $Product) {
            /* @var $builder \Symfony\Component\Form\FormBuilderInterface */
            $builder = $this->formFactory->createNamedBuilder(
                '',
                AddCartType::class,
                null,
                [
                    'product' => $ProductsAndClassCategories[$Product->getId()],
                    'allow_extra_fields' => true,
                ]
            );
            $addCartForm = $builder->getForm();

            $forms[$Product->getId()] = $addCartForm->createView();
        }

        // 表示件数
        $builder = $this->formFactory->createNamedBuilder(
            'disp_number',
            ProductListMaxType::class,
            null,
            [
                'required' => false,
                'allow_extra_fields' => true,
            ]
        );
        if ($request->getMethod() === 'GET') {
            $builder->setMethod('GET');
        }

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_INDEX_DISP, $event);

        $dispNumberForm = $builder->getForm();

        $dispNumberForm->handleRequest($request);

        // ソート順
        $builder = $this->formFactory->createNamedBuilder(
            'orderby',
            ProductListOrderByType::class,
            null,
            [
                'required' => false,
                'allow_extra_fields' => true,
            ]
        );
        if ($request->getMethod() === 'GET') {
            $builder->setMethod('GET');
        }

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_INDEX_ORDER, $event);

        $orderByForm = $builder->getForm();

        $orderByForm->handleRequest($request);

        $Category = $searchForm->get('category_id')->getData();

        return [
            'subtitle' => $this->getPageTitle($searchData),
            'pagination' => $pagination,
            'search_form' => $searchForm->createView(),
            'disp_number_form' => $dispNumberForm->createView(),
            'order_by_form' => $orderByForm->createView(),
            'forms' => $forms,
            'Category' => $Category,
        ];
    }




    /**
     * 商品詳細画面.
     *
     * @Route("/products/detail/{id}/{old}", name="product_detail", methods={"GET"}, requirements={"id" = "\d+"})
     * @Template("Product/detail.twig")
     * @ParamConverter("Product", options={"repository_method" = "findWithSortedClassCategories"})
     *
     * @param Request $request
     * @param Product $Product
     *
     * @return array
     */
    public function detail(Request $request, Product $Product, $old = null)
    {
        if (!$this->checkVisibility($Product)) {
            throw new NotFoundHttpException();
        }
        $builder = $this->formFactory->createNamedBuilder(
            '',
            AddCartType::class,
            null,
            [
                'product' => $Product,
                'id_add_product_id' => false,
            ]
        );

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Product' => $Product,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_DETAIL_INITIALIZE, $event);

        $is_favorite = false;
        if ($this->isGranted('ROLE_USER')) {
            $Customer = $this->getUser();
            $is_favorite = $this->customerFavoriteProductRepository->isFavorite($Customer, $Product);
        }

//		$lotteryProbabilities = $this->lotteryProbabilityRepository->findBy(
//			['product_id' => $Product->getId() ]
//		);
		$lotteryProbabilities = $this->lotteryProbabilityRepository->findAll();
		
		$has_class = $Product->hasProductClass();
        if (!$has_class) {
            $ProductClasses = $Product->getProductClasses();
            foreach ($ProductClasses as $pc) {
                if (!is_null($pc->getClassCategory1())) {
                    continue;
                }
                if ($pc->isVisible()) {
                    $ProductClass = $pc;
                    break;
                }
            }
        }

		$productAssist = $this->getDoctrine()->getRepository(ProductAssist::class)->find(['id'=>$Product->product_assist_id]);
        $bulkTexts = array();
        $cartButtonText = $productAssist->getCartButtonText();

        if($productAssist->getBulkConfigLottery())
        {
            $relatedBulkProduct = $this->getDoctrine()->getRepository(Product::class)->findOneBy(['id' => $productAssist->getBulkConfigLottery()]);
            if($relatedBulkProduct != null){
                $relatedBulkAssist = $this->getDoctrine()->getRepository(ProductAssist::class)->findOneBy(['id' => $relatedBulkProduct->product_assist_id]);
                $ProductAssistConfs = $relatedBulkAssist->getSettings();
                // $cartButtonText = $relatedBulkAssist->getCartButtonText();
                
                foreach($ProductAssistConfs as $item)
                {
                    if ($item->getGroupId() == 1){
                        $prizeList = $this->getDoctrine()->getRepository(PrizeList::class)->find(['id' => $item->getSetOption()]);
                        if(isset($prizeList))
                            array_push($bulkTexts, $prizeList->getName().": ".$item->getSetCount()."個");
                    }
                }
            }
        }


		$productAssist = $this->getDoctrine()->getRepository(ProductAssist::class)->find(['id'=>$Product->product_assist_id]);
		$productAssistBulks = array();
		$productAssistConfirmed = array();
		$productAssistLottery = array();
		$bulkImages = array();
		$confirmedImages = array();
		$lotteryImages = array();
		$allPrizes = array();
		if(!empty($productAssist)){
			foreach($productAssist->getSettings() as $item)
			{
				if($item->getGroupId() == 1){
					$prizeList = $this->getDoctrine()->getRepository(PrizeList::class)->find(['id' => $item->getSetOption()]);
					array_push($productAssistBulks, array( $item, $prizeList ));
					$prizes = $prizeList->getSettings();
					foreach($prizes as $prize)
						array_push($allPrizes, $prize);
					array_push($bulkImages, $prizes->getValues());
				}
				else if($item->getGroupId() == 2){
					$prizeList = $this->getDoctrine()->getRepository(PrizeList::class)->find(['id' => $item->getSetOption()]);
					array_push($productAssistConfirmed, array( $item, $prizeList ));
					$prizes = $prizeList->getSettings();
					foreach($prizes as $prize)
						array_push($allPrizes, $prize);
					array_push($confirmedImages, $prizes->getValues());
				}
				else if($item->getGroupId() == 3){
					$prizeList = $this->getDoctrine()->getRepository(PrizeList::class)->find(['id' => $item->getSetOption()]);
					array_push($productAssistLottery, array( $item, $prizeList ));
					$prizes = $prizeList->getSettings()->getValues();
					foreach($prizes as $prize)
						array_push($allPrizes, $prize);
					array_push($lotteryImages, $prizes);
				}
			}				
		}
//		$allPrizes = $this->getDoctrine()->getRepository(Prize::class)->findAll();
        $Customer = 1;
		if ($this->isGranted('ROLE_USER')) {
            $Customer = $this->getUser();
		}

        $Products = $this->getDoctrine()->getRepository(Product::class)->findBy([],['position' => 'asc']);
		$assists = $this->getDoctrine()->getRepository(ProductAssist::class)->findAll();
        $res = array();
        foreach($assists as $item)
		{
			$res[$item->product_id] = $item->getSaleEndText();			
		}

        // confirm if sale_limit is checked and its process
        $sale_limit = 0;

        $Customer = $this->getUser();
        if(isset($Customer)) {
            if($Product->limit_count == '1日に1回' || $Product->limit_count == '1アカウントに1回'){
                $qb = $this->getDoctrine()->getRepository(Order::class)->getQueryBuilderByCustomer($Customer);
                $paginator = new Paginator();
                $pagination = $paginator->paginate(
                    $qb,
                    $request->get('pageno', 1),
                    $this->eccubeConfig['eccube_search_pmax']
                );
                
                foreach($pagination as $Order){
                    if($Product->limit_count == '1日に1回'){
                        if(null !== $Order->getOrderDate()){
                            if($Order->getOrderDate()->format('Y/m/d') == date("Y/m/d"))
                            {
                                foreach($Order->getMergedProductOrderItems() as $OrderItem)
                                {
                                    if($Product->getId() == $OrderItem->getProduct()->getId())
                                    {
                                        $sale_limit = 1;
                                        break;
                                    }
                                }
                                 if($sale_limit)
                                    break;
                            }
                        }
                    }
                    else {
                        foreach($Order->getMergedProductOrderItems() as $OrderItem){
                            if($Product->getId() == $OrderItem->getProduct()->getId())
                            {
                                $sale_limit = 1;
                                break;
                            }
                        }
                        if($sale_limit) break;
                    }
                }
            }    
        }
        return [
            'title' => $this->title,
            'subtitle' => $Product->getName(),
            'form' => $builder->getForm()->createView(),
            'Product' => $Product,
            'is_favorite' => $is_favorite,
			'ProductClass' => $ProductClass,
			'lotteries'	=> $lotteryProbabilities,
			'assist' => $productAssist,
			'bulkConf' => $productAssistBulks,
			'bulkImage' => $bulkImages,
			'confirmedConf' => $productAssistConfirmed,
			'confirmedImage' => $confirmedImages,
			'lotteryConf' => $productAssistLottery,
			'lotteryImage' => $lotteryImages,
			'allPrizes' => $allPrizes,
			'Customer' => $Customer,
            'cartButtonText' => $cartButtonText,
            'bulkTexts' => $bulkTexts,
            'bulkProduct' => isset($relatedBulkProduct) ? $relatedBulkProduct : '',
            'assists' => $res,
            'Products' => $Products,
            'sale_limit' => $sale_limit
        ];
    }

    /**
     * @Route("/old_item", name="old_item_link")
     * @Template("Product/old_item.twig")
     */
    public function old_item(Request $request) {        
        $Products = $this->getDoctrine()->getRepository(Product::class)->findBy([],['position' => 'asc']);
		$assists = $this->getDoctrine()->getRepository(ProductAssist::class)->findAll();
        $res = array();
        foreach($assists as $item)
		{
			$res[$item->product_id] = $item->getSaleEndText();			
		}

        return [
            'Products' => $Products,
            'assists' => $res
        ];
    }

    /**
     * お気に入り追加.
     *
     * @Route("/products/add_favorite/{id}", name="product_add_favorite", requirements={"id" = "\d+"})
     */
    public function addFavorite(Request $request, Product $Product)
    {
        $this->checkVisibility($Product);

        $event = new EventArgs(
            [
                'Product' => $Product,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_FAVORITE_ADD_INITIALIZE, $event);

        if ($this->isGranted('ROLE_USER')) {
            $Customer = $this->getUser();
            $this->customerFavoriteProductRepository->addFavorite($Customer, $Product);
            $this->session->getFlashBag()->set('product_detail.just_added_favorite', $Product->getId());

            $event = new EventArgs(
                [
                    'Product' => $Product,
                ],
                $request
            );
            $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_FAVORITE_ADD_COMPLETE, $event);

            return $this->redirectToRoute('product_detail', ['id' => $Product->getId()]);
        } else {
            // 非会員の場合、ログイン画面を表示
            //  ログイン後の画面遷移先を設定
            $this->setLoginTargetPath($this->generateUrl('product_add_favorite', ['id' => $Product->getId()], UrlGeneratorInterface::ABSOLUTE_URL));
            $this->session->getFlashBag()->set('eccube.add.favorite', true);

            $event = new EventArgs(
                [
                    'Product' => $Product,
                ],
                $request
            );
            $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_FAVORITE_ADD_COMPLETE, $event);

            return $this->redirectToRoute('mypage_login');
        }
    }


    /**
     * カートに追加.
     *
     * @Route("/products/add_cart/{id}", name="product_add_cart", methods={"POST"}, requirements={"id" = "\d+"})
     */
    public function addCart(Request $request, Product $Product)
    {
        // エラーメッセージの配列
        $errorMessages = [];
        if (!$this->checkVisibility($Product)) {
            throw new NotFoundHttpException();
        }
        $builder = $this->formFactory->createNamedBuilder(
            '',
            AddCartType::class,
            null,
            [
                'product' => $Product,
                'id_add_product_id' => false,
            ]
        );

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Product' => $Product,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_CART_ADD_INITIALIZE, $event);

        /* @var $form \Symfony\Component\Form\FormInterface */
        $form = $builder->getForm();
        $form->handleRequest($request);

        if (!$form->isValid()) {
            $class_id = $Product->getProductClasses()->getValues()[0]->getId();
            if ($class_id == null)
                throw new NotFoundHttpException();
            $quantity = 1;
            $this->cartService->addProduct($class_id, $quantity);
        }
        else {
            $addCartData = $form->getData();
            log_info(
                'カート追加処理開始',
                [
                    'product_id' => $Product->getId(),
                    'product_class_id' => $addCartData['product_class_id'],
                    'quantity' => $addCartData['quantity'],
                ]
            );
            $this->cartService->addProduct($addCartData['product_class_id'], $addCartData['quantity'], $Product->getShipCount());
        }
        // カートへ追加


        // 明細の正規化
        $Carts = $this->cartService->getCarts();
        foreach ($Carts as $Cart) {
            $result = $this->purchaseFlow->validate($Cart, new PurchaseContext($Cart, $this->getUser()));
            // 復旧不可のエラーが発生した場合は追加した明細を削除.
            if ($result->hasError()) {
                $this->cartService->removeProduct($addCartData['product_class_id']);
                foreach ($result->getErrors() as $error) {
                    $errorMessages[] = $error->getMessage();
                }
            }
            foreach ($result->getWarning() as $warning) {
                $errorMessages[] = $warning->getMessage();
            }
        }

        $this->cartService->save();

        log_info(
            'カート追加処理完了',
            [
                'product_id' => $Product->getId(),
                'product_class_id' => isset($addCartData['product_class_id']) ? $addCartData['product_class_id'] : $class_id,
                'quantity' => isset($addCartData['quantity']) ? $addCartData['quantity'] : $quantity,
            ]
        );

        $event = new EventArgs(
            [
                'form' => $form,
                'Product' => $Product,
            ],
            $request
        );
		
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_CART_ADD_COMPLETE, $event);
        if ($event->getResponse() !== null) {
            return $event->getResponse();
        }
            
        if ($request->isXmlHttpRequest()) {
            // ajaxでのリクエストの場合は結果をjson形式で返す。

            // 初期化
            $done = null;
            $messages = [];

            if (empty($errorMessages)) {
                // エラーが発生していない場合
                $done = true;
                array_push($messages, trans('front.product.add_cart_complete'));
            } else {
                // エラーが発生している場合
                $done = false;
                $messages = $errorMessages;
            }
			
            return $this->json(['done' => $done, 'messages' => $messages]);
        } else {
            // ajax以外でのリクエストの場合はカート画面へリダイレクト
            foreach ($errorMessages as $errorMessage) {
                $this->addRequestError($errorMessage);
            }

            return $this->redirectToRoute('cart');
        }
    }

    /**
     * ページタイトルの設定
     *
     * @param  null|array $searchData
     *
     * @return str
     */
    protected function getPageTitle($searchData)
    {
        if (isset($searchData['name']) && !empty($searchData['name'])) {
            return trans('front.product.search_result');
        } elseif (isset($searchData['category_id']) && $searchData['category_id']) {
            return $searchData['category_id']->getName();
        } else {
            return trans('front.product.all_products');
        }
    }

    /**
     * 閲覧可能な商品かどうかを判定
     *
     * @param Product $Product
     *
     * @return boolean 閲覧可能な場合はtrue
     */
    protected function checkVisibility(Product $Product)
    {
        $is_admin = $this->session->has('_security_admin');

        // 管理ユーザの場合はステータスやオプションにかかわらず閲覧可能.
        if (!$is_admin) {
            // 在庫なし商品の非表示オプションが有効な場合.
            // if ($this->BaseInfo->isOptionNostockHidden()) {
            //     if (!$Product->getStockFind()) {
            //         return false;
            //     }
            // }
            // 公開ステータスでない商品は表示しない.
            if ($Product->getStatus()->getId() !== ProductStatus::DISPLAY_SHOW) {
                return false;
            }
        }

        return true;
    }
}
