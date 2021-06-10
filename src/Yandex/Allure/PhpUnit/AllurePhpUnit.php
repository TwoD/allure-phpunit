<?php

namespace Yandex\Allure\PhpUnit;

use Behat\Testwork\EventDispatcher\Event\AfterTested;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\DataProviderTestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\Runner\AfterIncompleteTestHook;
use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\AfterRiskyTestHook;
use PHPUnit\Runner\AfterSkippedTestHook;
use PHPUnit\Runner\AfterSuccessfulTestHook;
use PHPUnit\Runner\AfterTestErrorHook;
use PHPUnit\Runner\AfterTestFailureHook;
use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeFirstTestHook;
use PHPUnit\Runner\BeforeTestHook;
use Throwable;
use Yandex\Allure\Adapter\Allure;
use Yandex\Allure\Adapter\Annotation;
use Yandex\Allure\Adapter\Event\TestCaseBrokenEvent;
use Yandex\Allure\Adapter\Event\TestCaseCanceledEvent;
use Yandex\Allure\Adapter\Event\TestCaseFailedEvent;
use Yandex\Allure\Adapter\Event\TestCaseFinishedEvent;
use Yandex\Allure\Adapter\Event\TestCasePendingEvent;
use Yandex\Allure\Adapter\Event\TestCaseStartedEvent;
use Yandex\Allure\Adapter\Event\TestSuiteFinishedEvent;
use Yandex\Allure\Adapter\Event\TestSuiteStartedEvent;
use Yandex\Allure\Adapter\Model;

class AllurePhpUnit implements AfterTestErrorHook, AfterTestFailureHook, AfterIncompleteTestHook, AfterRiskyTestHook, AfterSkippedTestHook, BeforeFirstTestHook, AfterLastTestHook, BeforeTestHook, AfterSuccessfulTestHook, AfterTestHook
{

    private $uuid;

    /**
     * Annotations that should be ignored by the annotations parser (especially PHPUnit annotations)
     * @var array
     */
    private $ignoredAnnotations = [
        'after', 'afterClass', 'backupGlobals', 'backupStaticAttributes', 'before', 'beforeClass',
        'codeCoverageIgnore', 'codeCoverageIgnoreStart', 'codeCoverageIgnoreEnd', 'covers',
        'coversDefaultClass', 'coversNothing', 'dataProvider', 'depends', 'expectedException',
        'expectedExceptionCode', 'expectedExceptionMessage', 'group', 'large', 'medium',
        'preserveGlobalState', 'requires', 'runTestsInSeparateProcesses', 'runInSeparateProcess',
        'small', 'test', 'testdox', 'ticket', 'uses',
    ];

    /**
     * @param string $outputDirectory XML files output directory
     * @param bool $deletePreviousResults Whether to delete previous results on return
     * @param array $ignoredAnnotations Extra annotations to ignore in addition to standard PHPUnit annotations
     */
    public function __construct(
        $outputDirectory,
        $deletePreviousResults = false,
        array $ignoredAnnotations = []
    ) {
        if (!isset($outputDirectory)){
            $outputDirectory = 'build' . DIRECTORY_SEPARATOR . 'allure-results';
        }

        $this->prepareOutputDirectory(getcwd() . DIRECTORY_SEPARATOR . $outputDirectory, $deletePreviousResults);

        // Add standard PHPUnit annotations
        Annotation\AnnotationProvider::addIgnoredAnnotations($this->ignoredAnnotations);
        // Add custom ignored annotations
        Annotation\AnnotationProvider::addIgnoredAnnotations($ignoredAnnotations);
    }

    public function prepareOutputDirectory($outputDirectory, $deletePreviousResults)
    {
        if (!file_exists($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }
        if ($deletePreviousResults) {
            $files = scandir($outputDirectory);
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_null(Model\Provider::getOutputDirectory())) {
            Model\Provider::setOutputDirectory($outputDirectory);
        }
    }

    public function executeAfterTestError(string $test, string $message, float $time): void {
      $event = new TestCaseBrokenEvent();
      Allure::lifecycle()->fire($event->withMessage($test . ' -> ' . $message));
    }

    public function executeAfterTestFailure(string $test, string $message, float $time): void {
      $event = new TestCaseFailedEvent();
      Allure::lifecycle()->fire($event->withMessage($test . ' -> ' . $message));
    }

    public function executeAfterIncompleteTest(string $test, string $message, float $time): void {
      $event = new TestCasePendingEvent();
      Allure::lifecycle()->fire($event->withMessage($test . ' -> ' . $message));
    }

    public function executeAfterRiskyTest(string $test, string $message, float $time): void {
      $this->executeAfterIncompleteTest($test, $message, $time);
    }

    public function executeAfterSkippedTest(string $test, string $message, float $time): void {
      $event = new TestCaseCanceledEvent();
      Allure::lifecycle()->fire($event->withMessage($test . ' -> ' . $message));
    }

    public function executeBeforeFirstTest(): void {
      $event = new TestSuiteStartedEvent('PHPUnit');
      $this->uuid = $event->getUuid();
      Allure::lifecycle()->fire($event);
    }

    public function executeAfterLastTest(): void {
      $event = new TestSuiteFinishedEvent($this->uuid);
      Allure::lifecycle()->fire($event);
    }

    public function executeBeforeTest(string $test): void {
      $event = new TestCaseStartedEvent($this->uuid, $test);
      Allure::lifecycle()->fire($event);
    }

    public function executeAfterSuccessfulTest(string $test, float $time): void {
      $event = new TestCaseFinishedEvent();
      Allure::lifecycle()->fire($event);
    }

    public function executeAfterTest(string $test, float $time): void {
      $event = new TestCaseFinishedEvent();
      Allure::lifecycle()->fire($event);
    }

}
