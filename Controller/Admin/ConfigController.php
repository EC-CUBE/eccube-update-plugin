<?php

namespace Plugin\EccubeUpdater400to401\Controller\Admin;

use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Plugin\EccubeUpdater400to401\Form\Type\Admin\ConfigType;
use Plugin\EccubeUpdater400to401\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;

class ConfigController extends AbstractController
{
    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    protected $eccubeConfig;

    /**
     * ConfigController constructor.
     *
     * @param ConfigRepository $configRepository
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(ConfigRepository $configRepository, EccubeConfig $eccubeConfig)
    {
        $this->configRepository = $configRepository;
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * @Route("/%eccube_admin_route%/eccube_updater_400_to_401/config", name="eccube_updater400to401_admin_config")
     * @Template("@EccubeUpdater400to401/admin/config.twig")
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

            return $this->redirectToRoute('eccube_updater_admin_config');
        }

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * 変更ファイルの競合を調査
     *
     * @Route("/%eccube_admin_route%/eccube_updater/check_source", name="eccube_updater_admin_check_source")
     * @Template("@EccubeUpdater400to401/admin/check_source.twig")
     */
    public function checkSource(Request $request)
    {

        $fileHash = Yaml::parseFile($this->eccubeConfig->get('plugin_realdir') . "/EccubeUpdater400to401" . '/Resource/file_hash/4.0.0.yaml');
        $fileHashCrlf = Yaml::parseFile($this->eccubeConfig->get('plugin_realdir') . "/EccubeUpdater400to401" . '/Resource/file_hash/4.0.0_crlf.yaml');

        $changes = [];
        foreach ($fileHash as $file => $hash) {
            $filePath = $this->eccubeConfig->get('kernel.project_dir') . '/' . $file;
            if (file_exists($filePath)) {
                $hash = hash_file('md5', $filePath);
                if($fileHash[$file] != $hash && $fileHashCrlf[$file] != $hash) {
                    $changes[] = $file;
                }
            }
        }

        return [
            'changes' => $changes,
        ];
    }
}
