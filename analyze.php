<?php

declare(strict_types=1);

require_once __DIR__ . '/src/functions.php';

$analysis = $argv[1] ?? 'all';
$usage = 'Usage: php analyze.php [all|analyzeAccessAttempts|analyzeSharedLicenses|analyzeHardwareClasses]' . PHP_EOL;
if ($argc === 2 && in_array($analysis, ['--help', '-h'], true)) {
    echo $usage;
    exit(0);
}
if ($argc > 2 || !in_array($analysis, ['all', 'analyzeAccessAttempts', 'analyzeSharedLicenses', 'analyzeHardwareClasses'], true)) {
    fwrite(STDERR, $usage);
    exit(1);
}

if ($analysis !== 'analyzeAccessAttempts' && !extension_loaded('zlib')) {
    fwrite(STDERR, 'The PHP zlib extension is required to decode device metadata.' . PHP_EOL);
    exit(1);
}

foreach (['logs', 'results'] as $folder) {
    $directory = __DIR__ . '/' . $folder;
    if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
        fwrite(STDERR, 'Cannot create the ' . $folder . '/ folder.' . PHP_EOL);
        exit(1);
    }
}

$files = array_values(array_filter(
    glob(__DIR__ . '/logs/*') ?: [],
    static fn (string $file): bool => is_file($file) && !str_starts_with(basename($file), '.')
));

if ($files === []) {
    fwrite(STDERR, 'Place your log files in the logs/ folder first.' . PHP_EOL);
    exit(1);
}

try {
    $createdFiles = [];
    if ($analysis === 'all' || $analysis === 'analyzeAccessAttempts') {
        $accessReport = analyzeAccessAttempts($files);
        if (file_put_contents(__DIR__ . '/results/top-licenses.txt', $accessReport) === false) {
            throw new RuntimeException('Cannot write the results file.');
        }
        $createdFiles[] = 'top-licenses.txt';
    }

    if ($analysis === 'all' || $analysis === 'analyzeSharedLicenses') {
        $deviceReport = analyzeSharedLicenses($files);
        if (file_put_contents(__DIR__ . '/results/top-shared-licenses.txt', $deviceReport) === false) {
            throw new RuntimeException('Cannot write the device results file.');
        }
        $createdFiles[] = 'top-shared-licenses.txt';
    }
    if ($analysis === 'all' || $analysis === 'analyzeHardwareClasses') {
        $hardwareReport = analyzeHardwareClasses($files);
        if (file_put_contents(__DIR__ . '/results/hardware-classes.txt', $hardwareReport) === false) {
            throw new RuntimeException('Cannot write the hardware results file.');
        }
        $createdFiles[] = 'hardware-classes.txt';
    }
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

echo 'TXT files created in results/: ' . implode(' and ', $createdFiles) . PHP_EOL;
