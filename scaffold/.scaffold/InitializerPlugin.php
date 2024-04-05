<?php

namespace DrevOps\Composer\Plugin\Scaffold;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem;

/**
 * Composer plugin for handling project initialization.
 *
 * DrevOps Scaffold project is used both as a project template, for initial
 * setup with `composer create-project`, and a dependency package that provides
 * the scaffold files for the future updates.
 *
 * This plugin is only used to "reset" the project to a generic state - remove
 * all the "wiring" added to support `composer create-project` command.
 * It is intentionally designed to be lightweight so that it could run without
 * installing any dependencies (`composer create --no-install`).
 *
 * It is used only once, right after the project is created, and then it removes
 * itself from the project dependencies. `drevops/scaffold` is then added as
 * a dev dependency so that the DrevOps Scaffold files could be managed by the
 * core-composer-scaffold plugin.
 *
 * @todo Subscribe to the `post-root-package-install` event once
 * https://github.com/composer/composer/issues/11919 is fixed.
 *
 * @see https://www.drupal.org/docs/develop/using-composer/using-drupals-composer-scaffold
 */
class InitializerPlugin implements PluginInterface {

  /**
   * DrevOps Scaffold version.
   *
   * @var string
   */
  const DREVOPS_SCAFFOLD_VERSION = '1';

  /**
   * Generic project name.
   *
   * @var string
   */
  const PROJECT_NAME = 'your_org/your_site';

  /**
   * Scaffold file mappings managed by the DrevOps Scaffold.
   *
   * @var string[]
   */
  const SCAFFOLD_FILE_MAPPINGS = [
    '[project-root]/.circleci/config.yml',
  ];

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io) {
  }

  /**
   * Update the project root package.
   */
  public static function postRootPackageInstall(Event $event): void {
    $path = getcwd() . '/composer.json';
    $json = json_decode(file_get_contents($path), TRUE);

    $name = $json['name'];

    // Change the project name to a generic value. The `drevops/scaffold` name
    // was used to distribute the scaffold itself as a package. The consumer
    // site's 'name' in composer.json should have a generic value.
    $json['name'] = self::PROJECT_NAME;
    self::arrayUpsert($json, isset($json['description']) ? 'description' : 'name', 'type', 'project');
    self::arrayUpsert($json, 'type', 'license', 'proprietary');

    // Add the package itself as a dev dependency.
    $json['require-dev'][$name] = getenv('DREVOPS_SCAFFOLD_VERSION') ?: '^' . ($json['version'] ?? self::DREVOPS_SCAFFOLD_VERSION);
    $json['config']['allow-plugins'][$name] = TRUE;

    // Remove Scaffold's file mappings. Consumer site's can still add these
    // mappings if they want to prevent Scaffold from updating them.
    // @see https://www.drupal.org/docs/develop/using-composer/using-drupals-composer-scaffold#toc_6
    foreach (self::SCAFFOLD_FILE_MAPPINGS as $file) {
      unset($json['extra']['drupal-scaffold']['file-mapping'][$file]);
    }
    $json['extra']['drupal-scaffold']['allowed-packages'][] = $name;

    // Remove this plugin and all references to it.
    unset($json['version']);
    unset($json['authors']);

    unset($json['autoload']['psr-4'][__NAMESPACE__ . '\\']);
    self::arrayRemoveEmpty($json, 'autoload');

    unset($json['scripts']['post-root-package-install'][array_search(__METHOD__, $json['scripts']['post-root-package-install'])]);
    self::arrayRemoveEmpty($json, 'scripts');

    $fs = new Filesystem();
    $fs->removeDirectory(getcwd() . '/.scaffold');

    // Preserve format of the 'patches' section.
    if (isset($json['extra']['patches']) && count($json['extra']['patches']) === 0) {
      $json['extra']['patches'] = (object) $json['extra']['patches'];
    }

    // Write the updated composer.json file.
    file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $event->getIO()->write('<info>Initialised project from DrevOps Scaffold</info>');
    if (in_array('--no-install', $_SERVER['argv'])) {
      $event->getIO()->write('<comment>Run `composer install` to further customise the project</comment>');
    }
  }

  /**
   * Insert a value into an array after a specific key.
   *
   * For existing keys with array values, the passed value will be merged
   * with these existing values.
   */
  protected static function arrayUpsert(&$array, $after, $key, $value) {
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

  /**
   * Remove empty values from an array.
   */
  protected static function arrayRemoveEmpty(array &$array, $key = NULL) {
    $keys = $key !== NULL && isset($array[$key]) ? [$key] : array_keys($array);

    foreach ($keys as $k) {
      if (is_array($array[$k])) {
        foreach ($array[$k] as $kk => $value) {
          if (is_array($value)) {
            self::arrayRemoveEmpty($array[$k][$kk]);
          }
          if (empty($array[$k][$kk])) {
            unset($array[$k][$kk]);
          }
        }
        if (empty($array[$k])) {
          unset($array[$k]);
        }
      }
      elseif (empty($array[$k])) {
        unset($array[$k]);
      }
    }
  }

}
