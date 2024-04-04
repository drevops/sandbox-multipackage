<?php

namespace DrevOps\Composer\Plugin\Installer;

use Composer\Composer;
use Composer\EventDispatcher\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Composer plugin for handling drupal scaffold.
 *
 * @internal
 */
class Plugin implements PluginInterface, EventSubscriberInterface {

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
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'pre-drupal-scaffold-cmd' => 'preDrupalScaffoldCmd',
      'post-drupal-scaffold-cmd' => 'postDrupalScaffoldCmd',
    ];
  }

  public static function preDrupalScaffoldCmd(Event $event) {
    print 'INSTALLER - EVENT - ' . $event->getName();
    print PHP_EOL;
  }

  public static function postDrupalScaffoldCmd(Event $event) {
    print 'INSTALLER - EVENT - ' . $event->getName();
    print PHP_EOL;
  }

  public static function preDrupalScaffoldCmdStatic(Event $event) {
    print 'INSTALLER - SCRIPT - ' . $event->getName();
    print PHP_EOL;
  }

  public static function postDrupalScaffoldCmdStatic(Event $event) {
    print 'INSTALLER - SCRIPT - ' . $event->getName();
    print PHP_EOL;
  }

}
