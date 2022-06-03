<?php

namespace Elbformat\SymfonyBehatBundle\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Monolog\Handler\Handler;
use Monolog\Handler\TestHandler;

/**
 * Test interactions with the logger service.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class LoggingContext implements Context
{
    use DiffTrait;

    /** @var array<string, Handler> */
    protected $logHandler = [];

    protected $logger;

    protected RequestContext $requestContext;

    /** @BeforeScenario */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $environment = $scope->getEnvironment();
        $this->requestContext = $environment->getContext(RequestContext::class);
    }

    /**
     * @Then the :log logfile should not contain any entries
     *
     * @param string $log Name of the log
     *
     * @throws \Exception
     */
    public function logfileContainsNoEntries($log)
    {
        $logHandler = $this->getLogHandler($log);
        $records = $logHandler->getRecords();
        if ($records) {
            throw new \Exception(var_export($records[0], true));
        }
    }

    /**
     * @Then the :log logfile must contain a(n) :level entry :text
     *
     * @param string $log   Name of the log
     * @param string $level Log Level
     * @param string $text  Text to be contained
     *
     * @throws \Exception
     */
    public function logfileContainsEntry($log, $level, $text)
    {
        $logHandler = $this->getLogHandler($log);
        $levelNum = \constant('Monolog\Logger::' . strtoupper($level));
        if ($logHandler->hasRecordThatContains($text, $levelNum)) {
            return;
        }
        $errMsg = sprintf("Log entry '%s' not found.", $text);

        $records = $logHandler->getRecords();
        foreach (array_reverse($records) as $record) {
            if ($record['level'] === $levelNum) {
                $errMsg .= sprintf("\nLast %s message was '%s'", $level, $record['message']);
                break;
            }
        }
        $lastRecord = end($records);
        if (isset($lastRecord['message'])) {
            $errMsg .= sprintf("\nLast message was '%s'", $lastRecord['message']);
        }
        throw new \Exception($errMsg);
    }

    /**
     * @Then the :log logfile must not contain a(n) :level entry :text
     *
     * @param string $log   Name of the log
     * @param string $level Log Level
     * @param string $text  Text to be contained
     *
     * @throws \Exception
     */
    public function logfileNotContainsEntry($log, $level, $text)
    {
        try {
            $this->logfileContainsEntry($log, $level, $text);
        } catch (\Exception $t) {
            return;
        }
        $logHandler = $this->getLogHandler($log);
        $records = $logHandler->getRecords();
        foreach ($records as $record) {
            if ($record['message'] === $text) {
                throw new \Exception(var_export($record, true));
            }
        }
    }

    /**
     * @Then the :log logfile must contain a(n) :level entry :text with context:
     *
     * @param string    $log   Name of the log
     * @param string    $level Log Level
     * @param string    $text  Text to be contained
     * @param TableNode $table Expected context
     *
     * @throws \Exception
     */
    public function logfileContainsEntryWithContext($log, $level, $text, TableNode $table)
    {
        // Check message itself first
        $this->logfileContainsEntry($log, $level, $text);

        $nearest = null;
        $callable = function ($entry) use ($text, $table, &$nearest) {
            // Message differs
            if ($entry['message'] !== $text) {
                return false;
            }

            // At least the message is ok
            $nearest = $entry;

            // Check context
            foreach ($table->getRowsHash() as $key => $val) {
                // Context missing
                if (!isset($entry['context'][$key])) {
                    return false;
                }

                // Perform a regex compare
                if (strpos($val, '~') === 0 && preg_match('/' . preg_quote(substr($val, -1), '/') . '/', $entry['context'][$key])) {
                    continue;
                }

                if ($entry['context'][$key] == $val) {
                    continue;
                }

                if (\is_array($entry['context'][$key])) {
                    $valArr = json_decode($val, true);
                    if (null === $valArr) {
                        echo json_last_error_msg();

                        return false;
                    }

                    try {
                        $this->arrayEquals($entry['context'][$key], $valArr);
                        continue;
                    } catch (\DomainException $e) {
                        echo $e->getMessage();

                        return false;
                    }
                }

                return false;
            }

            return true;
        };

        $levelNum = \constant('Monolog\Logger::' . strtoupper($level));

        $logHandler = $this->getLogHandler($log);
        if (!$logHandler->hasRecordThatPasses($callable, $levelNum)) {
            $errMsg = sprintf("Log entry '%s' with given context not found.", $text);

            // Print message with same text but different context
            if ($nearest) {
                $errMsg .= "\nSame message with different context found:";
                $errMsg .= sprintf("\n%s", var_export($nearest['context'], true));
            }

            throw new \Exception($errMsg);
        }
    }

    public function dumpLog(): void
    {
        $logHandler = $this->getLogHandler();
        $records = $logHandler->getRecords();
        foreach (array_reverse($records) as $record) {
            echo $record['formatted'];
        }
    }

    protected function getLogHandler(string $log = 'main'): TestHandler
    {
        return $this->requestContext->getInternalContainer()->get('monolog.handler.' . $log);
    }
}
