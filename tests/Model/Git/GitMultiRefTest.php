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

namespace HubKit\Tests\Model\Git;

use HubKit\Model\Git\GitMultiRef;
use HubKit\Model\Git\GitRef;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class GitMultiRefTest extends TestCase
{
    /**
     * @test
     */
    public function constructing(): void
    {
        $gitMultiRef = new GitMultiRef('main', '2.0');
        self::assertEquals(new GitRef('main'), $gitMultiRef->source);
        self::assertEquals(new GitRef('2.0'), $gitMultiRef->target);
        self::assertEquals('main:2.0', (string) $gitMultiRef);

        $gitMultiRef = new GitMultiRef(new GitRef('main'), new GitRef('2.0'));
        self::assertEquals(new GitRef('main'), $gitMultiRef->source);
        self::assertEquals(new GitRef('2.0'), $gitMultiRef->target);
        self::assertEquals('main:2.0', (string) $gitMultiRef);
    }

    /**
     * @test
     */
    public function from_string(): void
    {
        $gitMultiRef = GitMultiRef::fromString('main:2.0');
        self::assertEquals(new GitRef('main'), $gitMultiRef->source);
        self::assertEquals(new GitRef('2.0'), $gitMultiRef->target);
        self::assertEquals('main:2.0', (string) $gitMultiRef);
    }

    /**
     * @test
     */
    public function from_string_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ref "main" does not contain a target ref, either "source:target".');

        GitMultiRef::fromString('main');
    }

    /**
     * @test
     */
    public function from_string_invalid_usage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ref "main:main:3.0" contains more than one ":".');

        GitMultiRef::fromString('main:main:3.0');
    }

    /**
     * @test
     */
    public function source_not_remote(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ref "refs/remotes/main" for source. Cannot use "refs/remotes" for source.');

        new GitMultiRef('refs/remotes/main', 'main');
    }

    /**
     * @test
     */
    public function target_not_head(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ref for target. Cannot use HEAD for remote target, use a branch name instead.');

        new GitMultiRef('main', 'HEAD');
    }
}
