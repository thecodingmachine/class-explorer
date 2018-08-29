<?php

namespace TheCodingMachine\ClassExplorer;


use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TheCodingMachine\ClassExplorer\Events\IdentifierNotFoundEvent;

class ClassesExplorerTest extends TestCase
{

    public function testGetClasses()
    {
        $eventDispatcher = new EventDispatcher();
        $classExplorer = new BetterReflectionClassesExplorer($eventDispatcher);

        $notFounds = [];

        $eventDispatcher->addListener(IdentifierNotFoundEvent::NAME, function(IdentifierNotFoundEvent $event) use (&$notFounds) {
            $notFounds[] = $event->getIdentifier();
        });

        $classes = $classExplorer->getClasses();
        var_export($classes);

        $this->assertContains('Composer\\Plugin\\PluginInterface', $notFounds);

        $this->assertSame('class', $classes['classes']['Roave\\BetterReflection\\BetterReflection']['type']);
        $this->assertSame('vendor/roave/better-reflection/src/BetterReflection.php', $classes['classes']['Roave\\BetterReflection\\BetterReflection']['path']);

        $classes2 = $classExplorer->getClasses();

        $this->assertEquals($classes, $classes2);
    }
}
