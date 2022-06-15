<?php

declare(strict_types=1);

namespace Elbformat\SymfonyBehatBundle\Context;

use Behat\Behat\Context\Context;
use DomainException;
use Elbformat\SymfonyBehatBundle\Application\ApplicationFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Interaction with a symfony console command.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class CommandContext implements Context
{
    private ?string $output = null;
    private ?int $returnCode= null;
    protected ApplicationFactory $appFactory;

    public function __construct(ApplicationFactory $appFactory)
    {
        $this->appFactory = $appFactory;
    }

    /**
     * @BeforeScenario
     */
    public function resetDocumentIdStack(): void
    {
        $this->output = null;
        $this->returnCode = null;
    }

    /**
     * @When I run command :command
     */
    public function iRunCommand(string $command): void
    {
        $params = explode(' ', $command);
        array_unshift($params, '-n');
        array_unshift($params, 'console');
        $input = new ArgvInput($params);
        $output = new BufferedOutput();

        try {
            $application = $this->appFactory->create();
            $this->returnCode = $application->run($input, $output);
            $this->output = $output->fetch();
        } catch (\Throwable $t) {
            $prev = $t->getPrevious();
            while (null !== $prev) {
                echo $prev->getMessage()."\n";
                $prev = $prev->getPrevious();
            }
            throw $t;
        }
    }

    /**
     * @Then the command has a return value of :code
     */
    public function theCommandSHasAReturnValueOf(string $code): void
    {
        if (($this->getReturnCode()) !== ((int)$code)) {
            $msg = sprintf('Expected the command to return code %d but got %d', $code, $this->getReturnCode());
            $msg .= "\n".$this->getOutput();
            throw new DomainException($msg);
        }
    }

    /**
     * @Then the command outputs :text
     */
    public function theCommandOutputs(string $text): void
    {
        $found = $this->getOutput();
        if (false === strpos($found, $text)) {
            throw new DomainException(sprintf("Text not found in\n%s", $found));
        }
    }

    protected function getOutput(): string
    {
        if (null === $this->output) {
            throw new DomainException('No command has run yet.');
        }

        return $this->output;
    }

    protected function getReturnCode(): int
    {
        if (null === $this->returnCode) {
            throw new DomainException('No command has run yet.');
        }

        return $this->returnCode;
    }
}
