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

final readonly class GitMultiRef
{
    public GitRef $source;
    public GitRef $target;

    public function __construct(
        GitRef | string $source,
        GitRef | string $target,
    ) {
        if (\is_string($source)) {
            $source = new GitRef($source);
        }

        if (\is_string($target)) {
            $target = new GitRef($target);
        }

        $this->target = $target;
        $this->source = $source;

        if (str_starts_with($target->ref, 'refs/remotes')) {
            throw new \InvalidArgumentException(\sprintf('Invalid ref name "%s" for target, cannot use ref/remotes for remote target.', $target->ref));
        }
    }

    public static function fromString(string $ref): self
    {
        if (! str_contains($ref, ':')) {
            throw new \InvalidArgumentException(\sprintf('Ref "%s" does not contain a target ref, either "source:target".', $ref));
        }

        return new self(...explode(':', $ref, 2));
    }

    public function __toString(): string
    {
        return \sprintf('%s:%s', $this->source, $this->target);
    }
}
