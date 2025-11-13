# Takeout Redate CLI

A Symfony Console application that can be compiled into a PHAR file for distribution as a standalone CLI tool.

## Installation

### Development

```bash
composer install
```

### As PHAR

Download the compiled PHAR file and make it executable:

```bash
chmod +x takeout-redate.phar
mv takeout-redate.phar /usr/local/bin/takeout-redate
```

Or use it directly:

```bash
php takeout-redate.phar
```

## Usage

```bash
# Run the application
php bin/app

# Or after building the PHAR
./takeout-redate.phar

# Run a specific command
php bin/app hello
php bin/app hello --name="Your Name"
```

## Code Quality

This project includes PHP-CS-Fixer and PHPStan for maintaining code quality.

### PHP-CS-Fixer

PHP-CS-Fixer automatically fixes code style issues according to Symfony coding standards.

```bash
# Fix code style issues
composer cs-fix

# Check code style without fixing
composer cs-check
```

### PHPStan

PHPStan performs static analysis to find bugs in your code.

```bash
# Run PHPStan analysis
composer phpstan
```

### Run All Quality Checks

```bash
# Run both code style fixes and static analysis
composer quality
```

## Building the PHAR

### Prerequisites

- PHP 8.1 or higher
- Composer
- Box (included as dev dependency)

### Build Steps

1. Install dependencies:
   ```bash
   composer install
   ```

2. Build the PHAR:
   ```bash
   composer build
   # or
   vendor/bin/box compile
   ```

3. The compiled PHAR will be created as `takeout-redate.phar` in the project root.

4. Test the PHAR:
   ```bash
   ./takeout-redate.phar
   ```

## Adding New Commands

1. Create a new command class in `src/Command/`:

```php
<?php

namespace TakeoutRedate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MyCommand extends Command
{
    protected static $defaultName = 'my-command';
    protected static $defaultDescription = 'My command description';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('My command executed!');
        return Command::SUCCESS;
    }
}
```

2. Register it in `bin/app`:

```php
use TakeoutRedate\Command\MyCommand;

$commands = [
    new HelloCommand(),
    new MyCommand(), // Add here
];
```

## Project Structure

```
.
├── bin/
│   └── app                 # Application entry point
├── src/
│   ├── Application.php     # Main application class
│   └── Command/            # Command classes
├── .php-cs-fixer.php       # PHP-CS-Fixer configuration
├── phpstan.neon            # PHPStan configuration
├── box.json                # Box configuration for PHAR building
├── composer.json           # Dependencies
└── README.md              # This file
```

## License

MIT

