<?php


namespace TheCodingMachine\ClassExplorer\Glob;

use SplFileInfo;
use function array_keys;
use function chdir;
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
    /**
     * @var string
     */
    private $rootPath;
    /**
     * @var string|null
     */
    private $key;

    public function __construct(string $namespace, CacheInterface $cache, ?int $cacheTtl = null, ?ClassNameMapper $classNameMapper = null, bool $recursive = true, ?string $rootPath = null)
    {
        $this->namespace = $namespace;
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl;
        $this->classNameMapper = $classNameMapper;
        $this->recursive = $recursive;
        $this->rootPath = ($rootPath === null) ? __DIR__.'/../../../../../' : rtrim($rootPath, '/').'/';
    }

    /**
     * Returns an array of fully qualified class names.
     *
     * @return array<int,string>
     */
    public function getClasses(): array
    {
        return array_keys($this->getClassMap());
    }

    /**
     * Returns an array mapping the fully qualified class name to the file path.
     *
     * @return array<string,string>
     */
    public function getClassMap(): array
    {
        if ($this->key === null) {
            $this->key = 'globClassExplorer_'.hash('md4', $this->namespace.'___'.$this->recursive.$this->rootPath);
        }
        $classes = $this->cache->get($this->key);
        if ($classes === null) {
            $classes = $this->doGetClassMap();
            $this->cache->set($this->key, $classes, $this->cacheTtl);
        }
        return $classes;
    }

    /**
     * Returns an array of fully qualified class names, without the cache.
     *
     * @return array<string,string>
     */
    private function doGetClassMap(): array
    {
        $namespace = trim($this->namespace, '\\').'\\';
        if ($this->classNameMapper === null) {
            $this->classNameMapper = ClassNameMapper::createFromComposerFile();
        }
        $files = $this->classNameMapper->getPossibleFileNames($namespace.'XXX');

        $dirs = \array_map('dirname', $files);

        $oldCwd = getcwd();
        chdir($this->rootPath);
        $classes = [];
        foreach ($dirs as $dir) {
            $filesForDir = \iterator_to_array($this->getPhpFilesForDir($dir));
            $dirLen = \strlen($dir)+1;
            foreach ($filesForDir as $file) {
                // Trim the root directory name and the PHP extension
                $fileTrimPrefixSuffix = \substr($file, $dirLen, -4);
                $classes[$namespace.\str_replace('/', '\\', $fileTrimPrefixSuffix)] = $file->getRealPath();
            }
        }
        chdir($oldCwd);
        return $classes;
    }

    /**
     * @param string $directory
     * @return \Iterator<SplFileInfo>
     */
    private function getPhpFilesForDir(string $directory): \Iterator
    {
        if (!\is_dir($directory)) {
            return new \EmptyIterator();
        }
        if ($this->recursive) {
            $allFiles  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
            $iterator = new RegexIterator($allFiles, '/\.php$/i'/*, \RecursiveRegexIterator::GET_MATCH*/);
        } else {
            $iterator = new GlobIterator($directory.'/*.php');
        }
        return $iterator;
    }
}
