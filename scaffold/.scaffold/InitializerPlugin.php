<?php

namespace DrevOps\Composer\Plugin\Initializer;

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
 * @see https://www.drupal.org/docs/develop/using-composer/using-drupals-composer-scaffold
 */
class InitializerPlugin implements PluginInterface {

  /**
   * DrevOps Scaffold version.
   *
   * @var string
   */
  const DREVOPS_SCAFFOLD_VERSION = '^1';

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

    // Add 'drevops/scaffold' as a dev dependency.
    $json['require-dev']['drevops/scaffold'] = getenv('DREVOPS_SCAFFOLD_VERSION') ?: self::DREVOPS_SCAFFOLD_VERSION;
    $json['config']['allow-plugins']['drevops/scaffold'] = TRUE;

    // Change the project name to a generic value. The `drevops/scaffold` name
    // was used to distribute the scaffold itself as a package. The consumer
    // site's 'name' in composer.json should have a generic value.
    $json['name'] = self::PROJECT_NAME;

    // Remove Scaffold's file mappings. Consumer site's can still add these
    // mappings if they want to prevent Scaffold from updating them.
    // @see https://www.drupal.org/docs/develop/using-composer/using-drupals-composer-scaffold#toc_6
    foreach (self::SCAFFOLD_FILE_MAPPINGS as $file) {
      unset($json['extra']['drupal-scaffold']['file-mapping'][$file]);
    }

    // Remove this plugin and all references to it.
    unset($json['autoload']['psr-4']['DrevOps\\Composer\\Plugin\\Initializer\\']);
    if (empty($json['autoload']['psr-4'])) {
      unset($json['autoload']['psr-4']);
      if (empty($json['autoload'])) {
        unset($json['autoload']);
      }
    }
    unset($json['scripts']['post-root-package-install'][array_search('DrevOps\\Composer\\Plugin\\Initializer\\InitializerPlugin::postRootPackageInstall', $json['scripts']['post-root-package-install'])]);
    if (empty($json['scripts']['post-root-package-install'])) {
      unset($json['scripts']['post-root-package-install']);
      if (empty($json['scripts'])) {
        unset($json['scripts']);
      }
    }
    $fs = new Filesystem();
    $fs->removeDirectory(getcwd() . '/.scaffold');

    file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $event->getIO()->write('<info>Initialised project from DrevOps Scaffold</info>');
    if (in_array('--no-install', $_SERVER['argv'])) {
      $event->getIO()->write('<comment>Run `composer install` to further customise the project</comment>');
    }
  }

}
