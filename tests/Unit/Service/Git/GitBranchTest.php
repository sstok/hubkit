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

namespace HubKit\Tests\Unit\Service\Git;

use HubKit\Model\Git\RemoteName;
use HubKit\Service\CliProcess;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use HubKit\Service\Git\GitBranch;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class GitBranchTest extends TestCase
{
    use ProphecyTrait;

    public function provideExpectedVersions(): iterable
    {
        return [
            [['master', '1.0', 'v1.1', '2.0', 'x.1'], ['1.0', 'v1.1', '2.0']],
            [['v1.1', 'main', '2.0', '1.0'], ['1.0', 'v1.1', '2.0']],
            [['master', 'feature-1.0', '1.0', 'v1.1', '2.0'], ['1.0', 'v1.1', '2.0']],
            [['master', '1.0', 'v1.1', '2.0', '1.x'], ['1.0', 'v1.1', '1.x', '2.0']],
            [['master', '1.0', 'v1.1', '2.0', 'v1.x'], ['1.0', 'v1.1', 'v1.x', '2.0']],

            'Duplicate version match' => [['main', '1.0', 'v1.0', 'v1.1', '2.0', 'v1.x'], ['1.0', 'v1.0', 'v1.1', 'v1.x', '2.0']],
            'Duplicate version match 2' => [['main', 'v1.0', 'v1.1', '2.0', 'v1.x', '1.0'], ['v1.0', '1.0', 'v1.1', 'v1.x', '2.0']],
        ];
    }

    /**
     * @test
     *
     * @param string[] $branches
     * @param string[] $expectedVersions
     *
     * @dataProvider provideExpectedVersions
     */
    public function get_versioned_branches_in_correct_order(array $branches, array $expectedVersions): void
    {
        self::assertSame($expectedVersions, $this->createGitService($branches)->getVersionBranches(new RemoteName('upstream')));
    }

    /**
     * @param string[] $branches
     */
    private function createGitService(array $branches): GitBranch
    {
        $process = $this->prophesize(Process::class);
        $process->getOutput()->willReturn(implode("\n", $branches));

        $processHelper = $this->prophesize(CliProcess::class);
        $processHelper
            ->mustRun(['git', 'for-each-ref', '--format', '%(refname:strip=3)', 'refs/remotes/upstream'])
            ->willReturn($process->reveal())
        ;

        $filesystem = $this->prophesize(Filesystem::class);
        $filesystem->getCwd()->willReturn('/home/homer/project-1');

        return new GitBranch(
            $this->createStub(Git::class),
            $processHelper->reveal(),
            $this->createStub(StyleInterface::class),
            $filesystem->reveal(),
        );
    }
}
