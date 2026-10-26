# Cloud Recruitment

Command-line PHP script for the Securepoint Cloud Recruitment tasks: count license access attempts, identify licenses used on multiple devices and group licenses by hardware profile.

Requires PHP 8.2 or later with the zlib extension. No framework or Composer dependencies are needed. Plain PHP is sufficient for reading the logs, decoding the metadata and generating the reports. Log files are read line by line rather than loaded entirely into memory.

## Usage

Extract the log files into `logs/`, then run the following command from the project directory:

```sh
php analyze.php
```

This runs all three tasks. To run a single task, pass its function name as shown below.

All non-hidden files directly inside `logs/` are processed. Keep only extracted log files in this folder and avoid duplicate or overlapping copies: each log line counts as a separate access attempt. Reports are written to `results/`, which is created automatically.

## Tasks and commands

### Task 1: Most server access attempts

Task 1 uses `analyzeAccessAttempts()` to find the 10 license serial numbers with the most server access attempts and show the number of attempts for each license.

```sh
php analyze.php analyzeAccessAttempts
```

### Task 2: Licenses used on multiple devices

Task 2 uses `analyzeSharedLicenses()` to identify devices by their MAC address and find the 10 licenses used on the most distinct devices. Only licenses observed on more than one device are listed, together with their device count.

```sh
php analyze.php analyzeSharedLicenses
```

The assignment defines the MAC address of the first network interface in `specs` as globally unique and unchangeable by the user. It therefore serves as the device identifier. Repeated requests from the same device count once per license, including across log files. Requests without a usable MAC address are reported separately and do not contribute to the device count. Observing multiple devices does not prove simultaneous use.

### Task 3: Hardware classes (bonus)

Task 3 uses `analyzeHardwareClasses()` to group hardware by normalized CPU model, machine architecture and RAM rounded to the nearest GiB. Normalization ignores letter case, trademark markers, the word `CPU`, clock information introduced by `@` and extra whitespace.

```sh
php analyze.php analyzeHardwareClasses
```

For example, `Intel(R) Atom(TM) CPU E3815 @ 1.46GHz/`, `x86_64` and `3922292kB` produce the profile `intel atom e3815 / x86_64 / 4 GiB RAM`.

RAM rounding reduces fragmentation caused by small differences in reported memory, but does not reliably recover nominal installed capacity: values such as 15 or 31 GiB may still appear. These profiles are heuristic and do not establish a commercial appliance model or distinguish physical hardware from virtual machines.

Counting rules:

- **Active** means observed in the supplied log period, not currently valid: the assignment specifies that `not_after` and `remaining_days` are unset.
- Each distinct serial counts once per hardware class, even across multiple files or devices. A license seen on different classes counts in each, so row totals can exceed the overall distinct license count.
- Missing or invalid specs/CPU are grouped under `Unknown`; missing architecture or RAM is marked within the profile. A license with both unknown and identifiable observations appears in both classes.
- Lines without a usable serial are excluded and counted separately.

## Command help

Show the available commands:

```sh
php analyze.php --help
```

## Results

- `results/top-licenses.txt`: top 10 licenses by access attempts.
- `results/top-shared-licenses.txt`: top 10 licenses by distinct device count.
- `results/hardware-classes.txt`: all hardware classes and their distinct active license counts.

Results are replaced on each run of the selected task.

## Tests

Run the checks from the project directory:

```sh
php tests/access-attempts.php
php tests/shared-licenses.php
php tests/hardware-classes.php
```

The checks use synthetic data and do not require the private log files. Each script prints a success message when all its checks pass; a failed check throws an exception.

- **Access attempts:** repeated accesses across files, top-ten ranking, tied counts, missing serials and empty input.
- **Shared licenses:** distinct devices per license, MAC normalization, repeated requests across files, top-ten ranking, tied counts, invalid metadata and licenses without an identifiable device.
- **Hardware classes:** profile normalization, distinct license counts across files, different RAM classes, malformed metadata, missing serials, empty input and MAC decoding.

## Data privacy

Keep logs and generated reports on your device. Both `logs/` and `results/`, as well as PDF files, are excluded from Git. Do not upload the supplied access logs or include their contents in commits.
