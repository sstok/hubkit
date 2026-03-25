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
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use Symfony\Component\Console\Style\StyleInterface;

abstract class GitBase
{
    public function __construct(
        protected Git $git,
        protected CliProcess $process,
        protected StyleInterface $style,
        protected Filesystem $filesystem,
    ) {}

    protected function guardWorkingTreeReady(): void
    {
        $this->git->guardWorkingTreeReady();
    }
}
