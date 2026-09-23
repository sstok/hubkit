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

namespace HubKit\Model\Git;

/**
 * A reference to a Git internal object.
 *
 * Either a full hash, branch, tag, remote, or notes reference.
 *
 * Example:
 *
 * ```
 * new GitRef('main');
 * new GitRef('HEAD');
 * new GitRef('refs/heads/main');
 * new GitRef('refs/tags/v2.0');
 * new GitRef('refs/remotes/origin/main');
 * new GitRef('refs/notes/'0123456789abcdef0123456789abcdef01234567');
 * new GitRef('0123456789abcdef0123456789abcdef01234567');
 * ```
 *
 * **Note:** A hash ref is a 40 character hexadecimal string, short hashes are not supported.
 */
final readonly class GitRef
{
    public string $ref;

    public function __construct(string $ref)
    {
        if (strtoupper($ref) === 'HEAD') {
            $ref = 'HEAD';
        }

        $this->ref = $ref;
        $this->validate();
    }

    public function __toString(): string
    {
        return $this->ref;
    }

    public function isHash(): bool
    {
        return mb_strlen($this->ref) === 40 && preg_match('#^[0-9a-f]{40}$#', $this->ref);
    }

    public function isBranch(): bool
    {
        if ($this->isHead() || $this->isHash()) {
            return false;
        }

        return ! str_starts_with($this->ref, 'refs/') || str_starts_with($this->ref, 'refs/heads/');
    }

    public function isHead(): bool
    {
        return $this->ref === 'HEAD';
    }

    public function isRemote(): bool
    {
        return str_starts_with($this->ref, 'refs/remotes/');
    }

    public function isTag(): bool
    {
        return str_starts_with($this->ref, 'refs/tags/');
    }

    public function isNotes(): bool
    {
        return str_starts_with($this->ref, 'refs/notes/');
    }

    public function expectBranch(): self
    {
        if (! $this->isBranch()) {
            throw new \InvalidArgumentException(\sprintf('Ref "%s" is not a branch, expected branch ref.', $this->ref));
        }

        return $this;
    }

    public function expectHash(): self
    {
        if (! $this->isHash()) {
            throw new \InvalidArgumentException(\sprintf('Ref "%s" is not a (full) hash, expected hash ref.', $this->ref));
        }

        return $this;
    }

    public function expectRemote(): self
    {
        if (! $this->isRemote()) {
            throw new \InvalidArgumentException(\sprintf('Ref "%s" is not a remote, expected remote ref.', $this->ref));
        }

        return $this;
    }

    public function expectTag(): self
    {
        if (! $this->isTag()) {
            throw new \InvalidArgumentException(\sprintf('Ref "%s" is not a tag, expected tag ref.', $this->ref));
        }

        return $this;
    }

    public function expectNotes(): self
    {
        if (! $this->isNotes()) {
            throw new \InvalidArgumentException(\sprintf('Ref "%s" is not a notes, expected notes ref.', $this->ref));
        }

        return $this;
    }

    public function expectRelative(): self
    {
        if (str_starts_with($this->ref, 'refs/') || $this->isHead()) {
            throw new \InvalidArgumentException(\sprintf('Ref "%s" is not a relative ref. Expected either a hash, branch or tag ref.', $this->ref));
        }

        return $this;
    }

    private function validate(): void
    {
        // Hash type, only full ones.
        if (preg_match('#^[0-9a-f]{40}$#', $this->ref)) {
            return;
        }

        if (str_contains($this->ref, '..') || str_contains($this->ref, '/.')) {
            throw new \InvalidArgumentException(\sprintf('Invalid ref "%s".', $this->ref));
        }

        if (str_starts_with($this->ref, '/') || str_ends_with($this->ref, '/')) {
            throw new \InvalidArgumentException(\sprintf('Invalid ref "%s".', $this->ref));
        }

        if (! preg_match('#^((/?[a-zA-Z0-9-_][.a-zA-Z0-9-_]*)+)$#', $this->ref)) {
            throw new \InvalidArgumentException(\sprintf('Invalid ref "%s".', $this->ref));
        }

        if (str_starts_with($this->ref, 'refs/') && ! preg_match('#^refs/(heads|tags|remotes|notes)/#', $this->ref)) {
            throw new \InvalidArgumentException(\sprintf('Invalid ref "%s".', $this->ref));
        }
    }
}
