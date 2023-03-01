<?php

namespace Elbformat\SymfonyBehatBundle\Tests\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Testwork\Hook\Scope\AfterTestScope;
use Behat\Testwork\Tester\Result\TestResult;
use Elbformat\SymfonyBehatBundle\Context\LoggingContext;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

class LoggingContextTest extends TestCase
{
    protected ?LoggingContext $loggingContext = null;
    protected ?KernelInterface $kernel = null;
    protected ?ContainerInterface $container = null;
    protected ?TestHandler $handler = null;
    protected ?FormatterInterface $formatter = null;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(Kernel::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->handler = new TestHandler();
        $this->formatter = $this->createMock(FormatterInterface::class);
        $this->formatter->method('format')->willReturnCallback(function ($record) {
            return $record['message'];
        });
        $this->handler->setFormatter($this->formatter);
        $this->kernel->method('getContainer')->willReturn($this->container);
        $this->loggingContext = new LoggingContext($this->kernel);
    }

    public function testDumpLog(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $this->handle(100, 'ignore me');
        $event = $this->createMock(AfterTestScope::class);
        $testResult = $this->createMock(TestResult::class);
        $testResult->method('isPassed')->willReturn(false);
        $event->expects($this->once())->method('getTestResult')->willReturn($testResult);
        $this->loggingContext->dumpLog($event);
    }

    public function testDumpLogPassed(): void
    {
        $event = $this->createMock(AfterTestScope::class);
        $testResult = $this->createMock(TestResult::class);
        $testResult->expects($this->once())->method('isPassed')->willReturn(true);
        $event->expects($this->once())->method('getTestResult')->willReturn($testResult);
        $this->loggingContext->dumpLog($event);
    }

    public function testTheLogfileContainsAnEntry(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $this->formatter->expects($this->once())->method('format');
        $this->handle(400, 'Hello World');
        $this->loggingContext->theLogfileContainsAnEntry('main', 'error', 'Hello World');
    }

    public function testTheLogfileContainsAnEntryFail(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $this->handle(400, 'Bye World');
        $this->expectExceptionMessage('Log entry not found.');
        $this->loggingContext->theLogfileContainsAnEntry('main', 'error', 'Hello World');
    }

    public function testTheLogfileContainsAnEntryWithContext(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $record = [
            'level' => '400',
            'message' => 'Hello World',
            'context' => ['hello' => 'world'],
        ];
        $this->formatter->expects($this->once())->method('format');
        $this->handle(400, 'Hello World', ['hello' => 'world']);
        $tableData = [
            0 => ['hello', 'world'],
        ];
        $this->loggingContext->theLogfileContainsAnEntry('main', 'error', 'Hello World', new TableNode($tableData));
    }

    public function testTheLogfileContainsAnEntryWithContextRegex(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $record = [
            'level' => '400',
            'message' => 'Hello World',
            'context' => ['hello' => 'world'],
        ];
        $this->formatter->expects($this->once())->method('format');
        $this->handler->handle($record);
        $tableData = [
            0 => ['hello', '~or'],
        ];
        $this->loggingContext->theLogfileContainsAnEntry('main', 'error', 'Hello World', new TableNode($tableData));
    }

    public function testTheLogfileContainsAnEntryWithContextJson(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $record = [
            'level' => '400',
            'message' => 'Hello World',
            'context' => ['obj' => ['hello' => 'world']],
        ];
        $this->formatter->expects($this->once())->method('format');
        $this->handler->handle($record);
        $tableData = [
            0 => ['obj', '{"hello":"world"}'],
        ];
        $this->loggingContext->theLogfileContainsAnEntry('main', 'error', 'Hello World', new TableNode($tableData));
    }

    public function testTheLogfileContainsAnEntryWithContextFail(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $record = [
            'level' => '400',
            'message' => 'Hello World',
            'context' => ['hello' => 'mars'],
        ];
        $this->handler->handle($record);
        $record = [
            'level' => '400',
            'message' => 'Hello World',
        ];
        $this->handler->handle($record);
        $record = [
            'level' => '400',
            'message' => 'Bye World',
            'context' => ['hello' => 'world'],
        ];
        $this->handler->handle($record);
        $this->expectExceptionMessage('Log entry found, but with different context.');
        $tableData = [
            0 => ['hello', 'world'],
        ];
        $this->loggingContext->theLogfileContainsAnEntry('main', 'error', 'Hello World', new TableNode($tableData));
    }

    public function testTheLogfileContainsAnEntryWithContextJsonFail(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $this->handle(400, 'Hello World', ['obj' => ['hello' => 'not']]);
        $this->expectExceptionMessage('Log entry found, but with different context.');
        $tableData = [
            0 => ['obj', '{"hello":"world"}'],
        ];
        $this->loggingContext->theLogfileContainsAnEntry('main', 'error', 'Hello World', new TableNode($tableData));
    }

    public function testTheLogfileDoesntContainAnyEntries(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $this->formatter->expects($this->once())->method('format');
        $this->handle(100, 'Hello World');
        $this->loggingContext->theLogfileDoesntContainAnyEntries('main', 'error');
    }

    public function testTheLogfileDoesntContainAnyEntriesFail(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $this->handle(400, 'Hello World');
        $this->expectExceptionMessage('Log entries found');
        $this->loggingContext->theLogfileDoesntContainAnyEntries('main', 'error');
    }

    public function testTheLogfileDoesntContainAnEntry(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $this->formatter->expects($this->once())->method('format');
        $this->handle(100, 'Hello World');
        $this->loggingContext->theLogfileDoesntContainAnEntry('main', 'error', ' Hello world');
    }

    public function testTheLogfileDoesntContainAnEntryFail(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn($this->handler);
        $this->handle(400, 'Hello World');
        $this->expectExceptionMessage('Entry found');
        $this->loggingContext->theLogfileDoesntContainAnEntry('main', 'error', 'Hello World');
    }

    public function testGetLogHandlerFails(): void
    {
        $this->container->method('get')->with('monolog.handler.main')->willReturn(null);
        $this->expectExceptionMessage('No monolog TestHandler found named monolog.handler.main. Is it public?');
        $this->loggingContext->printLogs();
    }

    protected function handle(int $level, string $message, array $context = []): void
    {
        // Preparation for monolog 3.x
        if (class_exists('\\Monolog\\LogRecord')) {
            $record = new LogRecord(new \DateTimeImmutable(), 'main', Level::fromValue($level), $message, $context);
        } else {
            $record = [
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ];
        }
        $this->handler->handle($record);
    }
}
