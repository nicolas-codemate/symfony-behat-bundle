<?php

declare(strict_types=1);

namespace Elbformat\SymfonyBehatBundle\Context;

use Behat\Behat\Context\Context;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Interaction with a symfony console command.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class CommandContext implements Context
{
    private BufferedOutput $output;
    private int $returnCode;

    /**
     * @When I run command :command
     *
     * @throws \Exception
     */
    public function iRunCommand($command)
    {
        $params = explode(' ', $command);
        array_unshift($params, '-n');
        array_unshift($params, 'console');
        $input = new ArgvInput($params);
        $this->output = new BufferedOutput();

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        try {
            $this->returnCode = $application->run($input, $this->output);
        } catch (\Throwable $t) {
            $prev = $t->getPrevious();
            while (null !== $prev) {
                echo $prev->getMessage() . "\n";
                $prev = $prev->getPrevious();
            }
            throw $t;
        }
    }

    /**
     * @Then the command should have a return value of :code
     *
     * @throws \DomainException
     */
    public function theCommandShouldHaveAReturnValueOf($code)
    {
        if (((int) $this->returnCode) !== ((int) $code)) {
            $msg = sprintf('Expected the command to return code %d but got %d', $code, $this->returnCode);
            $msg .= "\n" . $this->output->fetch();
            throw new \DomainException($msg);
        }
    }
}
