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

namespace HubKit\Service;

use HubKit\Exception\WorkingTreeIsNotReady;
use HubKit\Model\Git\Commit;
use HubKit\Model\Git\GitMultiRef;
use HubKit\Model\Git\GitRef;
use HubKit\Model\Git\RemoteInfo;
use HubKit\Model\Git\RemoteName;
use HubKit\Service\Git\GitBranch;
use HubKit\Service\Git\GitCommit;
use HubKit\Service\Git\GitConfig;
use HubKit\Service\Git\GitRemote;
use HubKit\Service\Git\PushOptions;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Process\Process;

class Git
{
    final public const STATUS_UP_TO_DATE = 'up-to-date';
    final public const STATUS_NEED_PULL = 'need_pull';
    final public const STATUS_NEED_BRANCH = 'need_branch';
    final public const STATUS_NEED_PUSH = 'need_push';
    final public const STATUS_DIVERGED = 'diverged';

    private ?string $gitDir = null;

    private GitBranch $branch;
    private GitRemote $remote;
    private GitCommit $commit;
    private GitConfig $config;

    public function __construct(
        private CliProcess $process,
        private Filesystem $filesystem,
        StyleInterface $style,
    ) {
        $this->branch = new GitBranch($this, $process, $style, $filesystem);
        $this->remote = new GitRemote($this, $process, $style, $filesystem);
        $this->commit = new GitCommit($this, $process, $style, $filesystem);
        $this->config = new GitConfig($this, $process, $style, $filesystem);
    }

    public function isGitDir(): bool
    {
        $process = $this->process->run(['git', 'rev-parse', '--show-toplevel']);

        if (! $process->isSuccessful()) {
            return false;
        }

        $directory = mb_trim($process->getOutput());

        if ($directory === '') {
            return false;
        }

        return str_replace('\\', '/', $this->filesystem->getCwd()) === $directory;
    }

    public function branch(): GitBranch
    {
        return $this->branch;
    }

    public function remote(): GitRemote
    {
        return $this->remote;
    }

    public function commit(): GitCommit
    {
        return $this->commit;
    }

    public function config(): GitConfig
    {
        return $this->config;
    }

    /** @deprecated */
    public function getRemoteDiffStatus(string $remoteName, string $localBranch, ?string $remoteBranch = null): string
    {
        $this->remote->getDiffStatus(new RemoteName($remoteName), new GitMultiRef($localBranch, $remoteBranch ?? $localBranch))->value;
    }

    /** @deprecated */
    public function getActiveBranchName(): string
    {
        return $this->branch->getCurrent();
    }

    /** @deprecated */
    public function getLastTagOnBranch(string $ref = 'HEAD', bool $allowFailure = false): ?string
    {
        return $this->branch->getLastTag(new GitRef($ref), $allowFailure);
    }

    /** @return array<int, string> ['v1.0', 'v1.5', 'v2.0' '...'] */
    public function getVersionBranches(?string $remote = null): array
    {
        return $this->branch->getVersionBranches($remote);
    }

    /** @deprecated */
    public function getLogBetweenCommits(string $start, string $end): array
    {
        return array_map(static fn (Commit $model): array => $model->toArray(), iterator_to_array($this->commit->getLogBetweenCommits($start, $end)));
    }

    /** @deprecated */
    public function getFileChangesBetween(string $start, string $end): array
    {
        return $this->branch->getFileChangesBetween(new GitMultiRef($start, $end));
    }

    /** @deprecated */
    public function remoteBranchExists(string $remote, string $branch): bool
    {
        return $this->remote->branchExists(new RemoteName($remote), new GitRef($branch));
    }

    /** @deprecated */
    public function branchExists(string $branch): bool
    {
        return $this->branch->exists(new GitRef($branch));
    }

    public function deleteRemoteBranch(string $remote, string $ref): void
    {
        $this->process->mustRun(['git', 'push', $remote, ':' . $ref]);
    }

    /** @deprecated */
    public function deleteBranch(string $name, bool $allowFailure = false): void
    {
        $this->branch->delete(new GitRef($name), $allowFailure);
    }

    /** @deprecated */
    public function addNotes(string $notes, string $commitHash, string $ref = 'github-comments'): void
    {
        $this->remote->addNotes($notes, new GitRef($commitHash), $ref);
    }

    /** @deprecated */
    public function pushToRemote(string $remote, array | string $ref, bool $setUpstream = false, bool $force = false): void
    {
        $options = new PushOptions();

        if ($setUpstream) {
            $options->add(PushOptions::SET_UPSTREAM);
        }

        if ($force) {
            $options->add(PushOptions::FORCE);
        }

        $this->remote->push(new RemoteName($remote), null, new GitMultiRef($ref, $ref));
    }

    /** @deprecated */
    public function pullRemote(string $remote, ?string $ref = null): void
    {
        $this->remote->pull(new RemoteName($remote), true, $ref ? new GitRef($ref) : null);
    }

    /** @deprecated */
    public function fetchRemote(string $remote, string $ref): void
    {
        $this->remote->fetch(new RemoteName($remote), new GitRef($ref));
    }

    /** @deprecated */
    public function remoteUpdate(string $remote): void
    {
        $this->remote->fetch(new RemoteName($remote));
    }

    public function isWorkingTreeReady()
    {
        if (mb_trim($this->process->mustRun(['git', 'status', '--porcelain', '--untracked-files=no'])->getOutput()) !== '') {
            return false;
        }

        if (mb_trim($this->process->run(Process::fromShellCommandline('ls `git rev-parse --git-dir` | grep rebase'))->getOutput()) !== '') {
            return false;
        }

        return true;
    }

    /** @deprecated */
    public function checkout(string $branchName, bool $createBranch = false): void
    {
        if ($createBranch) {
            $this->branch->checkoutNew(new GitRef($branchName));

            return;
        }

        $this->branch->checkout(new GitRef($branchName));
    }

    /** @deprecated */
    public function checkoutRemoteBranch(string $remote, string $branchName, bool $create = true): void
    {
        if ($this->branchExists($branchName)) {
            $this->branch->checkout(new GitRef($branchName));

            return;
        }

        if ($create) {
            $this->remote->checkoutNew(new RemoteName($remote), new GitRef($branchName), new GitRef($branchName));
        } else {
            $this->remote->checkout(new RemoteName($remote), new GitRef($branchName));
        }
    }

    /** @deprecated */
    public function trackRemoteBranch(string $remote, string $branchName): void
    {
        $this->remote->trackBranch(new RemoteName($remote), new GitRef($branchName));
    }

    public function guardWorkingTreeReady(): void
    {
        if (! $this->isWorkingTreeReady()) {
            throw new WorkingTreeIsNotReady();
        }
    }

    /** @deprecated */
    public function ensureNotesFetching(string $remote): void
    {
        $this->remote->ensureNotesFetching(new RemoteName($remote));
    }

    /** @deprecated */
    public function ensureBranchInSync(string $remote, string $localBranch, bool $allowPush = true): void
    {
        $this->remote->ensureBranchInSync(new RemoteName($remote), new GitMultiRef($localBranch, $localBranch), $allowPush);
    }

    /** @deprecated */
    public function ensureRemoteExists(string $name, string $url): void
    {
        $this->config->ensureRemoteExists($name, $url);
    }

    /** @deprecated */
    public function getGitConfig(string $config, string $section = 'local', bool $all = false): string
    {
        if ($section === 'local') {
            return $all ? $this->config->getAllLocal($config) : $this->config->getLocal($config);
        }

        return $all ? $this->config->getAllGlobal($config) : $this->config->getGlobal($config);
    }

    /** @deprecated */
    public function getRemoteInfo(string $name = REMOTE_MAIN): RemoteInfo
    {
        return $this->remote->getRemoteInfo(new RemoteName($name));
    }

    /** @deprecated */
    public static function getGitUrlInfo(string $gitUri, ?string $name = null): RemoteInfo
    {
        return RemoteInfo::fromString($gitUri, $name ? new RemoteName($name) : null);
    }

    /** @deprecated */
    public function clone(string $url, string $remoteName = 'origin', ?int $depth = null): void
    {
        $this->remote->clone($url, '.', new RemoteName($remoteName), $depth);
    }

    public function getGitDirectory(): string
    {
        if ($this->gitDir === null) {
            $gitDir = mb_trim($this->process->run(['git', 'rev-parse', '--git-dir'])->getOutput());

            if ($gitDir === '.git') {
                $gitDir = $this->filesystem->getCwd() . '/.git';
            }

            $this->gitDir = $gitDir;
        }

        return $this->gitDir;
    }
}
