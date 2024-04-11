<?php

namespace DrevOps\Scaffold\Tests\Traits;

use Symfony\Component\Filesystem\Filesystem;

/**
 *
 */
trait FileTrait {

  public function fileFindDir(string $file, $start = NULL) {
    if (empty($start)) {
      $start = dirname(__FILE__);
    }

    $fs = new Filesystem();

    $current = $start;

    while (!empty($current) && $current !== DIRECTORY_SEPARATOR) {
      $path = $current . DIRECTORY_SEPARATOR . $file;
      if ($fs->exists($path)) {
        return $current;
      }
      $current = dirname((string) $current);
    }

    throw new \RuntimeException('File not found: ' . $file);
  }

}
