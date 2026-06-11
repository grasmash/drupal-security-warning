<?php

/**
 * @file
 * Generates a warning for installation of Drupal packages not supported by Security Team.
 */

declare(strict_types=1);

namespace grasmash\DrupalSecurityWarning\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Console\Formatter\OutputFormatter;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    protected Composer $composer;

    protected IOInterface $io;

    /**
     * @var \Composer\Package\PackageInterface[]
     */
    protected array $unsupportedPackages = [];

    /**
     * Apply plugin modifications to composer.
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @see https://getcomposer.org/doc/articles/scripts.md#event-names
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPostPackageEvent',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPostPackageEvent',
            ScriptEvents::POST_INSTALL_CMD => 'onPostCmdEvent',
            ScriptEvents::POST_UPDATE_CMD => 'onPostCmdEvent',
        ];
    }

    /**
     * Adds package to $this->unsupportedPackages if applicable.
     */
    public function onPostPackageEvent(PackageEvent $event): void
    {
        $package = $this->getDrupalPackage($event->getOperation());
        if ($package && !$this->isPackageSupported($package)) {
            $this->unsupportedPackages[$package->getName()] = $package;
        }
    }

    /**
     * Checks to see if this Drupal package is supported by the Drupal Security Team.
     */
    protected function isPackageSupported(PackageInterface $package): bool
    {
        $extra = $package->getExtra();
        if (
            !empty($extra['drupal']['security-coverage']['status'])
            && $extra['drupal']['security-coverage']['status'] === 'not-covered'
        ) {
            return false;
        }
        return true;
    }

    /**
     * Writes a warning for any unsupported packages after install/update.
     */
    public function onPostCmdEvent(Event $event): void
    {
        if (!empty($this->unsupportedPackages)) {
            $this->io->write(
                '<error>You are using Drupal packages that are not supported by the Drupal Security Team!</error>'
            );
            foreach ($this->unsupportedPackages as $package_name => $package) {
                $extra = $package->getExtra();
                $message = $extra['drupal']['security-coverage']['message']
                    ?? 'This package is not covered by Drupal security advisories.';
                $name_and_version = OutputFormatter::escape("$package_name:{$package->getPrettyVersion()}");
                $this->io->write(
                    "  - <comment>$name_and_version</comment>: " . OutputFormatter::escape($message)
                );
            }
            $this->io->write(
                '<comment>See https://www.drupal.org/security-advisory-policy for more information.</comment>'
            );
        }
    }

    /**
     * Gets the package if it is a Drupal related package.
     *
     * @return \Composer\Package\PackageInterface|null
     *   If the package is a Drupal package, it will be returned. Otherwise, NULL.
     */
    protected function getDrupalPackage(OperationInterface $operation): ?PackageInterface
    {
        if ($operation instanceof InstallOperation) {
            $package = $operation->getPackage();
        } elseif ($operation instanceof UpdateOperation) {
            $package = $operation->getTargetPackage();
        } else {
            return null;
        }
        if ($this->isDrupalPackage($package)) {
            return $package;
        }
        return null;
    }

    /**
     * Checks to see if a given package is a Drupal package.
     */
    protected function isDrupalPackage(?PackageInterface $package): bool
    {
        return $package instanceof PackageInterface
            && str_starts_with($package->getName(), 'drupal/');
    }

    /**
     * {@inheritDoc}
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }
}
