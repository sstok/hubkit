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

namespace HubKit\Service\Git;

use Composer\Semver\Comparator;
use HubKit\Model\Git\GitMultiRef;
use HubKit\Model\Git\GitRef;
use HubKit\Model\Git\RemoteName;
use HubKit\StringUtil;
use Rollerworks\Component\Version\Version;

class GitBranch extends GitBase
{
    public function getCurrent(): string
    {
        $activeBranch = trim($this->process->mustRun(['git', 'rev-parse', '--abbrev-ref', 'HEAD'])->getOutput());

        if ($activeBranch === 'HEAD') {
            throw new \RuntimeException(
                'You are currently in a detached HEAD state, unable to get active branch-name.' .
                'Please run `git checkout` first.'
            );
        }

        return $activeBranch;
    }

    public function exists(GitRef $branch): bool
    {
        $branches = StringUtil::splitLines($this->process->mustRun(['git', 'for-each-ref', '--format', '%(refname:short)', 'refs/heads/'])->getOutput());

        return \in_array($branch, $branches, true);
    }

    public function delete(GitRef $name, bool $allowFailure = false): void
    {
        if ($allowFailure) {
            $this->process->run(['git', 'branch', '-d', $name], \sprintf('Could not delete branch "%s".', $name));
        } else {
            $this->process->mustRun(['git', 'branch', '-d', $name]);
        }
    }

    public function forceDelete(GitRef $name): void
    {
        $this->process->run(['git', 'branch', '-D', $name], \sprintf('Could not delete branch "%s".', $name));
    }

    public function checkout(GitRef $branchName): void
    {
        $this->process->mustRun(['git', 'checkout', $branchName]);
    }

    public function checkoutNew(GitRef $branchName): void
    {
        $this->process->mustRun(['git', 'checkout', '-b', $branchName]);
    }

    public function merge(GitRef $branchName, MergeOptions $options): void
    {
        $cmd = ['git', 'merge'];

        if ($options->has(MergeOptions::SQUASH)) {
            $cmd[] = '--squash';
        }

        if ($options->has(MergeOptions::NO_FF)) {
            $cmd[] = '--no-ff';
        }

        if ($options->has(MergeOptions::WITH_LOG)) {
            $cmd[] = '--log';
        }

        if ($options->has(MergeOptions::NO_COMMIT)) {
            $cmd[] = '--no-commit';
        }

        $cmd[] = $branchName;

        $this->process->mustRun($cmd);
    }

    /**
     * @return ($allowFailure is true ? string|null : string)
     *
     * @throws \RuntimeException
     */
    public function getLastTag(GitRef $ref = new GitRef('HEAD'), bool $allowFailure = false): ?string
    {
        try {
            return trim($this->process->mustRun(['git', 'describe', '--tags', '--abbrev=0', $ref])->getOutput());
        } catch (\RuntimeException $e) {
            if (! $allowFailure) {
                throw $e;
            }

            return null;
        }
    }

    /**
     * @return string[] ['v1.0', 'v1.5', 'v2.0' '...']
     */
    public function getVersionBranches(?RemoteName $remote = null): array
    {
        if ($remote) {
            $cmd = ['git', 'for-each-ref', '--format', '%(refname:strip=3)', 'refs/remotes/' . $remote];
        } else {
            $cmd = ['git', 'for-each-ref', '--format', '%(refname:short)', 'refs/heads/'];
        }

        $branches = StringUtil::splitLines($this->process->mustRun($cmd)->getOutput());
        $branches = array_filter($branches, static fn (string $branch) => preg_match('/^v?' . Version::VERSION_REGEX . '$/i', $branch) || preg_match('/^v?(?P<major>\d++)\.(?P<rel>x)$/', $branch));

        // Sort in ascending order (lowest first).
        // Trim v prefix as this causes problems with the comparator.
        usort($branches, static function ($a, $b) {
            $a = ltrim($a, 'vV');
            $b = ltrim($b, 'vV');

            if (mb_substr($a, -1, 1) === 'x') {
                $a = substr_replace($a, '999', -1, 1);
            }

            if (mb_substr($b, -1, 1) === 'x') {
                $b = substr_replace($b, '999', -1, 1);
            }

            if (Comparator::equalTo($a, $b)) {
                return 0;
            }

            return Comparator::lessThan($a, $b) ? -1 : 1;
        });

        return array_merge([], $branches);
    }

    /**
     * Returns a list of changed files between two ranges (either commit or branch-name).
     *
     * @return string[]
     */
    public function getFileChangesBetween(GitMultiRef $range): array
    {
        $start = $range->source;
        $end = $range->target;

        $results = StringUtil::splitLines($this->process->mustRun(
            [
                'git',
                '--no-pager',
                'log',
                '--oneline',
                '--no-color',
                '--pretty=format:', // Ensures we only get the names, and not the commit refs
                '--name-only',
                $start . '..' . $end,
            ]
        )->getOutput());

        return array_values(array_unique(array_filter($results)));
    }
}
