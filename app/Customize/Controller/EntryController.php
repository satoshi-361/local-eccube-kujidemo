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
use Eccube\Entity\Master\CustomerStatus;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\Front\EntryType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\Master\CustomerStatusRepository;
use Eccube\Service\MailService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception as HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Eccube\Service\CartService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\EventDispatcher\EventDispatcher,
    Symfony\Component\Security\Http\Event\InteractiveLoginEvent;


class EntryController extends AbstractController
{
    /**
     * @var CustomerStatusRepository
     */
    protected $customerStatusRepository;

    /**
     * @var ValidatorInterface
     */
    protected $recursiveValidator;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var EncoderFactoryInterface
     */
    protected $encoderFactory;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var \Eccube\Service\CartService
     */
    protected $cartService;

    /**
     * EntryController constructor.
     *
     * @param CartService $cartService
     * @param CustomerStatusRepository $customerStatusRepository
     * @param MailService $mailService
     * @param BaseInfoRepository $baseInfoRepository
     * @param CustomerRepository $customerRepository
     * @param EncoderFactoryInterface $encoderFactory
     * @param ValidatorInterface $validatorInterface
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(
        CartService $cartService,
        CustomerStatusRepository $customerStatusRepository,
        MailService $mailService,
        BaseInfoRepository $baseInfoRepository,
        CustomerRepository $customerRepository,
        EncoderFactoryInterface $encoderFactory,
        ValidatorInterface $validatorInterface,
        TokenStorageInterface $tokenStorage
    ) {
        $this->customerStatusRepository = $customerStatusRepository;
        $this->mailService = $mailService;
        $this->BaseInfo = $baseInfoRepository->get();
        $this->customerRepository = $customerRepository;
        $this->encoderFactory = $encoderFactory;
        $this->recursiveValidator = $validatorInterface;
        $this->tokenStorage = $tokenStorage;
        $this->cartService = $cartService;
    }

    /**
     * 会員登録画面.
     *
     * @Route("/entry", name="entry")
     * @Template("Entry/index.twig")
     */
    public function index(Request $request)
    {
        if ($this->isGranted('ROLE_USER')) {
            log_info('認証済のためログイン処理をスキップ');

            return $this->redirectToRoute('mypage');
        }

        /** @var $Customer \Eccube\Entity\Customer */
        $Customer = $this->customerRepository->newCustomer();

        /* @var $builder \Symfony\Component\Form\FormBuilderInterface */
        $builder = $this->formFactory->createBuilder(EntryType::class, $Customer);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Customer' => $Customer,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_ENTRY_INDEX_INITIALIZE, $event);

        /* @var $form \Symfony\Component\Form\FormInterface */
        $form = $builder->getForm();

        if('' == $form['email']->getData()){
            $Customer_email = $this->get('session')->getFlashBag()->get('customer_email');
            if(isset($Customer_email[0]))
                $form['email']->setData($Customer_email[0]);
            if ($request->getSession()->get('is_niconico_signup') == true) $form['password']->setData('12345678');
        }

        $is_niconico_signup = false;
        if ($request->getSession()->get('is_niconico_signup') == true) {
            $is_niconico_signup = true;
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            switch ($request->get('mode')) {
                case 'confirm':
                    log_info('会員登録確認開始');
                    log_info('会員登録確認完了');

                    $Customer_email = $this->get('session')->getFlashBag()->get('customer_email');

                    return $this->render(
                        'Entry/confirm.twig',
                        [
                            'form' => $form->createView(),
                            'is_niconico_signup' => $is_niconico_signup
                        ]
                    );

                case 'complete':
                    log_info('会員登録開始');

                    $encoder = $this->encoderFactory->getEncoder($Customer);
                    $salt = $encoder->createSalt();
                    $password = $encoder->encodePassword($Customer->getPassword(), $salt);
                    $secretKey = $this->customerRepository->getUniqueSecretKey();

                    $Customer
                        ->setSalt($salt)
                        ->setPassword($password)
                        ->setSecretKey($secretKey)
                        ->setPoint(0);
                    $Customer_status = $this->get('session')->getFlashBag()->get('customer_status');
                    $Customer_point = $this->get('session')->getFlashBag()->get('customer_point');
                    $Customer_premium = $this->get('session')->getFlashBag()->get('customer_premium');
                    $Customer_channel = $this->get('session')->getFlashBag()->get('customer_channel');
                    $Customer_ticket = $this->get('session')->getFlashBag()->get('customer_ticket');
                    // if(isset($this->get('session')->getFlashBag()->get('customer_status')) && isset($this->get('session')->getFlashBag()->get('customer_point')) && isset($this->get('session')->getFlashBag()->get('customer_email'))){
                    if(isset($Customer_point[0]) && isset($Customer_status[0])){
                        $Customer->customer_rank = $Customer_status[0];
                        $Customer->setPoint($Customer_point[0]);
                        $Customer->premium = $Customer_premium[0];
                        $Customer->channel = $Customer_channel[0];
                        $Customer->ticket = $Customer_ticket[0];
                    }
                    $this->entityManager->persist($Customer);
                    $this->entityManager->flush();

                    log_info('会員登録完了');

                    $event = new EventArgs(
                        [
                            'form' => $form,
                            'Customer' => $Customer,
                        ],
                        $request
                    );
                    $this->eventDispatcher->dispatch(EccubeEvents::FRONT_ENTRY_INDEX_COMPLETE, $event);

                    if(isset($Customer_point[0]) && isset($Customer_status[0])){
                        $this->entryActivate($request, $Customer->getSecretKey());

                        if( $request->getSession()->get('is_niconico_signup') == true){
                            $request->getSession()->set('is_niconico_signup', false);
                            $request->getSession()->set('is_niconico_logged', true);
                            if ($request->getSession()->get('where_to_login') == 'shopping')
                                return $this->redirectToRoute('shopping');
                            if ($request->getSession()->get('where_to_login') == 'mypage')
                                return $this->redirectToRoute('homepage');
                        }
                    }


                    $activateFlg = $this->BaseInfo->isOptionCustomerActivate();

                    // 仮会員設定が有効な場合は、確認メールを送信し完了画面表示.
                    if ($activateFlg) {
                        $activateUrl = $this->generateUrl('entry_activate', ['secret_key' => $Customer->getSecretKey()], UrlGeneratorInterface::ABSOLUTE_URL);

                        // メール送信
                        $this->mailService->sendCustomerConfirmMail($Customer, $activateUrl);

                        if ($event->hasResponse()) {
                            return $event->getResponse();
                        }

                        log_info('仮会員登録完了画面へリダイレクト');

                        return $this->redirectToRoute('entry_complete');

                    } else {
                        // 仮会員設定が無効な場合は、会員登録を完了させる.
                        $qtyInCart = $this->entryActivate($request, $Customer->getSecretKey());

                        // URLを変更するため完了画面にリダイレクト
                        return $this->redirectToRoute('entry_activate', [
                            'secret_key' => $Customer->getSecretKey(),
                            'qtyInCart' => $qtyInCart,
                        ]);

                    }
            }
        }

        return [
            'form' => $form->createView(),
            'is_niconico_signup' => $is_niconico_signup
        ];
    }

    /**
     * 会員登録完了画面.
     *
     * @Route("/entry/complete", name="entry_complete")
     * @Template("Entry/complete.twig")
     */
    public function complete()
    {
        return [];
    }

    /**
     * 会員のアクティベート（本会員化）を行う.
     *
     * @Route("/entry/activate/{secret_key}/{qtyInCart}", name="entry_activate")
     * @Template("Entry/activate.twig")
     */
    public function activate(Request $request, $secret_key, $qtyInCart = null)
    {
        $errors = $this->recursiveValidator->validate(
            $secret_key,
            [
                new Assert\NotBlank(),
                new Assert\Regex(
                    [
                        'pattern' => '/^[a-zA-Z0-9]+$/',
                    ]
                ),
            ]
        );

        if(!is_null($qtyInCart)) {

            return [
                'qtyInCart' => $qtyInCart,
            ];
        } elseif ($request->getMethod() === 'GET' && count($errors) === 0) {

            // 会員登録処理を行う
            $qtyInCart = $this->entryActivate($request, $secret_key);

            return [
                'qtyInCart' => $qtyInCart,
            ];
        }

        throw new HttpException\NotFoundHttpException();
    }


    /**
     * 会員登録処理を行う
     *
     * @param Request $request
     * @param $secret_key
     * @return \Eccube\Entity\Cart|mixed
     */
    private function entryActivate(Request $request, $secret_key)
    {
        log_info('本会員登録開始');
        $Customer = $this->customerRepository->getProvisionalCustomerBySecretKey($secret_key);
        if (is_null($Customer)) {
            throw new HttpException\NotFoundHttpException();
        }

        $CustomerStatus = $this->customerStatusRepository->find(CustomerStatus::REGULAR);
        $Customer->setStatus($CustomerStatus);
        $this->entityManager->persist($Customer);
        $this->entityManager->flush();

        log_info('本会員登録完了');

        $event = new EventArgs(
            [
                'Customer' => $Customer,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_ENTRY_ACTIVATE_COMPLETE, $event);

        // メール送信
        $this->mailService->sendCustomerCompleteMail($Customer);

        // Assign session carts into customer carts
        $Carts = $this->cartService->getCarts();
        $qtyInCart = 0;
        foreach ($Carts as $Cart) {
            $qtyInCart += $Cart->getTotalQuantity();
        }

        // 本会員登録してログイン状態にする
        $token = new UsernamePasswordToken($Customer, null, 'customer', ['ROLE_USER']);
        $this->tokenStorage->setToken($token);
        $request->getSession()->migrate(true);

        if ($qtyInCart) {
            $this->cartService->save();
        }

        log_info('ログイン済に変更', [$this->getUser()->getId()]);

        return $qtyInCart;

    }

}
