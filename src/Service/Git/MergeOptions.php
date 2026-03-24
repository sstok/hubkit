<?php

declare(strict_types=1);

namespace HubKit\Service\Git;

use HubKit\Bitmask;

final class MergeOptions extends Bitmask
{
    public const int NO_FF = 1;
    public const int SQUASH = 2;
    public const int WITH_LOG = 4;
    public const int NO_COMMIT = 8;
}
