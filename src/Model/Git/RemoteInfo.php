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

final readonly class RemoteInfo
{
    public function __construct(
        public string $url,
        public ?RemoteName $name = null,
        public ?string $host = null,
        public ?string $organization = null,
        public ?string $repository = null,
        public ?string $directory = null,
    ) {}

    /** @deprecated */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,

            'host' => $this->host ?? '',
            'org' => $this->organization ?? '',
            'repo' => $this->repository ?? '',
            'directory' => $this->directory,
        ];
    }

    public static function fromString(string $gitUri, ?RemoteName $name = null): self
    {
        $info = [
            'host' => '',
            'org' => '',
            'repo' => '',
            'path' => '',
        ];

        if (str_starts_with($gitUri, 'file://')) {
            return new self(url: $gitUri, host: $info['path'], organization: null, repository: null, directory: $info['path']);
        }

        if (str_starts_with($gitUri, 'http://') || str_starts_with($gitUri, 'https://')) {
            $url = parse_url($gitUri);

            if ($url === false) {
                throw new \InvalidArgumentException(\sprintf('Malformed Git url "%s".', $gitUri));
            }

            $info['host'] = $url['host'];
            $info['path'] = mb_ltrim($url['path'] ?? '', '/');
        } elseif (preg_match('%^(?:(?:git|ssh)://)?[^@]+@(?P<host>[^:]+):(?P<path>[^$]+)$%', $gitUri, $match)) {
            $info['host'] = $match['host'];
            $info['path'] = $match['path'];
        } elseif (preg_match('%^(?:(?:git|ssh)://)?([^@]+@)?(?P<host>[^/]+)/(?P<path>[^$]+)$%', $gitUri, $match)) {
            $info['host'] = $match['host'];
            $info['path'] = $match['path'];
        }

        if (str_contains($info['path'], '/')) {
            $dirs = \array_slice(explode('/', $info['path']), -2, 2);

            $info['org'] = $dirs[0];
            $info['repo'] = mb_substr($dirs[1], -4, 4) === '.git' ? mb_substr($dirs[1], 0, -4) : $dirs[1];
        }

        return new self(url: $gitUri, name: $name, host: $info['host'], organization: $info['org'], repository: $info['repo']);
    }
}
