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

namespace Customize\Controller\Mypage;

use Eccube\Controller\AbstractController;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\CustomerAddress;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\Front\CustomerAddressType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CustomerAddressRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use Eccube\Entity\Master\CustomerStatus;
use Eccube\Repository\Master\CustomerStatusRepository;
use Eccube\Service\CartService;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Util\StringUtil;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DeliveryController extends AbstractController
{
    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var CustomerAddressRepository
     */
    protected $customerAddressRepository;



        /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var CustomerStatusRepository
     */
    protected $customerStatusRepository;

    /**
     * @var TokenStorage
     */
    protected $tokenStorage;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    public function __construct(BaseInfoRepository $baseInfoRepository, CustomerAddressRepository $customerAddressRepository,
        MailService $mailService,
        CustomerStatusRepository $customerStatusRepository,
        TokenStorageInterface $tokenStorage,
        CartService $cartService,
        OrderHelper $orderHelper
    )
    {
        $this->BaseInfo = $baseInfoRepository->get();
        $this->customerAddressRepository = $customerAddressRepository;

        
        $this->mailService = $mailService;
        $this->customerStatusRepository = $customerStatusRepository;
        $this->tokenStorage = $tokenStorage;
        $this->cartService = $cartService;
        $this->orderHelper = $orderHelper;
    }

    /**
     * お届け先一覧画面.
     *
     * @Route("/mypage/delivery", name="mypage_delivery")
     * @Template("Mypage/delivery.twig")
     */
    public function index(Request $request)
    {
        $Customer = $this->getUser();

        return [
            'Customer' => $Customer,
        ];
    }

    /**
     * お届け先編集画面.
     *
     * @Route("/mypage/delivery/new", name="mypage_delivery_new")
     * @Route("/mypage/delivery/{id}/edit", name="mypage_delivery_edit", requirements={"id" = "\d+"})
     * @Template("Mypage/delivery_edit.twig")
     */
    public function edit(Request $request, $id = null)
    {
        $Customer = $this->getUser();

        // 配送先住所最大値判定
        // $idが存在する際は、追加処理ではなく、編集の処理ため本ロジックスキップ
        if (is_null($id)) {
            $addressCurrNum = count($Customer->getCustomerAddresses());
            $addressMax = $this->eccubeConfig['eccube_deliv_addr_max'];
            if ($addressCurrNum >= $addressMax) {
                throw new NotFoundHttpException();
            }
            $CustomerAddress = new CustomerAddress();
            $CustomerAddress->setCustomer($Customer);
        } else {
            $CustomerAddress = $this->customerAddressRepository->findOneBy(
                [
                    'id' => $id,
                    'Customer' => $Customer,
                ]
            );
            if (!$CustomerAddress) {
                throw new NotFoundHttpException();
            }
        }

        $parentPage = $request->get('parent_page', null);

        // 正しい遷移かをチェック
        $allowedParents = [
            $this->generateUrl('mypage_delivery'),
            $this->generateUrl('shopping_redirect_to'),
        ];

        // 遷移が正しくない場合、デフォルトであるマイページの配送先追加の画面を設定する
        if (!in_array($parentPage, $allowedParents)) {
            // @deprecated 使用されていないコード
            $parentPage = $this->generateUrl('mypage_delivery');
        }

        $builder = $this->formFactory
            ->createBuilder(CustomerAddressType::class, $CustomerAddress);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Customer' => $Customer,
                'CustomerAddress' => $CustomerAddress,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_DELIVERY_EDIT_INITIALIZE, $event);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            log_info('お届け先登録開始', [$id]);

            $this->entityManager->persist($CustomerAddress);
            $this->entityManager->flush();

            log_info('お届け先登録完了', [$id]);

            $event = new EventArgs(
                [
                    'form' => $form,
                    'Customer' => $Customer,
                    'CustomerAddress' => $CustomerAddress,
                ],
                $request
            );
            $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_DELIVERY_EDIT_COMPLETE, $event);

            $this->addSuccess('mypage.delivery.add.complete');

            return $this->redirect($this->generateUrl('mypage_delivery'));
        }

        return [
            'form' => $form->createView(),
            'parentPage' => $parentPage,
            'BaseInfo' => $this->BaseInfo,
        ];
    }

    /**
     * お届け先を削除する.
     *
     * @Route("/mypage/delivery/{id}/delete", name="mypage_delivery_delete", methods={"DELETE"})
     */
    public function delete(Request $request, CustomerAddress $CustomerAddress)
    {
        $this->isTokenValid();

        log_info('お届け先削除開始', [$CustomerAddress->getId()]);

        $Customer = $this->getUser();

        if ($Customer->getId() != $CustomerAddress->getCustomer()->getId()) {
            throw new BadRequestHttpException();
        }

        $this->customerAddressRepository->delete($CustomerAddress);

        $event = new EventArgs(
            [
                'Customer' => $Customer,
                'CustomerAddress' => $CustomerAddress,
            ], $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_DELIVERY_DELETE_COMPLETE, $event);

        $this->addSuccess('mypage.address.delete.complete');

        log_info('お届け先削除完了', [$CustomerAddress->getId()]);

        return $this->redirect($this->generateUrl('mypage_delivery'));
    }

        /**
     * 退会画面.
     *
     * @Route("/mypage/withdraw", name="mypage_withdraw")
     * @Template("Mypage/withdraw.twig")
     */
    public function withdraw(Request $request)
    {
        $builder = $this->formFactory->createBuilder();

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_WITHDRAW_INDEX_INITIALIZE, $event);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            switch ($request->get('mode')) {
                case 'confirm':
                    log_info('退会確認画面表示');

                    return $this->redirectToRoute('contact');

                    return $this->render(
                        'Mypage/withdraw_confirm.twig',
                        [
                            'form' => $form->createView(),
                        ]
                    );

                case 'complete':
                    log_info('退会処理開始');

                    /* @var $Customer \Eccube\Entity\Customer */
                    $Customer = $this->getUser();
                    $email = $Customer->getEmail();

                    // 退会ステータスに変更
                    $CustomerStatus = $this->customerStatusRepository->find(CustomerStatus::WITHDRAWING_PERMISSION);
                    $Customer->setStatus($CustomerStatus);
                    // $Customer->setEmail(StringUtil::random(60).'@dummy.dummy');

                    $this->entityManager->flush();

                    log_info('退会処理完了');

                    $event = new EventArgs(
                        [
                            'form' => $form,
                            'Customer' => $Customer,
                        ], $request
                    );
                    $this->eventDispatcher->dispatch(EccubeEvents::FRONT_MYPAGE_WITHDRAW_INDEX_COMPLETE, $event);

                    // メール送信
                    // $this->mailService->sendCustomerWithdrawMail($Customer, $email);

                    // // カートと受注のセッションを削除
                    // $this->cartService->clear();
                    // $this->orderHelper->removeSession();

                    // // ログアウト
                    // $this->tokenStorage->setToken(null);

                    // log_info('ログアウト完了');

                    // return $this->redirect($this->generateUrl('mypage_withdraw_complete'));
                    return $this->redirect($this->generateUrl('mypage_withdraw'));
            }
        }

        return [
            'form' => $form->createView(),
        ];
    }
}
