<?php

namespace DrevOps\Customizer;

class Customizer {

  protected $number;

  public function assess() {
    $this->number = 1;
    print 'CUSTOMIZER - ASSESS' . PHP_EOL;
    $this->number++;
  }

  public function process() {
    print 'CUSTOMIZER - PROCESS' . PHP_EOL;
    print 'Number: ' . $this->number . PHP_EOL;
  }

}
