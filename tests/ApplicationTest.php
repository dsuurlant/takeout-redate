<?php

declare(strict_types=1);

namespace TakeoutRedate\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TakeoutRedate\Application;

class ApplicationTest extends TestCase
{
    public function testApplicationHasCorrectNameAndVersion(): void
    {
        $app = new Application();

        $this->assertSame('Takeout Redate', $app->getName());
        $this->assertSame('0.1.0', $app->getVersion());
    }

    public function testApplicationCanAddCommands(): void
    {
        // Create real Command instances with names set via setName
        $command1 = new class extends Command {
            public function __construct()
            {
                parent::__construct('test:command1');
            }
            protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                return Command::SUCCESS;
            }
        };
        
        $command2 = new class extends Command {
            public function __construct()
            {
                parent::__construct('test:command2');
            }
            protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                return Command::SUCCESS;
            }
        };

        $app = new Application([$command1, $command2]);

        // Verify that commands can be retrieved by name
        $this->assertTrue($app->has('test:command1'));
        $this->assertTrue($app->has('test:command2'));
        
        $retrieved1 = $app->find('test:command1');
        $retrieved2 = $app->find('test:command2');

        $this->assertSame('test:command1', $retrieved1->getName());
        $this->assertSame('test:command2', $retrieved2->getName());
    }

    public function testApplicationCanBeConstructedWithoutCommands(): void
    {
        $app = new Application();

        $this->assertInstanceOf(Application::class, $app);
        $this->assertSame('Takeout Redate', $app->getName());
    }

    public function testApplicationCanAddEmptyCommandArray(): void
    {
        $app = new Application([]);

        $this->assertInstanceOf(Application::class, $app);
    }
}

