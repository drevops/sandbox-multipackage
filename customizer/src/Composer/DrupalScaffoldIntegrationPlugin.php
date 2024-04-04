<?php

namespace DrevOps\Composer\Plugin\Customizer;

use Composer\Composer;
use Composer\EventDispatcher\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use DrevOps\Customizer\Customizer;
use Drupal\Composer\Plugin\Scaffold\Handler;

/**
 * Composer plugin for handling drupal scaffold.
 *
 * @internal
 */
class DrupalScaffoldIntegrationPlugin implements PluginInterface, EventSubscriberInterface {

  protected Customizer $customizer;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->customizer = new Customizer();
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
      Handler::PRE_DRUPAL_SCAFFOLD_CMD => 'preDrupalScaffoldCmd',
      Handler::POST_DRUPAL_SCAFFOLD_CMD => 'postDrupalScaffoldCmd',
    ];
  }

  public function preDrupalScaffoldCmd(Event $event) {
    print 'CUSTOMIZER - EVENT - ' . $event->getName();
    print PHP_EOL;

    $this->customizer->assess();
  }

  public function postDrupalScaffoldCmd(Event $event) {
    print 'CUSTOMIZER - EVENT - ' . $event->getName();
    print PHP_EOL;

    $this->customizer->process();
  }

}
