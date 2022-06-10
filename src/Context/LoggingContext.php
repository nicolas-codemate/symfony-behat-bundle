<?php

namespace Elbformat\SymfonyBehatBundle\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Elbformat\SymfonyBehatBundle\Helper\ArrayDeepCompare;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Test interactions with the logger service.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class LoggingContext implements Context
{
    protected KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * As we use a testHandler, logs are not available to the output.
     * So we will provide them here, if a scenario fails.
     *
     * @AfterScenario
     */
    public function dumpLog(AfterScenarioScope $event): void
    {
        if ($event->getTestResult()
            ->isPassed()) {
            return;
        }
        $this->printLogs();
    }

    /**
     * @Then the :log logfile contains a(n) :level entry :text
     */
    public function theLogfileContainsAnEntry(string $log, string $level, string $text, ?TableNode $table = null, bool $dumpLogs = true): void
    {
        $logHandler = $this->getLogHandler($log);
        $levelNum = Logger::toMonologLevel($level);
        if (!$logHandler->hasRecordThatContains($text, $levelNum)) {
            if ($dumpLogs) {
                $this->printLogs($levelNum);
            }
            throw new \DomainException('Log entry not found.');
        }
        if (null === $table) {
            return;
        }
        $tableRows = $table->getRowsHash();
        if (!$logHandler->hasRecordThatPasses(function ($entry) use ($text, $tableRows) {
            // Message differs
            if ($entry['message'] !== $text) {
                return false;
            }

            // Check context
            foreach ($tableRows as $key => $val) {
                $foundVal = $entry['context'][$key] ?? null;

                // Context missing
                if (null === $foundVal) {
                    return false;
                }

                // Regex compare
                if (strpos($val, '~') === 0 && preg_match('/'.preg_quote(substr($val, -1), '/').'/', $foundVal)) {
                    continue;
                }

                // Simple Compare
                if ($foundVal == $val) {
                    continue;
                }

                // Array/Json compare
                if (\is_array($foundVal)) {
                    $valArr = json_decode($val, true, 512, JSON_THROW_ON_ERROR);
                    if (null === $valArr) {
                        throw new \DomainException(json_last_error_msg());
                    }

                    $dc = new ArrayDeepCompare();
                    if ($dc->arrayEquals($foundVal, $valArr)) {
                        continue;
                    }
                    echo $dc->getDifference();

                    return false;
                }

                return false;
            }

            return true;
        }, $levelNum)) {
            if ($dumpLogs) {
                $this->printLogs($levelNum);
            }
            throw new \DomainException('Log entry found, but with different context.');
        }
    }

    /**
     * @Then the :log logfile doesn't contain any :level entries
     */
    public function theLogfileDoesntContainAnyEntries(string $log, string $level): void
    {
        $logHandler = $this->getLogHandler($log);
        if ($logHandler->hasRecords($level)) {
            $levelNum = Logger::toMonologLevel($level);
            $this->printLogs($levelNum);
            throw new \DomainException('Log entries found');
        }
    }

    /**
     * @Then the :log logfile doesn't contain a(n) :level entry :text
     */
    public function theLogfileDoesntContainAnEntry(string $log, string $level, string $text, ?TableNode $table = null): void
    {
        try {
            $this->theLogfileContainsAnEntry($log, $level, $text, $table, false);
        } catch (\DomainException $t) {
            return;
        }
        $this->printLogs();
        throw new \DomainException('Entry found');
    }


    protected function getLogHandler(string $log = 'main'): TestHandler
    {
        return $this->kernel->getContainer()->get('monolog.handler.'.$log);
    }

    public function printLogs(int $minLevel = Logger::WARNING): void
    {
        $logHandler = $this->getLogHandler();
        $records = $logHandler->getRecords();
        foreach (array_reverse($records) as $record) {
            // Skip everything that isn't at least a the required level
            if ($record['level'] < $minLevel) {
                continue;
            }
            echo $record['formatted'];
        }
    }
}
