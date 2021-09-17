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

namespace Plugin\EccubeUpdater406to410\Controller\Admin;

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
use Plugin\EccubeUpdater406to410\Common\Constant as UpdaterConstant;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        $this->supported = version_compare(Constant::VERSION, UpdaterConstant::FROM_VERSION.'-p1', '=');

        $this->projectDir = realpath($eccubeConfig->get('kernel.project_dir'));
        $this->dataDir = $this->projectDir.'/app/PluginData/eccube_update_plugin';
        $this->updateFile = realpath(__DIR__.'/../../Resource/update_file.tar.gz');
    }

    /**
     * @Route("/%eccube_admin_route%/eccube_updater_406_to_410/config", name="eccube_updater406to410_admin_config")
     * @Template("@EccubeUpdater406to410/admin/config.twig")
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

        return [
            'supported' => $this->supported,
        ];
    }

    /**
     * プラグインのEC-CUBE対応バージョンのチェックを行う.
     *
     * @Route("/%eccube_admin_route%/eccube_updater_406_to_410/check_plugin_version", name="eccube_updater406to410_admin_check_plugin_version")
     * @Template("@EccubeUpdater406to410/admin/check_plugin_vesrion.twig")
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
            dump($data);
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
     * @Route("/%eccube_admin_route%/eccube_updater_406_to_410/check_permission", name="eccube_updater406to410_admin_check_permission", methods={"POST"})
     * @Template("@EccubeUpdater406to410/admin/check_permission.twig")
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
     * @Route("/%eccube_admin_route%/eccube_updater_406_to_410/check_source", name="eccube_updater406to410_admin_check_source", methods={"POST"})
     * @Template("@EccubeUpdater406to410/admin/check_source.twig")
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
     * @Route("/%eccube_admin_route%/eccube_updater_406_to_410/update_files", name="eccube_updater406to410_admin_update_files", methods={"POST"})
     */
    public function updateFiles(Request $request, CacheUtil $cacheUtil)
    {
        $this->isTokenValid();

        set_time_limit(0);

        $completeUrl = $this->generateUrl('eccube_updater406to410_admin_complete', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->clearComposerCache();
        $this->clearProxies();
        $this->clearSessions();
        $this->removeDeletedFiles41();

        // 更新ファイルで上書き
        $fs = new Filesystem();
        $fs->mirror($this->dataDir, $this->projectDir);

        $phpPath = '/Users/chihiro_adachi/.anyenv/envs/phpenv/shims/php';

        $commands = [
            'cache:clear --no-warmup',
            'cache:warmup --no-optional-warmers',
            'eccube:update406to401:plugin-already-installed',
            'eccube:generate:proxies',
            'doctrine:schema:update --dump-sql -f',
            'doctrine:migrations:migrate --no-interaction',
            'eccube:update406to401:update-pre-install-plugins',
            'cache:clear --no-warmup',
            'cache:warmup --no-optional-warmers',
            'eccube:update406to401:dump-autoload',
        ];

        foreach ($commands as $command) {
            \error_log($phpPath.' '.$this->projectDir.'/bin/console '.$command);
            $process = new Process($phpPath.' '.$this->projectDir.'/bin/console '.$command);
            $process->setTimeout(600);
            $process->run();
            \error_log($process->isSuccessful() ? 'ok' : 'ng');
        }

        // ファイル上書き後, return Responseで遷移できないため直接リダイレクト
        header('Location: '.$completeUrl);
        exit;
    }

    /**
     * 完了画面を表示.
     *
     * @Route("/%eccube_admin_route%/eccube_updater_406_to_410/complete", name="eccube_updater406to410_admin_complete")
     * @Template("@EccubeUpdater406to410/admin/complete.twig")
     */
    public function complete(CacheUtil $cacheUtil)
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
            $packageNames[] = 'ec-cube/'.strtolower($Plugin->getCode()).':'.$Plugin->getVersion();
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

    private function clearComposerCache()
    {
        $fs = new Filesystem();
        $fs->remove($this->projectDir.'/app/Plugin/.composer');
    }

    private function clearSessions()
    {
        $fs = new Filesystem();
        $fs->remove($this->projectDir.'/var/sessions');
    }

    private function clearProxies()
    {
        $fs = new Filesystem();
        $finder = new Finder();
        $finder->in($this->projectDir.'/app/proxy/entity')
            ->name('*.php');

        foreach ($finder->files() as $file) {
            $fs->remove($file->getRealPath());
        }
    }

    /**
     * 4.1で削除されたファイルを削除する
     */
    private function removeDeletedFiles41()
    {
        $fs = new Filesystem();
        $fs->remove($this->projectDir.'/src/Eccube/Application.php');
        $fs->remove($this->projectDir.'/src/Eccube/Doctrine/EventSubscriber/LoadEventSubscriber.php');
        $fs->remove($this->projectDir.'/src/Eccube/ServiceProvider/EccubeServiceProvider.php');
        $fs->remove($this->projectDir.'/src/Eccube/ServiceProvider/ServiceProviderInterface.php');
    }
}
