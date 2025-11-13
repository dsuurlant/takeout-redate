# Takeout Redate CLI

A Symfony Console application to recursively go through your Google Takeout archive and rewrite the file dates according to the accompanying metadata json.

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

## License

MIT