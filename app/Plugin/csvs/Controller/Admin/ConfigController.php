<?php

namespace Plugin\csvs\Controller\Admin;

use Eccube\Controller\Admin\AbstractCsvImportController;
use Plugin\csvs\Form\Type\Admin\ConfigType;
use Plugin\csvs\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Eccube\Form\Type\Admin\CsvImportType;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Eccube\Util\CacheUtil;
use Eccube\Util\StringUtil;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Eccube\Common\Constant;
use Symfony\Component\Filesystem\Filesystem;
use Eccube\Entity\Customer;
use Eccube\Entity\ProductClass;
use Eccube\Entity\Order;
use Eccube\Entity\Shipping;
use Eccube\Entity\Master\Pref;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\ProductRepository;
use Eccube\Entity\Master\CustomerStatus;
use Eccube\Entity\Master\Country;
use Symfony\Component\Routing\RouterInterface;
use Eccube\Service\OrderHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Eccube\Form\Type\Admin\OrderType;
use Eccube\Service\PurchaseFlow\Processor\OrderNoProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseException;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Event\EccubeEvents;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Event\EventArgs;
use Symfony\Component\HttpFoundation\Response;
use Plugin\PrizeShow\Entity\Config;
use Plugin\PrizeShow\Entity\PrizeList;
use Plugin\PrizesPerProduct\Entity\Config as PrizesPerProduct;
use Plugin\ProductAssist\Entity\Config as ProductAssist;
use Plugin\ProductAssistConfig\Entity\Config as ProductAssistConfig;

class ConfigController extends AbstractCsvImportController
{
    /**
     * @var ValidatorInterface
     */
    protected $validator;

    private $errors = [];

    protected $isSplitCsv = false;

    protected $csvFileNo = 1;

    protected $currentLineNo = 1;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var EncoderFactoryInterface
     */
    protected $encoderFactory;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;
    
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderNoProcessor
     */
    protected $orderNoProcessor;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * ConfigController constructor.
     *
     * @param ConfigRepository $configRepository
     * @param ValidatorInterface $validator
     * @param OrderHelper $orderHelper
     * @param OrderStatusRepository $orderStatusRepository
     * @param OrderRepository $orderRepository
     * @param PurchaseFlow $orderPurchaseFlow
     * @param OrderNoProcessor $orderNoProcessor
     */
    public function __construct(
        ConfigRepository $configRepository, 
        ValidatorInterface $validator,
        CustomerRepository $customerRepository,
        EncoderFactoryInterface $encoderFactory,
        ProductRepository $productRepository,
        OrderStatusRepository $orderStatusRepository,
        OrderRepository $orderRepository,
        PurchaseFlow $orderPurchaseFlow,
        OrderNoProcessor $orderNoProcessor,
        OrderHelper $orderHelper
    ) {
        $this->configRepository = $configRepository;
        $this->validator = $validator;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->orderRepository = $orderRepository;
        $this->encoderFactory = $encoderFactory;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->orderHelper = $orderHelper;
        $this->purchaseFlow = $orderPurchaseFlow;
        $this->orderNoProcessor = $orderNoProcessor;
    }

    /**
     *
     * @Route("/%eccube_admin_route%/csvs/member-info", name="csvs_admin_member_info")
     * @Template("@csvs/admin/config.twig")
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function csvMemberInfo(Request $request, CacheUtil $cacheUtil)
    {
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $headers = [];
        if ('POST' === $request->getMethod()) {
            $headers = $this->getMemberCsvHeader();

            $form->handleRequest($request);
            if ($form->isValid()) {
                $this->isSplitCsv = $form['is_split_csv']->getData();
                $this->csvFileNo = $form['csv_file_no']->getData();

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

                    $this->entityManager->getConfiguration()->setSQLLogger(null);
                    $this->entityManager->getConnection()->beginTransaction();
                    // CSVファイルの登録処理
                    foreach ($data as $row) {
                        $line = $this->convertLineNo($data->key() + 1);
                        $this->currentLineNo = $line;

                        $Customer = new Customer();

                        if (!StringUtil::isBlank($row[$headerByKey['old_mem_id']])) {
                            $Customer->setOldMemId(intval(StringUtil::trimAll($row[$headerByKey['old_mem_id']])));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['email']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['email']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setEmail(StringUtil::trimAll($row[$headerByKey['email']]));
                        }
                        
                        if (StringUtil::isBlank($row[$headerByKey['point']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['point']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setPoint(StringUtil::trimAll($row[$headerByKey['point']]));
                        }
                        
                        if (StringUtil::isBlank($row[$headerByKey['name01']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['name01']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setName01(StringUtil::trimAll($row[$headerByKey['name01']]));
                        }
                        
                        if (StringUtil::isBlank($row[$headerByKey['name02']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['name02']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setName02(StringUtil::trimAll($row[$headerByKey['name02']]));
                        }

                        if (!StringUtil::isBlank($row[$headerByKey['kana01']])) {
                            $Customer->setKana01(StringUtil::trimAll($row[$headerByKey['kana01']]));
                        }
                        
                        if (!StringUtil::isBlank($row[$headerByKey['kana02']])) {
                            $Customer->setKana02(StringUtil::trimAll($row[$headerByKey['kana02']]));
                        }
                        
                        if (StringUtil::isBlank($row[$headerByKey['postal_code']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['postal_code']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setPostalCode(StringUtil::trimAll($row[$headerByKey['postal_code']]));
                        }
                        
                        if (StringUtil::isBlank($row[$headerByKey['pref_id']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['pref_id']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $prefRepository = $this->getDoctrine()->getRepository(Pref::class);
                            $Customer->setPref( $prefRepository->findOneBy(['name' => StringUtil::trimAll($row[$headerByKey['pref_id']])]) );
                        }
                        
                        if (StringUtil::isBlank($row[$headerByKey['addr01']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['addr01']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setAddr01(StringUtil::trimAll($row[$headerByKey['addr01']]));
                        }
                        
                        if (!StringUtil::isBlank($row[$headerByKey['addr02']])) {
                            $Customer->setAddr02(StringUtil::trimAll($row[$headerByKey['addr02']]) . StringUtil::trimAll($row[$headerByKey['addr03']]));
                        }
                        
                        if (StringUtil::isBlank($row[$headerByKey['phone_number']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['phone_number']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setPhoneNumber(StringUtil::trimAll($row[$headerByKey['phone_number']]));
                        }
                        
                        if (!StringUtil::isBlank($row[$headerByKey['create_date']])) {
                            $Customer->setCreateDate(StringUtil::trimAll($row[$headerByKey['create_date']]));
                        } 

                        $encoder = $this->encoderFactory->getEncoder($Customer);

                        $Customer->setPassword($this->eccubeConfig['eccube_default_password']);
                        if ($Customer->getSalt() === null) {
                            $Customer->setSalt($encoder->createSalt());
                            $Customer->setSecretKey($this->customerRepository->getUniqueSecretKey());
                        }

                        $Customer->setStatus($this->getDoctrine()->getRepository(CustomerStatus::class)->find(2));

                        $this->entityManager->persist($Customer);
                        $this->entityManager->flush();
                    }
                    $this->entityManager->getConnection()->commit();

                    log_info('商品CSV登録完了');
                    if (!$this->isSplitCsv) {
                        $message = 'admin.common.csv_upload_complete';
                        $this->session->getFlashBag()->add('eccube.admin.success', $message);
                    }

                    $cacheUtil->clearDoctrineCache();
                }
            }
        }

        return $this->renderWithError($form, $headers);
    }

    /**
     *
     * @Route("/%eccube_admin_route%/csvs/member-meta", name="csvs_admin_member_meta")
     * @Template("@csvs/admin/config.twig")
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function csvMemberMeta(Request $request, CacheUtil $cacheUtil)
    {
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $headers = [];
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $this->isSplitCsv = $form['is_split_csv']->getData();
                $this->csvFileNo = $form['csv_file_no']->getData();

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

                    $this->entityManager->getConfiguration()->setSQLLogger(null);
                    $this->entityManager->getConnection()->beginTransaction();
                    // CSVファイルの登録処理
                    foreach ($data as $row) { 
                        $line = $this->convertLineNo($data->key() + 1);
                        $this->currentLineNo = $line;

                        if (!StringUtil::isBlank($row['member_id'])) {
                            $Customer = $this->customerRepository->findOneBy(['old_mem_id' => $row['member_id']]);
                            $countryRepository = $this->getDoctrine()->getRepository(Country::class);

                            if ($row['meta_value'] == 'JP' && !is_null($Customer)) {
                                $Customer->setCountry($countryRepository->findOneBy(['name' => '日本']));
                                $this->entityManager->persist($Customer);
                            }
                            
                        }
                    }
                    $this->entityManager->flush();
                    $this->entityManager->getConnection()->commit();

                    log_info('商品CSV登録完了');
                    if (!$this->isSplitCsv) {
                        $message = 'admin.common.csv_upload_complete';
                        $this->session->getFlashBag()->add('eccube.admin.success', $message);
                    }

                    $cacheUtil->clearDoctrineCache();
                }
            }
        }

        return $this->renderWithError($form, $headers);
    }    
    
    /**
    *
    * @Route("/%eccube_admin_route%/csvs/order-info", name="csvs_admin_order_info")
    * @Template("@csvs/admin/config.twig")
    *
    * @return array
    *
    * @throws \Doctrine\DBAL\ConnectionException
    * @throws \Doctrine\ORM\NoResultException
    */
   public function csvOrderInfo(Request $request, CacheUtil $cacheUtil, CsrfTokenManagerInterface $tokenManager)
   {
    $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
    $headers = [];

    if ('POST' === $request->getMethod()) {
        $headers = $this->getOrderCsvHeader();

        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->isSplitCsv = $form['is_split_csv']->getData();
            $this->csvFileNo = $form['csv_file_no']->getData();

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
                // $headerByKey = array_flip(array_map($getId, $headers));
                $columnHeaders = array_flip($columnHeaders); 

                $this->entityManager->getConfiguration()->setSQLLogger(null);
                $this->entityManager->getConnection()->beginTransaction();

                // CSVファイルの登録処理
                foreach ($data as $row) {
                    $line = $this->convertLineNo($data->key() + 1);
                    $this->currentLineNo = $line;

                    $prefRepository = $this->getDoctrine()->getRepository(Pref::class);

                    if (!StringUtil::isBlank($row['mem_id'])) {
                        $Customer = $this->customerRepository->findOneBy(['old_mem_id' => intval($row['mem_id'])]);

                        if (is_null($Customer)) continue;
                    }

                    $order_email = '';
                    $order_name1 = '';
                    $order_name2 = '';
                    $order_name3 = '';
                    $order_name4 = '';
                    $order_zip = '';
                    $order_email = '';
                    $order_pref = '';
                    $order_address1 = '';
                    $order_address2 = '';
                    $order_address3 = '';
                    $order_tel = '';
                    $order_fax = '';
                    $order_note = '';
                    $order_usedpoint = '0';

                    if (!StringUtil::isBlank($row['order_email'])) {
                        $order_email = $row['order_email'];
                    }

                    if (!StringUtil::isBlank($row['order_name1'])) {
                        $order_name1 = $row['order_name1'];
                    }

                    if (!StringUtil::isBlank($row['order_name2'])) {
                        $order_name2 = $row['order_name2'];
                    }

                    if (!StringUtil::isBlank($row['order_name3'])) {
                        $order_name3 = $row['order_name3'];
                    }

                    if (!StringUtil::isBlank($row['order_name4'])) {
                        $order_name4 = $row['order_name4'];
                    }

                    if (!StringUtil::isBlank($row['order_zip'])) {
                        $order_zip = $row['order_zip'];
                    }

                    if (!StringUtil::isBlank($row['order_email'])) {
                        $order_email = $row['order_email'];
                    }

                    if (!StringUtil::isBlank($row['order_pref'])) {
                        $order_pref = $prefRepository->findOneBy(['name' =>  $row['order_pref']])->getId();
                    }

                    if (!StringUtil::isBlank($row['order_address1'])) {
                        $order_address1 = $row['order_address1'];
                    }

                    if (!StringUtil::isBlank($row['order_address2'])) {
                        $order_address2 = $row['order_address2'];
                    }

                    if (!StringUtil::isBlank($row['order_address3'])) {
                        $order_address3 = $row['order_address3'];
                    }

                    if (!StringUtil::isBlank($row['order_tel'])) {
                        $order_tel = $row['order_tel'];
                    }

                    if (!StringUtil::isBlank($row['order_fax'])) {
                        $order_fax = $row['order_fax'];
                    }

                    $OrderItems = [];
                    if (!StringUtil::isBlank($row['order_cart'])) {
                        $temp = unserialize($row['order_cart']); 

                        foreach($temp as $item) {
                            $productClassRepository = $this->getDoctrine()->getRepository(ProductClass::class);
                            $ProductClass = $productClassRepository->findOneBy(['code' => $item['sku']]);

                            if (isset($ProductClass)) {
                                array_push($OrderItems, [
                                    "product_name" => $ProductClass->getProduct()->getName(),
                                    "ProductClass" => $ProductClass->getId(),
                                    "order_item_type" => "1",
                                    "price" => $ProductClass->getProduct()->getPrice02IncTaxMin(),
                                    "quantity" => $item['quantity'],
                                    "tax_type" => ""
                                ]);
                            }
                        }
                    }

                    if (!StringUtil::isBlank($row['order_delivery'])) { 
                        $temp = unserialize($row['order_delivery']); 
                        $Shipping = [
                            'name' => [
                                'name01' =>  $temp['name1'],
                                'name02' =>  $temp['name2'],
                            ],
                            'kana' => [
                                'kana01' =>  $temp['name3'],
                                'kana02' =>  $temp['name4'],
                            ],
                            'postal_code' =>  $temp['zipcode'],
                            'address' => [
                                'pref' =>  $prefRepository->findOneBy(['name' => $temp['pref']])->getId(),
                                'addr01' =>  $temp['address1'],
                                'addr02' =>  $temp['address2'] . $temp['address3'],
                            ],
                            'phone_number' =>  $temp['tel'],
                            'company_name' => '',
                            'tracking_number' => '',
                            'Delivery' => '1',
                            'note' => '',
                            'shipping_delivery_date' => [
                              'year' => '',
                              'month' => '',
                              'day' => ''
                            ],
                            'DeliveryTime' => '',
                        ];
                    }

                    if (!StringUtil::isBlank($row['order_note'])) {
                        $order_note = $row['order_note'];
                    }

                    if (!StringUtil::isBlank($row['order_usedpoint'])) {
                        $order_usedpoint = $row['order_usedpoint'];
                    }

                    $request->request->remove('file_name');
                    $request->request->remove('file_no');
                    $request->request->remove('admin_csv_import');

                    $request->request->set('order', [
                        Constant::TOKEN_NAME => $tokenManager->getToken('admin_csv_import')->getValue(),
                        'return_link' => true,
                        'Payment' => '4',
                        'Customer' => $Customer->getId(),
                        'name' => [
                            'name01' => $order_name1,
                            'name02' => $order_name2,
                        ],
                        'kana' => [
                            'kana01' => $order_name3,
                            'kana02' => $order_name4,
                        ],
                        'postal_code' => $order_zip,
                        'address' => [
                            'pref' => $order_pref,
                            'addr01' => $order_address1,
                            'addr02' => $order_address2 . $order_address3,
                        ],
                        'email' => $order_email,
                        'phone_number' => $order_tel,
                        'company_name' => '',
                        'message' =>  '',
                        'Shipping' => $Shipping,
                        'OrderItems' => $OrderItems,
                        'use_point' => $order_usedpoint,
                        'old_id' => $row['ID'],
                        'note' => $order_note
                    ]);

                    $request->setMethod('POST');

                    if (count($OrderItems))
                        $this->forwardToRoute('csvs_admin_order');
                }
                $this->entityManager->flush();
                $this->entityManager->getConnection()->commit();

                log_info('商品CSV登録完了');
                if (!$this->isSplitCsv) {
                    $message = 'admin.common.csv_upload_complete';
                    $this->session->getFlashBag()->add('eccube.admin.success', $message);
                }

                $cacheUtil->clearDoctrineCache();
            }
        }
    }

    return $this->renderWithError($form, $headers);
   }    
   
   /**
   *
   * @Route("/%eccube_admin_route%/csvs/order-meta", name="csvs_admin_order_meta")
   * @Template("@csvs/admin/config.twig")
   *
   * @return array
   *
   * @throws \Doctrine\DBAL\ConnectionException
   * @throws \Doctrine\ORM\NoResultException
   */
  public function csvOrderMeta(Request $request, CacheUtil $cacheUtil)
  {
    $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
    $headers = [];
    if ('POST' === $request->getMethod()) {
        $headers = $this->getMemberCsvHeader();

        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->isSplitCsv = $form['is_split_csv']->getData();
            $this->csvFileNo = $form['csv_file_no']->getData();

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

                $this->entityManager->getConfiguration()->setSQLLogger(null);
                $this->entityManager->getConnection()->beginTransaction();
                // CSVファイルの登録処理
                foreach ($data as $row) {
                    $line = $this->convertLineNo($data->key() + 1);
                    $this->currentLineNo = $line;
                }
                $this->entityManager->flush();
                $this->entityManager->getConnection()->commit();

                log_info('商品CSV登録完了');
                if (!$this->isSplitCsv) {
                    $message = 'admin.common.csv_upload_complete';
                    $this->session->getFlashBag()->add('eccube.admin.success', $message);
                }

                $cacheUtil->clearDoctrineCache();
            }
        }
    }

    return $this->renderWithError($form, $headers);
  }    
  
  /**
  *
  * @Route("/%eccube_admin_route%/csvs/prize-product", name="csvs_admin_prize_product")
  * @Template("@csvs/admin/config.twig")
  *
  * @return array
  *
  * @throws \Doctrine\DBAL\ConnectionException
  * @throws \Doctrine\ORM\NoResultException
  */
 public function csvPrizeProduct(Request $request, CacheUtil $cacheUtil)
 {
    $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
    $headers = [];
    if ('POST' === $request->getMethod()) {
        $headers = $this->getMemberCsvHeader();

        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->isSplitCsv = $form['is_split_csv']->getData();
            $this->csvFileNo = $form['csv_file_no']->getData();

            $formFile = $form['import_file']->getData();
            if (!empty($formFile)) {
                log_info('商品CSV登録開始');
                $data = $this->getImportData($formFile);

                if ($data === false) {
                    $this->addErrors(trans('admin.common.csv_invalid_format'));

                    return $this->renderWithError($form, $headers, false);
                }

                $PrizeListRepo = $this->getDoctrine()->getRepository(PrizeList::class);
                $ProductAssistRepo = $this->getDoctrine()->getRepository(ProductAssist::class);
                $ProductAssistConfigRepo = $this->getDoctrine()->getRepository(ProductAssistConfig::class);
                $PrizesPerProductRepo = $this->getDoctrine()->getRepository(PrizesPerProduct::class);
        
                // CSVファイルの登録処理
                foreach ($data as $row) {
                    $Order = $this->orderRepository->findOneBy(['old_id' => $row['order_id']]);
                    if ($Order == null) continue;
                    if ($row['order_id'] != $this->session->get('csvs.kuji.cur_order_id')) {
                        foreach($Order->getMergedProductOrderItems() as $OrderItem) {
                            $Product = $OrderItem->getProduct();
                            $ProductAssist = $this->getDoctrine()->getRepository(ProductAssist::class)->findOneBy(['id' => $Product->product_assist_id]);
                            $ProductAssistConfs = $ProductAssist->getSettings();
                            $Categories = $Product->getProductCategories();

                            $lotteryConfs = [];
                            $confirmedConfs = [];
                            $bulkConfs = [];
                            
                            foreach($ProductAssistConfs as $conf)
                            {
                                if ($conf->getGroupId() == 1)
                                    array_push($bulkConfs, $conf);
                                if ($conf->getGroupId() == 2)
                                    array_push($confirmedConfs, $conf);
                                if ($conf->getGroupId() == 3)
                                    array_push($lotteryConfs, $conf);
                            }
                            foreach($Categories as $Category)
                            {	
                                if ($Category->getCategory()->getName() == "通常くじ")
                                {
                                    foreach($lotteryConfs as $conf)
                                    {
                                        $prizeList = $PrizeListRepo->find($conf->getSetOption());
                                        $Prizes = $prizeList->getSettings()->getValues();
                                        foreach($Prizes as $Prize)
                                        {
                                            $PrizePerProduct = new PrizesPerProduct();
                                            $PrizePerProduct->setPrizeOpen(true);
                            
                                            $PrizePerProduct->setOrderId($Order->getId());
                                            $PrizePerProduct->setProductId($Product->getId());
                                            $PrizePerProduct->setPrizeImage($Prize->getImage());
                                            $PrizePerProduct->setPrizeName($Prize->getName());
                                            $PrizePerProduct->setPrizeGrade($conf->getGrade());
                                            $PrizePerProduct->setPrizeClassName($conf->getClassName());
                                            $PrizePerProduct->setPrizeColor($conf->getColorName());
                                            $PrizePerProduct->setPrizeListId($prizeList->getId()); 
                                            
                                            $this->entityManager->persist($PrizePerProduct);
                                        }
                                    }
                                } else if ($Category->getCategory()->getName() == "確定くじ")
                                {
                                    foreach($confirmedConfs as $conf)
                                    {
                                        $prizeList = $PrizeListRepo->find($conf->getSetOption());
                                        $Prizes = $prizeList->getSettings()->getValues();
                                        foreach($Prizes as $Prize)
                                        {
                                            $PrizePerProduct = new PrizesPerProduct();
                                            $PrizePerProduct->setPrizeOpen(true);
                            
                                            $PrizePerProduct->setOrderId($Order->getId());
                                            $PrizePerProduct->setProductId($Product->getId());
                                            $PrizePerProduct->setPrizeImage($Prize->getImage());
                                            $PrizePerProduct->setPrizeName($Prize->getName());
                                            $PrizePerProduct->setPrizeGrade($conf->getGrade());
                                            $PrizePerProduct->setPrizeClassName($conf->getClassName());
                                            $PrizePerProduct->setPrizeColor($conf->getColorName());
                                            $PrizePerProduct->setPrizeListId($prizeList->getId()); 
                                            
                                            $this->entityManager->persist($PrizePerProduct);
                                        }
                                    }
                    
                                    foreach($lotteryConfs as $conf)
                                    {
                                        $prizeList = $PrizeListRepo->find($conf->getSetOption());
                                        $Prizes = $prizeList->getSettings()->getValues();
                                        foreach($Prizes as $Prize)
                                        {
                                            $PrizePerProduct = new PrizesPerProduct();
                                            $PrizePerProduct->setPrizeOpen(true);
                            
                                            $PrizePerProduct->setOrderId($Order->getId());
                                            $PrizePerProduct->setProductId($Product->getId());
                                            $PrizePerProduct->setPrizeImage($Prize->getImage());
                                            $PrizePerProduct->setPrizeName($Prize->getName());
                                            $PrizePerProduct->setPrizeGrade($conf->getGrade());
                                            $PrizePerProduct->setPrizeClassName($conf->getClassName());
                                            $PrizePerProduct->setPrizeColor($conf->getColorName());
                                            $PrizePerProduct->setPrizeListId($prizeList->getId()); 
                                            
                                            $this->entityManager->persist($PrizePerProduct);
                                        }
                                    }
                                } else if ($Category->getCategory()->getName() == "大人買いくじ")
                                {
                                    foreach($lotteryConfs as $conf)
                                    {
                                        $text = "";
                                        $prizeList = $PrizeListRepo->find($conf->getSetOption());
                                        $Prizes = $prizeList->getSettings()->getValues();
                    
                                        foreach($Prizes as $Prize)
                                        {
                                            $remain = $Prize->getRemain();
                                            $Prize->setRemain($remain - 1);
                    
                                            if($remain == 0)
                                                $Prize->setRemain(0);
                    
                                            $text .= $Prize->getName()."###";  
                                        }                    
                                        $PrizePerProduct = new PrizesPerProduct();
                        
                                        $PrizePerProduct->setOrderId($Order->getId());
                                        $PrizePerProduct->setProductId($Product->getId());
                                        $PrizePerProduct->setPrizeName($prizeList->getName());
                                        $PrizePerProduct->setPrizeImage($text);
                                        $PrizePerProduct->setPrizeGrade($conf->getGrade());
                                        $PrizePerProduct->setPrizeClassName($conf->getClassName());
                                        $PrizePerProduct->setPrizeOpen(true);
                                        $PrizePerProduct->setPrizeColor($conf->getColorName());
                                        $PrizePerProduct->setPrizeListId($prizeList->getId());
                                        
                                        $this->entityManager->persist($PrizePerProduct);
                                    }
                                } else if ($Category->getCategory()->getName() == "まとめ買いくじ")
                                {
                                    $PrizePerProduct = new PrizesPerProduct();
                                    $PrizePerProduct->setOrderId($Order->getId());
                                    $PrizePerProduct->setProductId($Product->getId());
                    
                                    $text = "";
                                    $text_grade = "";
                    
                                    foreach($bulkConfs as $conf)
                                    {
                                        $prizeList = $PrizeListRepo->find(['id' => $conf->getSetOption()]);
                                        $text .= $prizeList->getName().": ".$conf->getSetCount()."個###";
                                        $text_grade .= $conf->getGrade().$conf->getClassName().';';
                                    }
                                    $PrizePerProduct->setPrizeName($text);
                                    if (count($Product->getProductImage()) > 2)
                                        $PrizePerProduct->setPrizeImage($Product->getProductImage()[2]->getFileName());
                                    $PrizePerProduct->setPrizeOpen(true);
                                    $PrizePerProduct->setPrizeGrade($text_grade);
                                    $PrizePerProduct->setPrizeColor($OrderItem->getQuantity());
                    
                                    $this->entityManager->persist($PrizePerProduct);
                                }
                                $this->entityManager->flush();
                                $this->entityManager->clear();
                            }
                        }
                        $text = $this->session->get('csvs.kuji.prize');
                        
                        for($i = 0; $i < ceil(strlen($text) / PRIZE_NAME_SIZE); $i++)
                        {
                            $PrizePerProduct = new PrizesPerProduct();
                            $PrizePerProduct->setPrizeOpen(true);
                            $PrizePerProduct->setOrderId($Order->getId());
                            // $PrizePerProduct->setProductId($Product->getId());
                            $PrizePerProduct->setPrizeGrade(0);
            
                            $PrizePerProduct->setPrizeName(substr($text, $i * PRIZE_NAME_SIZE, PRIZE_NAME_SIZE));
            
                            $this->entityManager->persist($PrizePerProduct);
                            $this->entityManager->flush();
                            $this->entityManager->clear();
                        }
                        $this->session->set('csvs.kuji.cur_order_id', $row['order_id']);
                    } else {
                        $text = $this->session->get('csvs.kuji.prize');
                        if (strlen($text) > 200) {
                            for($i = 0; $i < ceil(strlen($text) / PRIZE_NAME_SIZE); $i++)
                            {
                                $PrizePerProduct = new PrizesPerProduct();
                                $PrizePerProduct->setPrizeOpen(true);
                                $PrizePerProduct->setOrderId($Order->getId());
                                // $PrizePerProduct->setProductId($Product->getId());
                                $PrizePerProduct->setPrizeGrade(0);
                
                                $PrizePerProduct->setPrizeName(substr($text, $i * PRIZE_NAME_SIZE, PRIZE_NAME_SIZE));
                
                                $this->entityManager->persist($PrizePerProduct);
                                $this->entityManager->flush();
                                $this->entityManager->clear();
                            }
                            $text = '';
                        }

                        $PrizePerProductList = $PrizesPerProductRepo->findBy(['orderId' => $Order->getId()]);
                        
                        foreach($PrizePerProductList as $item) {
                            if($item->getPrizeName() == $row['prize'])
                            {
                                $text .= $item->getId();

                                if ($row['open'] == 0)
                                    $text .= ',0;';
                                else  $text .= ',1;';
                                break;
                            }
                        }
                        $this->session->set('csvs.kuji.prize', $text);
                    }
                }
                $this->entityManager->flush();

                log_info('商品CSV登録完了');
                if (!$this->isSplitCsv) {
                    $message = 'admin.common.csv_upload_complete';
                    $this->session->getFlashBag()->add('eccube.admin.success', $message);
                }

                $cacheUtil->clearDoctrineCache();
            }
        }
    }

    return $this->renderWithError($form, $headers);
 }    
  
 /**
 *
 * @Route("/%eccube_admin_route%/csvs/welcart-page", name="csvs_admin_welcart_page")
 * @Template("@csvs/admin/config.twig")
 *
 * @return array
 *
 * @throws \Doctrine\DBAL\ConnectionException
 * @throws \Doctrine\ORM\NoResultException
 */
public function csvWelcartPage(Request $request, CacheUtil $cacheUtil)
{
   $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
   $headers = [];
   if ('POST' === $request->getMethod()) {
        $headers = $this->getMemberCsvHeader();

        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->isSplitCsv = $form['is_split_csv']->getData();
            $this->csvFileNo = $form['csv_file_no']->getData();

            $formFile = $form['import_file']->getData();
            if (!empty($formFile)) {
                $data = $this->getImportData($formFile);

                if ($data === false) {
                    $this->addErrors(trans('admin.common.csv_invalid_format'));
                    
                    return $this->renderWithError($form, $headers, false);
                }
                
                $postmeta = $this->session->get('csvs.postmeta.prize');
                $postmeta_keys = array_keys($postmeta);
            
                $arr = $this->session->get('csvs.post.prize'); 

                foreach ($data as $row) {
                    if (in_array( intval($row['ID']), $postmeta_keys )) $arr[$row['ID']] = $row['post_title'];
                }
                $this->session->set('csvs.post.prize', $arr);
            }
        }
   }

   return $this->renderWithError($form, $headers);
}   
  
/**
*
* @Route("/%eccube_admin_route%/csvs/welcart-page-meta", name="csvs_admin_welcart_page_meta")
* @Template("@csvs/admin/config.twig")
*
* @return array
*
* @throws \Doctrine\DBAL\ConnectionException
* @throws \Doctrine\ORM\NoResultException
*/
public function csvWelcartPageMeta(Request $request, CacheUtil $cacheUtil)
{
  $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
  $headers = [];

    if ('POST' === $request->getMethod()) {
        $headers = $this->getMemberCsvHeader();

        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->isSplitCsv = $form['is_split_csv']->getData();
            $this->csvFileNo = $form['csv_file_no']->getData();

            $formFile = $form['import_file']->getData();

            if (!empty($formFile)) {
                $data = $this->getImportData($formFile);

                if ($data === false) {
                    $this->addErrors(trans('admin.common.csv_invalid_format'));
                    
                    return $this->renderWithError($form, $headers, false);
                }

                foreach ($data as $row) {
                    if ($row['meta_key'] == 'prize_item_group') {
                        $arr = $this->session->get('csvs.postmeta.prize');
                        $arr[$row['post_id']] = $row['meta_value'];
                        $arr = array_unique($arr);
                        $this->session->set('csvs.postmeta.prize', $arr);
                    }
                } 
            }
        }
  }
  return $this->renderWithError($form, $headers);
}   

    /**
     * Member登録CSVヘッダー定義
     *
     * @return array
     */
    protected function getMemberCsvHeader()
    {
        return [
            trans('ID') => [
                'id' => 'old_mem_id',
                'description' => '',
                'required' => false,
            ],
            trans('mem_email') => [
                'id' => 'email',
                'description' => '',
                'required' => false,
            ],
            trans('mem_point') => [
                'id' => 'point',
                'description' => '',
                'required' => false,
            ],
            trans('mem_name1') => [
                'id' => 'name01',
                'description' => '',
                'required' => false,
            ],
            trans('mem_name2') => [
                'id' => 'name02',
                'description' => '',
                'required' => false,
            ],
            trans('mem_name3') => [
                'id' => 'kana01',
                'description' => '',
                'required' => false,
            ],
            trans('mem_name4') => [
                'id' => 'kana02',
                'description' => '',
                'required' => false,
            ],
            trans('mem_zip') => [
                'id' => 'postal_code',
                'description' => '',
                'required' => false,
            ],
            trans('mem_pref') => [
                'id' => 'pref_id',
                'description' => '',
                'required' => false,
            ],
            trans('mem_address1') => [
                'id' => 'addr01',
                'description' => '',
                'required' => false,
            ],
            trans('mem_address2') => [
                'id' => 'addr02',
                'description' => '',
                'required' => false,
            ],
            trans('mem_address3') => [
                'id' => 'addr03',
                'description' => '',
                'required' => false,
            ],
            trans('mem_tel') => [
                'id' => 'phone_number',
                'description' => '',
                'required' => false,
            ],
            trans('mem_registered') => [
                'id' => 'create_date',
                'description' => '',
                'required' => false,
            ],
        ];
    }
    
    /**
     * Order登録CSVヘッダー定義
     *
     * @return array
     */
    protected function getOrderCsvHeader()
    {
        return [
            trans('ID') => [
                'id' => 'old_id',
                'description' => '',
                'required' => false,
            ],
            trans('mem_email') => [
                'id' => 'email',
                'description' => '',
                'required' => false,
            ],
            trans('mem_point') => [
                'id' => 'point',
                'description' => '',
                'required' => false,
            ],
            trans('mem_name1') => [
                'id' => 'name01',
                'description' => '',
                'required' => false,
            ],
            trans('mem_name2') => [
                'id' => 'name02',
                'description' => '',
                'required' => false,
            ],
            trans('mem_name3') => [
                'id' => 'kana01',
                'description' => '',
                'required' => false,
            ],
            trans('mem_name4') => [
                'id' => 'kana02',
                'description' => '',
                'required' => false,
            ],
            trans('mem_zip') => [
                'id' => 'postal_code',
                'description' => '',
                'required' => false,
            ],
            trans('mem_pref') => [
                'id' => 'pref_id',
                'description' => '',
                'required' => false,
            ],
            trans('mem_address1') => [
                'id' => 'addr01',
                'description' => '',
                'required' => false,
            ],
            trans('mem_address2') => [
                'id' => 'addr02',
                'description' => '',
                'required' => false,
            ],
            trans('mem_address3') => [
                'id' => 'addr03',
                'description' => '',
                'required' => false,
            ],
            trans('mem_tel') => [
                'id' => 'phone_number',
                'description' => '',
                'required' => false,
            ],
            trans('mem_registered') => [
                'id' => 'create_date',
                'description' => '',
                'required' => false,
            ],
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/csvs/csv_flag", name="admin_csvs_set_flag", methods={"POST"})
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function setFlag(Request $request)
    {
        $request->getSession()->set('csv_flag', $request->get('flag'));
        
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/%eccube_admin_route%/csvs/csv_split", name="admin_csvs_split", methods={"POST"})
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function splitCsv(Request $request)
    {
        $this->isTokenValid();

        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }

        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dir = $this->eccubeConfig['eccube_csv_temp_realdir'];
            if (!file_exists($dir)) {
                $fs = new Filesystem();
                $fs->mkdir($dir);
            }

            $data = $form['import_file']->getData();
            $src = new \SplFileObject($data->getRealPath());
            $src->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY);

            $fileNo = 1;
            $fileName = StringUtil::random(8);

            $dist = new \SplFileObject($dir.'/'.$fileName.$fileNo.'.csv', 'w');
            $header = $src->current();
            $src->next();
            $dist->fputcsv($header);

            $i = 0;
            while ($row = $src->current()) {
                $dist->fputcsv($row);
                $src->next();

                if (!$src->eof() && ++$i % $this->eccubeConfig['eccube_csv_split_lines'] === 0) {
                    $fileNo++;
                    $dist = new \SplFileObject($dir.'/'.$fileName.$fileNo.'.csv', 'w');
                    $dist->fputcsv($header);
                }
            }

            return $this->json(['success' => true, 'file_name' => $fileName, 'max_file_no' => $fileNo]);
        }

        return $this->json(['success' => false, 'message' => $form->getErrors(true, true)]);
    }


    /**
     * @Route("/%eccube_admin_route%/csvs/csv_pre_postmeta", name="admin_csvs_pre_postmeta", methods={"GET"})
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function prePostMeta(Request $request){
        $arr = [];
        $this->session->set('csvs.postmeta.prize', $arr);
        $this->session->set('csvs.post.prize', $arr);

        $this->session->set('csvs.kuji.cur_order_id', 0);
        $this->session->set('csvs.kuji.prize', '');
        return $this->json(['response' => 'ok']);
    }

    /**
     * @Route("/%eccube_admin_route%/csvs/csv_after_post", name="admin_csvs_after_post", methods={"GET"})
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function afterPost(Request $request){
        $postmeta_prize = $this->session->get('csvs.postmeta.prize');
        $post_prize = $this->session->get('csvs.post.prize');

        foreach($post_prize as $key => $item) {
            $prize_list = unserialize($postmeta_prize[$key]);

            $PrizeList = new PrizeList();
            $PrizeList->setName($item);
            $PrizeList->setOldId($key);

            foreach($prize_list as $it) {
                $Prize = new Config();

                $filepath = $this->eccubeConfig['eccube_temp_image_dir'] . '/' . $it['prize_item_image_id'];
                $r = file_put_contents($filepath, file_get_contents($it['prize_item_image']));
                print_r($r);

                $Prize->setImage($it['prize_item_image_id']);
                $Prize->setName($it['prize_item_title']);
                $Prize->setRemain($it['prize_item_select']);
                $Prize->setPrizeGroup($PrizeList);

                $PrizeList->addSetting($Prize);

                $this->entityManager->persist($Prize);
            }
            $this->entityManager->persist($PrizeList);
            $this->entityManager->flush();
        }
        return $this->json(['response' => 'ok']);
    }

    /**
     * @Route("/%eccube_admin_route%/csvs/csv_split_import", name="admin_csvs_split_import", methods={"POST"})
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function importCsv(Request $request, CsrfTokenManagerInterface $tokenManager)
    {
        $this->isTokenValid();

        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }

        $choices = $this->getCsvTempFiles();

        $filename = $request->get('file_name');
        if (!isset($choices[$filename])) {
            throw new BadRequestHttpException();
        }

        $path = $this->eccubeConfig['eccube_csv_temp_realdir'].'/'.$filename;
        $request->files->set('admin_csv_import', ['import_file' => new UploadedFile(
            $path,
            'import.csv',
            'text/csv',
            filesize($path),
            null,
            true
        )]);

        $request->setMethod('POST');
        $request->request->set('admin_csv_import', [
            Constant::TOKEN_NAME => $tokenManager->getToken('admin_csv_import')->getValue(),
            'is_split_csv' => true,
            'csv_file_no' => $request->get('file_no'),
        ]);

        
        $csv_flag = $request->getSession()->get('csv_flag');
        switch ($csv_flag) {
            case 'member-info':
                return $this->forwardToRoute('csvs_admin_member_info');
                
            case 'member-meta':
                return $this->forwardToRoute('csvs_admin_member_meta');
                
            case 'order-info':
                return $this->forwardToRoute('csvs_admin_order_info');
                
            case 'order-meta':
                return $this->forwardToRoute('csvs_admin_order_meta');
                
            case 'prize-product':
                return $this->forwardToRoute('csvs_admin_prize_product');
                
            case 'welcart-page':
                return $this->forwardToRoute('csvs_admin_welcart_page');
                
            case 'welcart-page-meta':
                return $this->forwardToRoute('csvs_admin_welcart_page_meta');
        }

    }

    /**
     * @Route("/%eccube_admin_route%/csvs/csv_split_cleanup", name="admin_csvs_split_cleanup", methods={"POST"})
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function cleanupSplitCsv(Request $request)
    {
        $this->isTokenValid();

        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }

        $files = $request->get('files', []);
        $choices = $this->getCsvTempFiles();

        foreach ($files as $filename) {
            if (isset($choices[$filename])) {
                unlink($choices[$filename]);
            } else {
                return $this->json(['success' => false]);
            }
        }

        return $this->json(['success' => true]);
    }

    protected function getCsvTempFiles()
    {
        $files = Finder::create()
            ->in($this->eccubeConfig['eccube_csv_temp_realdir'])
            ->name('*.csv')
            ->files();

        $choices = [];
        foreach ($files as $file) {
            $choices[$file->getBaseName()] = $file->getRealPath();
        }

        return $choices;
    }

    protected function convertLineNo($currentLineNo)
    {
        if ($this->isSplitCsv) {
            return ($this->eccubeConfig['eccube_csv_split_lines']) * ($this->csvFileNo - 1) + $currentLineNo;
        }

        return $currentLineNo;
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

        if ($this->isSplitCsv) {
            return $this->json([
                'success' => !$this->hasErrors(),
                'success_message' => trans('admin.common.csv_upload_line_success', [
                    '%from%' => $this->convertLineNo(2),
                    '%to%' => $this->currentLineNo, ]),
                'errors' => $this->errors,
                'error_message' => trans('admin.common.csv_upload_line_error', [
                    '%from%' => $this->convertLineNo(2), ]),
            ]);
        }

        return [
            'form' => $form->createView(),
            'headers' => $headers,
            'errors' => $this->errors,
        ];
    }

    /**
     * @return boolean
     */
    protected function hasErrors()
    {
        return count($this->getErrors()) > 0;
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
     * 受注登録.
     *
     * @Route("/%eccube_admin_route%/csvs/order", name="csvs_admin_order", methods={"GET", "POST"})
     */
    public function registeOrder(Request $request, RouterInterface $router)
    {
        $TargetOrder = null;
        $OriginOrder = null;

        // 空のエンティティを作成.
        $TargetOrder = new Order();
        $TargetOrder->addShipping((new Shipping())->setOrder($TargetOrder));

        $preOrderId = $this->orderHelper->createPreOrderId();
        $TargetOrder->setPreOrderId($preOrderId);

        // 編集前の受注情報を保持
        $OriginOrder = clone $TargetOrder;
        $OriginItems = new ArrayCollection();
        foreach ($TargetOrder->getOrderItems() as $Item) {
            $OriginItems->add($Item);
        }

        $builder = $this->formFactory->createBuilder(OrderType::class, $TargetOrder);
        $form = $builder->getForm();

        $purchaseContext = new PurchaseContext($OriginOrder, $OriginOrder->getCustomer());
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);

            if ($form['OrderItems']->isValid()) {
                $event = new EventArgs(
                    [
                        'builder' => $builder,
                        'OriginOrder' => $OriginOrder,
                        'TargetOrder' => $TargetOrder,
                        'PurchaseContext' => $purchaseContext,
                    ],
                    $request
                );
                $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_ORDER_EDIT_INDEX_PROGRESS, $event);
    
                $flowResult = $this->purchaseFlow->validate($TargetOrder, $purchaseContext);
    
                if ($flowResult->hasWarning()) {
                    foreach ($flowResult->getWarning() as $warning) {
                        $this->addWarning($warning->getMessage(), 'admin');
                    }
                }
    
                if ($flowResult->hasError()) {
                    foreach ($flowResult->getErrors() as $error) {
                        $this->addError($error->getMessage(), 'admin');
                    }
                }

                if (!$flowResult->hasError()) {
                    try {
                        $this->purchaseFlow->prepare($TargetOrder, $purchaseContext);
                        $this->purchaseFlow->commit($TargetOrder, $purchaseContext);
                    } catch (PurchaseException $e) {
                        $this->addError($e->getMessage(), 'admin');
                    }
                    $OldStatus = $OriginOrder->getOrderStatus();
                    $NewStatus = $TargetOrder->getOrderStatus();
                    // ステータスが変更されている場合はステートマシンを実行.
                    if ($TargetOrder->getId() && $OldStatus->getId() != $NewStatus->getId()) {
                        // 発送済に変更された場合は, 発送日をセットする.
                        if ($NewStatus->getId() == OrderStatus::DELIVERED) {
                            $TargetOrder->getShippings()->map(function (Shipping $Shipping) {
                                if (!$Shipping->isShipped()) {
                                    $Shipping->setShippingDate(new \DateTime());
                                }
                            });
                        }
                        // ステートマシンでステータスは更新されるので, 古いステータスに戻す.
                        $TargetOrder->setOrderStatus($OldStatus);
                        try {
                            // FormTypeでステータスの遷移チェックは行っているのでapplyのみ実行.
                            $this->orderStateMachine->apply($TargetOrder, $NewStatus);
                        } catch (ShoppingException $e) {
                            $this->addError($e->getMessage(), 'admin');
                        }
                    }
                    $this->entityManager->persist($TargetOrder);
                    $this->entityManager->flush();
                    foreach ($OriginItems as $Item) {
                        if ($TargetOrder->getOrderItems()->contains($Item) === false) {
                            $this->entityManager->remove($Item);
                        }
                    }
                    $this->entityManager->flush();
                    // 新規登録時はMySQL対応のためflushしてから採番
                    $this->orderNoProcessor->process($TargetOrder, $purchaseContext);
                    $this->entityManager->flush();
                    // 会員の場合、購入回数、購入金額などを更新
                    if ($Customer = $TargetOrder->getCustomer()) {
                        $this->orderRepository->updateOrderSummary($Customer);
                        $this->entityManager->flush();
                    }
                    $event = new EventArgs(
                        [
                            'form' => $form,
                            'OriginOrder' => $OriginOrder,
                            'TargetOrder' => $TargetOrder,
                            'Customer' => $Customer,
                        ],
                        $request
                    );
                    $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_ORDER_EDIT_INDEX_COMPLETE, $event);
                    $this->addSuccess('admin.common.save_complete');
                    log_info('受注登録完了', [$TargetOrder->getId()]);
                    if ($returnLink = $form->get('return_link')->getData()) {
                        try {
                            // $returnLinkはpathの形式で渡される. pathが存在するかをルータでチェックする.
                            $pattern = '/^'.preg_quote($request->getBasePath(), '/').'/';
                            $returnLink = preg_replace($pattern, '', $returnLink);
                            $result = $router->match($returnLink);
                            // パラメータのみ抽出
                            $params = array_filter($result, function ($key) {
                                return 0 !== \strpos($key, '_');
                            }, ARRAY_FILTER_USE_KEY);
                            // pathからurlを再構築してリダイレクト.
                            return $this->redirectToRoute($result['_route'], $params);
                        } catch (\Exception $e) {
                            // マッチしない場合はログ出力してスキップ.
                            log_warning('URLの形式が不正です。');
                        }
                    }
                    return;
                }
            }

        } 

        return;
    }
}
