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

namespace Plugin\PaygentPayment\Controller\Admin;

use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Eccube\Util\FilesystemUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Plugin\PaygentPayment\Service\PaygentBaseService;

class FileController extends AbstractController
{
    const SJIS = 'sjis-win';
    const UTF = 'UTF-8';
    private $errors = [];
    private $encode = '';

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var PaygentBaseService
     */
    protected $paygentBaseService;

    /**
     * FileController constructor.
     * 
     * @param EccubeConfig $eccubeConfig
     * @param PaygentBaseService $paygentBaseService
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        PaygentBaseService $paygentBaseService
    ) {
        $this->encode = self::UTF;
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->encode = self::SJIS;
        }
        $this->eccubeConfig = $eccubeConfig;
        $this->paygentBaseService = $paygentBaseService;
    }

    /**
     * @Route("/%eccube_admin_route%/paygent_payment/paygent_file_manager", name="paygent_payment_admin_paygent_file")
     * @Template("@PaygentPayment/admin/Setting/System/file.twig")
     */
    public function index(Request $request)
    {
        $form = $this->formFactory->createBuilder(FormType::class)
            ->add('file', FileType::class, [
                'multiple' => true,
                'attr' => [
                    'multiple' => 'multiple'
                ],
            ])
            ->getForm();

        // PluginData/PaygentPayment_dir
        $paygentPluginDataDir = $this->getPaygentPluginDataDir();

        // PluginData/PaygentPaymentディレクトリがない場合、作成する
        $filesystem = new Filesystem();
        if (!file_exists($paygentPluginDataDir)) {
            $filesystem->mkdir($paygentPluginDataDir);
        }
        $topDir = $this->normalizePath($paygentPluginDataDir);

        if ('POST' === $request->getMethod()) {
            switch ($request->get('mode')) {
                case 'upload':
                    $this->upload($request);
                    break;
                default:
                    break;
            }
        }

        // PluginData/PaygentPayment/配下のファイル一覧取得
        $arrFileList = $this->getFileList($topDir);

        $response = $this->paygentBaseService->setDefaultHeader(new Response());

        $arrReturn = [
            'form' => $form->createView(),
            'arrFileList' => $arrFileList,
            'errors' => $this->errors,
        ];

        return $this->render('@PaygentPayment/admin/Setting/System/file.twig', $arrReturn, $response);
    }

    /**
     * アップロードファイルの削除
     * 
     * @Route("/%eccube_admin_route%/paygent_payment/paygent_file_delete", name="paygent_payment_admin_paygent_file_delete", methods={"DELETE"})
     */
    public function delete(Request $request)
    {
        $this->isTokenValid();

        $selectFile = $request->get('select_file');
        if (is_null($selectFile) || $selectFile == '/') {
            return $this->redirectToRoute('paygent_payment_admin_paygent_file');
        }

        $topDir = $this->getPaygentPluginDataDir();
        $file = $this->convertStrToServer($this->getPaygentPluginDataDir($selectFile));
        if ($this->checkDir($file, $topDir)) {
            $filesystem = new Filesystem();
            if ($filesystem->exists($file)) {
                $filesystem->remove($file);
                $this->addSuccess('paygent_payment.admin.paygent_file.delete_complete', 'admin');
            }
        }

        return $this->redirectToRoute('paygent_payment_admin_paygent_file');
    }

    /**
     * ファイルアップロード
     */
    public function upload(Request $request)
    {
        $form = $this->formFactory->createBuilder(FormType::class)
            ->add('file', FileType::class, [
                'multiple' => true,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'paygent_payment.admin.paygent_file.file_select_empty',
                    ]),
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if (!$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->errors[] = ['message' => $error->getMessage()];
            }

            return;
        }

        $data = $form->getData();
        $topDir = $this->getPaygentPluginDataDir();

        $uploadCount = count($data['file']);
        $successCount = 0;

        foreach ($data['file'] as $file) {
            $filename = $this->convertStrToServer($file->getClientOriginalName());
            try {
                $file->move($topDir, $filename);
                $successCount ++;
            } catch (FileException $e) {
                $this->errors[] = ['message' => trans('paygent_payment.admin.paygent_file.upload_error', [
                    '%file_name%' => $filename,
                    '%error%' => $e->getMessage()
                ])];
            }
        }
        if ($successCount > 0) {
            $this->addSuccess(trans('paygent_payment.admin.paygent_file.upload_complete', [
                '%success%' => $successCount,
                '%count%' => $uploadCount
            ]), 'admin');
        }
    }

    /**
     * @param string $nowDir
     */
    private function getFileList($nowDir)
    {
        $topDir = $this->getPaygentPluginDataDir();
        $filter = function (\SplFileInfo $file) use ($topDir) {
            $acceptPath = realpath($topDir);
            $targetPath = $file->getRealPath();

            return strpos($targetPath, $acceptPath) === 0;
        };

        $finder = Finder::create()
            ->filter($filter)
            ->in($nowDir)
            ->ignoreDotFiles(false)
            ->sortByName()
            ->depth(0);
        $fileFinder = $finder->files();
        try {
            $files = $fileFinder->getIterator();
        } catch (\Exception $e) {
            $files = [];
        }

        $arrFileList = [];
        foreach ($files as $file) {
            $arrFileList[] = [
                'file_name' => $this->convertStrFromServer($file->getFilename()),
                'file_path' => $this->convertStrFromServer($this->getJailDir($this->normalizePath($file->getRealPath()))),
                'file_size' => FilesystemUtil::sizeToHumanReadable($file->getSize()),
                'file_time' => $file->getmTime(),
                'is_dir' => false,
                'is_empty' => false,
                'extension' => $file->getExtension(),
            ];
        }

        return $arrFileList;
    }

    protected function normalizePath($path)
    {
        return str_replace('\\', '/', realpath($path));
    }

    /**
     * @param string $topDir
     */
    protected function checkDir($targetDir, $topDir)
    {
        $targetDir = realpath($targetDir);
        $topDir = realpath($topDir);

        return strpos($targetDir, $topDir) === 0;
    }

    /**
     * @return string
     */
    private function convertStrFromServer($target)
    {
        if ($this->encode == self::SJIS) {
            return mb_convert_encoding($target, self::UTF, self::SJIS);
        }

        return $target;
    }

    private function convertStrToServer($target)
    {
        if ($this->encode == self::SJIS) {
            return mb_convert_encoding($target, self::SJIS, self::UTF);
        }

        return $target;
    }

    private function getPaygentPluginDataDir($nowDir = null)
    {
        $pluginCode = $this->eccubeConfig['paygent_payment']['paygent_payment_code'];
        return rtrim($this->getParameter('kernel.project_dir').'/app/PluginData/'.$pluginCode.$nowDir, '/');
    }

    private function getJailDir($path)
    {
        $realpath = realpath($path);
        $jailPath = str_replace(realpath($this->getPaygentPluginDataDir()), '', $realpath);

        return $jailPath ? $jailPath : '/';
    }
}
