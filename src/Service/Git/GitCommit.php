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

use HubKit\Model\Git\Commit;
use HubKit\StringUtil;

class GitCommit extends GitBase
{
    /**
     * Returns the log commits between two ranges (either commit or branch-name).
     *
     * @return \Generator<string, Commit>|Commit[] Returns in order of oldest to newest
     */
    public function getAll(string $start, string $end, bool $mergeOnly = true): iterable
    {
        // First we get all the commits, then of each commit we get the actual data
        // We can't use the commit data in one go because the body contains newlines

        $commits = StringUtil::splitLines($this->process->mustRun(
            [
                'git',
                '--no-pager',
                'log',
                '--oneline',
                '--no-color',
                '--format=%H',
                '--reverse',
                $start . '..' . $end,
            ]
        )->getOutput());

        foreach ($commits as $hash) {
            if ($hash === '') {
                continue;
            }

            $commit = $this->get($hash);

            if ($mergeOnly && ! $commit->merge) {
                continue;
            }

            yield $hash => $this->get($hash);
        }

        return [];
    }

    public function get(string $commitHash): Commit
    {
        // 0=parent(s), 1=date, 2=author, 3=author-email, 4=subject, anything higher then 4 is the full message
        $commitData = StringUtil::splitLines(
            $this->process->run(
                [
                    'git',
                    '--no-pager',
                    'show',
                    '--format=%P%n%aI%n%an%n%ae%n%s%n%b', // %n = newline
                    '--no-color',
                    '--no-patch',
                    $commitHash,
                ], 'Could not get commit data.'
            )->getOutput()
        );

        $parents = (string) array_shift($commitData); // [merged into] [merged from?]
        $date = (string) array_shift($commitData);
        $author = (string) array_shift($commitData);
        $authorEmail = (string) array_shift($commitData);
        $subject = (string) array_shift($commitData);
        $isMergeCommit = str_contains($parents, ' ');

        return new Commit($commitHash, $author, $authorEmail, new \DateTimeImmutable($date), $subject, implode("\n", $commitData), $isMergeCommit);
    }

    public function add(string $path, bool $force = false): void
    {
        $this->process->run(
            [
                'git',
                'add',
                $force ? '--force' : '',
                $path,
            ], 'Could not add file.'
        );
    }

    public function addAll(string $path, bool $force = false): void
    {
        $this->process->run(
            [
                'git',
                'add',
                '--all',
                $force ? '--force' : '',
                $path,
            ], 'Could not add all files.'
        );
    }
}
