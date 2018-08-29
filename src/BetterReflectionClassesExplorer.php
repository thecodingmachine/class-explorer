<?php


namespace TheCodingMachine\ClassExplorer;


use phpDocumentor\Reflection\DocBlockFactoryInterface;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Identifier\IdentifierType;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\AutoloadSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\EvaledCodeSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\FileIteratorSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\MemoizingSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TheCodingMachine\ClassExplorer\Events\IdentifierNotFoundEvent;
use TheCodingMachine\ClassExplorer\Exceptions\ClassExplorerException;

class BetterReflectionClassesExplorer
{
    /*
     * Datastructure used as cache:
     *
     * [
     *   "files" => [
     *     "full/file/name.php" => [
     *       "mtime" => 25346554
     *       "classes" => [] // Classes, interfaces or traits, key is class name, value is irrelevent
     *     ]
     *   ],
     *   "classes": [
     *     "FQCN": [
     *       "type": "interface|class|trait",
     *       "internal" : true // Present if class is a system class like \Exception
     *       "implements": [] // Direct level (does not contain the interfaces extended by the interface implemented)
     *       "extends": [""] // The parents hierarchy
     *       "uses": [] // Only the traits for this class, not the parent classes.
     *       "dependencies": [] // classes, interfaces, traits implementing/extending/using this object => key is class/interface/trait name
     *       "path" => "" // Relative path to the file (or absolute path if not part of project)
     *     ]
     *   ]
     * ]
     */
    private $data;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    public function __construct(EventDispatcher $eventDispatcher, $data = ['files'=>[], 'classes'=>[]])
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->data = $data;
    }

    public function getClasses()
    {
        $rootPath = \ComposerLocator::getRootPath().'/';
        $data = $this->data;
        $phpFiles = \iterator_to_array(PackageSourceLocator::getVendorPhpFiles());

        // From the list of deleted files, let's remove the list of interfaces/classes/... that were in those files.
        // Also, let's flag for reloading the classes that need to be reloaded.
        $deletedFiles = $this->getDeletedFiles($phpFiles);

        $deletedClasses = [];
        foreach ($deletedFiles as $path => $definition) {
            $deletedClasses += $definition['classes'];
        }

        $toRefreshClasses = [];
        foreach ($deletedClasses as $deletedClass => $foo) {
            $toRefreshClasses += $data['classes'][$deletedClass]['dependencies'];
        }

        // A class cannot be "to refresh" if it is deleted also.
        $toRefreshClasses = \array_diff_key($toRefreshClasses, $deletedClasses);

        $modifiedPhpFiles = $this->getModifiedFiles($phpFiles);

        // Let's reset the files datastructure for modified files:
        foreach ($modifiedPhpFiles as $path => $def) {
            $data['files'][$path]['classes'] = [];
            $data['files'][$path]['mtime'] = \filemtime($path);
        }

        $betterReflection = new BetterReflection();
        $astLocator = $betterReflection->astLocator();

        // TODO: FileIteratorSourceLocator does not benefit from the MemoizingSourceLocator declared in $betterReflection->classReflector.
        $filesSourceLocator = new FileIteratorSourceLocator(new \ArrayIterator($modifiedPhpFiles), $astLocator);
        $reflector = $betterReflection->classReflector();
        /** @var ReflectionClass[] $classes */
        $classes = $filesSourceLocator->locateIdentifiersByType(
            $reflector,
            new IdentifierType(IdentifierType::IDENTIFIER_CLASS)
        );

        foreach ($toRefreshClasses as $toRefreshClass) {
            $classes[] = $reflector->reflect($toRefreshClass);
        }

        foreach ($classes as $class) {
            if (!isset($data['files'][$class->getFileName()])) {
                throw new ClassExplorerException('Unexpected missing key: class '.$class->getName().' is supposed to be part of file "'.$class->getFileName().'" but this file was not found in list of modified files"');
            }
            try {

                $data['files'][$class->getFileName()]['classes'][$class->getName()] = true;
                if ($class->isInterface()) {
                    $type = 'interface';
                } elseif ($class->isTrait()) {
                    $type = 'trait';
                } else {
                    $type = 'class';
                }
                $def = [
                    'type' => $type
                ];
                $extends = [];
                $parentClass = $class;
                while ($parentClass = $parentClass->getParentClass()) {
                    $extends[] = $parentClass->getName();
                }
                $def['extends'] = $extends;
                $def['implements'] = $class->getInterfaceNames();
                $def['uses'] = array_map(function($trait) { return $trait->getName(); }, $class->getTraits());
                $def['path'] = $this->getRelativePathIfAvailable($class->getFileName(), $rootPath);
            } catch (\Roave\BetterReflection\Reflector\Exception\IdentifierNotFound $e) {
                $this->eventDispatcher->dispatch(IdentifierNotFoundEvent::NAME, new IdentifierNotFoundEvent($e->getIdentifier()->getName()));
                continue;
            }

            $data['classes'][$class->getName()] = $def;
        }

        $data['classes'] = $this->generateDependenciesData($data['classes']);

        $this->data = $data;

        return $data;
    }

    /**
     * Returns the list of files that have disappeared.
     *
     * @param \SplFileInfo[] $files Typically the result of a call to PackageSourceLocator::getVendorPhpFiles casted to array
     * @return array[] Key: full path, Value: Same structure as $this->data['files'][]
     */
    private function getDeletedFiles(array $files): array
    {
        return \array_diff_key($this->data['files'], $files);
    }

    /**
     * Returns modified files OR new files.
     *
     * @param \SplFileInfo[] $files
     * @return \SplFileInfo[]
     */
    private function getModifiedFiles(array $files): array
    {
        $oldFiles = $this->data['files'];
        return \array_filter($files, function(\SplFileInfo $file, $path) use ($oldFiles) {
            return !isset($oldFiles[$path]) || (isset($oldFiles[$path]) && $oldFiles[$path]['mtime'] !== $file->getMTime());
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Returns the relative path to $rootPath or if $path is not part of $rootPath, the absolute path.
     *
     * @param string $path
     * @param string $rootPath supposed to end with '/'
     * @return string
     */
    private function getRelativePathIfAvailable(string $path, string $rootPath): string
    {
        if (strpos($path, $rootPath) === 0) {
            return substr($path, \strlen($rootPath));
        }
        return $path;
    }

    /**
     * @param array[] $classes The array of $data['classes']
     * @return array[]
     */
    private function generateDependenciesData(array $classes): array
    {
        foreach ($classes as &$class) {
            $class['dependencies'] = [];
        }

        foreach ($classes as $className => $class) {
            if (isset($class['internal'])) {
                continue;
            }
            foreach ($class['extends'] as $parent) {
                // If we extend from a root class (like \Exception)
                if (!isset($classes[$parent])) {
                    $classes[$parent] = [
                        'internal' => true,
                    ];
                }
                $classes[$parent]['dependencies'][] = $className;
            }

            foreach ($class['implements'] as $interface) {
                // If we extend from a root class (like \Exception)
                if (!isset($classes[$interface])) {
                    $classes[$interface] = [
                        'internal' => true,
                    ];
                }
                $classes[$interface]['dependencies'][] = $className;
            }

            foreach ($class['uses'] as $trait) {
                // If we extend from a root class (like \Exception)
                if (!isset($classes[$trait])) {
                    $classes[$trait] = [
                        'internal' => true,
                    ];
                }
                $classes[$trait]['dependencies'][] = $className;
            }

        }

        return $classes;
    }
}
