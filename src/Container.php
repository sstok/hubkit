<?php

declare(strict_types=1);

/*
 * This file is part of the HuPKit package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace HubKit;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;

class Container extends \Pimple\Container implements ContainerInterface
{
    public function __construct(array $values = [])
    {
        parent::__construct($values);

        $this['config'] = static function (self $container) {
            $localConfigFile = null;

            if (getenv('HUBKIT_NO_LOCAL') !== 'true' && $container['git.file_reader']->fileExists('_hubkit', 'config.php')) {
                $localConfigFile = $container['git.file_reader']->getFile('_hubkit', 'config.php');
            }

            if (getenv('HUBKIT_NO_LOCAL') === 'true') {
                $container['style']->warning('Env HUBKIT_NO_LOCAL=true was set, local configuration was not loaded.');
            }

            return ConfigFactory::createFromFiles($container['current_dir'], $container['config_file'], $localConfigFile);
        };

        $this['guzzle'] = static function (self $container) {
            $options = [];

            if ($container['console_io']->isDebug()) {
                $options['debug'] = true;
            }

            return new GuzzleClient($options);
        };

        $this['style'] = static fn (self $container) => new SymfonyStyle($container['sf.console_input'], $container['sf.console_output']);

        $this['process'] = static fn (self $container) => new Service\CliProcess($container['sf.console_output']);

        $this['git'] = static fn (self $container) => new Service\Git($container['process'], $container['filesystem'], $container['style']);

        $this['git.branch'] = static fn (self $container) => new Service\Git\GitBranch($container['process'], $container['style']);

        $this['git.config'] = static fn (self $container) => new Service\Git\GitConfig($container['process'], $container['style']);

        $this['git.temp_repository'] = static fn (self $container) => new Service\Git\GitTempRepository($container['process'], $container['filesystem']);

        $this['git.file_reader'] = static fn (self $container) => new Service\Git\GitFileReader($container['git.branch'], $container['git.config'], $container['process'], $container['git.temp_repository']);

        $this['splitsh_git'] = static fn (self $container) => new Service\SplitshGit(
            $container['git'],
            $container['process'],
            $container['logger'],
            $container['git.temp_repository'],
            (new ExecutableFinder())->find('splitsh-lite') ?? ''
        );

        $this['branch_splitsh_git'] = static fn (self $container) => new Service\BranchSplitsh(
            $container['splitsh_git'],
            $container['github'],
            $container['config'],
            $container['style'],
            $container['git'],
        );

        $this['filesystem'] = static fn () => new Service\Filesystem(cacheDir: $_SERVER['HUBKIT_CACHE_DIR'] ?? null);

        $this['editor'] = static fn (self $container) => new Service\Editor($container['process'], $container['filesystem']);

        $this['release_hooks'] = static fn (self $container) => new Service\ReleaseHooks(
            $container['git.file_reader'],
            $container['logger'],
            $container,
            $container['git']
        );

        //
        // Third-party APIs
        //

        $this['github'] = static fn (self $container) => new Service\GitHub($container['guzzle'], $container['config']);
    }

    public function get($id)
    {
        return $this[$id];
    }

    public function has($id): bool
    {
        return isset($this[$id]);
    }
}
