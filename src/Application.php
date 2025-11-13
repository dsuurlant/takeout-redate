<?php

declare(strict_types=1);

namespace TakeoutRedate;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;

class Application extends BaseApplication
{
    /**
     * @param Command[] $commands
     */
    public function __construct(iterable $commands = [])
    {
        parent::__construct('Takeout Redate', '0.1.0');

        $commandArray = [];
        foreach ($commands as $command) {
            $this->add($command);
            $commandArray[] = $command;
        }

        // If there's only one command, make it the default
        if (\count($commandArray) === 1) {
            $commandName = $commandArray[0]->getName();
            if ($commandName !== null) {
                $this->setDefaultCommand($commandName, true);
            }
        }
    }
}
