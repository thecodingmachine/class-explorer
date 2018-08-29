<?php


namespace TheCodingMachine\ClassExplorer;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use TheCodingMachine\ClassExplorer\Exceptions\PackageDetectionException;

class PackageSourceLocator
{
    /**
     * @return string[][] An array with 2 keys: "directories" and "files". Each value is an array of string
     * @throws PackageDetectionException
     */
    public static function getVendorSources(): array
    {
        $rootPath = \ComposerLocator::getRootPath();

        $packageLockContent = \file_get_contents($rootPath.'/composer.lock');
        if ($packageLockContent === false) {
            throw PackageDetectionException::cannotLoadComposerLock($rootPath.'/composer.lock');
        }

        $packageLock = json_decode($packageLockContent, true);
        if ($packageLock === false) {
            throw PackageDetectionException::unableToDecodeJson();
        }

        $directories = [];
        $files = [];

        foreach ($packageLock['packages'] as $packageDef) {
            self::addDirectoriesAndFiles($packageDef, $directories, $files, false);
        }
        if ($packageLock['packages-dev']) {
            foreach ($packageLock['packages-dev'] as $packageDef) {
                // We are not sure that dev packages are installed so we allow failure to retrieve them.
                self::addDirectoriesAndFiles($packageDef, $directories, $files, true);
            }
        }

        // TODO: add support for exclude-from-classmap: https://getcomposer.org/doc/04-schema.md#exclude-files-from-classmaps

        return ['directories' => $directories, 'files' => $files];
    }

    private static function addDirectoriesAndFiles(array $packageDef, array &$directories, array &$files, bool $canFail): void
    {
        if (!isset($packageDef['autoload'])) {
            return;
        }

        $autoload = $packageDef['autoload'];
        try {
            $packagePath = \ComposerLocator::getPath($packageDef['name']).'/';
        } catch (\RuntimeException $runtimeException) {
            if ($canFail === true) {
                return;
            } else {
                throw $runtimeException;
            }
        }

        foreach (['psr-0', 'psr-4'] as $psrNumber) {
            if (isset($autoload[$psrNumber])) {
                foreach ($autoload[$psrNumber] as $dir) {
                    if (\is_array($dir)) {
                        foreach ($dir as $item) {
                            $directories[] = $packagePath.$item;
                        }
                    } else {
                        $directories[] = $packagePath.$dir;
                    }
                }
            }
        }
        if (isset($autoload['classmap'])) {
            foreach ($autoload['classmap'] as $file) {
                if (\is_dir($packagePath.$file)) {
                    $directories[] = $packagePath.$file;
                } else {
                    $files[] = $packagePath.$file;
                }
            }
        }
        if (isset($autoload['files'])) {
            foreach ($autoload['files'] as $file) {
                $files[] = $packagePath.$file;
            }
        }
    }

    /**
     * Returns a list of all vendor PHP files.
     * The key of the iterator is the absolute path of the file. The value is the SplFileInfo.
     *
     * @return \Iterator|\SplFileInfo[]
     */
    public static function getVendorPhpFiles(): \Iterator
    {
        $appendIterator = new \AppendIterator();
        ['directories' => $directories, 'files' => $files] = self::getVendorSources();

        foreach ($directories as $directory) {
            $appendIterator->append(self::getPhpFilesForDir($directory));
        }

        $filesInfo = [];
        foreach ($files as $file) {
            $filesInfo[(string) $file] = new \SplFileInfo($file);
        }

        $appendIterator->append(new \ArrayIterator($filesInfo));
        //$appendIterator->append(new \ArrayIterator(\array_flip($files)));

        return $appendIterator;
    }

    /**
     * @param string $directory
     * @return \Iterator|\SplFileInfo[]
     */
    private static function getPhpFilesForDir(string $directory): \Iterator
    {
        // If there is an error in the directory (might happen with dependencies), let's ignore that.
        if (!\is_dir($directory)) {
            return new \EmptyIterator();
        }
        $allFiles  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
        // TODO: it would be better to keep the values as a SplFileInfo since we have to:
        // check modification time
        // use SplFileInfo in iterator from better-reflection
        return new RegexIterator($allFiles, '/\.php$/i'/*, \RecursiveRegexIterator::GET_MATCH*/);
    }
}
