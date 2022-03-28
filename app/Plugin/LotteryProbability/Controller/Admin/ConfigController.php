<?php

namespace Plugin\LotteryProbability\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\LotteryProbability\Form\Type\Admin\ConfigType;
use Plugin\LotteryProbability\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

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
     * @Route("/%eccube_admin_route%/lottery_probability/config", name="lottery_probability_admin_config")
     * @Template("@LotteryProbability/admin/config.twig")
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

            return $this->redirectToRoute('lottery_probability_admin_config');
        }

        return [
            'form' => $form->createView(),
        ];
    }

    
    /**
     * @Route("/%eccube_admin_route%/product/product/{id}/add/winning/register", name="admin_product_winning_register")
     * 
     */
    public function winning_register(Request $request)
    {
        $equal_number = $request->get('Equal_number');
        $free_rank_name = $request->get('Free_rank_name');
        $explain_text = $request->get('Explain_text');
        $winning_probability = $request->get('Winning_probability');
        $display_wp = $request->get('Display_wp');
        $product_set = $request->get('Product_set');
        $sales_type = $request->get('Sales_type');

        $lottery = $this->configRepository->newConfig();
        $lottery->setProductID($equal_number);
        $lottery->setRankname($free_rank_name);
        $lottery->setExplaintext($explain_text);
        $lottery->setWinningProbability($winning_probability);
        $lottery->setDisplayWinning($display_wp);
        $lottery->setProductSet($product_set);
        $lottery->setColor($sales_type);

        $this->entityManager->persist($lottery);
        $this->entityManager->flush($lottery);

        return new Response(
          '',
          Response::HTTP_OK,
          array('Content-Type' => 'text/plain; charset=utf-8')
        );
    }
}
