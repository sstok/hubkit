<?php

declare(strict_types=1);

namespace HubKit\Test;

use HubKit\Service\Git;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

trait GitMockTrait
{
    use ProphecyTrait;

    private bool $workingDirReady = true;
    private bool $isGitDir = true;

    public function __construct(private TestCase $testCase, private ?string $location = null)
    {
        if (! method_exists($testCase, 'prophesize')) {
            throw new \RuntimeException(sprintf('Test class "%s" does not contain the "ProphecyTrait". Add `use %s;` to the class.', $testCase::class, ProphecyTrait::class));
        }
    }

    public function setWorkingTreeReady(bool $ready = true): self
    {
        $this->workingDirReady = $ready;

        return $this;
    }

    public function setIsGitDir(bool $isGit = true): self
    {
        $this->isGitDir = $isGit;

        return $this;
    }

    public function getMockedGit(): Git
    {
        $mocker = $this;

        $git = $this->prophesize(Git::class);


        $git->isGitDir()->will(static fn () => $mocker->isGitDir);
        $git->isWorkingTreeReady()->will(static fn () => $mocker->workingDirReady);

        return $git->reveal();
    }
}

abstract class MockGit extends Git
{
    private ?string $location = null;

    private bool $workingDirReady = true;
    private bool $isGitDir = true;



    public function setWorkingTreeReady(bool $ready = true): self
    {
        $this->workingDirReady = $ready;

        return $this;
    }

    public function setIsGitDir(bool $isGit = true): self
    {
        $this->isGitDir = $isGit;

        return $this;
    }
}
