<?php

declare(strict_types=1);

namespace HubKit\Tests\Model\Git;

use HubKit\Model\Git\RemoteInfo;
use HubKit\Model\Git\RemoteName;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class RemoteInfoTest extends TestCase
{
    /**
     * @return iterable<array{string, RemoteInfo}>
     */
    public function provideGitUrls(): iterable
    {
        yield 'Https' => [
            'https://github.com/sstok/hupkit',
            new RemoteInfo(
                url: 'https://github.com/sstok/hupkit',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'Http' => [
            'http://github.com/sstok/hupkit',
            new RemoteInfo(
                url: 'http://github.com/sstok/hupkit',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'Https with .git suffix' => [
            'https://github.com/sstok/hupkit.git',
            new RemoteInfo(
                url: 'https://github.com/sstok/hupkit.git',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'Https with port number' => [
            'http://github.com:80/sstok/hupkit',
            new RemoteInfo(
                url: 'http://github.com:80/sstok/hupkit',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'Https with username authenticator in hostname' => [
            'https://sstok@github.com/sstok/hupkit',
            new RemoteInfo(
                url: 'https://sstok@github.com/sstok/hupkit',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'Https without repository, organization only' => [
            'https://github.com/sstok',
            new RemoteInfo(
                url: 'https://github.com/sstok',
                host: 'github.com',
            ),
        ];

        yield 'Https host only' => [
            'https://github.com',
            new RemoteInfo(
                url: 'https://github.com',
                host: 'github.com',
            ),
        ];

        yield 'Git protocol' => [
            'git://sstok@github.com/sstok/hupkit',
            new RemoteInfo(
                url: 'git://sstok@github.com/sstok/hupkit',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'Git protocol without resolvable location' => [
            'git://sstok@github.com/sstok-hupkit',
            new RemoteInfo(
                url: 'git://sstok@github.com/sstok-hupkit',
                host: 'github.com',
            ),
        ];

        yield 'Ssh+git protocol' => [
            'ssh+git://sstok@github.com/sstok/hupkit',
            new RemoteInfo(
                url: 'ssh+git://sstok@github.com/sstok/hupkit',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'Ssh protocol' => [
            'ssh://github.com/sstok/hupkit',
            new RemoteInfo(
                url: 'ssh://github.com/sstok/hupkit',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'Ssh protocol with username' => [
            'ssh://sstok@github.com/sstok/hupkit',
            new RemoteInfo(
                url: 'ssh://sstok@github.com/sstok/hupkit',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'Ssh with relative path location' => [
            'ssh://github.com/~home/sstok/hupkit',
            new RemoteInfo(
                url: 'ssh://github.com/~home/sstok/hupkit',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'Ssh with .git suffix' => [
            'ssh://github.com/sstok/hupkit.git',
            new RemoteInfo(
                url: 'ssh://github.com/sstok/hupkit.git',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'Ssh port number' => [
            'ssh://sstok@github.com:8080/sstok/hupkit',
            new RemoteInfo(
                url: 'ssh://sstok@github.com:8080/sstok/hupkit',
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
        ];

        yield 'File local protocol' => [
            'file:///home/homer/projects/sstok/',
            new RemoteInfo('file:///home/homer/projects/sstok/'),
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideGitUrls
     */
    public function create_from_string(string $url, RemoteInfo $info): void
    {
        self::assertEquals($info, RemoteInfo::fromString($url));
    }

    /**
     * @test
     */
    public function create_with_remote_name(): void
    {
        self::assertEquals(
            new RemoteInfo(
                url: 'https://github.com/sstok/hupkit',
                name: new RemoteName('origin'),
                host: 'github.com',
                organization: 'sstok',
                repository: 'hupkit',
            ),
            RemoteInfo::fromString('https://github.com/sstok/hupkit', new RemoteName('origin')),
        );


        self::assertEquals(
            new RemoteInfo(
                url: 'https://github.com/hupkit/hupkit',
                name: new RemoteName('upstream'),
                host: 'github.com',
                organization: 'hupkit',
                repository: 'hupkit',
            ),
            RemoteInfo::fromString('https://github.com/hupkit/hupkit', new RemoteName('upstream')),
        );
    }
}
