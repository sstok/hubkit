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

namespace HubKit\Tests;

use HubKit\Config;
use HubKit\ConfigFactory;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ConfigFactoryTest extends TestCase
{
    /** @test */
    public function it_creates(): void
    {
        $config = new Config([
            'schema_version' => 3,
            'github' => [
                'github.com' => [
                    'username' => 'test',
                    'api_token' => 'test-token',
                ],
            ],
            '_local' => $local = [
                'schema_version' => 3,
                'adapter' => 'github',
                'host' => null,
                'repository' => null,
                'main_branch' => 'main',
                'branches_alias' => [],
                'branches' => [
                    ':default' => [
                        'upmerge' => true,
                        'sync-tags' => true,
                        'maintained' => true,
                        'ignore-default' => false,
                        'split' => [],
                    ],
                ],
                'release' => [
                    'split' => 'all',
                    'signed' => true,
                ],
                'pull_request' => [
                    'split' => 'all',
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config',
        ]);

        self::assertEquals(
            $config,
            $resolved = ConfigFactory::createFromFiles(
                __DIR__ . '/Fixtures/config/',
                __DIR__ . '/Fixtures/config/config.php',
            )
        );

        self::assertEquals('main', $resolved->getMainBranch());
        self::assertEquals(
            $config,
            ConfigFactory::create(
                [
                    'schema_version' => 3,
                    'github' => [
                        'github.com' => [
                            'username' => 'test',
                            'api_token' => 'test-token',
                        ],
                    ],
                ],
                $local,
                __DIR__ . '/Fixtures/config',
            ),
        );
    }

    /** @test */
    public function it_creates_with_local_config_file(): void
    {
        $config = new Config([
            'schema_version' => 3,
            'github' => [
                'github.com' => [
                    'username' => 'test',
                    'api_token' => 'test-token',
                ],
            ],
            '_local' => [
                'schema_version' => 2,
                'main_branch' => 'trunk',
                'branches_alias' => [],
                'branches' => [
                    ':default' => [
                        'upmerge' => true,
                        'sync-tags' => true,
                        'split' => [],
                        'ignore-default' => false,
                        'maintained' => true,
                    ],
                    '2.0' => [
                        'sync-tags' => true,
                        'split' => [
                            'src/Bundle/CoreBundle' => [
                                'url' => 'git@github.com:park-manager/core-bundle.git',
                                'sync-tags' => null,
                            ],
                            'src/Bundle/UserBundle' => [
                                'url' => 'git@github.com:park-manager/user-bundle.git',
                                'sync-tags' => null,
                            ],
                            'doc' => [
                                'url' => 'git@github.com:park-manager/doc.git',
                                'sync-tags' => false,
                            ],
                        ],
                        'upmerge' => true,
                        'ignore-default' => false,
                        'maintained' => true,
                    ],
                ],
                'adapter' => 'github',
                'host' => null,
                'repository' => null,
                'pull_request' => [
                    'split' => 'all',
                ],
                'release' => [
                    'split' => 'all',
                    'signed' => true,
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config',
        ]);

        self::assertEquals(
            $config,
            $resolved = ConfigFactory::createFromFiles(
                __DIR__ . '/Fixtures/config/',
                __DIR__ . '/Fixtures/config/config.php',
                __DIR__ . '/Fixtures/config/_hupkit/config.php',
            )
        );
        self::assertEquals('trunk', $resolved->getMainBranch());
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidBranchNames
     */
    public function it_validates_branches_naming(string $branchName, string $message): void
    {
        try {
            ConfigFactory::resolveLocalConfig([
                'schema_version' => 2,
                'branches' => [
                    $branchName => [
                        'split' => [
                            'doc' => [
                                'url' => 'git@github.com:park-manager/doc.git',
                                'sync-tags' => false,
                            ],
                        ],
                    ],
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (\RuntimeException $e) {
            self::assertEquals('Local configuration contains one or more errors. Invalid configuration for path "hubkit.branches": ' . $message, $e->getMessage());
        }
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function provideInvalidBranchNames(): iterable
    {
        // Any valid branch name, including non versioned
        // Disallow deep references branch-names, HEAD, etc.

        yield 'head ref' => ['head', 'Invalid branch-name or relative pattern "head", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot use Git ref HEAD as branch name.'];
        yield 'HEAD ref' => ['HEAD', 'Invalid branch-name or relative pattern "HEAD", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot use Git ref HEAD as branch name.'];
        yield '#HEAD ref' => ['#HEAD', 'Invalid branch-name or relative pattern "HEAD", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot use Git ref HEAD as branch name.']; // Nice try
        yield 'heads ref' => ['heads/main', 'Invalid branch-name or relative pattern "heads/main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'tags ref' => ['tags/v1.0.0', 'Invalid branch-name or relative pattern "tags/v1.0.0", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'remote ref' => ['remotes/upstream/main', 'Invalid branch-name or relative pattern "remotes/upstream/main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'notes ref' => ['notes/pull-request-metadata', 'Invalid branch-name or relative pattern "notes/pull-request-metadata", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot start with Git refs (heads, tags, remotes, notes)/.'];

        yield 'double dot' => ['feature../main', 'Invalid branch-name or relative pattern "feature../main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double slash' => ['feature//main', 'Invalid branch-name or relative pattern "feature//main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];
        yield 'ends with slash' => ['feature/main/', 'Invalid branch-name or relative pattern "feature/main/", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];
        yield 'space character' => ['feature main', 'Invalid branch-name or relative pattern "feature main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double-dash' => ['feature--main', 'Invalid branch-name or relative pattern "feature--main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double-dash with slash' => ['feature/-main', 'Invalid branch-name or relative pattern "feature/-main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];

        yield 'invalid regexp' => ['/[]/', 'Invalid regexp "/[]/" error: "preg_match(): Compilation failed: missing terminating ] for character class at offset 2".'];
        yield 'regexp with options' => ['/\d\.\d+/s', 'Invalid regexp "\/\\\\d\\\\.\\\\d+\/s", cannot contain start/end anchor or options. Either "/[5-9]\.x/" not "/^[5-9].x$/i".'];
        yield 'regexp with anchors' => ['/^\d\.\d+$/', 'Invalid regexp "\/^\\\\d\\\\.\\\\d+$\/", cannot contain start/end anchor or options. Either "/[5-9]\.x/" not "/^[5-9].x$/i".'];
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidMainBranches
     */
    public function it_requires_main_branch_is_valid(string $branch, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Local configuration contains one or more errors. Invalid configuration for path "hubkit.main_branch": ' . $message);

        ConfigFactory::resolveLocalConfig(['schema_version' => 2, 'main_branch' => $branch]);
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidSplitPrefixes
     */
    public function it_validates_split_prefixes(string $prefix, string $message): void
    {
        try {
            ConfigFactory::resolveLocalConfig([
                'schema_version' => 2,
                'branches' => [
                    'main' => [
                        'split' => [
                            'src/Core' => [
                                'url' => 'git@github.com:park-manager/core.git',
                            ],
                            $prefix => [
                                'url' => 'git@github.com:park-manager/doc.git',
                                'sync-tags' => false,
                            ],
                        ],
                    ],
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (\RuntimeException $e) {
            self::assertEquals('Local configuration contains one or more errors. Invalid configuration for path "hubkit.branches.main": ' . $message, $e->getMessage());
        }
    }

    /** @return iterable<int, array{0: string, 1: string}> */
    public static function provideInvalidSplitPrefixes(): iterable
    {
        yield ['/he', 'Invalid prefix "/he". Cannot start or end with a slash.'];
        yield ['he/', 'Invalid prefix "he/". Cannot start or end with a slash.'];
        yield ['/he/', 'Invalid prefix "/he/". Cannot start or end with a slash.'];
        yield ['he/wat/', 'Invalid prefix "he/wat/". Cannot start or end with a slash.'];
        yield ['he/../wat', 'Invalid prefix "he/../wat". Must be a path relative to the root directory, without backtracking.'];

        yield [' he', 'Invalid prefix " he". Cannot start or end with space characters.'];
        yield ['he ', 'Invalid prefix "he ". Cannot start or end with space characters.'];
        yield ["he\n/he", 'Invalid prefix "he\n/he". Cannot contain newline characters.'];
        yield [' ', 'Invalid prefix " ". Cannot start or end with space characters.'];
        yield ['', 'A prefix cannot be empty.'];

        yield ['src/core', 'Prefix "src/core" is already set as "src/Core".'];
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function provideInvalidMainBranches(): iterable
    {
        yield 'head ref' => ['head', 'Cannot use Git ref HEAD as branch name.'];
        yield 'HEAD ref' => ['HEAD', 'Cannot use Git ref HEAD as branch name.'];
        yield 'heads ref' => ['heads/main', 'Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'tags ref' => ['tags/v1.0.0', 'Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'remote ref' => ['remotes/upstream/main', 'Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'notes ref' => ['notes/pull-request-metadata', 'Cannot start with Git refs (heads, tags, remotes, notes)/.'];

        yield 'double dot' => ['feature../main', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double slash' => ['feature//main', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'begins with slash' => ['/feature/main', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'ends with slash' => ['feature/main/', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'space character' => ['feature main', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double-dash' => ['feature--main', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double-dash with slash' => ['feature/-main', 'Invalid name provided, must follow the Git convention for branch names.'];
    }

    /**
     * @test
     *
     * @dataProvider provideValidMainBranches
     */
    public function it_accepts_valid_main_branch_value(string $branch): void
    {
        $config = ConfigFactory::resolveLocalConfig(['schema_version' => 2, 'main_branch' => $branch]);

        self::assertEquals($branch, $config['main_branch']);
    }

    /** @return iterable<string, array{0: string}> */
    public static function provideValidMainBranches(): iterable
    {
        yield 'main' => ['main'];
        yield 'HEAD-ish' => ['HEADing'];
        yield 'dash' => ['main-master'];
        yield 'underscore' => ['main_master'];
        yield 'versioned' => ['2.0'];
        yield 'relative' => ['2.x'];
        yield 'nested' => ['development/new'];
        yield 'nested deep' => ['development/remotes/new'];
        yield 'unicode' => ["\xCE\xA9"];
    }

    /** @test */
    public function it_accepts_branches_aliasing(): void
    {
        $config = ConfigFactory::resolveLocalConfig([
            'schema_version' => 2,
            'main_branch' => 'main',
        ]);

        self::assertEquals([
            'schema_version' => 2,
            'main_branch' => 'main',
            'branches' => [
                ':default' => [
                    'upmerge' => true,
                    'sync-tags' => true,
                    'maintained' => true,
                    'ignore-default' => false,
                    'split' => [],
                ],
            ],
            'branches_alias' => [],
            'adapter' => 'github',
            'host' => null,
            'repository' => null,
            'pull_request' => [
                'split' => 'all',
            ],
            'release' => ['split' => 'all', 'signed' => true],
        ], $config);

        $config = ConfigFactory::resolveLocalConfig([
            'schema_version' => 2,
            'main_branch' => 'main',
            'branches' => [
                ':default' => [
                    'upmerge' => true,
                    'sync-tags' => true,
                    'maintained' => true,
                    'ignore-default' => false,
                    'split' => [],
                ],
            ],
            'branches_alias' => [
                'main' => '2.0',
                'dev/trunk' => '3.0',
            ],
            'adapter' => 'github',
            'host' => null,
            'repository' => null,
            'pull_request' => [
                'split' => 'all',
            ],
        ]);

        self::assertEquals([
            'schema_version' => 2,
            'main_branch' => 'main',
            'branches' => [
                ':default' => [
                    'upmerge' => true,
                    'sync-tags' => true,
                    'maintained' => true,
                    'ignore-default' => false,
                    'split' => [],
                ],
            ],
            'branches_alias' => [
                'main' => '2.0-dev',
                'dev/trunk' => '3.0-dev',
            ],
            'adapter' => 'github',
            'host' => null,
            'repository' => null,
            'pull_request' => [
                'split' => 'all',
            ],
            'release' => ['split' => 'all', 'signed' => true],
        ], $config);
    }
}
