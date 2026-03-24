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

use HubKit\Service\CliProcess;
use Symfony\Component\Console\Style\StyleInterface;

class GitConfig extends GitBase
{
    public function setLocal(string $key, int | string $value, bool $overwrite = false): void
    {
        $this->setConfig($key, $value, $overwrite, 'local');
    }

    public function getLocal(string $key): string
    {
        return $this->getConfig($key, 'local');
    }

    public function getAllLocal(string $key): string
    {
        return $this->getConfig($key, 'local', true);
    }

    public function getGlobal(string $key): string
    {
        return $this->getConfig($key, 'global');
    }

    public function getAllGlobal(string $key): string
    {
        return $this->getConfig($key, 'global', true);
    }

    public function ensureRemoteExists(string $name, string $url): void
    {
        if ($url === $this->getConfig('remote.' . $name . '.url')) {
            return;
        }

        $this->style->note(\sprintf('Adding remote "%s" with "%s".', $name, $url));

        if (! $this->getConfig('remote.' . $name . '.url')) {
            $this->process->mustRun(['git', 'remote', 'add', $name, $url]);
        } else {
            $this->setConfig('remote.' . $name . '.url', $url, true);
        }
    }

    private function setConfig(string $config, int | string $value, bool $overwrite = false, string $section = 'local'): void
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

        // Git adds a new value (superseding the old one) but we want replace the entire value.
        // And `--replace-all` requires a regexp (WAT?) to properly replace the value...
        $this->process->run(['git', 'config', '--' . $section, '--unset', $config]);
        $this->process->mustRun(['git', 'config', '--' . $section, $config, $value]);
    }

    public function getConfig(string $config, string $section = 'local', bool $all = false): string
    {
        $process = $this->process->run(['git', 'config', '--' . $section, '--' . ($all ? 'get-all' : 'get'), $config]);

        return trim($process->getOutput());
    }
}
