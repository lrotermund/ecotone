<?php

namespace SimplyCodedSoftware\IntegrationMessaging\Config\Annotation;

use Doctrine\Common\Annotations\Reader;
use SimplyCodedSoftware\IntegrationMessaging\Config\ConfigurationException;

/**
 * Class ClassLocator
 * @package SimplyCodedSoftware\IntegrationMessaging\Config\Annotation
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class FileSystemClassLocator implements ClassLocator {

    const INTEGRATION_MESSAGING_NAMESPACE = 'IntegrationMessaging';
    const SIMPLY_CODED_SOFTWARE_NAMESPACE = 'SimplyCodedSoftware';

    private const FILE_EXTENSION = '.php';
    /**
     * @var array|string[]
     */
    private $projectClasses;
    /**
     * @var string[]
     */
    private $namespacesToUse;
    /**
     * @var Reader
     */
    private $annotationReader;

    /**
     * @param Reader $annotationReader
     * @param string[] $paths One or multiple paths where mapping documents can be found.
     * @param array|string[] $namespaces
     */
    public function __construct(Reader $annotationReader, array $paths, array $namespaces)
    {
        $this->annotationReader = $annotationReader;
        $this->namespacesToUse = $namespaces;
        $this->init($paths);
    }

    /**
     * @inheritdoc
     */
    public function getAllClasses() : array
    {
        return $this->projectClasses;
    }

    /**
     * @inheritdoc
     */
    public function getAllClassesWithAnnotation(string $annotationName) : array
    {
        $classesWithAnnotations = [];
        foreach ($this->getAllClasses() as $class) {
            $classReflection = new \ReflectionClass($class);

            $classAnnotation = $this->annotationReader->getClassAnnotation($classReflection, $annotationName);

            if ($classAnnotation) {
                $classesWithAnnotations[] = $class;
            }
        }

        return $classesWithAnnotations;
    }

    /**
     * {@inheritDoc}
     */
    private function init(array $paths)
    {
        $classes = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                throw ConfigurationException::create("There is no path: {$path}");
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                $fileName = $file->getBasename(self::FILE_EXTENSION);
                $filePath = $file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename();

                if ($fileName == $file->getBasename()) {
                    continue;
                }

                $classContent = file_get_contents($filePath);
                preg_match_all("#\snamespace([^\n]*);#", $classContent, $results);
                $namespace = isset($results[1][0]) ? trim($results[1][0]) : "";

                foreach ($this->namespacesToUse as $namespaceToUse) {
                    if (substr($namespace, 0, strlen($namespaceToUse)) !== $namespaceToUse) {
                        continue;
                    }

                    $classes[] = trim($namespace) . '\\' . $fileName;
                }
            }
        }

        $this->projectClasses = array_unique($classes);
    }

}