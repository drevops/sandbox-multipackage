<?php

namespace DrevOps\Scaffold\Tests\Traits;

use Flow\JSONPath\JSONPath;
use Helmich\JsonAssert\JsonAssertions;

/**
 *
 */
trait JsonAssertTrait {

  use JsonAssertions;

  public function assertJsonHasNoKey($jsonDocument, string $jsonPath, $message = NULL): void {
    $result = (new JSONPath($jsonDocument))->find($jsonPath);

    if (isset($result[0])) {
      $this->fail($message ?: sprintf("The JSON path '%s' exists, but it was expected not to.", $jsonPath));
    }

    $this->addToAssertionCount(1);
  }

}
