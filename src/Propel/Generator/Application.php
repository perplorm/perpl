<?php

declare(strict_types = 1);

namespace Propel\Generator;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function extension_loaded;
use function in_array;

class Application extends SymfonyApplication
{
    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    #[\Override]
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $command = $input->getFirstArgument();
        $xdebugNoWarn = in_array($command, ['completion', '_complete']);

        if (!$xdebugNoWarn && extension_loaded('xdebug')) {
            $output->writeln(
                '<comment>You are running perpl with xdebug enabled. This has a major impact on runtime performance.</comment>' . "\n",
            );
        }

        return parent::doRun($input, $output);
    }
}
