<?php

declare(strict_types=1);

namespace DrevOps\Scaffold\Tests\Functional;

use DrevOps\Scaffold\Tests\Dirs;
use DrevOps\Scaffold\Tests\Traits\CmdTrait;
use DrevOps\Scaffold\Tests\Traits\ComposerTrait;
use DrevOps\Scaffold\Tests\Traits\EnvTrait;
use DrevOps\Scaffold\Tests\Traits\JsonAssertTrait;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus\Failure;
use Symfony\Component\Filesystem\Filesystem;

class ScaffoldTest extends TestCase {

  use CmdTrait;
  use ComposerTrait;
  use EnvTrait;
  use JsonAssertTrait;

  /**
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fs;

  /**
   * @var \DrevOps\Scaffold\Tests\Dirs
   */
  protected $dirs;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fs = new Filesystem();

    $this->dirs = new Dirs();
    $this->dirs->initLocations();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (!$this->hasFailed()) {
      $this->dirs->deleteLocations();
    }

    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  protected function onNotSuccessfulTest(\Throwable $t): never {
    $this->dirs->printInfo();

    parent::onNotSuccessfulTest($t); // Rethrow the exception to allow the test to fail normally.
  }

  public function hasFailed(): bool {
    $status = $this->status();

    return $status instanceof Failure;
  }

}
