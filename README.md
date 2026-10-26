# Cloud Recruitment - Task 1

Find the 10 license serial numbers with the most requests using plain PHP.
No framework, Composer, Node.js, database, or web server is required.

## Usage

Requires PHP 8.2 or later.

1. Put extracted, plain-text log files in `logs/`.
2. Run from the project folder:

```sh
php analyze.php
```

The top 10 appear in the console and in `results/top-licenses.txt`. Each run replaces the previous result. Missing input and output folders are created automatically.

All non-hidden files directly inside `logs/` are analyzed together. Subfolders are ignored. Extract compressed files first. Each line containing a usable `serial=` value counts as one request regardless of HTTP status. Missing or empty serials and `serial=-` are skipped and counted as `Lines without a usable serial`. Ties are sorted by serial number. Duplicate input files count requests twice.

Log files and results stay local and are excluded from Git. Only task 1 is implemented.

## Files

- `analyze.php`: complete console analysis
- `logs/`: private input files
- `results/`: generated results
- `.gitignore`: exclusions for local and generated files
