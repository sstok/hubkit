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

final readonly class Commit
{
    public function __construct(
        public string $sha,
        public string $author,
        public string $authorEmail,
        public string $subject,
        public string $message,
        public bool $merge = false,
    ) {}

    public function toArray(): array
    {
        return [
            'sha' => $this->sha,
            'author' => \sprintf('%s <%s>', $this->author, $this->authorEmail),
            'subject' => $this->subject,
            'message' => $this->message,
            'merge' => $this->merge,
        ];
    }
}
