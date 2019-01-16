<?php


namespace TheCodingMachine\ClassExplorer\Glob;

use DirectoryIterator;
use GlobIterator;
use Mouf\Composer\ClassNameMapper;
use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use TheCodingMachine\ClassExplorer\ClassExplorerInterface;
use function var_dump;

/**
 * Returns a set of classes by analyzing the PHP files in a directory.
 * The directory is located thanks to Composer PSR-0 or PSR-4 autoloaders.
 *
 * This explorer:
 *
 * - looks only for classes in YOUR project (not in the vendor directory)
 * - can return classes of a given namespace only
 * - assumes that if a file exists in a PSR-0 or PSR-4 directory, the class is available (assumes the file respects PSR-1)
 * - makes no attempt at autoloading the class
 * - is pretty fast
 */
class GlobClassExplorer implements ClassExplorerInterface
{
    /**
     * @var string
     */
    private $namespace;
    /**
     * @var CacheInterface
     */
    private $cache;
    /**
     * @var int|null
     */
    private $cacheTtl;
    /**
     * @var ClassNameMapper|null
     */
    private $classNameMapper;
    /**
     * @var bool
     */
    private $recursive;

    public function __construct(string $namespace, CacheInterface $cache, ?int $cacheTtl = null, ?ClassNameMapper $classNameMapper = null, bool $recursive = true)
    {
        $this->namespace = $namespace;
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl;
        $this->classNameMapper = $classNameMapper;
        $this->recursive = $recursive;
    }

    /**
     * Returns an array of fully qualified class names.
     *
     * @return string[]
     */
    public function getClasses(): array
    {
        $key = 'globClassExplorer_'.$this->namespace;
        $classes = $this->cache->get($key);
        if ($classes === null) {
            $classes = $this->doGetClasses();
            $this->cache->set($key, $classes, $this->cacheTtl);
        }
        return $classes;
    }

    /**
     * Returns an array of fully qualified class names, without the cache.
     *
     * @return string[]
     */
    private function doGetClasses(): array
    {
        $namespace = trim($this->namespace, '\\').'\\';
        if ($this->classNameMapper === null) {
            $this->classNameMapper = ClassNameMapper::createFromComposerFile();
        }
        $files = $this->classNameMapper->getPossibleFileNames($namespace.'XXX');

        $dirs = \array_map('dirname', $files);

        $classes = [];
        foreach ($dirs as $dir) {
            $filesForDir = \iterator_to_array($this->getPhpFilesForDir($dir));
            $dirLen = \strlen($dir)+1;
            foreach ($filesForDir as $file) {
                // Trim the root directory name and the PHP extension
                $fileTrimPrefixSuffix = \substr($file, $dirLen, -4);
                $classes[] = $namespace.\str_replace('/', '\\', $fileTrimPrefixSuffix);
            }
        }
        return $classes;
    }

    /**
     * @param string $directory
     * @return \Iterator
     */
    private function getPhpFilesForDir(string $directory): \Iterator
    {
        if (!\is_dir($directory)) {
            return new \EmptyIterator();
        }
        if ($this->recursive) {
            $allFiles  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
            return new RegexIterator($allFiles, '/\.php$/i'/*, \RecursiveRegexIterator::GET_MATCH*/);
        } else {
            return new GlobIterator($directory.'/*.php');
        }
    }
}
