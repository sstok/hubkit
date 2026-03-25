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

namespace HubKit;

/**
 * Immutable Bitmask ValueObject.
 *
 * Possible masks are expected to be powers of 2 (without duplicates):
 *
 * const VIEW = 1 << 0;          // 1
 * const CREATE = 1 << 1;        // 2
 * const EDIT = 1 << 2;          // 4
 * const DELETE = 1 << 3;        // 8
 * const UNDELETE = 1 << 4;      // 16
 * const OPERATOR = 1 << 5;      // 32
 * const MASTER = 1 << 6;        // 64
 * const OWNER = 1 << 7;         // 128
 * const IDDQD = 1 << 30;        // ...
 * const ALL = self::VIEW | ... | self::EDIT;
 *
 * Originally inspired on the Symfony ACL Permission MaskBuilder.
 */
abstract class Bitmask
{
    protected int $mask = 0;

    final public function __construct(int | string ...$mask)
    {
        foreach ($mask as $nibble) {
            $this->mask |= $this->resolveMask($nibble);
        }
    }

    public function get(): int
    {
        return $this->mask;
    }

    public function add(int | string ...$mask): static
    {
        $new = clone $this;

        foreach ($mask as $nibble) {
            $new->mask |= $new->resolveMask($nibble);
        }

        return $new;
    }

    public function has(int | string $flag): bool
    {
        $flag = $this->resolveMask($flag);

        return ($this->mask & $flag) === $flag;
    }

    /**
     * Returns the mask for the passed code.
     *
     * @throws \InvalidArgumentException
     */
    public function resolveMask(int | string $code): int
    {
        if (\is_int($code)) {
            return $code;
        }

        $name = \sprintf('static::%s', mb_strtoupper($code));

        if (! \defined($name)) {
            throw new \InvalidArgumentException(\sprintf('The code "%s" is not supported', $code));
        }

        return \constant($name);
    }

    public function remove(int | string ...$mask): static
    {
        $new = clone $this;

        foreach ($mask as $nibble) {
            $new->mask &= ~$this->resolveMask($nibble);
        }

        return $new;
    }

    public function clear(): static
    {
        return new static(0);
    }
}
