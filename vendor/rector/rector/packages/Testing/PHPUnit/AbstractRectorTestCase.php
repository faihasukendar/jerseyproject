<?php

declare (strict_types=1);
namespace Rector\Testing\PHPUnit;

use RectorPrefix202308\Illuminate\Container\RewindableGenerator;
use Iterator;
use RectorPrefix202308\Nette\Utils\FileSystem;
use RectorPrefix202308\Nette\Utils\Strings;
use PHPStan\Analyser\NodeScopeResolver;
use PHPUnit\Framework\ExpectationFailedException;
use Rector\Core\Application\ApplicationFileProcessor;
use Rector\Core\Autoloading\AdditionalAutoloader;
use Rector\Core\Autoloading\BootstrapFilesIncluder;
use Rector\Core\Configuration\ConfigurationFactory;
use Rector\Core\Configuration\Option;
use Rector\Core\Configuration\Parameter\SimpleParameterProvider;
use Rector\Core\Configuration\RenamedClassesDataCollector;
use Rector\Core\Contract\Rector\PhpRectorInterface;
use Rector\Core\Contract\Rector\RectorInterface;
use Rector\Core\Exception\ShouldNotHappenException;
use Rector\Core\PhpParser\NodeTraverser\RectorNodeTraverser;
use Rector\NodeTypeResolver\Reflection\BetterReflection\SourceLocatorProvider\DynamicSourceLocatorProvider;
use Rector\Testing\Contract\RectorTestInterface;
use Rector\Testing\Fixture\FixtureFileFinder;
use Rector\Testing\Fixture\FixtureFileUpdater;
use Rector\Testing\Fixture\FixtureSplitter;
abstract class AbstractRectorTestCase extends \Rector\Testing\PHPUnit\AbstractLazyTestCase implements RectorTestInterface
{
    /**
     * @var \Rector\NodeTypeResolver\Reflection\BetterReflection\SourceLocatorProvider\DynamicSourceLocatorProvider
     */
    private $dynamicSourceLocatorProvider;
    /**
     * @var \Rector\Core\Application\ApplicationFileProcessor
     */
    private $applicationFileProcessor;
    /**
     * @var string|null
     */
    private $inputFilePath;
    /**
     * @var array<string, true>
     */
    private static $cacheByRuleAndConfig = [];
    /**
     * Restore default parameters
     */
    public static function tearDownAfterClass() : void
    {
        SimpleParameterProvider::setParameter(Option::AUTO_IMPORT_NAMES, \false);
        SimpleParameterProvider::setParameter(Option::AUTO_IMPORT_DOC_BLOCK_NAMES, \false);
        SimpleParameterProvider::setParameter(Option::REMOVE_UNUSED_IMPORTS, \false);
        SimpleParameterProvider::setParameter(Option::IMPORT_SHORT_CLASSES, \true);
        SimpleParameterProvider::setParameter(Option::INDENT_CHAR, ' ');
        SimpleParameterProvider::setParameter(Option::INDENT_SIZE, 4);
    }
    protected function setUp() : void
    {
        $this->includePreloadFilesAndScoperAutoload();
        @\ini_set('memory_limit', '-1');
        $configFile = $this->provideConfigFilePath();
        // cleanup all registered rectors, so you can use only the new ones
        $rectorConfig = self::getContainer();
        // boot once for config + test case to avoid booting again and again for every test fixture
        $cacheKey = \sha1($configFile . static::class);
        if (!isset(self::$cacheByRuleAndConfig[$cacheKey])) {
            // reset class rename
            $renamedClassesDataCollector = $rectorConfig->make(RenamedClassesDataCollector::class);
            $renamedClassesDataCollector->reset();
            $this->forgetRectorsRules();
            // this has to be always empty, so we can add new rules with their configuration
            $this->assertEmpty($rectorConfig->tagged(RectorInterface::class));
            $this->assertEmpty($rectorConfig->tagged(PhpRectorInterface::class));
            $this->bootFromConfigFiles([$configFile]);
            $phpRectorsGenerator = $rectorConfig->tagged(PhpRectorInterface::class);
            if ($phpRectorsGenerator instanceof RewindableGenerator) {
                $phpRectors = \iterator_to_array($phpRectorsGenerator->getIterator());
            } else {
                // no rules at all, e.g. in case of only post rector run
                $phpRectors = [];
            }
            $rectorNodeTraverser = $rectorConfig->make(RectorNodeTraverser::class);
            $rectorNodeTraverser->refreshPhpRectors($phpRectors);
            // store cache
            self::$cacheByRuleAndConfig[$cacheKey] = \true;
            self::$cacheByRuleAndConfig[$cacheKey] = \true;
        }
        $this->applicationFileProcessor = $this->make(ApplicationFileProcessor::class);
        $this->dynamicSourceLocatorProvider = $this->make(DynamicSourceLocatorProvider::class);
        /** @var AdditionalAutoloader $additionalAutoloader */
        $additionalAutoloader = $this->make(AdditionalAutoloader::class);
        $additionalAutoloader->autoloadPaths();
        /** @var BootstrapFilesIncluder $bootstrapFilesIncluder */
        $bootstrapFilesIncluder = $this->make(BootstrapFilesIncluder::class);
        $bootstrapFilesIncluder->includeBootstrapFiles();
    }
    protected function tearDown() : void
    {
        // clear temporary file
        if (\is_string($this->inputFilePath)) {
            FileSystem::delete($this->inputFilePath);
        }
    }
    /**
     * @return Iterator<<string>>
     */
    protected static function yieldFilesFromDirectory(string $directory, string $suffix = '*.php.inc') : Iterator
    {
        return FixtureFileFinder::yieldDirectory($directory, $suffix);
    }
    protected function isWindows() : bool
    {
        return \strncasecmp(\PHP_OS, 'WIN', 3) === 0;
    }
    protected function doTestFile(string $fixtureFilePath) : void
    {
        // prepare input file contents and expected file output contents
        $fixtureFileContents = FileSystem::read($fixtureFilePath);
        if (FixtureSplitter::containsSplit($fixtureFileContents)) {
            // changed content
            [$inputFileContents, $expectedFileContents] = FixtureSplitter::splitFixtureFileContents($fixtureFileContents);
        } else {
            // no change
            $inputFileContents = $fixtureFileContents;
            $expectedFileContents = $fixtureFileContents;
        }
        $inputFilePath = $this->createInputFilePath($fixtureFilePath);
        // to remove later in tearDown()
        $this->inputFilePath = $inputFilePath;
        if ($fixtureFilePath === $inputFilePath) {
            throw new ShouldNotHappenException('Fixture file and input file cannot be the same: ' . $fixtureFilePath);
        }
        // write temp file
        FileSystem::write($inputFilePath, $inputFileContents);
        $this->doTestFileMatchesExpectedContent($inputFilePath, $expectedFileContents, $fixtureFilePath);
    }
    private function includePreloadFilesAndScoperAutoload() : void
    {
        if (\file_exists(__DIR__ . '/../../../preload.php')) {
            if (\file_exists(__DIR__ . '/../../../vendor')) {
                require_once __DIR__ . '/../../../preload.php';
                // test case in rector split package
            } elseif (\file_exists(__DIR__ . '/../../../../../../vendor')) {
                require_once __DIR__ . '/../../../preload-split-package.php';
            }
        }
        if (\file_exists(__DIR__ . '/../../../vendor/scoper-autoload.php')) {
            require_once __DIR__ . '/../../../vendor/scoper-autoload.php';
        }
    }
    private function doTestFileMatchesExpectedContent(string $originalFilePath, string $expectedFileContents, string $fixtureFilePath) : void
    {
        SimpleParameterProvider::setParameter(Option::SOURCE, [$originalFilePath]);
        $changedContent = $this->processFilePath($originalFilePath);
        $fixtureFilename = \basename($fixtureFilePath);
        $failureMessage = \sprintf('Failed on fixture file "%s"', $fixtureFilename);
        try {
            $this->assertSame($expectedFileContents, $changedContent, $failureMessage);
        } catch (ExpectationFailedException $exception) {
            FixtureFileUpdater::updateFixtureContent($originalFilePath, $changedContent, $fixtureFilePath);
            // if not exact match, check the regex version (useful for generated hashes/uuids in the code)
            $this->assertStringMatchesFormat($expectedFileContents, $changedContent, $failureMessage);
        }
    }
    private function processFilePath(string $filePath) : string
    {
        $this->dynamicSourceLocatorProvider->setFilePath($filePath);
        // needed for PHPStan, because the analyzed file is just created in /temp - need for trait and similar deps
        /** @var NodeScopeResolver $nodeScopeResolver */
        $nodeScopeResolver = $this->make(NodeScopeResolver::class);
        $nodeScopeResolver->setAnalysedFiles([$filePath]);
        /** @var ConfigurationFactory $configurationFactory */
        $configurationFactory = $this->make(ConfigurationFactory::class);
        $configuration = $configurationFactory->createForTests([$filePath]);
        $this->applicationFileProcessor->processFiles([$filePath], $configuration);
        return FileSystem::read($filePath);
    }
    private function createInputFilePath(string $fixtureFilePath) : string
    {
        $inputFileDirectory = \dirname($fixtureFilePath);
        // remove ".inc" suffix
        if (\substr_compare($fixtureFilePath, '.inc', -\strlen('.inc')) === 0) {
            $trimmedFixtureFilePath = Strings::substring($fixtureFilePath, 0, -4);
        } else {
            $trimmedFixtureFilePath = $fixtureFilePath;
        }
        $fixtureBasename = \pathinfo($trimmedFixtureFilePath, \PATHINFO_BASENAME);
        return $inputFileDirectory . '/' . $fixtureBasename;
    }
}
