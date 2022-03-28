<?php

namespace Plugin\PrizeShow\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\PrizeShow\Form\Type\Admin\ConfigType;
use Plugin\PrizeShow\Repository\ConfigRepository;
use Plugin\PrizeShow\Repository\PrizeListRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Plugin\PrizeShow\Entity\Config;
use Plugin\PrizeShow\Entity\PrizeList;

use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Plugin\PrizeShow\Form\Type\Admin\PrizeListType;
use Reflector;

class ConfigController extends AbstractController
{
    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var PrizeListRepository
     */
    protected $prizeListRepository;

    /**
     * ConfigController constructor.
     *
     * @param ConfigRepository $prizeRepository
     */
    public function __construct(ConfigRepository $configRepository, PrizeListRepository $prizeListRepository)
    {
        $this->configRepository = $configRepository;
        $this->prizeListRepository = $prizeListRepository;
    }


    public function edit(Request $request, $id = null)
    {
        $item = $this->configRepository->newPrize();
        $builder = $this->formFactory
        ->createBuilder(ConfigType::class, $item);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($id) {
            $prize = $this->prizeListRepository
                ->get($id);

            if (is_null($prize)) {
                throw new NotFoundHttpException();
            }
        }
        else {
            return [
                'form' => $form->createView(),
                'prizeList' => '',
                'groupName' => $prizeGroupName
            ];
        } 

        $prizeList = $this->configRepository->findBy(array('prizeGroup' => $id));
        $prizeGroupName = $this->prizeListRepository->get($id)->getName();

        return [
            'form' => $form->createView(),
            'prizeList' => $prizeList,
            'groupName' => $prizeGroupName
        ];
    }    
    

    /**
     * @Route("/%eccube_admin_route%/prize_show", name="prize_show_admin")
     * @Template("@PrizeShow/admin/list.twig")
     */
    public function list(Request $request)
    {
        $prizeList = $this->prizeListRepository
        ->findAll();
        return ['prizeList' => $prizeList];
    }

    /**
     * @Route("/%eccube_admin_route%/prize_show/add_image", name="prize_show_admin_add_image", methods={"POST"})
     */
    public function addImage(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }
        $images = $request->files->get('files');
        
        $image = $images[0];

        $allowExtensions = ['gif', 'jpg', 'jpeg', 'png'];
        //ファイルフォーマット検証
        $mimeType = $image->getMimeType();
        if (0 !== strpos($mimeType, 'image')) {
            throw new UnsupportedMediaTypeHttpException();
        }
        // 拡張子
        $extension = $image->getClientOriginalExtension();
        if (!in_array(strtolower($extension), $allowExtensions)) {
            throw new UnsupportedMediaTypeHttpException();
        }
        $filename = date('mdHis').uniqid('_').'.'.$extension;
        $image->move($this->eccubeConfig['eccube_temp_image_dir'], $filename);
        $files[] = $filename;

        $event = new EventArgs(
            [
                'images' => $image,
                'files' => $files,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_ADD_IMAGE_COMPLETE, $event);
        $files = $event->getArgument('files');

        return $this->json(['files' => $files], 200);
    }

    /**
     * @Route("/%eccube_admin_route%/prize_show/add_item", name="prize_show_admin_add_item")
     */
    public function insertItem(Request $request)
    {
        $item = $this->configRepository->newPrize();

        $item->setName($request->get('item_name'));
        $item->setImage($request->get('item_image'));
        $item->setRemain($request->get('item_remain'));

        $lastItem = $this->configRepository->findBy(array(),array('prizeGroup'=>'DESC'),1,0);
        if (empty($lastItem))
            $itemGroupID = 1;
        else
            $itemGroupID = $lastItem[0]['prizeGroup'] + 1;

        $item->setPrizeGroup($itemGroupID);

        $this->entityManager->persist($item);
        $this->entityManager->flush($item);

        return $this->redirectToRoute('prize_show_admin');
    }

    /**
     * @Route("/%eccube_admin_route%/prize_show/add_list", name="prize_show_admin_add_list")
     */
    public function insertList(Request $request)
    {
        $prizeGroup = $request->get("prize_group");

        $prizeList = $this->prizeListRepository->newPrizeList();
        $prizeList->setName($prizeGroup);

        $this->entityManager->persist($prizeList);
        $this->entityManager->flush($prizeList);

        return $this->redirectToRoute('prize_show_admin');
    }

    /**
     * @Route("/%eccube_admin_route%/prize_show/new", name="prize_show_admin_new")
     * @Route("/%eccube_admin_route%/prize_show/{id}/edit", name="prize_show_admin_edit")
     * @Template("@PrizeShow/admin/config.twig")
     */
    public function prizeEdit(Request $request, $id = null)
    {
        
        if (is_null($id)){
            $prizeList = new PrizeList();
            // $prizeList->addSetting($prize);
            // $prize->setPrizeGroup($prizeList);
            $prizes = array();
        }
        else{
            $prizeList = $this->getDoctrine()->getRepository(PrizeList::class)->find($id);
            if (empty($prizeList)) {
                throw new NotFoundHttpException();
            }
            $prizes = $prizeList->getSettings();
        } 
        $builder = $this->formFactory
        ->createBuilder(PrizeListType::class, $prizeList);
        $form = $builder->getForm();
        $form['prize_list']->setData($prizes);
        $form->handleRequest($request);

        if ('POST' === $request->getMethod()) {
            // if ($form->isValid()) {
                foreach($prizes as $prize)
                {
                    $prize->setPrizeGroup(null);
                    $prizeList->getSettings()->removeElement($prize);
                    $this->entityManager->persist($prize);
                }
                $this->entityManager->persist($prizeList);
                $this->entityManager->flush();

                $prizes = $form['prize_list']->getData();

                foreach($prizes as $prize)
                {
                    $prize->setPrizeGroup($prizeList);
                    $prizeList->addSetting($prize); 
                    $this->entityManager->persist($prize);
                }
                
                $this->entityManager->persist($prizeList);
                $this->entityManager->flush();

                return $this->redirectToRoute('prize_show_admin_edit', ['id' => $prizeList->getId()]);        
            // }
        }

        return [
            'form' => $form->createView(),
            'PrizeList' => $prizeList,
			'id' => $id
        ];
    }
}
