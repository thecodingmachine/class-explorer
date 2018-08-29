<?php

namespace TheCodingMachine\ClassExplorer\Glob;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Simple\NullCache;

class GlobClassExplorerTest extends TestCase
{
    public function testGetClasses()
    {
        $explorer = new GlobClassExplorer('\\TheCodingMachine\\ClassExplorer\\Glob\\', new NullCache());
        $classes = $explorer->getClasses();

        $this->assertSame([GlobClassExplorer::class], $classes);
    }
}
