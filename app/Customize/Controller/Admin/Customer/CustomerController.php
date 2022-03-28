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

namespace Customize\Controller\Admin\Customer;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\QueryBuilder;
use Eccube\Common\Constant;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\CsvType;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\Admin\SearchCustomerType;
use Eccube\Form\Type\Admin\CsvImportType;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\Master\PageMaxRepository;
use Eccube\Repository\Master\PrefRepository;
use Eccube\Repository\Master\SexRepository;
use Eccube\Service\CsvExportService;
use Eccube\Service\MailService;
use Eccube\Util\FormUtil;
use Knp\Component\Pager\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;

use Eccube\Entity\Master\CustomerStatus;
use Eccube\Repository\Master\CustomerStatusRepository;
use Eccube\Util\StringUtil;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Eccube\Util\CacheUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Eccube\Service\CsvImportService;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class CustomerController extends AbstractController
{
    /**
     * @var CsvExportService
     */
    protected $csvExportService;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var PrefRepository
     */
    protected $prefRepository;

    /**
     * @var SexRepository
     */
    protected $sexRepository;

    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    private $errors = [];

    /**
     * @var CustomerStatusRepository
     */
    protected $customerStatusRepository;

    /**
     * @var EncoderFactoryInterface
     */
    protected $encoderFactory;

    public function __construct(
        PageMaxRepository $pageMaxRepository,
        CustomerRepository $customerRepository,
        SexRepository $sexRepository,
        PrefRepository $prefRepository,
        MailService $mailService,
        CsvExportService $csvExportService,
        CustomerStatusRepository $customerStatusRepository,
        EncoderFactoryInterface $encoderFactory
    ) {
        $this->pageMaxRepository = $pageMaxRepository;
        $this->customerRepository = $customerRepository;
        $this->sexRepository = $sexRepository;
        $this->prefRepository = $prefRepository;
        $this->mailService = $mailService;
        $this->csvExportService = $csvExportService;
        $this->encoderFactory = $encoderFactory;
        $this->customerStatusRepository = $customerStatusRepository;
    }

    /**
     * @Route("/%eccube_admin_route%/customer", name="admin_customer")
     * @Route("/%eccube_admin_route%/customer/page/{page_no}", requirements={"page_no" = "\d+"}, name="admin_customer_page")
     * @Template("@admin/Customer/index.twig")
     */
    public function index(Request $request, $page_no = null, Paginator $paginator)
    {
        $csvForm = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $headers = $this->getCustomerCsvHeader();

        $session = $this->session;
        $builder = $this->formFactory->createBuilder(SearchCustomerType::class);

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_CUSTOMER_INDEX_INITIALIZE, $event);

        $searchForm = $builder->getForm();

        $pageMaxis = $this->pageMaxRepository->findAll();
        $pageCount = $session->get('eccube.admin.customer.search.page_count', $this->eccubeConfig['eccube_default_page_count']);
        $pageCountParam = $request->get('page_count');
        if ($pageCountParam && is_numeric($pageCountParam)) {
            foreach ($pageMaxis as $pageMax) {
                if ($pageCountParam == $pageMax->getName()) {
                    $pageCount = $pageMax->getName();
                    $session->set('eccube.admin.customer.search.page_count', $pageCount);
                    break;
                }
            }
        }

        if ('POST' === $request->getMethod()) {
            $searchForm->handleRequest($request);
            if ($searchForm->isValid()) {
                $searchData = $searchForm->getData();
                $page_no = 1;

                $session->set('eccube.admin.customer.search', FormUtil::getViewData($searchForm));
                $session->set('eccube.admin.customer.search.page_no', $page_no);
            } else {
                return [
                    'searchForm' => $searchForm->createView(),
                    'pagination' => [],
                    'pageMaxis' => $pageMaxis,
                    'page_no' => $page_no,
                    'page_count' => $pageCount,
                    'has_errors' => true,
                ];
            }
        } else {
            if (null !== $page_no || $request->get('resume')) {
                if ($page_no) {
                    $session->set('eccube.admin.customer.search.page_no', (int) $page_no);
                } else {
                    $page_no = $session->get('eccube.admin.customer.search.page_no', 1);
                }
                $viewData = $session->get('eccube.admin.customer.search', []);
            } else {
                $page_no = 1;
                $viewData = FormUtil::getViewData($searchForm);
                $session->set('eccube.admin.customer.search', $viewData);
                $session->set('eccube.admin.customer.search.page_no', $page_no);
            }
            $searchData = FormUtil::submitAndGetData($searchForm, $viewData);
        }

        /** @var QueryBuilder $qb */
        // $qb = $this->customerRepository->getQueryBuilderBySearchData($searchData);
        $qb = $this->customerRepository->getNonWithdrawingCustomers();
        

        $event = new EventArgs(
            [
                'form' => $searchForm,
                'csvForm' => $csvForm,
                'qb' => $qb,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_CUSTOMER_INDEX_SEARCH, $event);

        $pagination = $paginator->paginate(
            $qb,
            $page_no,
            $pageCount
        );

        return [
            'searchForm' => $searchForm->createView(),
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $pageCount,
            'has_errors' => false,
            'csvForm' => $csvForm->createView(),
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/customer/{id}/resend", requirements={"id" = "\d+"}, name="admin_customer_resend")
     */
    public function resend(Request $request, $id)
    {
        $this->isTokenValid();

        $Customer = $this->customerRepository
            ->find($id);

        if (is_null($Customer)) {
            throw new NotFoundHttpException();
        }

        $activateUrl = $this->generateUrl(
            'entry_activate',
            ['secret_key' => $Customer->getSecretKey()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // メール送信
        $this->mailService->sendAdminCustomerConfirmMail($Customer, $activateUrl);

        $event = new EventArgs(
            [
                'Customer' => $Customer,
                'activateUrl' => $activateUrl,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_CUSTOMER_RESEND_COMPLETE, $event);

        $this->addSuccess('admin.common.send_complete', 'admin');

        return $this->redirectToRoute('admin_customer');
    }

    /**
     * @Route("/%eccube_admin_route%/customer/{id}/delete", requirements={"id" = "\d+"}, name="admin_customer_delete", methods={"DELETE"})
     */
    public function delete(Request $request, $id, TranslatorInterface $translator)
    {
        $this->isTokenValid();

        log_info('会員削除開始', [$id]);

        $page_no = intval($this->session->get('eccube.admin.customer.search.page_no'));
        $page_no = $page_no ? $page_no : Constant::ENABLED;

        $Customer = $this->customerRepository
            ->find($id);

        if (!$Customer) {
            $this->deleteMessage();

            return $this->redirect($this->generateUrl('admin_customer_page',
                    ['page_no' => $page_no]).'?resume='.Constant::ENABLED);
        }

        try {
            // $this->entityManager->remove($Customer);
            // $this->entityManager->flush($Customer);
            // $this->addSuccess('admin.common.delete_complete', 'admin');

            $email = $Customer->getEmail();
            $CustomerStatus = $this->customerStatusRepository->find(CustomerStatus::WITHDRAWING);
            $this->mailService->sendCustomerWithdrawMail($Customer, $email);
            
            $Customer->setStatus($CustomerStatus);
            $Customer->setEmail(StringUtil::random(60).'@dummy.dummy');

            $this->entityManager->flush();
            log_info('退会処理完了');

        } catch (ForeignKeyConstraintViolationException $e) {
            log_error('会員削除失敗', [$e], 'admin');

            $message = trans('admin.common.delete_error_foreign_key', ['%name%' => $Customer->getName01().' '.$Customer->getName02()]);
            $this->addError($message, 'admin');
        }

        log_info('会員削除完了', [$id]);

        $event = new EventArgs(
            [
                'Customer' => $Customer,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_CUSTOMER_DELETE_COMPLETE, $event);

        return $this->redirect($this->generateUrl('admin_customer_page',
                ['page_no' => $page_no]).'?resume='.Constant::ENABLED);
    }

    /**
     * 会員CSVの出力.
     *
     * @Route("/%eccube_admin_route%/customer/export", name="admin_customer_export")
     *
     * @param Request $request
     *
     * @return StreamedResponse
     */
    public function export(Request $request)
    {
        // タイムアウトを無効にする.
        set_time_limit(0);

        // sql loggerを無効にする.
        $em = $this->entityManager;
        $em->getConfiguration()->setSQLLogger(null);

        $response = new StreamedResponse();
        $response->setCallback(function () use ($request) {
            // CSV種別を元に初期化.
            $this->csvExportService->initCsvType(CsvType::CSV_TYPE_CUSTOMER);

            // ヘッダ行の出力.
            $this->csvExportService->exportHeader();

            // 会員データ検索用のクエリビルダを取得.
            $qb = $this->csvExportService
                ->getCustomerQueryBuilder($request);

            // データ行の出力.
            $this->csvExportService->setExportQueryBuilder($qb);
            $this->csvExportService->exportData(function ($entity, $csvService) use ($request) {
                $Csvs = $csvService->getCsvs();

                /** @var $Customer \Eccube\Entity\Customer */
                $Customer = $entity;

                $ExportCsvRow = new \Eccube\Entity\ExportCsvRow();

                // CSV出力項目と合致するデータを取得.
                foreach ($Csvs as $Csv) {
                    // 会員データを検索.
                    $ExportCsvRow->setData($csvService->getData($Csv, $Customer));

                    $event = new EventArgs(
                        [
                            'csvService' => $csvService,
                            'Csv' => $Csv,
                            'Customer' => $Customer,
                            'ExportCsvRow' => $ExportCsvRow,
                        ],
                        $request
                    );
                    $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_CUSTOMER_CSV_EXPORT, $event);

                    $ExportCsvRow->pushData();
                }

                //$row[] = number_format(memory_get_usage(true));
                // 出力.
                $csvService->fputcsv($ExportCsvRow->getRow());
            });
        });

        $now = new \DateTime();
        $filename = 'customer_'.$now->format('YmdHis').'.csv';
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);

        $response->send();

        log_info('会員CSVファイル名', [$filename]);

        return $response;
    }

    
    /**
     * 商品登録CSVヘッダー定義
     *
     * @return array
     */
    protected function getCustomerCsvHeader()
    {
        return [
            trans('姓') => [
                'id' => 'name01',
                'description' => '姓',
                'required' => true,
            ],
            trans('名') => [
                'id' => 'name02',
                'description' => '名',
                'required' => true,
            ],
            trans('姓フリガナ') => [
                'id' => 'kana01',
                'description' => '姓フリガナ',
                'required' => false,
            ],
            trans('名フリガナ') => [
                'id' => 'kana02',
                'description' => '名フリガナ',
                'required' => false,
            ],
            trans('郵便番号') => [
                'id' => 'postal_code',
                'description' => '郵便番号',
                'required' => false,
            ],
            trans('国') => [
                'id' => 'Country',
                'description' => '国',
                'required' => false,
            ],
            trans('都道府県') => [
                'id' => 'Pref',
                'description' => '都道府県',
                'required' => false,
            ],
            trans('市区郡町村') => [
                'id' => 'addr01',
                'description' => '市区郡町村',
                'required' => false,
            ],
            trans('番地') => [
                'id' => 'addr02',
                'description' => '番地',
                'required' => false,
            ],
            trans('電話番号') => [
                'id' => 'phone_number',
                'description' => '電話番号',
                'required' => false,
            ],
            trans('Eメール') => [
                'id' => 'email',
                'description' => 'Eメール',
                'required' => true,
            ],
            trans('入会日') => [
                'id' => 'update_date',
                'description' => '入会日',
                'required' => true,
            ],
            trans('保有PT') => [
                'id' => 'point',
                'description' => '保有PT',
                'required' => true,
            ],
        ];
    }
    
    /**
     * @Route("/%eccube_admin_route%/customer/csv_split_import", name="admin_customer_csv_split_import", methods={"POST"})
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

        return $this->forwardToRoute('admin_customer_csv_import');
    }

      /**
     * 会員登録CSVアップロード
     *
     * @Route("/%eccube_admin_route%/customer/customer_csv_upload", name="admin_customer_csv_import", methods={"GET", "POST"})
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function csvCustomer(Request $request, CacheUtil $cacheUtil)
    {        
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $headers = $this->getCustomerCsvHeader();
        if ('POST' === $request->getMethod()) {
            // $form->handleRequest($request);
            // if ($form->isValid()) {
                // $this->isSplitCsv = $form['is_split_csv']->getData();
                // $this->csvFileNo = $form['csv_file_no']->getData();
                // $formFile = $form['import_file']->getData();

                $this->isSplitCsv = $request->request->get('admin_csv_import')['is_split_csv'];
                $this->csvFileNo = $request->request->get('admin_csv_import')['csv_file_no'];

                $formFile = $request->files->get('admin_csv_import')['import_file'];
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

                    if (count(array_diff($requireHeader, $columnHeaders)) > 0) {
                        $this->addErrors(trans('admin.common.csv_invalid_format'));

                        return $this->renderWithError($form, $headers, false);
                    }

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

                        $Customer = $this->customerRepository->newCustomer();
                        $encoder = $this->encoderFactory->getEncoder($Customer);

                        $Customer->setSalt($encoder->createSalt());
                        $Customer->setSecretKey($this->customerRepository->getUniqueSecretKey());
                        $Customer->setPassword($encoder->encodePassword('abcdef', $Customer->getSalt()));

                        if (isset($row[$headerByKey['name01']]) && StringUtil::isNotBlank($row[$headerByKey['name01']])) {
                          $Customer->setName01($row[$headerByKey['name01']]);
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }
                        
                        if (isset($row[$headerByKey['name02']]) && StringUtil::isNotBlank($row[$headerByKey['name02']])) {
                          $Customer->setName02($row[$headerByKey['name02']]);
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }
                        
                        if (isset($row[$headerByKey['kana01']])) {
                            if (StringUtil::isNotBlank($row[$headerByKey['kana01']]))
                              $Customer->setKana01($row[$headerByKey['kana01']]);
                            else $Customer->setKana01('');
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }

                        if (isset($row[$headerByKey['kana02']])) {
                            if (StringUtil::isNotBlank($row[$headerByKey['kana02']]))
                              $Customer->setKana02($row[$headerByKey['kana02']]);
                            else $Customer->setKana02('');
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }

                        if (isset($row[$headerByKey['postal_code']])) {
                            if (StringUtil::isNotBlank($row[$headerByKey['postal_code']]))
                              $Customer->setPostalCode($row[$headerByKey['postal_code']]);
                            else $Customer->setPostalCode('5000001');
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }

                        if (isset($row[$headerByKey['addr01']])) {
                            if (StringUtil::isNotBlank($row[$headerByKey['addr01']]))
                              $Customer->setAddr01($row[$headerByKey['addr01']]);
                            else $Customer->setAddr01('add01');
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }

                        if (isset($row[$headerByKey['addr02']])) {
                            if (StringUtil::isNotBlank($row[$headerByKey['addr02']]))
                              $Customer->setAddr02($row[$headerByKey['addr02']]);
                            else $Customer->setAddr02('');
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }
                        
                        if (isset($row[$headerByKey['phone_number']])) {
                            if (StringUtil::isNotBlank($row[$headerByKey['phone_number']]))
                              $Customer->setPhoneNumber($row[$headerByKey['phone_number']]);
                            else $Customer->setPhoneNumber('');
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }
                        
                        if (isset($row[$headerByKey['email']])) {
                            if (StringUtil::isNotBlank($row[$headerByKey['email']]))
                              $Customer->setEmail($row[$headerByKey['email']]);
                            else
                              return $this->renderWithError($form, $headers);
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }

                        if (isset($row[$headerByKey['update_date']])) {
                            if (StringUtil::isNotBlank($row[$headerByKey['update_date']]))
                              $Customer->setUpdateDate($row[$headerByKey['update_date']]);
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }
                        
                        if (isset($row[$headerByKey['point']])) {
                            if (StringUtil::isNotBlank($row[$headerByKey['point']]))
                              $Customer->setPoint($row[$headerByKey['point']]);
                        } else {
                          return $this->json(['success' => false, 'message' => 'failed']);
                        }
                        $Customer->setStatus($this->customerStatusRepository->find(\Eccube\Entity\Master\CustomerStatus::ACTIVE));
                        $this->entityManager->persist($Customer);
                    }
                    $this->entityManager->flush();
                    $this->entityManager->getConnection()->commit();

                    log_info('商品CSV登録完了');
                    $message = 'admin.common.csv_upload_complete';
                    $this->session->getFlashBag()->add('eccube.admin.success', $message);

                    $cacheUtil->clearDoctrineCache();
                }
            // }
            
          return $this->json(['success' => true, 'message' => 'success']);
        }

        return $this->json(['success' => false, 'message' => 'failed']);
    }

    /**
     * @Route("/%eccube_admin_route%/customer/csv_split", name="admin_customer_csv_split", methods={"POST"})
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

        // $this->removeUploadedFile();

        return [
            'form' => $form->createView(),
            'headers' => $headers,
            'errors' => $this->errors,
        ];
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
     * アップロードされたCSVファイルの行ごとの処理
     *
     * @param UploadedFile $formFile
     *
     * @return CsvImportService|bool
     */
    protected function getImportData(UploadedFile $formFile)
    {
        // アップロードされたCSVファイルを一時ディレクトリに保存
        $this->csvFileName = 'upload_'.StringUtil::random().'.'.$formFile->getClientOriginalExtension();
        $formFile->move($this->eccubeConfig['eccube_csv_temp_realdir'], $this->csvFileName);

        $file = file_get_contents($this->eccubeConfig['eccube_csv_temp_realdir'].'/'.$this->csvFileName);

        if ('\\' === DIRECTORY_SEPARATOR && PHP_VERSION_ID >= 70000) {
            // Windows 環境の PHP7 の場合はファイルエンコーディングを CP932 に合わせる
            // see https://github.com/EC-CUBE/ec-cube/issues/1780
            setlocale(LC_ALL, ''); // 既定のロケールに設定
            if (mb_detect_encoding($file) === 'UTF-8') { // UTF-8 を検出したら SJIS-win に変換
                $file = mb_convert_encoding($file, 'SJIS-win', 'UTF-8');
            }
        } else {
            // アップロードされたファイルがUTF-8以外は文字コード変換を行う
            $encode = StringUtil::characterEncoding($file, $this->eccubeConfig['eccube_csv_import_encoding']);
            if (!empty($encode) && $encode != 'UTF-8') {
                $file = mb_convert_encoding($file, 'UTF-8', $encode);
            }
        }

        $file = StringUtil::convertLineFeed($file);

        $tmp = tmpfile();
        fwrite($tmp, $file);
        rewind($tmp);
        $meta = stream_get_meta_data($tmp);
        $file = new \SplFileObject($meta['uri']);

        set_time_limit(0);

        // アップロードされたCSVファイルを行ごとに取得
        $data = new CsvImportService($file, $this->eccubeConfig['eccube_csv_import_delimiter'], $this->eccubeConfig['eccube_csv_import_enclosure']);

        return $data->setHeaderRowNumber(0) ? $data : false;
    }
}
