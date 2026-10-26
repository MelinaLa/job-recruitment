# Cloud Recruitment

PHP script for tasks 1 and 2. Requires PHP 8.2 or later with zlib.

## Usage

Extract the log files into `logs/` and run:

```sh
php analyze.php
```

This runs both tasks. To run a single task, pass its function name as shown below.

## Tasks and commands

Task 1 uses `analyzeAccessAttempts()` to find the 10 license serial numbers with the most server access attempts and show the number of attempts for each license.

```sh
php analyze.php analyzeAccessAttempts
```

Task 2 uses `analyzeSharedLicenses()` to identify devices by their MAC address and find the 10 licenses used on the most distinct devices. Only licenses observed on more than one device are listed, together with their device count.

```sh
php analyze.php analyzeSharedLicenses
```

Show the available commands:

```sh
php analyze.php --help
```

## Results

- `results/top-licenses.txt`: top 10 licenses by access attempts.
- `results/top-shared-licenses.txt`: top 10 licenses by distinct device count.

Results are replaced on each run of the selected task.

Devices are identified by the MAC address in `specs`. Repeated requests from the same device count once per license. Requests without an identifiable device do not contribute to the device count. Multiple devices do not prove simultaneous use.

Keep logs and results private. Both folders are excluded from Git. The hardware bonus is not implemented.
