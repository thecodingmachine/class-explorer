<?php

namespace TheCodingMachine\ClassExplorer\Glob;

use Mouf\Composer\ClassNameMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Simple\NullCache;
use TheCodingMachine\ClassExplorer\ClassExplorerInterface;

class GlobClassExplorerTest extends TestCase
{
    public function testGetClasses()
    {
        $explorer = new GlobClassExplorer('\\TheCodingMachine\\ClassExplorer\\', new NullCache());
        $classes = $explorer->getClasses();

        $this->assertSame([GlobClassExplorer::class, ClassExplorerInterface::class], $classes);
    }

    public function testGetClassesNonRecursive()
    {
        $explorer = new GlobClassExplorer('\\TheCodingMachine\\ClassExplorer\\', new NullCache(), null, null, false);
        $classes = $explorer->getClasses();

        $this->assertSame([ClassExplorerInterface::class], $classes);
    }

    public function testGetDevClasses()
    {
        $explorer = new GlobClassExplorer('\\TheCodingMachine\\ClassExplorer\\Glob\\', new NullCache(), null, ClassNameMapper::createFromComposerFile(null, null, true));
        $classes = $explorer->getClasses();

        $this->assertSame([GlobClassExplorer::class, GlobClassExplorerTest::class], $classes);
    }

    public function testGetNotExistingClasses()
    {
        $explorer = new GlobClassExplorer('\\TheCodingMachine\\ClassExplorer\\Glob\\Foobar\\', new NullCache());
        $classes = $explorer->getClasses();

        $this->assertSame([], $classes);
    }
}
