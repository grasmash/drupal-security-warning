<?php

namespace grasmash\DrupalSecurityWarning\Tests;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\IO\BufferIO;
use Composer\Package\Package;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use grasmash\DrupalSecurityWarning\Composer\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Exposes protected internals of Plugin for unit testing.
 */
class TestablePlugin extends Plugin
{
    public function callIsDrupalPackage($package): bool
    {
        return $this->isDrupalPackage($package);
    }

    public function callGetDrupalPackage($operation)
    {
        return $this->getDrupalPackage($operation);
    }

    public function callIsPackageSupported($package): bool
    {
        return $this->isPackageSupported($package);
    }

    public function getUnsupportedPackages(): array
    {
        return $this->unsupportedPackages;
    }

    public function addUnsupportedPackage(Package $package): void
    {
        $this->unsupportedPackages[$package->getName()] = $package;
    }
}

class PluginUnitTest extends TestCase
{
    protected TestablePlugin $plugin;

    protected BufferIO $io;

    protected function setUp(): void
    {
        $this->plugin = new TestablePlugin();
        $this->io = new BufferIO();
        $this->plugin->activate(new Composer(), $this->io);
    }

    protected function createPackage(
        string $name,
        string $version = '1.0.0',
        array $extra = []
    ): Package {
        $package = new Package($name, $version . '.0', $version);
        $package->setExtra($extra);
        return $package;
    }

    public function testDrupalPackageIsDetected(): void
    {
        $package = $this->createPackage('drupal/token');
        $this->assertTrue($this->plugin->callIsDrupalPackage($package));
    }

    public function testNonDrupalPackageIsNotDetected(): void
    {
        $package = $this->createPackage('symfony/console');
        $this->assertFalse($this->plugin->callIsDrupalPackage($package));
    }

    public function testVendorNameContainingDrupalSubstringIsNotDetected(): void
    {
        $package = $this->createPackage('notdrupal/foo');
        $this->assertFalse($this->plugin->callIsDrupalPackage($package));
    }

    public function testGetDrupalPackageFromInstallOperation(): void
    {
        $package = $this->createPackage('drupal/token');
        $operation = new InstallOperation($package);
        $this->assertSame($package, $this->plugin->callGetDrupalPackage($operation));
    }

    public function testGetDrupalPackageFromUpdateOperation(): void
    {
        $initial = $this->createPackage('drupal/token');
        $target = $this->createPackage('drupal/token', '2.0.0');
        $operation = new UpdateOperation($initial, $target);
        $this->assertSame($target, $this->plugin->callGetDrupalPackage($operation));
    }

    public function testGetDrupalPackageFromUninstallOperationReturnsNull(): void
    {
        $package = $this->createPackage('drupal/token');
        $operation = new UninstallOperation($package);
        $this->assertNull($this->plugin->callGetDrupalPackage($operation));
    }

    public function testGetDrupalPackageFromNonDrupalInstallReturnsNull(): void
    {
        $package = $this->createPackage('symfony/console');
        $operation = new InstallOperation($package);
        $this->assertNull($this->plugin->callGetDrupalPackage($operation));
    }

    public function testPackageWithoutCoverageMetadataIsSupported(): void
    {
        $package = $this->createPackage('drupal/token');
        $this->assertTrue($this->plugin->callIsPackageSupported($package));
    }

    public function testCoveredPackageIsSupported(): void
    {
        $package = $this->createPackage('drupal/token', '1.0.0', [
            'drupal' => ['security-coverage' => ['status' => 'covered']],
        ]);
        $this->assertTrue($this->plugin->callIsPackageSupported($package));
    }

    public function testNotCoveredPackageIsUnsupported(): void
    {
        $package = $this->createPackage('drupal/ctools', '3.0.0-alpha27', [
            'drupal' => ['security-coverage' => ['status' => 'not-covered']],
        ]);
        $this->assertFalse($this->plugin->callIsPackageSupported($package));
    }

    public function testWarningIsWrittenForUnsupportedPackages(): void
    {
        $package = $this->createPackage('drupal/ctools', '3.0.0-alpha27', [
            'drupal' => [
                'security-coverage' => [
                    'status' => 'not-covered',
                    'message' => 'Alpha releases are not covered by Drupal security advisories.',
                ],
            ],
        ]);
        $this->plugin->addUnsupportedPackage($package);

        $this->plugin->onPostCmdEvent($this->createScriptEvent());
        $output = $this->io->getOutput();

        $this->assertStringContainsString(
            'You are using Drupal packages that are not supported by the Drupal Security Team!',
            $output
        );
        $this->assertStringContainsString('drupal/ctools', $output);
        $this->assertStringContainsString(
            'Alpha releases are not covered by Drupal security advisories.',
            $output
        );
    }

    public function testWarningHandlesMissingCoverageMessage(): void
    {
        $package = $this->createPackage('drupal/ctools', '3.0.0-alpha27', [
            'drupal' => ['security-coverage' => ['status' => 'not-covered']],
        ]);
        $this->plugin->addUnsupportedPackage($package);

        $this->plugin->onPostCmdEvent($this->createScriptEvent());
        $output = $this->io->getOutput();

        $this->assertStringContainsString('drupal/ctools', $output);
    }

    public function testWarningEscapesFormattingTagsInPackageMetadata(): void
    {
        $package = $this->createPackage('drupal/evil', '1.0.0', [
            'drupal' => [
                'security-coverage' => [
                    'status' => 'not-covered',
                    'message' => 'Malicious <error>tag</error> injection',
                ],
            ],
        ]);
        $this->plugin->addUnsupportedPackage($package);

        $this->plugin->onPostCmdEvent($this->createScriptEvent());
        $output = $this->io->getOutput();

        $this->assertStringContainsString(
            'Malicious <error>tag</error> injection',
            $output,
            'Formatter tags in untrusted package metadata must be escaped, not interpreted.'
        );
    }

    public function testWarningUsesPrettyVersion(): void
    {
        $package = $this->createPackage('drupal/ctools', '3.0.0-alpha27', [
            'drupal' => [
                'security-coverage' => [
                    'status' => 'not-covered',
                    'message' => 'Alpha releases are not covered by Drupal security advisories.',
                ],
            ],
        ]);
        $this->plugin->addUnsupportedPackage($package);

        $this->plugin->onPostCmdEvent($this->createScriptEvent());
        $output = $this->io->getOutput();

        $this->assertStringContainsString('drupal/ctools:3.0.0-alpha27', $output);
        $this->assertStringNotContainsString('3.0.0-alpha27.0', $output);
    }

    public function testNoWarningIsWrittenWhenAllPackagesSupported(): void
    {
        $this->plugin->onPostCmdEvent($this->createScriptEvent());
        $this->assertSame('', $this->io->getOutput());
    }

    protected function createScriptEvent(): Event
    {
        return new Event(ScriptEvents::POST_INSTALL_CMD, new Composer(), $this->io, false);
    }
}
