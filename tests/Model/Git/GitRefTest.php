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

use HubKit\Model\Git\GitRef;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class GitRefTest extends TestCase
{
    /**
     * @test
     */
    public function constructing(): void
    {
        self::assertSame('main', (string) new GitRef('main'));
        self::assertSame('3.0', (string) new GitRef('3.0'));
        self::assertSame('main_3.0', (string) new GitRef('main_3.0'));
        self::assertSame('main-3', (string) new GitRef('main-3'));
        self::assertSame('_main', (string) new GitRef('_main'));
        self::assertSame('dev/main', (string) new GitRef('dev/main'));
        self::assertSame('HEAD', (string) new GitRef('HEAD'));
        self::assertSame('HEAD', (string) new GitRef('head'));
        self::assertSame('refs/heads/main', (string) new GitRef('refs/heads/main'));
        self::assertSame('refs/tags/v2.0', (string) new GitRef('refs/tags/v2.0'));
        self::assertSame('refs/remotes/main', (string) new GitRef('refs/remotes/main'));
        self::assertSame('refs/notes/d150f8cb10d7a3e312e7643b877e29f3096c50f8', (string) new GitRef('refs/notes/d150f8cb10d7a3e312e7643b877e29f3096c50f8'));
        self::assertSame('d150f8cb10d7a3e312e7643b877e29f3096c50f8', (string) new GitRef('d150f8cb10d7a3e312e7643b877e29f3096c50f8'));
    }

    /**
     * @test
     */
    public function detect_branch_type(): void
    {
        $ref = new GitRef('main');
        self::assertTrue($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isRemote());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isHash());

        $ref = new GitRef('dev/2.0');
        self::assertTrue($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isRemote());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isHash());

        $ref = new GitRef('bak/dev/main');
        self::assertTrue($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isRemote());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isHash());

        $ref = new GitRef('HEAD');
        self::assertFalse($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isRemote());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isHash());

        $ref = new GitRef('ref/heads/main');
        self::assertTrue($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isRemote());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isHash());

        $ref = new GitRef('ref/heads/bak/dev/main');
        self::assertTrue($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isRemote());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isHash());
    }

    /**
     * @test
     */
    public function detect_head_type(): void
    {
        $ref = new GitRef('HEAD');
        self::assertFalse($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isRemote());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isHash());

        $ref = new GitRef('Head');
        self::assertFalse($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isRemote());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isHash());
    }

    /**
     * @test
     */
    public function detect_tag_type(): void
    {
        $ref = new GitRef('refs/tags/v2.0');

        self::assertTrue($ref->isTag());
        self::assertFalse($ref->isBranch());
        self::assertFalse($ref->isRemote());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isHash());
    }

    /**
     * @test
     */
    public function detect_remote_type(): void
    {
        $ref = new GitRef('refs/remotes/main');

        self::assertTrue($ref->isRemote());
        self::assertFalse($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isHash());

        $ref = new GitRef('refs/remotes/bak/dev/main');
        self::assertTrue($ref->isRemote());
        self::assertFalse($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isHash());
    }

    /**
     * @test
     */
    public function detect_notes_type(): void
    {
        $ref = new GitRef('refs/notes/d150f8cb10d7a3e312e7643b877e29f3096c50f8');

        self::assertTrue($ref->isNotes());
        self::assertFalse($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isRemote());
        self::assertFalse($ref->isHash());
    }

    /**
     * @test
     */
    public function detect_hash_type(): void
    {
        $ref = new GitRef('d150f8cb10d7a3e312e7643b877e29f3096c50f8');

        self::assertTrue($ref->isHash());
        self::assertFalse($ref->isNotes());
        self::assertFalse($ref->isBranch());
        self::assertFalse($ref->isTag());
        self::assertFalse($ref->isRemote());
    }

    /**
     * @test
     *
     * @dataProvider provide_expect_type
     */
    public function expect_type(GitRef $ref, string $type): void
    {
        self::assertSame($ref, $ref->{'expect' . ucfirst($type)}());
        self::assertTrue($ref->{'is' . ucfirst($type)}());
    }

    /**
     * @test
     *
     * @dataProvider provide_expect_type
     */
    public function expect_type_failure(GitRef $ref, string $type): void
    {

        $types = ['branch', 'tag', 'remote', 'notes', 'hash'];
        $types = array_filter($types, fn(string $v) => $type !== $v);

        foreach ($types as $t) {
            try {
                $ref->{'expect' . ucfirst($t)}();

                self::fail(\sprintf('Expected exception for ref "%s" and type "%s"', $ref, $t));
            } catch (\InvalidArgumentException $e) {
                if ($t === 'hash') {
                    self::assertSame(\sprintf('Ref "%s" is not a (full) hash, expected hash ref.', $ref), $e->getMessage());
                } else {
                    self::assertSame(\sprintf('Ref "%s" is not a %s, expected %2$s ref.', $ref, $t), $e->getMessage());
                }
            }
        }
    }

    public static function provide_expect_type(): iterable
    {
        yield 'branch' => [new GitRef('main'), 'branch'];
        yield 'tag' => [new GitRef('refs/tags/v2.0'), 'tag'];
        yield 'remote' => [new GitRef('refs/remotes/upstream'), 'remote'];
        yield 'notes' => [new GitRef('refs/notes/d150f8cb10d7a3e312e7643b877e29f3096c50f8'), 'notes'];
        yield 'hash' => [new GitRef('d150f8cb10d7a3e312e7643b877e29f3096c50f8'), 'hash'];
    }

    /**
     * @test
     *
     * @dataProvider provide_invalid_ref
     */
    public function invalid_ref(string $ref): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Invalid ref "%s".', $ref));

        new GitRef($ref);
    }

    public static function provide_invalid_ref(): iterable
    {
        yield ['/main'];
        yield ['/.main'];
        yield ['main/'];

        yield ['main/ main'];
        yield ['main/main/'];
        yield ['.main'];
        yield ['root..main'];

        yield ['refs/error'];
    }
}
