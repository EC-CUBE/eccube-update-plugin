<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * https://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\EccubeUpdater405to406\Controller\Admin;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Eccube\Common\Constant;
use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Plugin;
use Eccube\Exception\PluginException;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\PluginRepository;
use Eccube\Service\Composer\ComposerApiService;
use Eccube\Service\PluginApiService;
use Eccube\Util\CacheUtil;
use Plugin\EccubeUpdater405to406\Common\Constant as UpdaterConstant;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
    protected $dataDir;

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
     * @param PluginApiService $pluginApiService
     * @param ComposerApiService $composerApiService
     * @param KernelInterface $kernel
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        BaseInfoRepository $baseInfoRepository,
        PluginRepository $pluginRepository,
        PluginApiService $pluginApiService,
        ComposerApiService $composerApiService,
        KernelInterface $kernel
    ) {
        $this->kernel = $kernel;
        $this->baseInfoRepository = $baseInfoRepository;
        $this->pluginRepository = $pluginRepository;
        $this->pluginApiService = $pluginApiService;
        $this->composerApiService = $composerApiService;
        $this->eccubeConfig = $eccubeConfig;

        // 4.0.5, 4.0.5-p1, 4.0.6 を対象とする
        if (version_compare(Constant::VERSION, UpdaterConstant::FROM_VERSION, '=')
            || version_compare(Constant::VERSION, UpdaterConstant::FROM_VERSION.'-p1', '=')
            || version_compare(Constant::VERSION, '4.0.6', '=')) {
            $this->supported = true;
        } else {
            $this->supported = false;
        }

        $this->projectDir = realpath($eccubeConfig->get('kernel.project_dir'));
        $this->dataDir = $this->projectDir.'/app/PluginData/eccube_update_plugin';
        $this->updateFile = realpath(__DIR__.'/../../Resource/update_file.tar.gz');
    }

    /**
     * @Route("/%eccube_admin_route%/eccube_updater_405_to_406/config", name="eccube_updater405to406_admin_config")
     * @Template("@EccubeUpdater405to406/admin/config.twig")
     */
    public function index(Request $request)
    {
        if (!$this->supported) {
            $message = sprintf('このプラグインは%s〜%sへのアップデートプラグインです。', UpdaterConstant::FROM_VERSION,
                UpdaterConstant::TO_VERSION);
            $this->addError($message, 'admin');
        }

        if (function_exists('xdebug_is_enabled()') && xdebug_is_enabled()) {
            $this->supported = false;
            $this->addError('xdebugが有効になっています。無効にしてください。', 'admin');
        }

        $fs = new Filesystem();
        $dir = $this->eccubeConfig->get('plugin_realdir').'/'.UpdaterConstant::PLUGIN_CODE;
        // 4.0.5-p1の場合は、ハッシュファイル・アップデートファイルを差し替え
        if (version_compare(Constant::VERSION, UpdaterConstant::FROM_VERSION.'-p1', '=')) {
            $fs->copy($dir.'/Resource/file_hash/405p1_file_hash.yaml', $dir.'/Resource/file_hash/file_hash.yaml', true);
            $fs->copy($dir.'/Resource/file_hash/405p1_file_hash_crlf.yaml', $dir.'/Resource/file_hash/file_hash_crlf.yaml', true);
            $fs->copy($dir.'/Resource/405p1_update_file.tar.gz', $dir.'/Resource/update_file.tar.gz', true);
        } elseif (version_compare(Constant::VERSION, '4.0.6', '=')) { // 4.0.6の場合は、ハッシュファイル・アップデートファイルを差し替え
            $fs->copy($dir.'/Resource/file_hash/406_file_hash.yaml', $dir.'/Resource/file_hash/file_hash.yaml', true);
            $fs->copy($dir.'/Resource/file_hash/406_file_hash_crlf.yaml', $dir.'/Resource/file_hash/file_hash_crlf.yaml', true);
            $fs->copy($dir.'/Resource/406_update_file.tar.gz', $dir.'/Resource/update_file.tar.gz', true);
        }

        return [
            'supported' => $this->supported,
        ];
    }

    /**
     * プラグインのEC-CUBE対応バージョンのチェックを行う.
     *
     * @Route("/%eccube_admin_route%/eccube_updater_405_to_406/check_plugin_version", name="eccube_updater405to406_admin_check_plugin_version")
     * @Template("@EccubeUpdater405to406/admin/check_plugin_vesrion.twig")
     */
    public function checkPluginVersion(Request $request)
    {
        $this->isTokenValid();

        $Plugins = $this->getPlugins();
        $unSupportedPlugins = [];

        foreach ($Plugins as $Plugin) {
            $packageNames[] = 'ec-cube/'.$Plugin->getCode().':'.$Plugin->getVersion();
            if ($Plugin->getCode() === UpdaterConstant::PLUGIN_CODE) {
                continue;
            }
            $data = $this->pluginApiService->getPlugin($Plugin->getCode());
            if (!in_array(UpdaterConstant::TO_VERSION, $data['supported_versions'])) {
                $unSupportedPlugins[] = $Plugin;
            }
        }

        return [
            'unSupportedPlugins' => $unSupportedPlugins,
        ];
    }

    /**
     * ファイルの書き込み権限チェックを行う.
     *
     * @Route("/%eccube_admin_route%/eccube_updater_405_to_406/check_permission", name="eccube_updater405to406_admin_check_permission", methods={"POST"})
     * @Template("@EccubeUpdater405to406/admin/check_permission.twig")
     */
    public function checkPermission(Request $request, Filesystem $fs)
    {
        $this->isTokenValid();

        if (file_exists($this->dataDir)) {
            $fs->remove($this->dataDir);
        }

        $fs->mkdir($this->dataDir);
        $this->dataDir = realpath($this->dataDir);

        $phar = new \PharData($this->updateFile);
        $phar->extractTo($this->dataDir, null, true);

        $noWritePermissions = [];

        // ディレクトリの書き込み権限をチェック
        $dirs = Finder::create()
            ->in($this->dataDir)
            ->directories();

        /** @var \SplFileInfo $dir */
        foreach ($dirs as $dir) {
            $path = $this->projectDir.str_replace($this->dataDir, '', $dir->getRealPath());
            if (file_exists($path) && !is_writable($path)) {
                $noWritePermissions[] = $path;
            }
        }

        // ファイルの書き込み権限をチェック
        $files = Finder::create()
            ->in($this->dataDir)
            ->files();

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $path = $this->projectDir.str_replace($this->dataDir, '', $file->getRealPath());
            if (file_exists($path) && !is_writable($path)) {
                $noWritePermissions[] = $path;
            }
        }

        return [
            'noWritePermissions' => $noWritePermissions,
        ];
    }

    /**
     * 更新ファイルの競合を確認する.
     *
     * @Route("/%eccube_admin_route%/eccube_updater_405_to_406/check_source", name="eccube_updater405to406_admin_check_source", methods={"POST"})
     * @Template("@EccubeUpdater405to406/admin/check_source.twig")
     */
    public function checkSource(Request $request)
    {
        $this->isTokenValid();

        $fileHash = Yaml::parseFile(
            $this->eccubeConfig->get('plugin_realdir').'/'.UpdaterConstant::PLUGIN_CODE.'/Resource/file_hash/file_hash.yaml'
        );
        $fileHashCrlf = Yaml::parseFile(
            $this->eccubeConfig->get('plugin_realdir').'/'.UpdaterConstant::PLUGIN_CODE.'/Resource/file_hash/file_hash_crlf.yaml'
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

        $current = \json_decode(file_get_contents($this->projectDir.'/composer.json'), true);
        $origin = \json_decode(file_get_contents(
            $this->eccubeConfig->get('plugin_realdir').'/'.UpdaterConstant::PLUGIN_CODE.'/Resource/file_hash/composer.json'
        ), true);

        $overwriteRequires = [];
        foreach (array_keys($current['require']) as $currentRequire) {
            if (\strpos($currentRequire, 'ec-cube') === 0) {
                continue;
            }
            $match = false;
            foreach (array_keys($origin['require']) as $originRequire) {
                if ($currentRequire === $originRequire) {
                    $match = true;
                    break;
                }
            }

            if (!$match) {
                $overwriteRequires[] = $currentRequire;
            }
        }

        return [
            'changes' => $changes,
            'overwriteRequires' => $overwriteRequires,
        ];
    }

    /**
     * ファイルを上書きする.
     *
     * @Route("/%eccube_admin_route%/eccube_updater_405_to_406/update_files", name="eccube_updater405to406_admin_update_files", methods={"POST"})
     */
    public function updateFiles(Request $request, CacheUtil $cacheUtil)
    {
        $this->isTokenValid();

        $fs = new Filesystem();

        $fs->mirror($this->dataDir, $this->projectDir);

        $this->addSuccess('ファイルの更新が完了しました。引き続き、データの更新を行ってください。', 'admin');

        // https://github.com/EC-CUBE/ec-cube/pull/4117
        // 上記の修正により, プロキシファイルの再生成が必要になる.
        // 1プロセスで完結できない処理のため, ルーティングを分割しながら順次実行する.
        $dir = $this->getParameter('kernel.project_dir').'/app/proxy/entity';
        $finder = new Finder();
        $finder->in($dir)
            ->name('*.php');

        foreach ($finder->files() as $file) {
            unlink($file->getRealPath());
        }

        $this->session->set('update_plugin.clear_proxy_ok', true);

        $cacheUtil->clearCache();

        return $this->redirectToRoute('eccube_updater405to406_admin_dump_autoload');
    }

    /**
     * @see https://github.com/EC-CUBE/ec-cube/pull/4117
     * @Route("/%eccube_admin_route%/eccube_updater_405_to_406/dump_autoload", name="eccube_updater405to406_admin_dump_autoload")
     */
    public function dumpAutoload(CacheUtil $cacheUtil)
    {
        if (!$this->session->get('update_plugin.clear_proxy_ok', false)) {
            throw new BadRequestHttpException();
        }

        $this->session->remove('update_plugin.clear_proxy_ok');

        $this->composerApiService->runCommand([
            'command' => 'dump-autoload',
        ]);

        $this->session->set('update_plugin.dump_autoload_ok', true);

        $cacheUtil->clearCache();

        return $this->redirectToRoute('eccube_updater405to406_admin_gen_proxy');
    }

    /**
     * @see https://github.com/EC-CUBE/ec-cube/pull/4117
     * @Route("/%eccube_admin_route%/eccube_updater_405_to_406/gen_proxy", name="eccube_updater405to406_admin_gen_proxy")
     */
    public function generateProxy(CacheUtil $cacheUtil)
    {
        if (!$this->session->get('update_plugin.dump_autoload_ok', false)) {
            throw new BadRequestHttpException();
        }

        $this->session->remove('update_plugin.dump_autoload_ok');

        $this->runCommand([
            'command' => 'eccube:generate:proxies',
        ]);

        $cacheUtil->clearCache();

        return $this->redirectToRoute('eccube_updater405to406_admin_update_data');
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
     * @Route("/%eccube_admin_route%/eccube_updater_405_to_406/update_data", name="eccube_updater405to406_admin_update_data")
     * @Template("@EccubeUpdater405to406/admin/update_data.twig")
     */
    public function updateData(Request $request, CacheUtil $cacheUtil)
    {
        set_time_limit(0);

        $form = $this->createForm(FormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

            return $this->redirectToRoute('eccube_updater405to406_admin_complete');
        }

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * 完了画面を表示.
     *
     * @Route("/%eccube_admin_route%/eccube_updater_405_to_406/complete", name="eccube_updater405to406_admin_complete")
     * @Template("@EccubeUpdater405to406/admin/complete.twig")
     */
    public function complete()
    {
        $fs = new Filesystem();
        if (file_exists($this->dataDir)) {
            $fs->remove($this->dataDir);
        }

        $this->addSuccess('バージョンアップが完了しました。', 'admin');

        return [];
    }

    protected function execRequirePlugins()
    {
        $packageNames = [];

        $Plugins = $this->getPlugins();

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

    /**
     * @return Plugin[]
     */
    protected function getPlugins()
    {
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

        return $Plugins;
    }
}
