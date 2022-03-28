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

namespace Customize\Controller\Admin\Product;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Eccube\Common\Constant;
use Eccube\Controller\Admin\AbstractCsvImportController;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Category;
use Eccube\Entity\Product;
use Eccube\Entity\ProductCategory;
use Eccube\Entity\ProductClass;
use Eccube\Entity\ProductImage;
use Eccube\Entity\ProductStock;
use Eccube\Entity\ProductTag;
use Eccube\Form\Type\Admin\CsvImportType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CategoryRepository;
use Eccube\Repository\ClassCategoryRepository;
use Eccube\Repository\DeliveryDurationRepository;
use Eccube\Repository\Master\ProductStatusRepository;
use Eccube\Repository\Master\SaleTypeRepository;
use Eccube\Repository\ProductRepository;
use Eccube\Repository\TagRepository;
use Eccube\Repository\TaxRuleRepository;
use Eccube\Service\CsvImportService;
use Eccube\Util\CacheUtil;
use Eccube\Util\StringUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plugin\ProductAssist\Entity\Config as ProductAssist;
use Plugin\ProductAssistConfig\Entity\Config as ProductAssistConfig;
use Plugin\ProductAssist\Repository\ConfigRepository as ProductAssistRepository;
use Plugin\ProductAssistConfig\Repository\ConfigRepository as ProductAssistConfigRepository;
use Plugin\PrizeShow\Entity\PrizeList;

class CsvImportController extends AbstractCsvImportController
{
    /**
     * @var DeliveryDurationRepository
     */
    protected $deliveryDurationRepository;

    /**
     * @var SaleTypeRepository
     */
    protected $saleTypeRepository;

    /**
     * @var TagRepository
     */
    protected $tagRepository;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var ClassCategoryRepository
     */
    protected $classCategoryRepository;

    /**
     * @var ProductStatusRepository
     */
    protected $productStatusRepository;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var TaxRuleRepository
     */
    private $taxRuleRepository;

    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    private $errors = [];

    /**
     * CsvImportController constructor.
     *
     * @param DeliveryDurationRepository $deliveryDurationRepository
     * @param SaleTypeRepository $saleTypeRepository
     * @param TagRepository $tagRepository
     * @param CategoryRepository $categoryRepository
     * @param ClassCategoryRepository $classCategoryRepository
     * @param ProductStatusRepository $productStatusRepository
     * @param ProductRepository $productRepository
     * @param TaxRuleRepository $taxRuleRepository
     * @param BaseInfoRepository $baseInfoRepository
     * @param ValidatorInterface $validator
     * @throws \Exception
     */
    public function __construct(
        DeliveryDurationRepository $deliveryDurationRepository,
        SaleTypeRepository $saleTypeRepository,
        TagRepository $tagRepository,
        CategoryRepository $categoryRepository,
        ClassCategoryRepository $classCategoryRepository,
        ProductStatusRepository $productStatusRepository,
        ProductRepository $productRepository,
        TaxRuleRepository $taxRuleRepository,
        BaseInfoRepository $baseInfoRepository,
        ValidatorInterface $validator
    ) {
        $this->deliveryDurationRepository = $deliveryDurationRepository;
        $this->saleTypeRepository = $saleTypeRepository;
        $this->tagRepository = $tagRepository;
        $this->categoryRepository = $categoryRepository;
        $this->classCategoryRepository = $classCategoryRepository;
        $this->productStatusRepository = $productStatusRepository;
        $this->productRepository = $productRepository;
        $this->taxRuleRepository = $taxRuleRepository;
        $this->BaseInfo = $baseInfoRepository->get();
        $this->validator = $validator;
    }

    /**
     * 商品登録CSVアップロード
     *
     * @Route("/%eccube_admin_route%/product/product_csv_upload", name="admin_product_csv_import")
     * @Template("@admin/Product/csv_product.twig")
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function csvProduct(Request $request, CacheUtil $cacheUtil)
    {
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $headers = $this->getProductCsvHeader();
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $formFile = $form['import_file']->getData();
                if (!empty($formFile)) {
                    log_info('商品CSV登録開始');
                    $data = $this->getImportData($formFile);
                    if ($data === false) {
                        $this->addErrors(trans('admin.common.csv_invalid_format'));

                        return $this->renderWithError($form, $headers, false);
                    }
                    $getId = function ($item) {
                        return $item['id'];
                    };
                    $requireHeader = array_keys(array_map($getId, array_filter($headers, function ($value) {
                        return $value['required'];
                    })));

                    $columnHeaders = $data->getColumnHeaders();

                    $size = count($data);

                    if ($size < 1) {
                        $this->addErrors(trans('admin.common.csv_invalid_no_data'));

                        return $this->renderWithError($form, $headers, false);
                    }

                    $headerSize = count($columnHeaders);
                    $headerByKey = array_flip(array_map($getId, $headers));
                    $deleteImages = [];

                    $columnHeaders = array_flip($columnHeaders);

                    foreach($headerByKey as $key => $header) {
                      $headerByKey[$key] = $columnHeaders[$header];
                    }

                    $this->entityManager->getConfiguration()->setSQLLogger(null);
                    $this->entityManager->getConnection()->beginTransaction();
                    // CSVファイルの登録処理
                    foreach ($data as $row) {
                        $line = $data->key() + 1;

                        $Product = new Product();
                        $this->entityManager->persist($Product);

                        $Product->setStatus($this->productStatusRepository->find(\Eccube\Entity\Master\ProductStatus::DISPLAY_SHOW));
                        $Product->setPosition(0);

                        if (StringUtil::isBlank($row[$headerByKey['name']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['name']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Product->setName(StringUtil::trimAll($row[$headerByKey['name']]));
                        }

                        if (isset($row[$headerByKey['description_detail']])) {
                            if (StringUtil::isNotBlank($row[$headerByKey['description_detail']])) {
                                if (mb_strlen($row[$headerByKey['description_detail']]) > $this->eccubeConfig['eccube_ltext_len']) {
                                    $message = trans('admin.common.csv_invalid_description_detail_upper_limit', [
                                        '%line%' => $line,
                                        '%name%' => $headerByKey['description_detail'],
                                        '%max%' => $this->eccubeConfig['eccube_ltext_len'],
                                    ]);
                                    $this->addErrors($message);

                                    return $this->renderWithError($form, $headers);
                                } else {
                                    $Product->setDescriptionDetail(StringUtil::trimAll($row[$headerByKey['description_detail']]));
                                }
                            } else {
                                $Product->setDescriptionDetail(null);
                            }
                        }
                        
                        $productAssist = new ProductAssist();
                        $productAssistConfigRepo = $this->getDoctrine()->getRepository(ProductAssistConfig::class);
                        $prizeListRepo = $this->getDoctrine()->getRepository(PrizeList::class);

                        if (isset($row[$headerByKey['winning_products']])) {
                          if (StringUtil::isNotBlank($row[$headerByKey['winning_products']])) {
                            $String = $row[$headerByKey['winning_products']];
                            if (strpos($String, 'limitation_premium=')) {
                              $pos = strpos($String, 'limitation_premium=');
                              $reString = substr($String, $pos + strlen('limitation_premium='), 15);
                              $value = substr($reString, 0, strpos($reString, ';'));

                              if ($value == 'true') $Product->premium = 1;
                              else $Product->premium = 0;
                            }
                            
                            if (strpos($String, 'limitation_ticket_flug=')) {
                              $pos = strpos($String, 'limitation_ticket_flug=');
                              $reString = substr($String, $pos + strlen('limitation_ticket_flug='), 15);
                              $value = substr($reString, 0, strpos($reString, ';'));

                              if ($value == 'false') $Product->specifics = 0;
                            }

                            if (strpos($String, 'limitation_ticket=')) {
                              $pos = strpos($String, 'limitation_ticket=');
                              $reString = substr($String, $pos + strlen('limitation_ticket='), 15);
                              $value = substr($reString, 0, strpos($reString, ';'));
                              
                              if ($value != '') $Product->specifics = $value;
                            }
                            
                            if (strpos($String, 'restriction=')) {
                              $pos = strpos($String, 'restriction=');
                              $reString = substr($String, $pos + strlen('restriction='), 15);
                              $value = substr($reString, 0, strpos($reString, ';'));
                              
                              switch($value) {
                                case '0':
                                  $Product->limit_count = '';
                                  break;
                                  
                                case '1':
                                  $Product->limit_count = '1日に1回';
                                  break;
                                  
                                case '2':
                                  $Product->limit_count = '1アカウントに1回';
                                  break;
                              }
                            }
                            
                            if (strpos($String, 'rank_setting_charge_num=')) {
                              $pos = strpos($String, 'rank_setting_charge_num=');
                              $reString = substr($String, $pos + strlen('rank_setting_charge_num='), 15);
                              $value = substr($reString, 0, strpos($reString, ';'));

                              $Product->setShipCount(intval($value));
                            }
                            
                            if (strpos($String, 'animation_on=')) {
                              $pos = strpos($String, 'animation_on=');
                              $reString = substr($String, $pos + strlen('animation_on='), 15);
                              $value = substr($reString, 0, strpos($reString, ';'));
                              
                              if ($value == 'on') $Product->setAnimateImage('1');
                              else $Product->setAnimateImage(0);
                            }

                            if (strpos($String, 'limitation_channel_flug=')) {
                              $pos = strpos($String, 'limitation_channel_flug=');
                              $reString = substr($String, $pos + strlen('limitation_channel_flug='), 15);
                              $value = substr($reString, 0, strpos($reString, ';'));
                              
                              if ($value == 'false') $Product->niconico = 0;
                            }

                            if (strpos($String, 'limitation_channel=')) {
                              $pos = strpos($String, 'limitation_channel=');
                              $reString = substr($String, $pos + strlen('limitation_channel='), 15);
                              $value = substr($reString, 0, strpos($reString, ';'));
                              if ($value == '')
                                $value = substr($reString, 0);
                              
                              if ($value != '') $Product->niconico = $value;
                            }
                            
                            if (strpos($String, 'rank_setting_group=')) {
                              $pos = strpos($String, 'rank_setting_group=');
                              $first_braket_pos = strpos( substr($String, $pos + strlen('rank_setting_group=')), '{' );
                              $last_braket_pos = strrpos( substr($String, $pos + strlen('rank_setting_group=')), '}' );
                              $reString = substr(substr($String, $pos + strlen('rank_setting_group=') - 4), $first_braket_pos, $last_braket_pos - $first_braket_pos + 5);
                              
                              $array = unserialize($reString);

                              foreach($array as $item) {
                                $productAssistConfig = new ProductAssistConfig();

                                if (array_key_exists('rank_setting_select_rank', $item))
                                  $productAssistConfig->setGrade($item['rank_setting_select_rank']);

                                if (array_key_exists('rank_setting_select_rank_name', $item))
                                  $productAssistConfig->setClassName($item['rank_setting_select_rank_name']);

                                if (array_key_exists('rank_setting_rank_description', $item))
                                  $productAssistConfig->setDescriptionText($item['rank_setting_rank_description']);

                                if (array_key_exists('rank_setting_select_probability', $item))
                                  $productAssistConfig->setSetCount($item['rank_setting_select_probability']);

                                if (array_key_exists('rank_setting_select_probability_name', $item))
                                  $productAssistConfig->setShowText($item['rank_setting_select_probability_name']);

                                if (array_key_exists('rank_setting_select_rank_color', $item))
                                  $productAssistConfig->setColorName($item['rank_setting_select_rank_color']);

                                if (array_key_exists('rank_setting_select_item', $item)) {
                                    $PrizeList = $prizeListRepo->findOneBy(['old_id' => $item['rank_setting_select_item']]);
                                    $productAssistConfig->setSetOption($PrizeList->getId());
                                }

                                $productAssist->addSetting($productAssistConfig);
                                $productAssistConfig->setProductAssist($productAssist);
                                $productAssistConfig->setGroupId(3);

                                $this->entityManager->persist($productAssistConfig);
                              }
                            }
                          }
                        }

                        // 商品画像登録
                        $this->createProductImage($row, $Product, $data, $headerByKey);

                        if(count($this->getDoctrine()->getRepository(Product::class)->findAll()))
                            $productAssist->product_id = $this->getDoctrine()->getRepository(Product::class)->findOneBy([],['id'=>'DESC'])->getId()+1;
                        else $productAssist->product_id = 1;
                        
                        if(count($this->getDoctrine()->getRepository(ProductAssist::class)->findAll()))
                            $Product->product_assist_id = $this->getDoctrine()->getRepository(ProductAssist::class)->findOneBy([],['id'=>'DESC'])->getId()+1;
                        else $Product->product_assist_id = 1;

                        $this->entityManager->persist($productAssist);
                        $this->entityManager->persist($Product);
                        $this->entityManager->flush();
                        
                        $productAssist->product_id = $this->getDoctrine()->getRepository(Product::class)->findBy(array(),array('id'=>'DESC'),1,0)[0]->getId();
                        $this->entityManager->persist($Product);
                        $this->entityManager->flush();

                        // 商品カテゴリ登録
                        $this->createProductCategory($row, $Product, $data, $headerByKey);

                        // 商品規格が存在しなければ新規登録
                        /** @var ProductClass[] $ProductClasses */
                        $ProductClasses = $Product->getProductClasses();
                        if ($ProductClasses->count() < 1) {
                            // 規格分類1(ID)がセットされていると規格なし商品、規格あり商品を作成
                            $ProductClassOrg = $this->createProductClass($row, $Product, $data, $headerByKey);
                            if ($this->BaseInfo->isOptionProductDeliveryFee()) {
                                if (isset($row[$headerByKey['delivery_fee']]) && StringUtil::isNotBlank($row[$headerByKey['delivery_fee']])) {
                                    $deliveryFee = $row[$headerByKey['delivery_fee']];
                                    if ($deliveryFee == 1)
                                      $ProductClassOrg->setDeliveryFee(1000);
                                    else if ($deliveryFee == 2)
                                      $ProductClassOrg->setDeliveryFee(500);
                                    else if ($deliveryFee == 3)
                                    $ProductClassOrg->setDeliveryFee(0);
                                }
                            }
                        }
                        if ($this->hasErrors()) {
                            return $this->renderWithError($form, $headers);
                        }
                        $this->entityManager->persist($Product);
                    }
                    $this->entityManager->flush();
                    $this->entityManager->getConnection()->commit();

                    log_info('商品CSV登録完了');
                    $message = 'admin.common.csv_upload_complete';
                    $this->session->getFlashBag()->add('eccube.admin.success', $message);

                    $cacheUtil->clearDoctrineCache();
                }
            }
        }

        return $this->renderWithError($form, $headers);
    }

    /**
     * カテゴリ登録CSVアップロード
     *
     * @Route("/%eccube_admin_route%/product/category_csv_upload", name="admin_product_category_csv_import")
     * @Template("@admin/Product/csv_category.twig")
     */
    public function csvCategory(Request $request, CacheUtil $cacheUtil)
    {
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();

        $headers = $this->getCategoryCsvHeader();
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $formFile = $form['import_file']->getData();
                if (!empty($formFile)) {
                    log_info('カテゴリCSV登録開始');
                    $data = $this->getImportData($formFile);
                    if ($data === false) {
                        $this->addErrors(trans('admin.common.csv_invalid_format'));

                        return $this->renderWithError($form, $headers, false);
                    }

                    $getId = function ($item) {
                        return $item['id'];
                    };
                    $requireHeader = array_keys(array_map($getId, array_filter($headers, function ($value) {
                        return $value['required'];
                    })));

                    $headerByKey = array_flip(array_map($getId, $headers));

                    $columnHeaders = $data->getColumnHeaders();
                    if (count(array_diff($requireHeader, $columnHeaders)) > 0) {
                        $this->addErrors(trans('admin.common.csv_invalid_format'));

                        return $this->renderWithError($form, $headers, false);
                    }

                    $size = count($data);
                    if ($size < 1) {
                        $this->addErrors(trans('admin.common.csv_invalid_no_data'));

                        return $this->renderWithError($form, $headers, false);
                    }
                    $this->entityManager->getConfiguration()->setSQLLogger(null);
                    $this->entityManager->getConnection()->beginTransaction();
                    // CSVファイルの登録処理
                    foreach ($data as $row) {
                        /** @var $Category Category */
                        $Category = new Category();
                        if (isset($row[$headerByKey['id']]) && strlen($row[$headerByKey['id']]) > 0) {
                            if (!preg_match('/^\d+$/', $row[$headerByKey['id']])) {
                                $this->addErrors(($data->key() + 1).'行目のカテゴリIDが存在しません。');

                                return $this->renderWithError($form, $headers);
                            }
                            $Category = $this->categoryRepository->find($row[$headerByKey['id']]);
                            if (!$Category) {
                                $this->addErrors(($data->key() + 1).'行目の更新対象のカテゴリIDが存在しません。新規登録の場合は、カテゴリIDの値を空で登録してください。');

                                return $this->renderWithError($form, $headers);
                            }
                            if ($row[$headerByKey['id']] == $row[$headerByKey['parent_category_id']]) {
                                $this->addErrors(($data->key() + 1).'行目のカテゴリIDと親カテゴリIDが同じです。');

                                return $this->renderWithError($form, $headers);
                            }
                        }

                        if (isset($row[$headerByKey['category_del_flg']]) && StringUtil::isNotBlank($row[$headerByKey['category_del_flg']])) {
                            if (StringUtil::trimAll($row[$headerByKey['category_del_flg']]) == 1) {
                                if ($Category->getId()) {
                                    log_info('カテゴリ削除開始', [$Category->getId()]);
                                    try {
                                        $this->categoryRepository->delete($Category);
                                        log_info('カテゴリ削除完了', [$Category->getId()]);
                                    } catch (ForeignKeyConstraintViolationException $e) {
                                        log_info('カテゴリ削除エラー', [$Category->getId(), $e]);
                                        $message = trans('admin.common.delete_error_foreign_key', ['%name%' => $Category->getName()]);
                                        $this->addError($message, 'admin');

                                        return $this->renderWithError($form, $headers);
                                    }
                                }

                                continue;
                            }
                        }

                        if (!isset($row[$headerByKey['category_name']]) || StringUtil::isBlank($row[$headerByKey['category_name']])) {
                            $this->addErrors(($data->key() + 1).'行目のカテゴリ名が設定されていません。');

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Category->setName(StringUtil::trimAll($row[$headerByKey['category_name']]));
                        }

                        $ParentCategory = null;
                        if (isset($row[$headerByKey['parent_category_id']]) && StringUtil::isNotBlank($row[$headerByKey['parent_category_id']])) {
                            if (!preg_match('/^\d+$/', $row[$headerByKey['parent_category_id']])) {
                                $this->addErrors(($data->key() + 1).'行目の親カテゴリIDが存在しません。');

                                return $this->renderWithError($form, $headers);
                            }

                            /** @var $ParentCategory Category */
                            $ParentCategory = $this->categoryRepository->find($row[$headerByKey['parent_category_id']]);
                            if (!$ParentCategory) {
                                $this->addErrors(($data->key() + 1).'行目の親カテゴリIDが存在しません。');

                                return $this->renderWithError($form, $headers);
                            }
                        }
                        $Category->setParent($ParentCategory);

                        // Level
                        if (isset($row['階層']) && StringUtil::isNotBlank($row['階層'])) {
                            if ($ParentCategory == null && $row['階層'] != 1) {
                                $this->addErrors(($data->key() + 1).'行目の親カテゴリIDが存在しません。');

                                return $this->renderWithError($form, $headers);
                            }
                            $level = StringUtil::trimAll($row['階層']);
                        } else {
                            $level = 1;
                            if ($ParentCategory) {
                                $level = $ParentCategory->getHierarchy() + 1;
                            }
                        }

                        $Category->setHierarchy($level);

                        if ($this->eccubeConfig['eccube_category_nest_level'] < $Category->getHierarchy()) {
                            $this->addErrors(($data->key() + 1).'行目のカテゴリが最大レベルを超えているため設定できません。');

                            return $this->renderWithError($form, $headers);
                        }

                        if ($this->hasErrors()) {
                            return $this->renderWithError($form, $headers);
                        }
                        $this->entityManager->persist($Category);
                        $this->categoryRepository->save($Category);
                    }

                    $this->entityManager->getConnection()->commit();
                    log_info('カテゴリCSV登録完了');
                    $message = 'admin.common.csv_upload_complete';
                    $this->session->getFlashBag()->add('eccube.admin.success', $message);

                    $cacheUtil->clearDoctrineCache();
                }
            }
        }

        return $this->renderWithError($form, $headers);
    }

    /**
     * アップロード用CSV雛形ファイルダウンロード
     *
     * @Route("/%eccube_admin_route%/product/csv_template/{type}", requirements={"type" = "\w+"}, name="admin_product_csv_template")
     *
     * @param $type
     *
     * @return StreamedResponse
     */
    public function csvTemplate(Request $request, $type)
    {
        if ($type == 'product') {
            $headers = $this->getProductCsvHeader();
            $filename = 'product.csv';
        } elseif ($type == 'category') {
            $headers = $this->getCategoryCsvHeader();
            $filename = 'category.csv';
        } else {
            throw new NotFoundHttpException();
        }

        return $this->sendTemplateResponse($request, array_keys($headers), $filename);
    }

    /**
     * 登録、更新時のエラー画面表示
     *
     * @param FormInterface $form
     * @param array $headers
     * @param bool $rollback
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    protected function renderWithError($form, $headers, $rollback = true)
    {
        if ($this->hasErrors()) {
            if ($rollback) {
                $this->entityManager->getConnection()->rollback();
            }
        }

        $this->removeUploadedFile();

        return [
            'form' => $form->createView(),
            'headers' => $headers,
            'errors' => $this->errors,
        ];
    }

    /**
     * 商品画像の削除、登録
     *
     * @param $row
     * @param Product $Product
     * @param CsvImportService $data
     * @param $headerByKey
     */
    protected function createProductImage($row, Product $Product, $data, $headerByKey)
    {
        if (!array_key_exists('product_image', $headerByKey))
        return;

        if (!isset($row[$headerByKey['product_image']])) {
            return;
        }
        if (StringUtil::isNotBlank($row[$headerByKey['product_image']])) {
            // 画像の削除
            $ProductImages = $Product->getProductImage();
            foreach ($ProductImages as $ProductImage) {
                $Product->removeProductImage($ProductImage);
                $this->entityManager->remove($ProductImage);
            }

            // 画像の登録
            $images = explode(',', $row[$headerByKey['product_image']]);

            $sortNo = 1;

            $pattern = "/\\$|^.*.\.\\\.*|\/$|^.*.\.\/\.*/";
            foreach ($images as $image) {
                $fileName = StringUtil::trimAll($image);

                // 商品画像名のフォーマットチェック
                if (strlen($fileName) > 0 && preg_match($pattern, $fileName)) {
                    $message = trans('admin.common.csv_invalid_image', ['%line%' => $data->key() + 1, '%name%' => $headerByKey['product_image']]);
                    $this->addErrors($message);
                } else {
                    // 空文字は登録対象外
                    if (!empty($fileName)) {
                        $ProductImage = new ProductImage();
                        $ProductImage->setFileName($fileName);
                        $ProductImage->setProduct($Product);
                        $ProductImage->setSortNo($sortNo);

                        $Product->addProductImage($ProductImage);
                        $sortNo++;
                        $this->entityManager->persist($ProductImage);
                    }
                }
            }
        }
    }

    /**
     * 商品カテゴリの削除、登録
     *
     * @param $row
     * @param Product $Product
     * @param CsvImportService $data
     * @param $headerByKey
     */
    protected function createProductCategory($row, Product $Product, $data, $headerByKey)
    {
        if (!isset($row[$headerByKey['product_category']])) {
            return;
        }
        // カテゴリの削除
        $ProductCategories = $Product->getProductCategories();
        foreach ($ProductCategories as $ProductCategory) {
            $Product->removeProductCategory($ProductCategory);
            $this->entityManager->remove($ProductCategory);
            $this->entityManager->flush();
        }

        if (StringUtil::isNotBlank($row[$headerByKey['product_category']])) {
            // カテゴリの登録
            $categories = explode(';', $row[$headerByKey['product_category']]);
            $sortNo = 1;
            $categoriesIdList = [];
            foreach ($categories as $category) {
                $line = $data->key() + 1;
                if (preg_match('/^\d+$/', $category)) {
                    switch($category) {
                      case 1:
                        $Category = $this->categoryRepository->findOneBy(['name' => '通常くじ']);
                        break;
                        
                      case 2:
                        $Category = $this->categoryRepository->findOneBy(['name' => '商品']);
                        break;
                        
                      case 4:
                        $Category = $this->categoryRepository->findOneBy(['name' => '新商品']);
                        break;
                        
                      case 9:
                        $Category = $this->categoryRepository->findOneBy(['name' => 'まとめ買いくじ']);
                        break;
                        
                      case 11:
                        $Category = $this->categoryRepository->findOneBy(['name' => '大人買いくじ']);
                        break;
                        
                      case 12:
                        $Category = $this->categoryRepository->findOneBy(['name' => '確定くじ']);
                        break;
                    }

                    if (!$Category) {
                        $message = trans('admin.common.csv_invalid_not_found_target', [
                            '%line%' => $line,
                            '%name%' => $headerByKey['product_category'],
                            '%target_name%' => $category,
                        ]);
                        $this->addErrors($message);
                    } else {
                        foreach ($Category->getPath() as $ParentCategory) {
                            if (!isset($categoriesIdList[$ParentCategory->getId()])) {
                                $ProductCategory = $this->makeProductCategory($Product, $ParentCategory, $sortNo);
                                $this->entityManager->persist($ProductCategory);
                                $sortNo++;

                                $Product->addProductCategory($ProductCategory);
                                $categoriesIdList[$ParentCategory->getId()] = true;
                            }
                        }
                        if (!isset($categoriesIdList[$Category->getId()])) {
                            $ProductCategory = $this->makeProductCategory($Product, $Category, $sortNo);
                            $sortNo++;
                            $this->entityManager->persist($ProductCategory);
                            $Product->addProductCategory($ProductCategory);
                            $categoriesIdList[$Category->getId()] = true;
                        }
                    }
                } else {
                    $message = trans('admin.common.csv_invalid_not_found_target', [
                        '%line%' => $line,
                        '%name%' => $headerByKey['product_category'],
                        '%target_name%' => $category,
                    ]);
                    $this->addErrors($message);
                }
            }
        }
    }

    /**
     * タグの登録
     *
     * @param array $row
     * @param Product $Product
     * @param CsvImportService $data
     */
    protected function createProductTag($row, Product $Product, $data, $headerByKey)
    {
        if (!isset($row[$headerByKey['product_tag']])) {
            return;
        }
        // タグの削除
        $ProductTags = $Product->getProductTag();
        foreach ($ProductTags as $ProductTag) {
            $Product->removeProductTag($ProductTag);
            $this->entityManager->remove($ProductTag);
        }

        if (StringUtil::isNotBlank($row[$headerByKey['product_tag']])) {
            // タグの登録
            $tags = explode(',', $row[$headerByKey['product_tag']]);
            foreach ($tags as $tag_id) {
                $Tag = null;
                if (preg_match('/^\d+$/', $tag_id)) {
                    $Tag = $this->tagRepository->find($tag_id);

                    if ($Tag) {
                        $ProductTags = new ProductTag();
                        $ProductTags
                            ->setProduct($Product)
                            ->setTag($Tag);

                        $Product->addProductTag($ProductTags);

                        $this->entityManager->persist($ProductTags);
                    }
                }
                if (!$Tag) {
                    $message = trans('admin.common.csv_invalid_not_found_target', [
                        '%line%' => $data->key() + 1,
                        '%name%' => $headerByKey['product_tag'],
                        '%target_name%' => $tag_id,
                    ]);
                    $this->addErrors($message);
                }
            }
        }
    }

    /**
     * 商品規格分類1、商品規格分類2がnullとなる商品規格情報を作成
     *
     * @param $row
     * @param Product $Product
     * @param CsvImportService $data
     * @param $headerByKey
     * @param null $ClassCategory1
     * @param null $ClassCategory2
     *
     * @return ProductClass
     */
    protected function createProductClass($row, Product $Product, $data, $headerByKey, $ClassCategory1 = null, $ClassCategory2 = null)
    {
        // 規格分類1、規格分類2がnullとなる商品を作成
        $ProductClass = new ProductClass();
        $ProductClass->setProduct($Product);
        $ProductClass->setVisible(true);

        $line = $data->key() + 1;
        $ProductClass->setSaleType($this->saleTypeRepository->find(\Eccube\Entity\Master\SaleType::SALE_TYPE_NORMAL));

        if (isset($row[$headerByKey['delivery_date']]) && StringUtil::isNotBlank($row[$headerByKey['delivery_date']])) {
            if (preg_match('/^\d+$/', $row[$headerByKey['delivery_date']])) {
                $DeliveryDuration = $this->deliveryDurationRepository->find($row[$headerByKey['delivery_date']]);
                if (!$DeliveryDuration) {
                    $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['delivery_date']]);
                    $this->addErrors($message);
                } else {
                    $ProductClass->setDeliveryDuration($DeliveryDuration);
                }
            } else {
                $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['delivery_date']]);
                $this->addErrors($message);
            }
        }

        if (isset($row[$headerByKey['product_code']]) && StringUtil::isNotBlank($row[$headerByKey['product_code']])) {
            $ProductClass->setCode(StringUtil::trimAll($row[$headerByKey['product_code']]));
        } else {
            $ProductClass->setCode(null);
        }

        $ProductClass->setStockUnlimited(false);
        // 在庫数が設定されていなければエラー
        if (isset($row[$headerByKey['stock']]) && StringUtil::isNotBlank($row[$headerByKey['stock']])) {
            $stock = str_replace(',', '', $row[$headerByKey['stock']]);
            if (preg_match('/^\d+$/', $stock) && $stock >= 0) {
                $ProductClass->setStock($stock);
            } else {
              $ProductClass->setStockUnlimited(true);
            }
        } else {
          $ProductClass->setStockUnlimited(true);
        }

        if (isset($row[$headerByKey['sale_limit']]) && StringUtil::isNotBlank($row[$headerByKey['sale_limit']])) {
            $saleLimit = str_replace(',', '', $row[$headerByKey['sale_limit']]);
            if (preg_match('/^\d+$/', $saleLimit) && $saleLimit >= 0) {
                $ProductClass->setSaleLimit($saleLimit);
            } else {
                $message = trans('admin.common.csv_invalid_greater_than_zero', ['%line%' => $line, '%name%' => $headerByKey['sale_limit']]);
                $this->addErrors($message);
            }
        }

        if (isset($row[$headerByKey['price01']]) && StringUtil::isNotBlank($row[$headerByKey['price01']])) {
            $price01 = str_replace(',', '', $row[$headerByKey['price01']]);
            $errors = $this->validator->validate($price01, new GreaterThanOrEqual(['value' => 0]));
            if ($errors->count() === 0) {
                $ProductClass->setPrice01($price01);
            } else {
                $message = trans('admin.common.csv_invalid_greater_than_zero', ['%line%' => $line, '%name%' => $headerByKey['price01']]);
                $this->addErrors($message);
            }
        }

        if (isset($row[$headerByKey['price02']]) && StringUtil::isNotBlank($row[$headerByKey['price02']])) {
            $price02 = str_replace(',', '', $row[$headerByKey['price02']]);
            $errors = $this->validator->validate($price02, new GreaterThanOrEqual(['value' => 0]));
            if ($errors->count() === 0) {
                $ProductClass->setPrice02($price02);
            } else {
                $message = trans('admin.common.csv_invalid_greater_than_zero', ['%line%' => $line, '%name%' => $headerByKey['price02']]);
                $this->addErrors($message);
            }
        } else {
            $message = trans('admin.common.csv_invalid_required', ['%line%' => $line, '%name%' => $headerByKey['price02']]);
            $this->addErrors($message);
        }

        
        if ($this->BaseInfo->isOptionProductDeliveryFee()) {
          if (isset($row[$headerByKey['delivery_fee']]) && StringUtil::isNotBlank($row[$headerByKey['delivery_fee']])) {
              $deliveryFee = $row[$headerByKey['delivery_fee']];
              if ($deliveryFee == 1)
                $ProductClass->setDeliveryFee(1000);
              else if ($deliveryFee == 2)
                $ProductClass->setDeliveryFee(500);
              else if ($deliveryFee == 3)
              $ProductClass->setDeliveryFee(0);
          }
        }
        

        if (isset($row[$headerByKey['remain_status']]) && StringUtil::isNotBlank($row[$headerByKey['remain_status']])) {
          $ProductClass->setRemainStatus(StringUtil::trimAll($row[$headerByKey['remain_status']]) + 1);
        } else {
            $ProductClass->setRemainStatus(1);
        }

        $Product->addProductClass($ProductClass);
        $ProductStock = new ProductStock();
        $ProductClass->setProductStock($ProductStock);
        $ProductStock->setProductClass($ProductClass);

        if (!$ProductClass->isStockUnlimited()) {
            $ProductStock->setStock($ProductClass->getStock());
        } else {
            // 在庫無制限時はnullを設定
            $ProductStock->setStock(null);
        }

        $this->entityManager->persist($ProductClass);
        $this->entityManager->persist($ProductStock);

        return $ProductClass;
    }

    /**
     * 商品規格情報を更新
     *
     * @param $row
     * @param Product $Product
     * @param ProductClass $ProductClass
     * @param CsvImportService $data
     *
     * @return ProductClass
     */
    protected function updateProductClass($row, Product $Product, ProductClass $ProductClass, $data, $headerByKey)
    {
        $ProductClass->setProduct($Product);

        $line = $data->key() + 1;
        if ($row[$headerByKey['sale_type']] == '') {
            $message = trans('admin.common.csv_invalid_required', ['%line%' => $line, '%name%' => $headerByKey['sale_type']]);
            $this->addErrors($message);
        } else {
            if (preg_match('/^\d+$/', $row[$headerByKey['sale_type']])) {
                $SaleType = $this->saleTypeRepository->find($row[$headerByKey['sale_type']]);
                if (!$SaleType) {
                    $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['sale_type']]);
                    $this->addErrors($message);
                } else {
                    $ProductClass->setSaleType($SaleType);
                }
            } else {
                $message = trans('admin.common.csv_invalid_required', ['%line%' => $line, '%name%' => $headerByKey['sale_type']]);
                $this->addErrors($message);
            }
        }

        // 規格分類1、2をそれぞれセットし作成
        if ($row[$headerByKey['class_category1']] != '') {
            if (preg_match('/^\d+$/', $row[$headerByKey['class_category1']])) {
                $ClassCategory = $this->classCategoryRepository->find($row[$headerByKey['class_category1']]);
                if (!$ClassCategory) {
                    $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['class_category1']]);
                    $this->addErrors($message);
                } else {
                    $ProductClass->setClassCategory1($ClassCategory);
                }
            } else {
                $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['class_category1']]);
                $this->addErrors($message);
            }
        }

        if ($row[$headerByKey['class_category2']] != '') {
            if (preg_match('/^\d+$/', $row[$headerByKey['class_category2']])) {
                $ClassCategory = $this->classCategoryRepository->find($row[$headerByKey['class_category2']]);
                if (!$ClassCategory) {
                    $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['class_category2']]);
                    $this->addErrors($message);
                } else {
                    $ProductClass->setClassCategory2($ClassCategory);
                }
            } else {
                $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['class_category2']]);
                $this->addErrors($message);
            }
        }

        if ($row[$headerByKey['delivery_date']] != '') {
            if (preg_match('/^\d+$/', $row[$headerByKey['delivery_date']])) {
                $DeliveryDuration = $this->deliveryDurationRepository->find($row[$headerByKey['delivery_date']]);
                if (!$DeliveryDuration) {
                    $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['delivery_date']]);
                    $this->addErrors($message);
                } else {
                    $ProductClass->setDeliveryDuration($DeliveryDuration);
                }
            } else {
                $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['delivery_date']]);
                $this->addErrors($message);
            }
        }

        if (StringUtil::isNotBlank($row[$headerByKey['product_code']])) {
            $ProductClass->setCode(StringUtil::trimAll($row[$headerByKey['product_code']]));
        } else {
            $ProductClass->setCode(null);
        }

        if (!isset($row[$headerByKey['stock_unlimited']])
            || StringUtil::isBlank($row[$headerByKey['stock_unlimited']])
            || $row[$headerByKey['stock_unlimited']] == (string) Constant::DISABLED
        ) {
            $ProductClass->setStockUnlimited(false);
            // 在庫数が設定されていなければエラー
            if ($row[$headerByKey['stock']] == '') {
                $message = trans('admin.common.csv_invalid_required', ['%line%' => $line, '%name%' => $headerByKey['stock']]);
                $this->addErrors($message);
            } else {
                $stock = str_replace(',', '', $row[$headerByKey['stock']]);
                if (preg_match('/^\d+$/', $stock) && $stock >= 0) {
                    $ProductClass->setStock($row[$headerByKey['stock']]);
                } else {
                    $message = trans('admin.common.csv_invalid_greater_than_zero', ['%line%' => $line, '%name%' => $headerByKey['stock']]);
                    $this->addErrors($message);
                }
            }
        } elseif ($row[$headerByKey['stock_unlimited']] == (string) Constant::ENABLED) {
            $ProductClass->setStockUnlimited(true);
            $ProductClass->setStock(null);
        } else {
            $message = trans('admin.common.csv_invalid_required', ['%line%' => $line, '%name%' => $headerByKey['stock_unlimited']]);
            $this->addErrors($message);
        }

        if ($row[$headerByKey['sale_limit']] != '') {
            $saleLimit = str_replace(',', '', $row[$headerByKey['sale_limit']]);
            if (preg_match('/^\d+$/', $saleLimit) && $saleLimit >= 0) {
                $ProductClass->setSaleLimit($saleLimit);
            } else {
                $message = trans('admin.common.csv_invalid_greater_than_zero', ['%line%' => $line, '%name%' => $headerByKey['sale_limit']]);
                $this->addErrors($message);
            }
        }

        if ($row[$headerByKey['price01']] != '') {
            $price01 = str_replace(',', '', $row[$headerByKey['price01']]);
            $errors = $this->validator->validate($price01, new GreaterThanOrEqual(['value' => 0]));
            if ($errors->count() === 0) {
                $ProductClass->setPrice01($price01);
            } else {
                $message = trans('admin.common.csv_invalid_greater_than_zero', ['%line%' => $line, '%name%' => $headerByKey['price01']]);
                $this->addErrors($message);
            }
        }

        if ($row[$headerByKey['price02']] == '') {
            $message = trans('admin.common.csv_invalid_required', ['%line%' => $line, '%name%' => $headerByKey['price02']]);
            $this->addErrors($message);
        } else {
            $price02 = str_replace(',', '', $row[$headerByKey['price02']]);
            $errors = $this->validator->validate($price02, new GreaterThanOrEqual(['value' => 0]));
            if ($errors->count() === 0) {
                $ProductClass->setPrice02($price02);
            } else {
                $message = trans('admin.common.csv_invalid_greater_than_zero', ['%line%' => $line, '%name%' => $headerByKey['price02']]);
                $this->addErrors($message);
            }
        }

        $ProductStock = $ProductClass->getProductStock();

        if (!$ProductClass->isStockUnlimited()) {
            $ProductStock->setStock($ProductClass->getStock());
        } else {
            // 在庫無制限時はnullを設定
            $ProductStock->setStock(null);
        }

        return $ProductClass;
    }

    /**
     * 登録、更新時のエラー画面表示
     */
    protected function addErrors($message)
    {
        $this->errors[] = $message;
    }

    /**
     * @return array
     */
    protected function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return boolean
     */
    protected function hasErrors()
    {
        return count($this->getErrors()) > 0;
    }

    /**
     * 商品登録CSVヘッダー定義
     *
     * @return array
     */
    protected function getProductCsvHeader()
    {
        return [
            trans('admin.product.product_csv.product_name_col') => [
                'id' => 'name',
                'description' => 'admin.product.product_csv.product_name_description',
                'required' => true,
            ],
            trans('説明') => [
                'id' => 'description_detail',
                'description' => 'admin.product.product_csv.description_detail_description',
                'required' => false,
            ],
            trans('カテゴリー') => [
                'id' => 'product_category',
                'description' => 'admin.product.product_csv.category_description',
                'required' => false,
            ],
            trans('発送日目安') => [
                'id' => 'delivery_date',
                'description' => 'admin.product.product_csv.delivery_duration_description',
                'required' => false,
            ],
            trans('SKUコード') => [
                'id' => 'product_code',
                'description' => 'admin.product.product_csv.product_code_description',
                'required' => false,
            ],
            trans('在庫数') => [
                'id' => 'stock',
                'description' => 'admin.product.product_csv.stock_description',
                'required' => false,
            ],
            trans('在庫状態') => [
                'id' => 'remain_status',
                'description' => '在庫状態',
                'required' => false,
            ],
            trans('購入制限数') => [
                'id' => 'sale_limit',
                'description' => 'admin.product.product_csv.sale_limit_description',
                'required' => false,
            ],
            trans('通常価') => [
                'id' => 'price01',
                'description' => 'admin.product.product_csv.normal_price_description',
                'required' => false,
            ],
            trans('売価') => [
                'id' => 'price02',
                'description' => 'admin.product.product_csv.sale_price_description',
                'required' => true,
            ],
            trans('送料') => [
                'id' => 'delivery_fee',
                'description' => 'admin.product.product_csv.delivery_fee_description',
                'required' => false,
            ],
            trans('公開日時') => [
                'id' => 'update_date',
                'description' => '公開日時',
                'required' => false,
            ],
            trans('カスタムフィールド') => [
                'id' => 'winning_products',
                'description' => '当選商品',
                'required' => false,
            ],
        ];
    }

    /**
     * カテゴリCSVヘッダー定義
     */
    protected function getCategoryCsvHeader()
    {
        return [
            trans('admin.product.category_csv.category_id_col') => [
                'id' => 'id',
                'description' => 'admin.product.category_csv.category_id_description',
                'required' => false,
            ],
            trans('admin.product.category_csv.category_name_col') => [
                'id' => 'category_name',
                'description' => 'admin.product.category_csv.category_name_description',
                'required' => true,
            ],
            trans('admin.product.category_csv.parent_category_id_col') => [
                'id' => 'parent_category_id',
                'description' => 'admin.product.category_csv.parent_category_id_description',
                'required' => false,
            ],
            trans('admin.product.category_csv.delete_flag_col') => [
                'id' => 'category_del_flg',
                'description' => 'admin.product.category_csv.delete_flag_description',
                'required' => false,
            ],
        ];
    }

    /**
     * ProductCategory作成
     *
     * @param \Eccube\Entity\Product $Product
     * @param \Eccube\Entity\Category $Category
     * @param int $sortNo
     *
     * @return ProductCategory
     */
    private function makeProductCategory($Product, $Category, $sortNo)
    {
        $ProductCategory = new ProductCategory();
        $ProductCategory->setProduct($Product);
        $ProductCategory->setProductId($Product->getId());
        $ProductCategory->setCategory($Category);
        $ProductCategory->setCategoryId($Category->getId());

        return $ProductCategory;
    }
}
