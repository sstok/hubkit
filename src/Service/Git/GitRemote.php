<?php

declare(strict_types=1);

namespace HubKit\Service\Git;

use HubKit\Model\Git\GitMultiRef;
use HubKit\Model\Git\GitRef;
use HubKit\Model\Git\RemoteName;
use HubKit\StringUtil;

class GitRemote extends GitBase
{
    public function clone(string $url, string $directory = '.', RemoteName $remoteName = new RemoteName('origin'), ?int $depth = null): void
    {
        $command = ['git', 'clone', $url, $directory];

        if ($depth !== null) {
            $command[] = '--depth';
            $command[] = $depth;
        }

        $command[] = '--origin';
        $command[] = $remoteName;

        $this->process->mustRun($command);
    }

    public function checkout(RemoteName $remote, GitRef $ref): void
    {
        $this->process->mustRun(['git', 'checkout', 'remotes/' . $remote . '/' . $ref]);
    }

    public function checkoutNew(RemoteName $remote, GitRef $source, GitRef $name): void
    {
        $name->expectBranch();

        $this->process->mustRun(['git', 'checkout', 'remotes/' . $remote . '/' . $source, '-b', $name]);
    }

    /**
     * @see https://gist.github.com/WebPlatformDocs/437f763b948c926ca7ba
     * @see https://stackoverflow.com/questions/3258243/git-check-if-pull-needed
     */
    public function getDiffStatus(RemoteName $remoteName, GitMultiRef $branches): RemoteDiffStatus
    {
        $localBranch = $branches->source;
        $remoteBranch = $branches->target;

        if (! $this->branchExists($remoteName, $remoteBranch)) {
            return RemoteDiffStatus::NeedPush;
        }

        $localRef = $this->process->mustRun(['git', 'rev-parse', $localBranch])->getOutput();
        $remoteRef = $this->process->mustRun(['git', 'rev-parse', 'refs/remotes/' . $remoteName . '/' . $remoteBranch])->getOutput();
        $baseRef = $this->process->mustRun(['git', 'merge-base', $localBranch, 'refs/remotes/' . $remoteName . '/' . $remoteBranch])->getOutput();

        if ($localRef === $remoteRef) {
            return RemoteDiffStatus::UpToDate;
        }

        if ($localRef === $baseRef) {
            return RemoteDiffStatus::NeedPull;
        }

        if ($remoteRef === $baseRef) {
            return RemoteDiffStatus::NeedPush;
        }

        return RemoteDiffStatus::Diverged;
    }

    public function branchExists(RemoteName $remote, GitRef $branch): bool
    {
        $branch->expectBranch();
        $this->fetch($remote);

        $branches = StringUtil::splitLines(
            $this->process->mustRun(
                ['git', 'for-each-ref', '--format', '%(refname:strip=3)', 'refs/remotes/' . $remote]
            )->getOutput()
        );

        return \in_array((string) $branch, $branches, true);
    }

    public function fetch(RemoteName $remote, ?GitRef $ref = null): void
    {
        if ($ref) {
            $this->process->mustRun(['git', 'fetch', $remote, $ref]);

            return;
        }

        $this->process->mustRun(['git', 'fetch', $remote]);
    }

    public function pull(RemoteName $remote, bool $rebase = true, ?GitRef $ref = null): void
    {
        $this->guardWorkingTreeReady();

        $cmd = ['git', 'pull'];

        if ($rebase) {
            $cmd[] = '--rebase';
        }

        if ($ref) {
            $cmd[] = $ref;
        }

        $cmd[] = $remote;

        $this->process->mustRun($cmd);
    }

    public function pushToRemote(RemoteName $remote, PushOptions $options = null, GitMultiRef ...$ref): void
    {
        $options ??= new PushOptions();

        $command = ['git', 'push'];

        if ($options->has(PushOptions::SET_UPSTREAM)) {
            $command[] = '--set-upstream';
        }

        if ($options->has(PushOptions::FORCE_WITH_LEASE)) {
            $command[] = '--force-with-lease';
        } elseif ($options->has(PushOptions::FORCE)) {
            $command[] = '--force';
        }

        $command[] = $remote;

        $this->process->mustRun(array_merge($command, $ref));
    }

    public function ensureBranchInSync(RemoteName $remote, GitMultiRef $localBranch, bool $allowPush = true): void
    {
        $status = $this->getDiffStatus($remote, $localBranch);

        if ($status === RemoteDiffStatus::NeedPull) {
            $this->style->note(\sprintf('Your local branch "%s" is outdated, running git pull.', $localBranch));
            $this->pull($remote, true, $localBranch->source);

            return;
        }

        if ($status === RemoteDiffStatus::Diverged) {
            throw new \RuntimeException(
                'Cannot safely perform the operation. ' .
                \sprintf('Your local and remote version of branch "%s" have differed.', $localBranch) .
                ' Please resolve this problem manually.'
            );
        }

        if (! $allowPush && $status === RemoteDiffStatus::NeedPush) {
            throw new \RuntimeException(
                \sprintf('Branch "%s" contains commits not existing in the remote version.', $localBranch) .
                'Push is prohibited for this operation. Create a new branch and do a `git reset --hard`.'
            );
        }
    }
}
