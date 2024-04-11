<?php

namespace DrevOps\Scaffold\Tests;

use DrevOps\Scaffold\Tests\Traits\FileTrait;
use Symfony\Component\Filesystem\Filesystem;

class Dirs {

  use FileTrait;

  /**
   * Directory where a copy of the DrevOps Scaffold (this) repository is located.
   *
   * This allows to isolate the test from this repository files and prevent
   * their accidental removal.
   *
   * @var string
   */
  public $repo;

  /**
   * Root build directory where the rest of the directories located.
   *
   * The "build" in this context is a place to store assets produced by a single
   * test run.
   *
   * @var string
   */
  public $build;

  /**
   * Directory where the test will run.
   *
   * @var string
   */
  public $sut;

  /**
   * The file system.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fs;

  public function __construct() {
    $this->fs = new Filesystem();
  }

  public function initLocations() {
    $this->build = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'drevops-scaffold-' . microtime(TRUE);
    $this->sut = "$this->build/sut";
    $this->repo = "$this->build/local_repo";

    $this->fs->mkdir($this->build);

    $this->prepareLocalRepo();
  }

  public function deleteLocations() {
    $this->fs->remove($this->build);
  }

  public function printInfo() {
    $lines[] = '-- LOCATIONS --';
    $lines[] = "Build      : {$this->build}";
    $lines[] = "SUT        : {$this->sut}";
    $lines[] = "Local repo : {$this->repo}";

    fwrite(STDERR, PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL);
  }

  protected function prepareLocalRepo() {
    $root = $this->fileFindDir('composer.json');

    $this->fs->copy($root . '/composer.json', $this->repo . '/composer.json');
    $this->fs->mirror($root . '/scripts', $this->repo . '/scripts');
    $this->fs->mirror($root . '/.circleci', $this->repo . '/.circleci');
    $this->fs->copy($root . '/myfile1.txt', $this->repo . '/myfile1.txt');

    // @todo Refactor temp adjustments below.
    $dstJson = json_decode(file_get_contents($this->repo . '/composer.json'), TRUE);
    unset($dstJson['repositories'][0]);
    unset($dstJson['require-dev']['drevops/customizer']);

    // Override the dir for the scaffold. This will later need to be
    // with an addition of the entry to `repositories` array once it is removed
    // from the `composer.json`.
    $dstJson['repositories'][1]['url'] = $this->repo;

    file_put_contents($this->repo . '/composer.json', json_encode($dstJson, JSON_PRETTY_PRINT));
  }

}
