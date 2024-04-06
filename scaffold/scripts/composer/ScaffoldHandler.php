<?php

namespace DrevOps\Composer\Plugin\Scaffold;

use Composer\Plugin\PrePoolCreateEvent;
use Composer\Script\Event;

/**
 * Composer event callbacks to handle the DrevOps Scaffold package.
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
 * projects and it's configuration mechanism is known to the Drupal community.
 *
 * The event callbacks in this file are used to reset the composer.json to a
 * "generic" state and add some handling of the DrevOps Scaffold depdendency
 * package.
 *
 * It is intentionally designed to be lightweight so that it could run without
 * installing any dependencies (`composer create --no-install`).
 *
 * @see https://www.drupal.org/docs/develop/using-composer/using-drupals-composer-scaffold
 */
class ScaffoldHandler {

  /**
   * DrevOps Scaffold package name.
   *
   * @var string
   */
  const DREVOPS_SCAFFOLD_NAME = 'drevops/scaffold';

  /**
   * DrevOps Scaffold version.
   *
   * @var string
   */
  const DREVOPS_SCAFFOLD_VERSION = '^1';

  /**
   * Generic project name.
   *
   * This name is used for the consumer project's composer.json. Also, it aligns
   * with other similar generic values in the DrevOps scaffold.
   *
   * @var string
   */
  const PROJECT_NAME = 'your_org/your_site';

  /**
   * Scaffold file mappings managed by the DrevOps Scaffold.
   *
   * These files are provided by the DrevOps Scaffold, when it is used as a
   * dependency, so that drupal/core-composer-scaffold plugin can manage them.
   * These mappings of these files are removed from the consumer project's
   * composer.json.
   * The consumer project can still add these mappings if they want to prevent
   * drupal/core-composer-scaffold plugin from updating them.
   *
   * @var string[]
   */
  const DRUPAL_SCAFFOLD_FILE_MAPPINGS = [
    '[project-root]/.circleci/config.yml',
  ];

  /**
   * Update the project's composer.json.
   *
   * We use `post-root-package-install` event to support running
   * `composer create-project --no-install`.
   */
  public static function postRootPackageInstall(Event $event): void {
    $is_install = !in_array('--no-install', $_SERVER['argv']);

    $path = getcwd() . '/composer.json';
    $json = json_decode(file_get_contents($path), TRUE);

    // Change the project name to a generic value. The current 'name' property
    // in composer.json is used to distribute the package via the registry.
    // The consumer project's 'name', 'type' and 'license' in composer.json
    // should have a generic value.
    $json['name'] = self::PROJECT_NAME;
    self::arrayUpsert($json, isset($json['description']) ? 'description' : 'name', 'type', 'project');
    self::arrayUpsert($json, 'type', 'license', 'proprietary');
    // The consumer project should not have a 'version' as it is not distributed
    // as a package.
    unset($json['version']);
    // Remove package authors.
    unset($json['authors']);

    // Add the package itself as a dev dependency to the resulting consumer
    // project.
    if ($is_install) {
      // When running project creation with installation, the addition of the
      // package to composer.json will not take effect. It must be included
      // after generating the lock file. To achieve this, we must dynamically
      // insert a script into the execution flow. This 'scripts' entry will not
      // appear in the consumer project's composer.json file.
      $package = $event->getComposer()->getPackage();
      $scripts = $event->getComposer()->getPackage()->getScripts();
      $scripts['post-create-project-cmd'][] = __CLASS__ . '::requireScaffold';
      $package->setScripts($scripts);
    }
    else {
      // When running project creation without installation, we can directly
      // add to composer.json.
      $json['require-dev'][static::DREVOPS_SCAFFOLD_NAME] = static::getVersion();
      $event->getIO()->notice('Added' . static::DREVOPS_SCAFFOLD_NAME . ' as dev dependency with version ' . $json['require-dev'][static::DREVOPS_SCAFFOLD_NAME]);
    }

    // Composer should not use this package's `require` section to resolve
    // dependencies, because these dependencies are used only during the project
    // creation.
    // We add `pre-update-cmd` to the resulting composer.json to
    // remove the package from the pool before the update.
    $json['scripts']['pre-update-cmd'][] = __CLASS__ . '::preUpdateCmd';

    // Remove script that was calling this plugin during project creation.
    unset($json['scripts']['post-root-package-install'][array_search(__METHOD__, $json['scripts']['post-root-package-install'])]);
    if (empty($json['scripts']['post-root-package-install'])) {
      unset($json['scripts']['post-root-package-install']);
    }

    // Remove Scaffold's file mappings. Consumer site's can still add these
    // mappings if they want to prevent Scaffold from updating them.
    // @see https://www.drupal.org/docs/develop/using-composer/using-drupals-composer-scaffold#toc_6
    if (!empty($json['extra']['drupal-scaffold'])) {
      foreach (self::DRUPAL_SCAFFOLD_FILE_MAPPINGS as $file) {
        unset($json['extra']['drupal-scaffold']['file-mapping'][$file]);
      }
      // Add the package itself if it provides drupal-scaffold config.
      $json['extra']['drupal-scaffold']['allowed-packages'][] = static::DREVOPS_SCAFFOLD_NAME;
    }

    // Preserve format of the 'patches' section.
    if (isset($json['extra']['patches']) && count($json['extra']['patches']) === 0) {
      $json['extra']['patches'] = (object) $json['extra']['patches'];
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
  public static function requireScaffold(Event $event): void {
    $ansi = $event->getIO()->isDecorated() ? '--ansi' : '--no-ansi';
    $cmd = 'composer require --dev ' . $ansi . ' ' . static::DREVOPS_SCAFFOLD_NAME . ':' . static::getVersion();
    passthru($cmd, $status);
    if ($status != 0) {
      throw new \Exception('Command failed with exit code ' . $status);
    }
  }

  /**
   * Callback for 'pre-update-cmd' event.
   */
  public static function preUpdateCmd(Event $event): void {
    $package = $event->getComposer()->getPackage();
    $scripts = $package->getScripts();

    $pre_pool_create = __CLASS__ . '::prePoolCreate';
    if (empty($scripts['pre-pool-create']) || !in_array($pre_pool_create, $scripts['pre-pool-create'])) {
      $scripts['pre-pool-create'][] = $pre_pool_create;
    }

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
        unset($autoload['classmap'][array_search('scripts/composer/ScaffoldHandler.php', $autoload['classmap'])]);
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
