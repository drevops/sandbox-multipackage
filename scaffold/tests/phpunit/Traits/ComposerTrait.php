<?php

namespace DrevOps\Scaffold\Tests\Traits;

use Composer\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

trait ComposerTrait {

  protected function composerCreateProject(array|string $args = NULL) {
    $args = $args ?? '';
    $args = is_array($args) ? $args : [$args];
    $args[] = $this->dirs->sut;
    $args = implode(' ', $args);

    return 'create-project --repository \'{"type": "path", "url": "' . $this->dirs->repo . '", "options": {"symlink": false}}\' drevops/scaffold="@dev" ' . $args;
  }

  /**
   * Runs a `composer` command.
   *
   * @param string $cmd
   *   The Composer command to execute (escaped as required)
   * @param string $cwd
   *   The current working directory to run the command from.
   *
   * @return string
   *   Standard output and standard error from the command.
   */
  public function composerRun($cmd, $cwd = NULL, $env = []) {
    $cwd = $cwd ?? $this->dirs->build;

    $env += [
      'DREVOPS_SCAFFOLD_VERSION' => '@dev',
    ];

    $this->envFromInput($env);

    chdir($cwd);

    $input = new StringInput($cmd);
    $output = new BufferedOutput();
    //    $output->setVerbosity(ConsoleOutput::VERBOSITY_QUIET);

    $application = new Application();
    $application->setAutoExit(FALSE);

    $code = $application->run($input, $output);
    $output = $output->fetch();

    $this->envReset();

    if ($code != 0) {
      throw new \Exception("Fixtures::composerRun failed to set up fixtures.\n\nCommand: '{$cmd}'\nExit code: {$code}\nOutput: \n\n$output");
    }

    return $output;
  }

  protected function composerReadJson($path = NULL) {
    $path = $path ?? $this->dirs->sut . '/composer.json';
    $this->assertFileExists($path);

    return json_decode(file_get_contents($path), TRUE);
  }

}
