<?php

namespace Plugin\EccubeUpdater\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\EccubeUpdater\Form\Type\Admin\ConfigType;
use Plugin\EccubeUpdater\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UpdateController extends AbstractController
{
    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * UpdateController constructor.
     *
     * @param ConfigRepository $configRepository
     */
    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    /**
     * @Route("/%eccube_admin_route%/eccube_updater/update", name="eccube_updater_admin_update")
     * @Template("@EccubeUpdater/admin/update.twig")
     */
    public function index(Request $request)
    {
        return [];
//        $Config = $this->configRepository->get();
//        $form = $this->createForm(ConfigType::class, $Config);
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid()) {
//            $Config = $form->getData();
//            $this->entityManager->persist($Config);
//            $this->entityManager->flush($Config);
//            $this->addSuccess('登録しました。', 'admin');
//
//            return $this->redirectToRoute('eccube_updater_admin_config');
//        }
//
//        return [
//            'form' => $form->createView(),
//        ];
    }
}
