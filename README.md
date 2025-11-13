# Takeout Redate CLI

A Symfony Console application to recursively go through your Google Photos Takeout archive and rewrite the file dates according to the accompanying metadata json.

## Requirements

- PHP 8.4 or higher

## Recommended Usage with Google Photos Takeout

When exporting your Google Photos archive, the ZIP files strip the original creation and modification dates from your photos and videos. However, these dates are preserved in the accompanying JSON metadata files. This tool restores those dates programmatically.

### Overview

The process involves three main steps:

1. **Obtain and extract** your Google Photos Takeout archive
2. **Consolidate** all files into a single directory structure
3. **Restore dates** using this tool

### Step 1: Obtain Your Archive

1. Request your Google Photos archive from Google Takeout
2. Download all ZIP files before they expire (archives have expiration dates)
3. Choose a storage location:
   - **Recommended**: Local device or NAS for faster processing
   - **Not recommended**: Directly to another cloud service (too slow)

> **Important**: If you do not download the entire archive before expiration, a newly requested Takeout archive will not have the same file distribution across different ZIPs, resulting in potential data loss. Make sure to download your entire archive before the expiration date.

### Step 2: Extract and Consolidate

1. Extract all ZIP files from your download

2. Consolidate the extracted directories.

   **On macOS**: If files are split into multiple "Takeout 1", "Takeout 2", etc. directories, use this script to merge them:

```bash
#!/bin/bash
for src in Takeout\ [0-9]*; do
  [ -d "$src" ] || continue
  rsync -a --info=progress2 --ignore-existing --remove-source-files "$src"/ "Takeout"/ && find "$src" -type d -empty -delete
done
```

   **On other platforms**: Files may automatically merge into a single directory, or you can manually combine them.

> **Note**: Google may distribute metadata JSON files across different ZIP archives, meaning a photo's metadata might not be in the same archive as the photo itself. You must have the entire archive extracted and consolidated before running the date restoration tool.

### Step 3: Restore Dates

Google exports photos organized by year:

```text
./Photos from 2018/
./Photos from 2019/
./Photos from 2020/
./Photos from 2021/
...
```

**Recommended approach**: Process one year at a time to avoid timeouts with large archives.

For each year directory:

1. **Set your base directory** (optional, for convenience):

```bash
export TAKEOUT_DIR="/mystorage/takeout/photos/"
```

2. **Test with dry-run** first:

```bash
php takeout-redate.phar --path="$TAKEOUT_DIR/Photos from 2018" --dry-run
```

3. **If no errors, run for real** and optionally delete metadata files after processing:

```bash
php takeout-redate.phar --path="$TAKEOUT_DIR/Photos from 2018" --delete
```

4. **Repeat for each year** directory in your archive

Happy archiving and good luck moving away from Google!

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
