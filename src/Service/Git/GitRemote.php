<?php

declare(strict_types=1);

namespace HubKit\Service\Git;

use Composer\Semver\Comparator;
use HubKit\StringUtil;
use Rollerworks\Component\Version\Version;

class GitRemote extends GitBase
{
    /**
     * @see https://gist.github.com/WebPlatformDocs/437f763b948c926ca7ba
     * @see https://stackoverflow.com/questions/3258243/git-check-if-pull-needed
     */
    public function getDiffStatus(string $remoteName, string $localBranch, ?string $remoteBranch = null): RemoteDiffStatus
    {
        if ($remoteBranch === null) {
            $remoteBranch = $localBranch;
        }

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

    public function branchExists(string $remote, string $branch): bool
    {
        $this->fetch($remote);

        $branches = StringUtil::splitLines(
            $this->process->mustRun(
                ['git', 'for-each-ref', '--format', '%(refname:strip=3)', 'refs/remotes/' . $remote]
            )->getOutput()
        );

        return \in_array($branch, $branches, true);
    }

    public function fetch(string $remote, ?string $ref = null): void
    {
        if ($ref) {
            $this->process->mustRun(['git', 'fetch', $remote, $ref]);

            return;
        }

        $this->process->mustRun(['git', 'fetch', $remote]);
    }

    public function pull(string $remote, bool $rebase = true, ?string $ref = null): void
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

    public function ensureBranchInSync(string $remote, string $localBranch, bool $allowPush = true): void
    {
        $status = $this->getDiffStatus($remote, $localBranch);

        if ($status === RemoteDiffStatus::NeedPull) {
            $this->style->note(
                \sprintf('Your local branch "%s" is outdated, running git pull.', $localBranch)
            );

            $this->pull($remote, true, $localBranch);

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
