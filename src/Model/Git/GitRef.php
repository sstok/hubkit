<?php

declare(strict_types=1);

namespace HubKit\Model\Git;

final readonly class GitRef
{
    public function __construct(
        public string $ref,
    ) {
        $this->validate($ref);
    }

    public function __toString(): string
    {
        return $this->ref;
    }

    public function isBranch(): bool
    {
        if ($this->ref === 'HEAD') {
            return false;
        }

        return ! str_starts_with($this->ref, 'refs/') || str_starts_with($this->ref, 'refs/heads/');
    }

    public function expectBranch(): self
    {
        if (! $this->isBranch()) {
            throw new \InvalidArgumentException(sprintf('Ref "%s" is not a branch, expected branch ref.', $this));
        }
    }

    private function validate(string $name): void
    {
        if (str_contains($name, '..') || str_contains($name, '/.')) {
            throw new \InvalidArgumentException(sprintf('Invalid ref name "%s".', $name));
        }

        if (! preg_match('#^((/[a-zA-Z0-9-_][.a-zA-Z0-9-_]*)+|/)$#', $name)) {
            throw new \InvalidArgumentException(sprintf('Invalid ref name "%s".', $name));
        }

        if (str_starts_with($name, '/') || str_ends_with($name, '/') ) {
            throw new \InvalidArgumentException(sprintf('Invalid ref name "%s".', $name));
        }

        if (str_starts_with($name, 'refs/')  && ! preg_match('#^refs/(heads|tags|remotes|notes)/#', $name)) {
            throw new \InvalidArgumentException(sprintf('Invalid ref name "%s".', $name));
        }
    }
}
