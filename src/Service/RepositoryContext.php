<?php

declare(strict_types=1);

namespace HubKit\Service;

/**
 * A RepositoryContext contains the context of a repository for easy access.
 *
 * This information is readonly and cannot be changed.
 * Use the RepositoryProvider to get the current RepositoryContext.
 */
final class RepositoryContext
{
    public function __construct(
        public readonly string $repository,
        public readonly string $host,

        public readonly string $defaultBranch = 'main',
        public readonly string $adapter = 'github',
        public readonly string $remote = 'upstream',
    ) {}
}
