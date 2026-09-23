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

use Symfony\Component\Process\Process;

class GitConfig extends GitBase
{
    public function setLocal(string $key, int | string $value, bool $overwrite = false): void
    {
        $this->setConfig($key, $value, 'local', $overwrite);
    }

    public function getLocal(string $key): string
    {
        return $this->getConfig($key, 'local');
    }

    public function getAllLocal(string $key): string
    {
        return $this->getConfigAll($key, 'local');
    }

    public function getGlobal(string $key): string
    {
        return $this->getConfig($key, 'global');
    }

    public function getAllGlobal(string $key): string
    {
        return $this->getConfigAll($key, 'global');
    }

    public function ensureRemoteExists(string $name, string $url): void
    {
        if ($url === $this->getConfig('remote.' . $name . '.url', 'local')) {
            return;
        }

        $this->style->note(\sprintf('Adding remote "%s" with "%s".', $name, $url));

        if (! $this->getConfig('remote.' . $name . '.url', 'local')) {
            $this->process->mustRun(['git', 'remote', 'add', $name, $url]);
        } else {
            $this->setConfig('remote.' . $name . '.url', $url, 'local', true);
        }
    }

    private function setConfig(string $config, int | string $value, string $section, bool $overwrite = false): void
    {
        if (! $overwrite && $this->getConfig($config, $section) !== '') {
            throw new \RuntimeException(
                \sprintf(
                    'Unable to set git config "%s" at %s, because the value is already set.',
                    $config,
                    $section
                )
            );
        }

        $cwd = $this->filesystem->getCwd();

        // Git adds a new value (superseding the old one) but we want replace the entire value.
        // And `--replace-all` requires a regexp (WAT?) to properly replace the value...
        $this->process->run(new Process(['git', 'config', '--' . $section, '--unset', $config], $cwd));
        $this->process->mustRun(new Process(['git', 'config', '--' . $section, $config, $value], $cwd));
    }

    private function getConfig(string $config, string $section): string
    {
        $process = $this->process->run(new Process(['git', 'config', '--' . $section, '--get', $config], $this->filesystem->getCwd()));

        return mb_trim($process->getOutput());
    }

    private function getConfigAll(string $config, string $section): string
    {
        $process = $this->process->run(new Process(['git', 'config', '--' . $section, '--get-all', $config], $this->filesystem->getCwd()));

        return mb_trim($process->getOutput());
    }
}
