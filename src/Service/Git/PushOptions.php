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

use HubKit\Bitmask;

final class PushOptions extends Bitmask
{
    public const int ALL = 1;
    public const int TAGS = 2;
    public const int FORCE = 4;
    public const int FORCE_WITH_LEASE = 8;
    public const int SET_UPSTREAM = 16;
}
