<?php

namespace DrevOps\composer;

use Composer\Plugin\PrePoolCreateEvent;
use Composer\Script\Event;

/**
 * Composer event script callbacks to handle the DrevOps Scaffold package.
 */
class ScaffoldScriptHandler {

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
    $scripts['pre-pool-create'][] = __CLASS__ . '::prePoolCreate';;
    $scripts['pre-pool-create'] = array_unique($scripts['pre-pool-create']);

    // Add the pre-autoload-dump event to remove the classmap for this script
    // from the Scaffold's package.
    $scripts['pre-autoload-dump'][] = __CLASS__ . '::preAutoloadDump';;
    $scripts['pre-autoload-dump'] = array_unique($scripts['pre-autoload-dump']);

    $package->setScripts($scripts);
  }

  /**
   * Remove DrevOps Scaffold dependencies from the project's dependency pool.
   *
   * This step ensures that dependencies needed only for the project creation
   * process do not affect the consumer project's own dependencies.
   */
  public static function prePoolCreate(PrePoolCreateEvent $event): void {
    $packages = $event->getPackages();
    foreach ($packages as $package) {
      if ($package->getName() === 'drevops/scaffold') {
        $package->setRequires([]);
      }
    }
  }

  /**
   * Prevent classmap duplication from DrevOps Scaffold.
   *
   * This step ensures that the classmap entries from the DrevOps Scaffold are
   * removed to avoid class duplication in the consumer project's autoload pool.
   */
  public static function preAutoloadDump(Event $event): void {
    $packages = $event
      ->getComposer()
      ->getRepositoryManager()
      ->getLocalRepository()
      ->getPackages();

    foreach ($packages as $package) {
      if ($package->getName() === 'drevops/scaffold') {
        $autoload = $package->getAutoload();
        unset($autoload['classmap'][array_search('scripts/composer/ScaffoldScriptHandler.php', $autoload['classmap'], true)]);
        unset($autoload['classmap'][array_search('scripts/composer/ScaffoldGeneralizer.php', $autoload['classmap'], true)]);
        unset($autoload['classmap'][array_search('scripts/composer/ScriptHandler.php', $autoload['classmap'], true)]);
        $package->setAutoload($autoload);
        $event->getIO()->debug('Removed classmap for ' . $package->getName());
        break;
      }
    }
  }

}
