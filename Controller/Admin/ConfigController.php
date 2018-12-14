<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\EccubeUpdater400to401\Controller\Admin;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Eccube\Common\Constant;
use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Eccube\Exception\PluginException;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Service\Composer\ComposerApiService;
use Eccube\Util\CacheUtil;
use Eccube\Util\StringUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;

class ConfigController extends AbstractController
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var BaseInfoRepository
     */
    protected $baseInfoRepository;

    /**
     * @var PluginRepository
     */
    protected $pluginRepository;

    /**
     * @var ComposerApiService
     */
    protected $composerApiService;

    /**
     * @var bool
     */
    protected $supported;

    /**
     * @var string
     */
    protected $varDir;

    /**
     * @var string
     */
    protected $extractDir;

    /**
     * @var string
     */
    protected $projectDir;

    /**
     * @var string
     */
    protected $updateFile;

    /**
     * ConfigController constructor.
     *
     * @param EccubeConfig $eccubeConfig
     * @param BaseInfoRepository $baseInfoRepository
     * @param PluginRepository $pluginRepository
     * @param ComposerApiService $composerApiService
     * @param KernelInterface $kernel
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        BaseInfoRepository $baseInfoRepository,
        PluginRepository $pluginRepository,
        ComposerApiService $composerApiService,
        KernelInterface $kernel
    ) {
        $this->kernel = $kernel;
        $this->baseInfoRepository = $baseInfoRepository;
        $this->pluginRepository = $pluginRepository;
        $this->composerApiService = $composerApiService;
        $this->eccubeConfig = $eccubeConfig;
        $this->supported = version_compare(Constant::VERSION, '4.0.0', '=');
        $this->projectDir = realpath($eccubeConfig->get('kernel.project_dir'));
        $this->varDir = realpath($this->projectDir.'/var');
        @mkdir($this->varDir.'/4.0.0...4.0.1');
        $this->extractDir = realpath($this->varDir.'/4.0.0...4.0.1');
        $this->updateFile = realpath(__DIR__.'/../../Resource/update_file/4.0.0...4.0.1.tar.gz');
    }

    /**
     * @Route("/%eccube_admin_route%/eccube_updater_400_to_401/config", name="eccube_updater400to401_admin_config")
     * @Template("@EccubeUpdater400to401/admin/config.twig")
     */
    public function index(Request $request)
    {
        $form = $this->createForm(FormType::class);

        if (!$this->supported) {
            $this->addError('このプラグインは4.0.0〜4.0.1へのアップデートプラグインです', 'admin');
        }

        return [
            'form' => $form->createView(),
            'supported' => $this->supported,
        ];
    }

    /**
     * ファイルの書き込み権限チェックを行う.
     *
     * @Route("/%eccube_admin_route%/eccube_updater_400_to_401/check_permission", name="eccube_updater400to401_admin_check_permission", methods={"POST"})
     * @Template("@EccubeUpdater400to401/admin/check_permission.twig")
     */
    public function checkPermission(Request $request)
    {
        $form = $this->createForm(FormType::class);
        $form->handleRequest($request);
        if (!($form->isSubmitted() && $form->isValid())) {
            return $this->redirectToRoute('eccube_updater400to401_admin_config');
        }

        $phar = new \PharData($this->updateFile);
        $phar->extractTo($this->varDir, null, true);

        $noWritePermissions = [];

        // ディレクトリの書き込み権限をチェック
        $dirs = Finder::create()
            ->in($this->extractDir)
            ->directories();

        /** @var \SplFileInfo $dir */
        foreach ($dirs as $dir) {
            $path = $this->projectDir.str_replace($this->extractDir, '', $dir->getRealPath());
            if (file_exists($path) && !is_writable($path)) {
                $noWritePermissions[] = $path;
            }
        }

        // ファイルの書き込み権限をチェック
        $files = Finder::create()
            ->in($this->extractDir)
            ->files();

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $path = $this->projectDir.str_replace($this->extractDir, '', $file->getRealPath());
            if (file_exists($path) && !is_writable($path)) {
                $noWritePermissions[] = $path;
            }
        }

        return [
            'form' => $form->createView(),
            'noWritePermissions' => $noWritePermissions,
        ];
    }

    /**
     * 更新ファイルの競合を確認する.
     *
     * @Route("/%eccube_admin_route%/eccube_updater_400_to_401/check_source", name="eccube_updater400to401_admin_check_source", methods={"POST"})
     * @Template("@EccubeUpdater400to401/admin/check_source.twig")
     */
    public function checkSource(Request $request)
    {
        $form = $this->createForm(FormType::class);
        $form->handleRequest($request);
        if (!($form->isSubmitted() && $form->isValid())) {
            return $this->redirectToRoute('eccube_updater400to401_admin_config');
        }

        $fileHash = Yaml::parseFile(
            $this->eccubeConfig->get('plugin_realdir').'/EccubeUpdater400to401'.'/Resource/file_hash/file_hash.yaml'
        );
        $fileHashCrlf = Yaml::parseFile(
            $this->eccubeConfig->get('plugin_realdir').'/EccubeUpdater400to401'.'/Resource/file_hash/file_hash_crlf.yaml'
        );

        $changes = [];
        foreach ($fileHash as $file => $hash) {
            $filePath = $this->eccubeConfig->get('kernel.project_dir').'/'.$file;
            if (file_exists($filePath)) {
                $hash = hash_file('md5', $filePath);
                if ($fileHash[$file] != $hash && $fileHashCrlf[$file] != $hash) {
                    $changes[] = $file;
                }
            }
        }

        return [
            'form' => $form->createView(),
            'changes' => $changes,
        ];
    }

    /**
     * ファイルを上書きする.
     *
     * @Route("/%eccube_admin_route%/eccube_updater_400_to_401/update_files", name="eccube_updater400to401_admin_update_files", methods={"POST"})
     */
    public function updateFiles(Request $request, CacheUtil $cacheUtil)
    {
        $this->isTokenValid();

        $fs = new Filesystem();
        $fs->mirror($this->extractDir, $this->projectDir);

        $cacheUtil->clearCache();

        return $this->redirectToRoute('eccube_updater400to401_admin_update_data');
    }

    /**
     * データ更新を行う.
     *
     * 以下を実行する.
     *
     * - .envファイルの更新
     * - composer.jsonの更新
     * - スキーマアップデート
     * - マイグレーション
     *
     * @Route("/%eccube_admin_route%/eccube_updater_400_to_401/update_data", name="eccube_updater400to401_admin_update_data")
     * @Template("@EccubeUpdater400to401/admin/update_data.twig")
     */
    public function updateData(Request $request, CacheUtil $cacheUtil)
    {
        $form = $this->createForm(FormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // .envファイルを更新.
            $this->execUpdateDotEnv();

            $BaseInfo = $this->baseInfoRepository->get();
            if ($BaseInfo->getAuthenticationKey()) {
                // プラグインのrequireを復元する.
                $this->execRequirePlugins();
            }

            // スキーマアップデートを実行.
            $this->runCommand([
                'command' => 'doctrine:schema:update',
                '--dump-sql' => true,
                '--force' => true,
            ]);

            // マイグレーションを実行.
            $this->runCommand([
                'command' => 'doctrine:migrations:migrate',
                '--no-interaction' => true,
            ]);

            $cacheUtil->clearCache();

            return $this->redirectToRoute('eccube_updater400to401_admin_complete');
        }

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * 完了画面を表示.
     *
     * @Route("/%eccube_admin_route%/eccube_updater_400_to_401/complete", name="eccube_updater400to401_admin_complete")
     * @Template("@EccubeUpdater400to401/admin/complete.twig")
     */
    public function complete()
    {
        return [];
    }

    protected function execRequirePlugins()
    {
        $packageNames = [];

        $qb = $this->pluginRepository->createQueryBuilder('p');
        $Plugins = [];
        try {
            $Plugins = $qb->select('p')
                ->where("p.source IS NOT NULL AND p.source <> '0' AND p.source <> ''")
                ->orderBy('p.code', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (NoResultException | NonUniqueResultException $e) {
            log_error($e->getMessage());
        }
        foreach ($Plugins as $Plugin) {
            $packageNames[] = 'ec-cube/'.$Plugin->getCode().':'.$Plugin->getVersion();
        }

        if ($packageNames) {
            try {
                $this->composerApiService->execRequire(implode(' ', $packageNames));
            } catch (PluginException $e) {
                log_error($e->getMessage());
            }
        }
    }

    /**
     * .envファイルを更新する.
     *
     * @see http://doc4.ec-cube.net/quickstart_update#400---401
     */
    protected function execUpdateDotEnv()
    {
        $envFile = $this->projectDir.'/.env';
        $envContent = file_get_contents($envFile);
        $envContent = StringUtil::replaceOrAddEnv(
            $envContent,
            [
                'ECCUBE_LOCALE' => $this->eccubeConfig->get('locale'),
                'ECCUBE_ADMIN_ROUTE' => $this->eccubeConfig->get('eccube_admin_route'),
                'ECCUBE_TEMPLATE_CODE' => $this->eccubeConfig->get('eccube_theme_code'),
            ]
        );

        file_put_contents($envFile, $envContent);
    }

    /**
     * コマンドを実行.
     */
    protected function runCommand(array $command)
    {
        $console = new Application($this->kernel);
        $console->setAutoExit(false);

        $input = new ArrayInput($command);

        $output = new BufferedOutput(
            OutputInterface::VERBOSITY_DEBUG,
            true
        );

        $console->run($input, $output);

        return $output;
    }
}
