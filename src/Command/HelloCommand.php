<?php

declare(strict_types=1);

namespace TakeoutRedate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class HelloCommand extends Command
{
    protected static ?string $defaultName = 'hello';
    protected static ?string $defaultDescription = 'A sample hello command';

    protected function configure(): void
    {
        $this
            ->setDescription('Greets the user')
            ->addOption(
                'name',
                null,
                InputOption::VALUE_OPTIONAL,
                'Name to greet',
                'World'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getOption('name');

        $io->success("Hello, {$name}!");

        return Command::SUCCESS;
    }
}
