<?php

namespace TheCodingMachine\ClassExplorer;


use PHPUnit\Framework\TestCase;

class PackageSourceLocatorTest extends TestCase
{

    public function testGetVendorSources(): void
    {
        $sources = PackageSourceLocator::getVendorSources();

        ['directories' => $directories, 'files' => $files] = $sources;

        $this->assertContains(\dirname(__DIR__).'/vendor/roave/better-reflection/src', $directories);
        $this->assertContains(\dirname(__DIR__).'/vendor/mindplay/composer-locator/src/ComposerLocator.php', $files);
    }

    public function testGetVendorPhpFiles(): void
    {
        $phpFiles = PackageSourceLocator::getVendorPhpFiles();

        $phpFiles = iterator_to_array($phpFiles);
        $files = [];

        foreach ($phpFiles as $fileInfo) {
            $files[(string) $fileInfo] = true;
        }

        $this->assertArrayHasKey(\dirname(__DIR__).'/vendor/mindplay/composer-locator/src/ComposerLocator.php', $files);
        $this->assertArrayHasKey(\dirname(__DIR__).'/vendor/roave/better-reflection/src/SourceLocator/Type/SourceLocator.php', $files);
    }
}
