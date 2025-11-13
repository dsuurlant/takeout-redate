# Takeout Redate CLI

A Symfony Console application to recursively go through your Google Takeout archive and rewrite the file dates according to the accompanying metadata json.

## Requirements

- PHP 8.4 or higher

## Known limitations

When dealing with 1000s of files over many years go per subdirectory, or the process may stall.

## Installation

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

```

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Build PHAR file
composer build

# Test build locally (runs tests, builds PHAR, and verifies it works)
composer test-build

# Code quality checks
composer quality
```

## License

MIT
