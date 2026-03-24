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
use HubKit\Model\CommitDto;
use HubKit\Service\Git\GitBranch;
use HubKit\Service\Git\GitCommit;
use HubKit\Service\Git\GitConfig;
use HubKit\Service\Git\GitRemote;
use HubKit\StringUtil;
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

    public function __construct(
        private CliProcess $process,
        private Filesystem $filesystem,
        private StyleInterface $style
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

        $directory = trim($process->getOutput());

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

    /**
     * @deprecated
     */
    public function getRemoteDiffStatus(string $remoteName, string $localBranch, ?string $remoteBranch = null): string
    {
        $this->remote->getDiffStatus($remoteName, $localBranch, $remoteBranch)->value;
    }

    /**
     * @deprecated
     */
    public function getActiveBranchName(): string
    {
        return $this->branch->getCurrent();
    }

    /**
     * @deprecated
     */
    public function getLastTagOnBranch(string $ref = 'HEAD', bool $allowFailure = false): ?string
    {
        return $this->branch->getLastTag($ref, $allowFailure);
    }

    /** @return array<int, string> ['v1.0', 'v1.5', 'v2.0' '...'] */
    public function getVersionBranches(?string $remote = null): array
    {
        return $this->branch->getVersionBranches($remote);
    }

    /**
     * @deprecated
     */
    public function getLogBetweenCommits(string $start, string $end): array
    {
        return array_map(static fn (CommitDto $model): array => $model->toArray(), iterator_to_array($this->commit->getLogBetweenCommits($start, $end)));
    }

    /**
     * @deprecated
     */
    public function getFileChangesBetween(string $start, string $end): array
    {
        return $this->branch->getFileChangesBetween($start, $end);
    }

    /**
     * @deprecated
     */
    public function remoteBranchExists(string $remote, string $branch): bool
    {
        return $this->remote->branchExists($remote, $branch);
    }

    /**
     * @deprecated
     */
    public function branchExists(string $branch): bool
    {
        return $this->branch->exists($branch);
    }

    public function deleteRemoteBranch(string $remote, string $ref): void
    {
        $this->process->mustRun(['git', 'push', $remote, ':' . $ref]);
    }

    /**
     * @deprecated
     */
    public function deleteBranch(string $name, bool $allowFailure = false): void
    {
        $this->branch->delete($name, $allowFailure);
    }

    public function addNotes(string $notes, string $commitHash, string $ref = 'github-comments'): void
    {
        $tmpName = $this->filesystem->newTempFilename();
        file_put_contents($tmpName, $notes);

        // Cannot add empty notes
        if (trim($notes) === '') {
            return;
        }

        $commands = [
            'git',
            'notes',
            '--ref=' . $ref,
            'add',
            '--no-stripspace',
            '-F',
            $tmpName,
            $commitHash,
        ];

        $this->process->run($commands, 'Adding git notes failed.');
    }

    /** @param array<int, string>|string $ref either a single ref of array of references */
    public function pushToRemote(string $remote, array | string $ref, bool $setUpstream = false, bool $force = false): void
    {
        $ref = (array) $ref;
        $ref = array_map(
            static function ($ref) {
                if ($ref[0] === ':') {
                    throw new \RuntimeException(
                        \sprintf(
                            'Push target "%s" does not include the local branch-name, please report this bug!',
                            $ref
                        )
                    );
                }

                return $ref;
            },
            $ref
        );

        $command = ['git', 'push'];

        if ($setUpstream) {
            $command[] = '--set-upstream';
        }

        if ($force) {
            $command[] = '--force';
        }

        $command[] = $remote;

        $this->process->mustRun(array_merge($command, $ref));
    }

    public function pullRemote(string $remote, ?string $ref = null): void
    {
        $this->guardWorkingTreeReady();

        $command = ['git', 'pull', '--rebase', $remote];

        if ($ref) {
            $command[] = $ref;
        }

        $this->process->mustRun($command);
    }

    public function fetchRemote(string $remote, string $ref): void
    {
        $this->guardWorkingTreeReady();

        $this->process->mustRun(['git', 'fetch', $remote, $ref]);
    }

    public function remoteUpdate(string $remote): void
    {
        $this->remote->fetch($remote);
    }

    public function isWorkingTreeReady()
    {
        if (trim($this->process->mustRun(['git', 'status', '--porcelain', '--untracked-files=no'])->getOutput()) !== '') {
            return false;
        }

        if (trim($this->process->run(Process::fromShellCommandline('ls `git rev-parse --git-dir` | grep rebase'))->getOutput()) !== '') {
            return false;
        }

        return true;
    }

    /**
     * @deprecated
     */
    public function checkout(string $branchName, bool $createBranch = false): void
    {
        if ($createBranch) {
            $this->branch->checkoutNew($branchName);

            return;
        }

        $this->branch->checkout($branchName);
    }

    /** Checkout a remote branch or create it when it doesn't exit yet. */
    public function checkoutRemoteBranch(string $remote, string $branchName, bool $create = true): void
    {
        if ($this->branchExists($branchName)) {
            $this->process->mustRun(['git', 'checkout', $branchName]);

            return;
        }

        $cmd = ['git', 'checkout', 'remotes/' . $remote . '/' . $branchName];

        if ($create) {
            $cmd[] = '-b';
            $cmd[] = $branchName;
        }

        $this->process->mustRun($cmd);
    }

    public function trackRemoteBranch(string $remote, string $branchName): void
    {
        $this->process->mustRun(['git', 'branch', '--set-upstream-to', $remote . '/' . $branchName, $branchName]);
    }

    public function guardWorkingTreeReady(): void
    {
        if (! $this->isWorkingTreeReady()) {
            throw new WorkingTreeIsNotReady();
        }
    }

    public function ensureNotesFetching(string $remote): void
    {
        $fetches = StringUtil::splitLines(
            $this->getGitConfig('remote.' . $remote . '.fetch', 'local', true)
        );

        if (! \in_array('+refs/notes/*:refs/notes/*', $fetches, true)) {
            $this->style->note(
                \sprintf('Set fetching of notes for remote "%s".', $remote)
            );

            $this->process->mustRun(
                ['git', 'config', '--add', '--local', 'remote.' . $remote . '.fetch', '+refs/notes/*:refs/notes/*']
            );
        }
    }

    /**
     * @deprecated
     */
    public function ensureBranchInSync(string $remote, string $localBranch, bool $allowPush = true): void
    {
        $this->remote->ensureBranchInSync($remote, $localBranch, $allowPush);
    }

    /**
     * @deprecated
     */
    public function ensureRemoteExists(string $name, string $url): void
    {
        $this->config->ensureRemoteExists($name, $url);
    }

    /**
     * @deprecated
     */
    public function getGitConfig(string $config, string $section = 'local', bool $all = false): string
    {
        if ($section === 'local') {
            return $all ? $this->config->getAllLocal($config) : $this->config->getLocal($config);
        }

        return $all ? $this->config->getAllGlobal($config) : $this->config->getGlobal($config);
    }

    /** @return array{'host': string, 'org': string, 'repo': string} */
    public function getRemoteInfo(string $name = REMOTE_MAIN): array
    {
        return self::getGitUrlInfo($this->getGitConfig('remote.' . $name . '.url'));
    }

    /** @return array{'host': string, 'org': string, 'repo': string} */
    public static function getGitUrlInfo(string $gitUri): array
    {
        $info = [
            'host' => '',
            'org' => '',
            'repo' => '',
            'path' => '',
        ];

        if (mb_stripos($gitUri, 'file://') === 0) {
            unset($info['path']);

            return $info;
        }

        if (mb_stripos($gitUri, 'http://') === 0 || mb_stripos($gitUri, 'https://') === 0) {
            $url = parse_url($gitUri);

            if ($url === false) {
                throw new \InvalidArgumentException(\sprintf('Malformed Git url "%s".', $gitUri));
            }

            $info['host'] = $url['host'];
            $info['path'] = ltrim($url['path'] ?? '', '/');
        } elseif (preg_match('%^(?:(?:git|ssh)://)?[^@]+@(?P<host>[^:]+):(?P<path>[^$]+)$%', $gitUri, $match)) {
            $info['host'] = $match['host'];
            $info['path'] = $match['path'];
        } elseif (preg_match('%^(?:(?:git|ssh)://)?([^@]+@)?(?P<host>[^/]+)/(?P<path>[^$]+)$%', $gitUri, $match)) {
            $info['host'] = $match['host'];
            $info['path'] = $match['path'];
        }

        if (str_contains($info['path'], '/')) {
            $dirs = \array_slice(explode('/', $info['path']), -2, 2);

            $info['org'] = $dirs[0];
            $info['repo'] = mb_substr($dirs[1], -4, 4) === '.git' ? mb_substr($dirs[1], 0, -4) : $dirs[1];
        }

        unset($info['path']);

        return $info;
    }

    public function clone(string $ssh_url, string $remoteName = 'origin', ?int $depth = null): void
    {
        $command = ['git', 'clone', $ssh_url, '.'];

        if ($depth !== null) {
            $command[] = '--depth';
            $command[] = $depth;
        }

        $this->process->mustRun($command);

        if ($remoteName !== 'origin') {
            $this->process->mustRun(['git', 'remote', 'rename', 'origin', $remoteName]);
        }
    }

    public function getGitDirectory(): string
    {
        if ($this->gitDir === null) {
            $gitDir = trim($this->process->run(['git', 'rev-parse', '--git-dir'])->getOutput());

            if ($gitDir === '.git') {
                $gitDir = $this->filesystem->getCwd() . '/.git';
            }

            $this->gitDir = $gitDir;
        }

        return $this->gitDir;
    }
}
