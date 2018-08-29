<?php


namespace TheCodingMachine\ClassExplorer\Exceptions;


class PackageDetectionException extends ClassExplorerException
{
    public static function cannotLoadComposerLock(string $path): self
    {
        return new self("Unable to load composer.lock file in '$path')");
    }

    public static function unableToDecodeJson(): self
    {
        return new self("Unable to decode JSON in composer.lock");
    }
}