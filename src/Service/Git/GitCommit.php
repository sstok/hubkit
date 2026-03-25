<?php

declare(strict_types=1);

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
    public function getLogBetweenCommits(string $start, string $end, bool $mergeOnly = true): iterable
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
        // 0=parent(s), 1=author, 2=author-email, 3=subject, anything higher then 3 is the full message
        $commitData = StringUtil::splitLines(
            $this->process->run(
                [
                    'git',
                    '--no-pager',
                    'show',
                    '--format=%P%n%an%n%ae%n%s%n%b',
                    '--no-color',
                    '--no-patch',
                    $commitHash,
                ], 'Could not get commit data.'
            )->getOutput()
        );

        $parents = (string) array_shift($commitData); // [merged into] [merged from?]
        $author = (string) array_shift($commitData);
        $authorEmail = (string) array_shift($commitData);
        $subject = (string) array_shift($commitData);
        $isMergeCommit = str_contains($parents, ' ');

        return new Commit($commitHash, $author, $authorEmail, $subject, implode("\n", $commitData), $isMergeCommit);
    }
}
