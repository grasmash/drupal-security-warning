<?php

/**
 * @file
 * Provides a way to patch Composer packages after installation.
 */

namespace grasmash\DrupalSecurityWarning\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvents;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Script\PackageEvent;
use Composer\Util\ProcessExecutor;
use Composer\Util\Filesystem;
use Symfony\Component\Process\Process;

class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * @var Composer $composer
     */
    protected $composer;

    /**
     * @var IOInterface $io
     */
    protected $io;

    /**
     * @var array $unsupportedPackages
     */
    protected $unsupportedPackages = [];

    /**
     * Apply plugin modifications to composer
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     */
    public static function getSubscribedEvents()
    {
        return array(
            PackageEvents::POST_PACKAGE_INSTALL => "onPostPackageEvent",
            PackageEvents::POST_PACKAGE_UPDATE => "onPostPackageEvent",
            ScriptEvents::POST_UPDATE_CMD => 'onPostCmdEvent'
        );
    }

    /**
     * Adds package to $this->unsupportedPackages if applicable.
     *
     * @param \Composer\Installer\PackageEvent $event
     */
    public function onPostPackageEvent(\Composer\Installer\PackageEvent $event)
    {
        $package = $this->getDrupalPackage($event->getOperation());
        if ($package) {
            if (!$this->isPackageSupported($package)) {
                $this->unsupportedPackages[$package->getName()] = $package;
            }
        }
    }

    /**
     * Checks to see if this Drupal package is supported by the Drupal Security Team.
     *
     * @param $package
     * @return bool
     */
    protected function isPackageSupported($package)
    {
        if (preg_match('/((alpha|beta|rc)\d)|\-dev|dev\-/', $package->getVersion())) {
            return false;
        }
        return true;
    }

    /**
     * Execute blt update after update command has been executed, if applicable.
     *
     * @param \Composer\Script\Event $event
     */
    public function onPostCmdEvent(\Composer\Script\Event $event)
    {
        if (!empty($this->unsupportedPackages)) {
            $this->io->write(
                '<error>You are using Drupal packages that are not supported by the Drupal Security Team!</error>'
            );
            foreach ($this->unsupportedPackages as $package_name => $package) {
                $this->io->write("  - <comment>$package_name:{$package->getVersion()}</comment>");
            }
            $this->io->write(
                '<comment>See https://www.drupal.org/security-advisory-policy for more information.</comment>'
            );
        }
    }

    /**
     * Gets the package if it is a Drupal related package.
     *
     * @param $operation
     *
     * @return mixed
     *   If the package is a Drupal package, it will be returned. Otherwise, NULL.
     */
    protected function getDrupalPackage($operation)
    {
        if ($operation instanceof InstallOperation) {
            $package = $operation->getPackage();
        } elseif ($operation instanceof UpdateOperation) {
            $package = $operation->getTargetPackage();
        }
        if ($this->isDrupalPackage($package)) {
            return $package;
        }
        return null;
    }

    /**
     * Checks to see if a given package is a Drupal package.
     *
     * @param $package
     *
     * @return bool
     *   TRUE if the package is a Drupal package.
     */
    protected function isDrupalPackage($package)
    {
        if (isset($package) && $package instanceof PackageInterface && strstr($package->getName(), 'drupal/')) {
            return true;
        }
        return false;
    }
}
