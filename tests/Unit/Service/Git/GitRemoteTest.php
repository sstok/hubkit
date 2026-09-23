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

use HubKit\Model\Git\GitMultiRef;
use HubKit\Model\Git\GitRef;
use HubKit\Model\Git\RemoteName;
use HubKit\Service\CliProcess;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use HubKit\Service\Git\GitBranch;
use HubKit\Service\Git\GitRemote;
use HubKit\Service\Git\PushOptions;
use HubKit\Service\Git\RemoteDiffStatus;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class GitRemoteTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_syncs_nothing_when_diff_status_gives_up_to_date(): void
    {
        $git = $this->givenGitRemoteDiffStatus(RemoteDiffStatus::UpToDate);

        $git->ensureBranchInSync(
            new RemoteName('origin'),
            new GitMultiRef('main', 'main'),
            allowPush: true
        );

        self::assertEquals(['origin', 'master'], $git->diffStatusCall);
        self::assertEquals([], $git->pullCall, 'No pull was expected');
        self::assertEquals([], $git->pushCall, 'No push was expected');
    }

    /** @test */
    public function it_syncs_pull_when_diff_status_gives_needs_pull(): void
    {
        $git = $this->givenGitRemoteDiffStatus(RemoteDiffStatus::STATUS_NEED_PULL);

        $git->ensureBranchInSync('origin', 'master', true);

        self::assertEquals(['origin', 'master'], $git->diffStatusCall);
        self::assertEquals(['origin', 'master'], $git->pullCall);
        self::assertEquals([], $git->pushCall, 'No push was expected');
    }

    /** @test */
    public function it_syncs_push_when_diff_status_gives_needs_push_and_push_is_allowed(): void
    {
        $git = $this->givenGitRemoteDiffStatus(RemoteDiffStatus::STATUS_NEED_PUSH);

        $git->ensureBranchInSync('origin', 'master', true);

        self::assertEquals(['origin', 'master'], $git->diffStatusCall);
        self::assertEquals([], $git->pullCall, 'No pull was expected');
        self::assertEquals([], $git->pushCall);
    }

    /** @test */
    public function it_syncs_throws_when_diff_status_gives_needs_push_but_push_is_forbidden(): void
    {
        $git = $this->givenGitRemoteDiffStatus(RemoteDiffStatus::STATUS_NEED_PUSH);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Branch "master" contains commits not existing in the remote version.Push is prohibited for this operation.'
        );

        $git->ensureBranchInSync('origin', 'master', false);
    }

    /** @test */
    public function it_syncs_throws_when_diff_status_gives_diverged(): void
    {
        $git = $this->givenGitRemoteDiffStatus(RemoteDiffStatus::STATUS_DIVERGED);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot safely perform the operation. ' .
            'Your local and remote version of branch "master" have differed.' .
            ' Please resolve this problem manually.'
        );

        $git->ensureBranchInSync('origin', 'master', true);
    }

    private function createGitService(array $branches): GitBranch
    {
        $process = $this->prophesize(Process::class);
        $process->getOutput()->willReturn(implode("\n", $branches));

        $processHelper = $this->prophesize(CliProcess::class);
        $processHelper
            ->mustRun(['git', 'for-each-ref', '--format', '%(refname:strip=3)', 'refs/remotes/upstream'])
            ->willReturn($process->reveal())
        ;

        $git = $this->prophesize(Git::class);

        $filesystem = $this->prophesize(Filesystem::class);
        $filesystem->getCwd()->willReturn('/home/homer/project-1');

        return new GitBranch(
            $git->reveal(),
            $processHelper->reveal(),
            $this->createMock(StyleInterface::class),
            $filesystem->reveal(),
        );
    }

    private function givenGitRemoteDiffStatus(RemoteDiffStatus $status): GitRemote
    {
        // Use a self-shunt to mock the return status of getDiffStatus and spy on the push() method.
        //
        // Performing actual Git commands for this related test would be much slower (and not to mention difficult),
        // plus the called methods are already tested on their own. ensureBranchInSync() is merely a helper method.
        $git = new class extends GitRemote {
            public RemoteDiffStatus $diffStatus;
            public array $diffStatusCall = [];
            public array $pushCall = [];
            public array $pullCall = [];

            /**
             * @override
             */
            public function __construct()
            {
            }

            public function getDiffStatus(RemoteName $remoteName, GitMultiRef $branches): RemoteDiffStatus
            {
                $this->diffStatusCall = [$remoteName, $branches];

                return $this->diffStatus;
            }

            public function push(RemoteName $remote, ?PushOptions $options = null, GitMultiRef ...$ref): void
            {
                $this->pushCall = [$remote, $options, ...$ref];
            }

            public function pull(RemoteName $remote, bool $rebase = true, ?GitRef $ref = null): void
            {
                $this->pullCall = [$remote, $ref];
            }
        };
        $git->diffStatus = $status;

        return $git;
    }
}
