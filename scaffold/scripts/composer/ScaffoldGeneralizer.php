<?php

namespace DrevOps\composer;

use Composer\Script\Event;
use Composer\Util\Filesystem;

/**
 * Convert DrevOps Scaffold package to a generic project.
 *
 * DrevOps Scaffold project is used both as a *project template*, for initial
 * setup with `composer create-project`, and a *dependency package* that
 * provides the scaffold assets files. This approach allows to *maintain*,
 * *distribute*, and *update* the scaffold files from a single source while
 * *preserving the project structure*.
 *
 * Once the consumer project is created, the DrevOps Scaffold project (the
 * project where this file is sourced from) is then added as a dev dependency
 * so that the DrevOps Scaffold files could be managed by the
 * drupal/core-composer-scaffold plugin.
 *
 * The event callbacks in this file are used to reset the composer.json to a
 * "generic" state. It is designed to be lightweight so that it could run
 * without installing any dependencies (`composer create --no-install`).
 *
 * This file and the references to it within the consumer project's
 * composer.json will be removed after the consumer project is created.
 *
 * @see https://www.drupal.org/docs/develop/using-composer/using-drupals-composer-scaffold
 */
class ScaffoldGeneralizer {

  /**
   * DrevOps Scaffold package name.
   *
   * @var string
   */
  const DREVOPS_SCAFFOLD_NAME = 'drevops/scaffold';

  /**
   * DrevOps Scaffold version.
   *
   * Can be overridden by setting the DREVOPS_SCAFFOLD_VERSION environment
   * variable.
   *
   * @var string
   */
  const DREVOPS_SCAFFOLD_VERSION = '^1';

  /**
   * Generic project name.
   *
   * This name is used for the consumer project's composer.json. Also, it aligns
   * with other similar generic values in the DrevOps Scaffold.
   *
   * @var string
   */
  const PROJECT_NAME = 'your_org/your_site';

  /**
   * Generic project description.
   *
   * This description is used for the consumer project's composer.json. Also,
   * it aligns with other similar generic values in the DrevOps Scaffold.
   *
   * @var string
   */
  const PROJECT_DESCRIPTION = 'Drupal implementation of YOURSITE for YOURORG';

  /**
   * Scaffold file mappings managed by the DrevOps Scaffold.
   *
   * These files are provided by the DrevOps Scaffold, when it is used as a
   * dependency, so that drupal/core-composer-scaffold plugin can manage them.
   * These mappings of the files are removed from the consumer project's
   * composer.json.
   * The consumer project developers can add these mappings manually into their
   * project if they want to prevent drupal/core-composer-scaffold plugin from
   * updating them.
   *
   * @see https://www.drupal.org/docs/develop/using-composer/using-drupals-composer-scaffold
   *
   * @var string[]
   */
  const DRUPAL_SCAFFOLD_FILE_MAPPINGS = [
    '[project-root]/.circleci/config.yml',
  ];

  /**
   * Update the project's composer.json.
   *
   * We use `post-root-package-install` event to support running both
   * `composer create-project` and `composer create-project --no-install`.
   */
  public static function generalizeAndRemoveItselfAfterProjectCreate(Event $event): void {
    $is_install = static::isInstall($event);

    $path = getcwd() . '/composer.json';
    $json = json_decode(file_get_contents($path), TRUE);

    // Change the project properties to generic values.
    // The current 'name' and 'description' properties in composer.json are used
    // to distribute the package via the registry. The 'name', 'description',
    // 'type' and 'license' properties in consumer project's composer.json
    // should have generic values.
    $json['name'] = self::PROJECT_NAME;
    $json['description'] = self::PROJECT_DESCRIPTION;
    self::arrayUpsert($json, 'description', 'type', 'project');
    self::arrayUpsert($json, 'type', 'license', 'proprietary');
    // The consumer project should not have a 'version' as it is not distributed
    // as a package.
    unset($json['version']);
    // The consumer project's authors are different to this package's authors,
    // so remove them.
    unset($json['authors']);

    // Remove Scaffold's file mappings. Consumer site's can still add these
    // mappings if they want to prevent Scaffold from updating them.
    // @see https://www.drupal.org/docs/develop/using-composer/using-drupals-composer-scaffold#toc_6
    if (!empty($json['extra']['drupal-scaffold'])) {
      foreach (self::DRUPAL_SCAFFOLD_FILE_MAPPINGS as $file) {
        unset($json['extra']['drupal-scaffold']['file-mapping'][$file]);
      }
      // Add the package itself if it provides drupal-scaffold config.
      $json['extra']['drupal-scaffold']['allowed-packages'][] = static::DREVOPS_SCAFFOLD_NAME;
      sort($json['extra']['drupal-scaffold']['allowed-packages']);
    }

    // Special treatment for the 'patches' section to preserve the format.
    if (isset($json['extra']['patches']) && count($json['extra']['patches']) === 0) {
      $json['extra']['patches'] = (object) $json['extra']['patches'];
    }

    // Add the package itself as a dev dependency to the resulting consumer
    // project.
    if ($is_install) {
      // When running project creation with installation, the addition of the
      // package to composer.json will not take effect as Composer will not
      // be re-reading the contents of the composer.json during events
      // processing. So we dynamically insert a script into the in-memory
      // Composer configuration.
      $package = $event->getComposer()->getPackage();
      $scripts = $event->getComposer()->getPackage()->getScripts();
      $scripts['post-create-project-cmd'][] = __CLASS__ . '::postCreateProjectCmd';
      $package->setScripts($scripts);
    }
    else {
      // When running project creation without installation, we can directly
      // add to composer.json as the manual installation will be initiated
      // later.
      $json['require-dev'][static::DREVOPS_SCAFFOLD_NAME] = static::getVersion();
      ksort($json['require-dev']);
      $event->getIO()->notice('Added' . static::DREVOPS_SCAFFOLD_NAME . ' as dev dependency with version ' . $json['require-dev'][static::DREVOPS_SCAFFOLD_NAME]);
    }

    // Remove references to this script.
    // Remove event script used to invoke this script during the project
    // creation.
    unset($json['scripts']['post-root-package-install'][array_search(__METHOD__, $json['scripts']['post-root-package-install'], TRUE)]);
    if (empty($json['scripts']['post-root-package-install'])) {
      unset($json['scripts']['post-root-package-install']);
    }
    // Remove the classmap for this script from the autoload section.
    unset($json['autoload']['classmap'][array_search('scripts/composer/ScaffoldGeneralizer.php', $json['autoload']['classmap'], TRUE)]);
    $json['autoload']['classmap'] = array_values($json['autoload']['classmap']);
    // Remove the script file.
    if (!$is_install) {
      $fs = new Filesystem();
      $fs->unlink(getcwd() . '/scripts/composer/ScaffoldGeneralizer.php');
    }

    // Write the updated composer.json file.
    file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $event->getIO()->write('<info>Initialised project from DrevOps Scaffold</info>');
    if (!$is_install) {
      $event->getIO()->write('<comment>Run `composer install` to further customise the project</comment>');
    }
  }

  /**
   * Require the DrevOps Scaffold package.
   */
  public static function postCreateProjectCmd(Event $event): void {
    $version = static::getVersion();
    $event->getIO()->write(sprintf('<info>Adding the DrevOps Scaffold at version "%s" as a development dependency.</info>', $version));

    $ansi = $event->getIO()->isDecorated() ? '--ansi' : '--no-ansi';
    $cmd = 'composer require --no-interaction --dev ' . $ansi . ' ' . static::DREVOPS_SCAFFOLD_NAME . ':' . $version . ' 2>&1';
    exec($cmd, $output, $result_code);
    if ($result_code != 0) {
      throw new \Exception(sprintf('Command failed with exit code %s and the following output: %s', $result_code, PHP_EOL . implode(PHP_EOL, $output)));
    }

    // Remove the script file.
    $fs = new Filesystem();
    $fs->unlink(getcwd() . '/scripts/composer/ScaffoldGeneralizer.php');
  }

  /**
   * Check if dependencies are being installed.
   */
  protected static function isInstall(Event $event): bool {
    $io = $event->getIO();

    $reflection = new \ReflectionObject($io);
    $property = $reflection->getProperty('input');
    $property->setAccessible(TRUE);
    /** @var \Symfony\Component\Console\Input\StringInput $input */
    $input = $property->getValue($io);

    return !$input->getOption('no-install');
  }

  /**
   * Get the version of the DrevOps Scaffold package.
   */
  protected static function getVersion(): string {
    $path = getcwd() . '/composer.json';
    $json = json_decode(file_get_contents($path), TRUE);

    return getenv('DREVOPS_SCAFFOLD_VERSION') ?: (isset($json['version']) ? '^' . $json['version'] : self::DREVOPS_SCAFFOLD_VERSION);
  }

  /**
   * Insert a value into an array after a specific key.
   *
   * If the specified key does not exist in the array, the value will not be
   * inserted.
   */
  protected static function arrayUpsert(&$array, $after, $key, $value): void {
    if (array_key_exists($after, $array)) {
      $position = array_search($after, array_keys($array), TRUE) + 1;
      $array = array_slice($array, 0, $position, TRUE)
        + [$key => $value]
        + array_slice($array, $position, NULL, TRUE);
    }
  }

}
