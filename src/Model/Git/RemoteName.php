<?php

declare(strict_types=1);

namespace HubKit\Model\Git;

final class RemoteName
{
    public function __construct(public string $name)
    {
        if (preg_match('/[^a-z0-9_-]/i', $name)) {
            throw new \InvalidArgumentException(sprintf('Remote name "%s" is invalid.', $name));
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
