<?php

namespace Plugin\EccubeUpdater\Controller\Admin;

use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Plugin\EccubeUpdater\Form\Type\Admin\ConfigType;
use Plugin\EccubeUpdater\Repository\ConfigRepository;
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
     * @Route("/%eccube_admin_route%/eccube_updater/config", name="eccube_updater_admin_config")
     * @Template("@EccubeUpdater/admin/config.twig")
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
     * @Template("@EccubeUpdater/admin/check_source.twig")
     */
    public function checkSource(Request $request)
    {
        // TODO: 暫定データなのでhashを作り直す
        // TODO: 対象ファイルの精査も必要
        // TODO: 改行コード違い等も考慮する必要がある

        $fileHash = Yaml::parseFile($this->eccubeConfig->get('plugin_realdir') . "/EccubeUpdater" . '/Resource/file_hash/4.0.0.yaml');

        $changes = array_filter($fileHash, function($hash, $file) {
            $filePath = $this->eccubeConfig->get('kernel.project_dir').'/'.$file;
            if (file_exists($filePath)) {
                return hash_file('md5', $filePath) !== $hash;
            } else {
                return true;
            }
        }, ARRAY_FILTER_USE_BOTH);

        return [
            'changes' => $changes,
        ];
    }
}
