<?php

namespace DrevOps\composer;

use Composer\Plugin\PrePoolCreateEvent;
use Composer\Script\Event;

/**
 * Composer event script callbacks to handle the DrevOps Scaffold package.
 *
 * DrevOps Scaffold project is used both as a *project template*, for initial
 * setup with `composer create-project`, and a *dependent package* that provides
 * the scaffold assets files. This approach allows to *maintain*, *distribute*
 * and *update* the scaffold files from a single source while *preserving
 * the project structure*.
 *
 * Once the consumer project is created, the DrevOps Scaffold project (the
 * project where this file is sourced from) is then added as a dev dependency
 * so that the DrevOps Scaffold files could be managed by the
 * drupal/core-composer-scaffold plugin. We are using this plugin for managing
 * the scaffold asset files because it is a de-facto standard for Drupal
 * projects, and it's configuration mechanism is known to the Drupal community.
 *
 * The event callbacks in this file are used to reset the composer.json to a
 * "generic" state and add some handling of the DrevOps Scaffold dependency
 * package.
 *
 * It is intentionally designed to be lightweight so that it could run without
 * installing any dependencies (`composer create --no-install`).
 *
 * @see https://www.drupal.org/docs/develop/using-composer/using-drupals-composer-scaffold
 */
class ScaffoldScriptHandler {

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
  public static function postRootPackageInstall(Event $event): void {
    $is_install = !in_array('--no-install', $_SERVER['argv']);

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

    // Remove event script used to invoke this script during the project
    // creation.
    unset($json['scripts']['post-root-package-install'][array_search(__METHOD__, $json['scripts']['post-root-package-install'])]);
    if (empty($json['scripts']['post-root-package-install'])) {
      unset($json['scripts']['post-root-package-install']);
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

    // When referenced in the consumer project as require-dev, this package's
    // `require` section should not be used by the Composer to resolve
    // dependencies, because these dependencies are used only during the project
    // creation.
    // We add `pre-update-cmd` event script to the resulting composer.json to
    // be able to listen to the Composer updates and remove the package itself
    // from the pool before the update runs.
    $json['scripts']['pre-update-cmd'][] = __CLASS__ . '::preUpdateCmd';

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
    $ansi = $event->getIO()->isDecorated() ? '--ansi' : '--no-ansi';
    $cmd = 'composer require --no-interaction --dev ' . $ansi . ' ' . static::DREVOPS_SCAFFOLD_NAME . ':' . static::getVersion();
    passthru($cmd, $status);
    if ($status != 0) {
      throw new \Exception('Command failed with exit code ' . $status);
    }
  }

  /**
   * Callback for 'pre-update-cmd' event.
   *
   * Used to dynamically add event scripts to the in-memory Composer
   * configuration.
   */
  public static function preUpdateCmd(Event $event): void {
    $package = $event->getComposer()->getPackage();
    $scripts = $package->getScripts();

    // Add the pre-pool-create event to remove the Scaffold's package from the
    // pool.
    $pre_pool_create = __CLASS__ . '::prePoolCreate';
    if (empty($scripts['pre-pool-create']) || !in_array($pre_pool_create, $scripts['pre-pool-create'])) {
      $scripts['pre-pool-create'][] = $pre_pool_create;
    }

    // Add the pre-autoload-dump event to remove the classmap for this script
    // from the Scaffold's package.
    $pre_autoload_dump = __CLASS__ . '::preAutoloadDump';
    if (empty($scripts['pre-autoload-dump']) || !in_array($pre_autoload_dump, $scripts['pre-autoload-dump'])) {
      $scripts['pre-autoload-dump'][] = $pre_autoload_dump;
    }

    $package->setScripts($scripts);
  }

  /**
   * Remove the DrevOps Scaffold's package required packages from the pool.
   */
  public static function prePoolCreate(PrePoolCreateEvent $event): void {
    $packages = $event->getPackages();
    foreach ($packages as $package) {
      if ($package->getName() === static::DREVOPS_SCAFFOLD_NAME) {
        $package->setRequires([]);
      }
    }
  }

  /**
   * Remove the classmap for the ScaffoldHandler.
   */
  public static function preAutoloadDump(Event $event): void {
    $packages = $event
      ->getComposer()
      ->getRepositoryManager()
      ->getLocalRepository()
      ->getPackages();

    foreach ($packages as $package) {
      if ($package->getName() === static::DREVOPS_SCAFFOLD_NAME) {
        $autoload = $package->getAutoload();
        unset($autoload['classmap'][array_search('scripts/composer/ScaffoldScriptHandler.php', $autoload['classmap'])]);
        unset($autoload['classmap'][array_search('scripts/composer/ScriptHandler.php', $autoload['classmap'])]);
        $package->setAutoload($autoload);
        $event->getIO()->debug('Removed classmap for ' . $package->getName());
        break;
      }
    }
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
   * For existing keys with array values, the passed value will be merged
   * with these existing values.
   */
  protected static function arrayUpsert(&$array, $after, $key, $value): void {
    $keys = array_keys($array);
    $position = array_search($after, $keys);

    if (array_key_exists($key, $array) && is_array($array[$key])) {
      $array[$key] = array_merge($array[$key], is_array($value) ? $value : [$value]);
    }
    else {
      $insert = [$key => $value];
      if ($position === FALSE) {
        $array = array_merge($array, $insert);
      }
      else {
        $position++;
        $array = array_merge(
          array_slice($array, 0, $position, TRUE),
          $insert,
          array_slice($array, $position, NULL, TRUE)
        );
      }
    }
  }

}
