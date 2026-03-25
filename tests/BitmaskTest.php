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

namespace Hubkit\Tests;

use HubKit\Bitmask;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class BitmaskTest extends TestCase
{
    #[Test]
    public function its_constructable(): void
    {
        self::assertSame(0, (new BitmaskStub())->get());
        self::assertSame(123456, (new BitmaskStub(123456))->get());
    }

    #[Test]
    public function it_resolves_a_mask(): void
    {
        $bitmask = new BitmaskStub();

        self::assertSame(BitmaskStub::VIEW, $bitmask->resolveMask('view'));
        self::assertSame(BitmaskStub::VIEW, $bitmask->resolveMask(BitmaskStub::VIEW));
    }

    #[Test]
    public function it_adds_and_removes(): void
    {
        $bitmask = new BitmaskStub();
        $bitmaskNew = $bitmask->add('view');

        self::assertNotSame($bitmask, $bitmaskNew);

        $bitmask = new BitmaskStub();
        $bitmask = $bitmask
            ->add('view')
            ->add('eDiT', 'ownEr');

        $mask = $bitmask->get();

        self::assertTrue($bitmask->has(BitmaskStub::VIEW));
        self::assertTrue($bitmask->has('view'));

        self::assertSame(BitmaskStub::VIEW, $mask & BitmaskStub::VIEW);
        self::assertSame(BitmaskStub::EDIT, $mask & BitmaskStub::EDIT);
        self::assertSame(BitmaskStub::OWNER, $mask & BitmaskStub::OWNER);
        self::assertTrue($bitmask->has(BitmaskStub::OWNER));

        self::assertSame(0, $mask & BitmaskStub::MASTER);
        self::assertSame(0, $mask & BitmaskStub::CREATE);
        self::assertSame(0, $mask & BitmaskStub::DELETE);
        self::assertSame(0, $mask & BitmaskStub::UNDELETE);

        // Remove
        $bitmaskRemoved = $bitmask->remove('edit', 'OWner');
        $mask = $bitmaskRemoved->get();

        self::assertNotSame($bitmask, $bitmaskRemoved);
        self::assertFalse($bitmaskRemoved->has(BitmaskStub::OWNER));
        self::assertSame(0, $mask & BitmaskStub::EDIT);
        self::assertSame(0, $mask & BitmaskStub::OWNER);
        self::assertSame(BitmaskStub::VIEW, $mask & BitmaskStub::VIEW);
    }

    #[Test]
    public function it_clears(): void
    {
        self::assertSame(0, (new BitmaskStub())->get());
        self::assertTrue((new BitmaskStub())->add('view')->get() > 0);
        self::assertSame(0, (new BitmaskStub())->add('view')->clear()->get());
    }
}

/**
 * @internal
 */
final class BitmaskStub extends Bitmask
{
    public const int VIEW = 1;        // 1 << 0
    public const int CREATE = 2;      // 1 << 1
    public const int EDIT = 4;        // 1 << 2
    public const int DELETE = 8;      // 1 << 3
    public const int UNDELETE = 16;   // 1 << 4
    public const int OPERATOR = 32;   // 1 << 5
    public const int MASTER = 64;     // 1 << 6
    public const int OWNER = 128;     // 1 << 7
}
