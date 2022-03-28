<?php

namespace Plugin\PrizesPerProduct\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\PrizesPerProduct\Form\Type\Admin\ConfigType;
use Plugin\PrizesPerProduct\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ConfigController extends AbstractController
{
    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * ConfigController constructor.
     *
     * @param ConfigRepository $configRepository
     */
    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    /**
     * @Route("/%eccube_admin_route%/prizes_per_product/config", name="prizes_per_product_admin_config")
     * @Template("@PrizesPerProduct/admin/config.twig")
     */
    public function index(Request $request)
    {
        $Config = $this->configRepository->get();
        $form = $this->createForm(ConfigType::class, $Config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $Config = $form->getData();
            $this->entityManager->persist($Config);
            $this->entityManager->flush($Config);
            $this->addSuccess('登録しました。', 'admin');

            return $this->redirectToRoute('prizes_per_product_admin_config');
        }

        return [
            'form' => $form->createView(),
        ];
    }
}
