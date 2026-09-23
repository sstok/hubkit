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
 * Represents a pair of Git references, consisting of a source reference and a target reference.
 *
 * This class ensures that the provided references adhere to specific constraints:
 * - The source reference must not be a remote reference.
 * - The target reference must not be the HEAD reference.
 *
 * Instances of this class can be created by passing `GitRef` objects directly or by using string values
 * that will be converted to `GitRef` objects internally.
 *
 * Additionally, objects of this class can be created from a string representation of the format
 * "source:target" using the `fromString` method.
 */
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

        if ($source->isRemote()) {
            throw new \InvalidArgumentException(\sprintf('Invalid ref "%s" for source. Cannot use "refs/remotes" for source.', $source->ref));
        }

        if ($target->isHead()) {
            throw new \InvalidArgumentException('Invalid ref for target. Cannot use HEAD for remote target, use a branch name instead.');
        }
    }

    public static function fromString(string $ref): self
    {
        if (! str_contains($ref, ':')) {
            throw new \InvalidArgumentException(\sprintf('Ref "%s" does not contain a target ref, either "source:target".', $ref));
        }

        if (substr_count($ref, ':') > 1) {
            throw new \InvalidArgumentException(\sprintf('Ref "%s" contains more than one ":".', $ref));
        }

        return new self(...explode(':', $ref, 2));
    }

    public function __toString(): string
    {
        return \sprintf('%s:%s', $this->source, $this->target);
    }
}
