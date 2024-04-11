<?php

namespace DrevOps\Scaffold\Tests\Traits;

use Flow\JSONPath\JSONPath;
use Helmich\JsonAssert\JsonAssertions;

trait JsonAssertTrait {

  use JsonAssertions;

  public function assertJsonHasNoKey($jsonDocument, string $jsonPath, $message = NULL) {
    $result = (new JSONPath($jsonDocument))->find($jsonPath);

    if (isset($result[0])) {
      $this->fail($message ?: "The JSON path '$jsonPath' exists, but it was expected not to.");
    }

    $this->addToAssertionCount(1);
  }

}
