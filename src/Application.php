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

        foreach ($commands as $command) {
            $this->add($command);
        }
    }
}
