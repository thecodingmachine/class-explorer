<?php


namespace TheCodingMachine\ClassExplorer;

interface ClassExplorerInterface
{
    /**
     * Returns an array of fully qualified class names.
     *
     * @return string[]
     */
    public function getClasses(): array;
}
