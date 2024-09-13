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

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\Exception as ConfigException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigFactory
{
    public static function createFromFiles(string $currentDir, string $configFile, ?string $localConfigFile = null): Config
    {
        $currentDir = self::normalizePath($currentDir);
        $configFile = self::normalizePath($configFile);

        $config = self::resolveConfigMainConfig(require $configFile);

        if ($localConfigFile) {
            $localConfigFile = self::normalizePath($localConfigFile);

            try {
                $localConfig = require $localConfigFile;
            } catch (\ParseError $e) {
                throw new \RuntimeException('Unable to load configuration file, run with env `HUBKIT_NO_LOCAL=true` to bypass local config loading. Error: ' . $e->getMessage(), 1, $e);
            }

            $config['_local'] = self::resolveLocalConfig($localConfig);
        } else {
            $config['_local'] = self::resolveLocalConfig(['schema_version' => 3]);
        }

        $config['current_dir'] = $currentDir;

        return new Config($config);
    }

    private static function normalizePath(?string $path = null): string
    {
        $realPath = realpath($path);

        if ($realPath === false) {
            throw new \InvalidArgumentException(\sprintf('Unable to normalize path "%s", no such file or directory.', $path));
        }

        return str_replace('\\', '//', $realPath);
    }

    public static function create(?array $mainConfig = null, ?array $localConfig = null, ?string $currentDir = null): Config
    {
        $config = self::resolveConfigMainConfig($mainConfig ?? ['schema_version' => 3]);
        $config['_local'] = self::resolveLocalConfig($localConfig ?? ['schema_version' => 3]);
        $config['current_dir'] = $currentDir ?? getcwd();

        return new Config($config);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public static function resolveConfigMainConfig(array $config): array
    {
        $treeBuilder = new TreeBuilder('hubkit');
        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('schema_version')
                    ->min(3)
                    ->max(3)
                ->end()
                ->arrayNode('github')
                    ->useAttributeAsKey('host')
                    ->arrayPrototype()
                        ->normalizeKeys(false)
                        ->children()
                            ->scalarNode('username')->cannotBeEmpty()->end()
                            ->scalarNode('api_token')->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        try {
            return (new Processor())->process($treeBuilder->buildTree(), [$config]);
        } catch (ConfigException $e) {
            throw new \RuntimeException('Configuration contains one or more errors. ' . $e->getMessage(), 1, $e);
        }
    }

    private static function addBranchesNode(): ArrayNodeDefinition
    {
        return (new TreeBuilder('branches'))
            ->getRootNode()
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->defaultValue([':default' => ['maintained' => true, 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'split' => []]])
            ->validate()
                ->always()
                ->then(static function ($v): array {
                    foreach ($v as $name => $config) {
                        if ($name === ':default') {
                            continue;
                        }

                        if ($name[0] === '/') {
                            if (@preg_match($name, 'test') === false) {
                                throw new \InvalidArgumentException(\sprintf('Invalid regexp %s error: %s.', json_encode($name, \JSON_UNESCAPED_SLASHES), json_encode(error_get_last()['message'] ?? 'Unknown')), \JSON_UNESCAPED_SLASHES);
                            }

                            if (preg_match('{[\$\^]|/\w+$}', $name) > 0) {
                                throw new \InvalidArgumentException(\sprintf('Invalid regexp %s, cannot contain start/end anchor or options. Either "/[5-9]\.x/" not "/^[5-9].x$/i".', json_encode($name)));
                            }
                        } else {
                            if ($name[0] === '#') {
                                $name = mb_substr($name, 1);
                            }

                            // 1.* would be invalid as branch-name
                            if (preg_match('{^(\d+\.\*)$}', $name) === 1) {
                                continue;
                            }

                            try {
                                self::validateBranchName($name);
                            } catch (\InvalidArgumentException $e) {
                                throw new \InvalidArgumentException(\sprintf('Invalid branch-name or relative pattern %s, must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: %s', json_encode($name, \JSON_UNESCAPED_SLASHES), $e->getMessage()), 0, $e);
                            }
                        }
                    }

                    return $v;
                })
            ->end()
            ->arrayPrototype()
                ->normalizeKeys(false)
                ->treatFalseLike(['maintained' => false, 'upmerge' => false, 'sync-tags' => true, 'ignore-default' => true, 'split' => []])
                ->children()
                    ->booleanNode('upmerge')->defaultTrue()->info('Set to false to disable upmerge for this branch configuration, and continue with next possible version')->end()
                    ->booleanNode('sync-tags')->defaultTrue()->end()
                    ->booleanNode('maintained')->defaultTrue()->end()
                    ->booleanNode('ignore-default')->defaultFalse()->info('Ignore the ":default" branch configuration')->end()
                    ->arrayNode('split')
                        ->normalizeKeys(false)
                        ->useAttributeAsKey('directory')
                        ->arrayPrototype()
                            ->normalizeKeys(false)
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static fn ($v): array => ['url' => $v])
                            ->end()
                            ->beforeNormalization()
                                ->ifTrue(static fn ($v): bool => $v === false)
                                ->then(static fn ($v): array => ['url' => $v])
                            ->end()
                            ->children()
                                ->scalarNode('sync-tags')
                                    ->defaultNull()
                                    ->treatNullLike(null)
                                    ->validate()
                                        ->ifTrue(static fn ($v): bool => $v !== null && ! \is_bool($v))
                                        ->thenInvalid('Value %s must be either true, false or null.')
                                    ->end()
                                ->end()
                                ->scalarNode('url')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                // When marked as unmaintained 'unset' all values
                ->validate()
                    ->ifTrue(static fn (array $v): bool => $v['maintained'] === false)
                    ->then(static fn (): array => ['maintained' => false, 'upmerge' => false, 'sync-tags' => false, 'ignore-default' => true, 'split' => []])
                ->end()
                ->validate()
                    ->always()
                    ->then(static function (array $v): array {
                        $found = [];

                        foreach ($v['split'] as $prefix => $config) {
                            if (preg_match('{^\s|\s$}', $prefix)) {
                                throw new \InvalidArgumentException(\sprintf('Invalid prefix %s. Cannot start or end with space characters.', json_encode($prefix, \JSON_UNESCAPED_SLASHES)));
                            }

                            if (preg_match('{[\n\r]}', $prefix)) {
                                throw new \InvalidArgumentException(\sprintf('Invalid prefix %s. Cannot contain newline characters.', json_encode($prefix, \JSON_UNESCAPED_SLASHES)));
                            }

                            if ($prefix === '') {
                                throw new \InvalidArgumentException('A prefix cannot be empty.');
                            }

                            if ($prefix[0] === '/' || str_ends_with($prefix, '/')) {
                                throw new \InvalidArgumentException(\sprintf('Invalid prefix %s. Cannot start or end with a slash.', json_encode($prefix, \JSON_UNESCAPED_SLASHES)));
                            }

                            if (str_contains($prefix, '/../')) {
                                throw new \InvalidArgumentException(\sprintf('Invalid prefix %s. Must be a path relative to the root directory, without backtracking.', json_encode($prefix, \JSON_UNESCAPED_SLASHES)));
                            }

                            if (isset($found[mb_strtolower($prefix)])) {
                                throw new \InvalidArgumentException(\sprintf('Prefix %s is already set as %s.', json_encode($prefix, \JSON_UNESCAPED_SLASHES), json_encode($found[mb_strtolower($prefix)], \JSON_UNESCAPED_SLASHES)));
                            }

                            $found[mb_strtolower($prefix)] = $prefix;
                        }

                        return $v;
                    })
                ->end()
            ->end()
        ;
    }

    private static function addBranchesAliasNode(): ArrayNodeDefinition
    {
        return (new TreeBuilder('branches_alias'))
            ->getRootNode()
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->validate()
                ->always()
                ->then(static function ($v): array {
                    foreach ($v as $name => $label) {
                        try {
                            self::validateBranchName($name);
                        } catch (\InvalidArgumentException $e) {
                            throw new \InvalidArgumentException(\sprintf('Invalid branch-name %s: %s', json_encode($name), $e->getMessage()), 0, $e);
                        }

                        if (! \is_string($label)) {
                            throw new \InvalidArgumentException(\sprintf('Invalid branch-alias for %s, should should be a string to prevent casting mismatches.', $name));
                        }

                        if (! preg_match('/^([1-9]\d*\.\d+)$/', $label)) {
                            throw new \InvalidArgumentException(\sprintf('Invalid branch-alias for %s, should consists of major and minor version without any prefix or suffix. like: 1.2. Got: %s', $name, $label));
                        }

                        $v[$name] = $label . '-dev';
                    }

                    return $v;
                })
            ->end()
            ->scalarPrototype()
            ->end()
        ;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public static function resolveLocalConfig(array $config): array
    {
        $treeBuilder = new TreeBuilder('hubkit');
        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('schema_version')
                    ->isRequired()
                    ->min(2)
                    ->max(3)
                ->end()
                ->append(self::addBranchesNode())
                ->append(self::addBranchesAliasNode())
                ->enumNode('adapter')
                    ->values(['github'])
                    ->defaultValue('github')
                ->end()
                ->scalarNode('host')->defaultNull()->end()
                ->scalarNode('repository')->defaultNull()->end()
                ->scalarNode('main_branch')
                    ->defaultValue('main')
                    ->validate()
                        ->always(fn ($v) => self::validateBranchName((string) $v))
                    ->end()
                ->end()
                ->arrayNode('pull_request')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('split')
                            ->values(['all', 'changed-only', 'none'])
                            ->defaultValue('all')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('release')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('split')
                            ->values(['all', 'changed-only'])
                            ->defaultValue('all')
                        ->end()
                        ->enumNode('signed')
                            ->values([true, false, null])
                            ->defaultValue(true)
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        try {
            return (new Processor())->process($treeBuilder->buildTree(), [$config]);
        } catch (ConfigException $e) {
            throw new \RuntimeException('Local configuration contains one or more errors. ' . $e->getMessage(), 1, $e);
        }
    }

    private static function validateBranchName(string $v): string
    {
        if (mb_strtoupper($v) === 'HEAD') {
            throw new \InvalidArgumentException('Cannot use Git ref HEAD as branch name.');
        }

        if (preg_match('{^(heads|tags|remotes|notes)/}i', $v) === 1) {
            throw new \InvalidArgumentException('Cannot start with Git refs (heads, tags, remotes, notes)/.');
        }

        if (preg_match('{^([\p{L}\p{N}]+([./_-]?[\p{L}\p{N}]+)?)+$}ui', $v) !== 1) {
            throw new \InvalidArgumentException('Invalid name provided, must follow the Git convention for branch names.');
        }

        return $v;
    }
}
