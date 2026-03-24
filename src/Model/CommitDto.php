<?php

declare(strict_types=1);

namespace HubKit\Model;

final readonly class CommitDto
{
    public function __construct(
        public string $sha,
        public string $author,
        public string $authorEmail,
        public string $subject,
        public string $message,
        public bool $merge = false,
    ) { }

    public function toArray(): array
    {
        return [
            'sha' => $this->sha,
            'author' => sprintf('%s <%s>', $this->author, $this->authorEmail),
            'subject' => $this->subject,
            'message' => $this->message,
            'merge' => $this->merge,
        ];
    }
}
