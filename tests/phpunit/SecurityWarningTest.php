<?php

namespace grasmash\DrupalSecurityWarning\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class SecurityWarningTest extends TestCase
{
    protected Filesystem $fs;

    protected string $tmpDir;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/drupal-security-warning-test';
        $this->fs->remove($this->tmpDir);
        $this->fs->mkdir($this->tmpDir);

        $projectRoot = dirname(__DIR__, 2);
        $fixture = file_get_contents(__DIR__ . '/../fixtures/example.composer.json');
        $fixture = str_replace('%PROJECT_ROOT%', $projectRoot, $fixture);
        file_put_contents($this->tmpDir . '/composer.json', $fixture);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    /**
     * Tests that unsupported packages trigger a warning in Composer output.
     */
    public function testComposerOutput(): void
    {
        $composer = dirname(__DIR__, 2) . '/vendor/bin/composer';
        $process = new Process(
            [PHP_BINARY, $composer, 'install', '--no-interaction', '-v'],
            $this->tmpDir
        );
        $process->setTimeout(300);
        $process->run();

        $output = $process->getOutput() . $process->getErrorOutput();

        $this->assertTrue(
            $process->isSuccessful(),
            "Composer install failed:\n" . $output
        );

        $this->assertStringContainsString(
            'You are using Drupal packages that are not supported by the Drupal Security Team!',
            $output
        );
        $this->assertStringContainsString(
            '- drupal/ctools:3.0.0-alpha27: Alpha releases are not covered by Drupal security advisories.',
            $output
        );
        $this->assertStringContainsString(
            'See https://www.drupal.org/security-advisory-policy for more information.',
            $output
        );
        $this->assertStringNotContainsString('- drupal/token:1.0.0', $output);
    }
}
