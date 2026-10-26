<?php

declare(strict_types=1);

foreach (['logs', 'results'] as $folder) {
    $directory = __DIR__ . '/' . $folder;
    if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
        fwrite(STDERR, 'Cannot create the ' . $folder . '/ folder.' . PHP_EOL);
        exit(1);
    }
}

$files = glob(__DIR__ . '/logs/*');
$counts = [];
$fileCount = 0;
$skipped = 0;
$skippedLines = [];

foreach ($files === false ? [] : $files as $file) {
    if (!is_file($file) || str_starts_with(basename($file), '.')) {
        continue;
    }

    $handle = @fopen($file, 'r');
    if ($handle === false) {
        fwrite(STDERR, 'Cannot read: ' . basename($file) . PHP_EOL);
        exit(1);
    }
    $fileCount++;
    $lineNumber = 0;

    while (($line = fgets($handle)) !== false) {
        $lineNumber++;
        if (preg_match('/(?:^|\s)serial=([^\s]+)/', $line, $matches)
            && $matches[1] !== '-') {
            $serial = $matches[1];
            $counts[$serial] = ($counts[$serial] ?? 0) + 1;
        } else {
            $skipped++;
            $skippedLines[] = basename($file) . ':' . $lineNumber . ': ' . rtrim($line, "\r\n");
        }
    }

    if (!feof($handle)) {
        fclose($handle);
        fwrite(STDERR, 'Error reading: ' . basename($file) . PHP_EOL);
        exit(1);
    }
    fclose($handle);
}

if ($fileCount === 0) {
    fwrite(STDERR, 'Place your log files in the logs/ folder first.' . PHP_EOL);
    exit(1);
}

uksort($counts, static fn (string $a, string $b): int =>
    ($counts[$b] <=> $counts[$a]) ?: strcmp($a, $b));

$topLicenses = array_slice($counts, 0, 10, true);
$serialWidth = strlen('License serial number');
$requestWidth = strlen('Access attempts');
foreach ($topLicenses as $serial => $count) {
    $serialWidth = max($serialWidth, strlen((string) $serial));
    $requestWidth = max($requestWidth, strlen((string) $count));
}

$rowFormat = '| %8s | %-' . $serialWidth . 's | %' . $requestWidth . 's |' . PHP_EOL;
$border = '+' . str_repeat('-', 10) . '+' . str_repeat('-', $serialWidth + 2)
    . '+' . str_repeat('-', $requestWidth + 2) . '+' . PHP_EOL;

$output = 'TOP 10 LICENSES BY SERVER ACCESS ATTEMPTS' . PHP_EOL . PHP_EOL;
$output .= $border;
$output .= sprintf($rowFormat, 'Position', 'License serial number', 'Access attempts');
$output .= $border;
$position = 1;
foreach ($topLicenses as $serial => $count) {
    $output .= sprintf($rowFormat, $position++, $serial, number_format($count, 0, '.', ''));
}
$output .= $border;
if ($topLicenses === []) {
    $output .= 'No usable license serial numbers found.' . PHP_EOL;
}
$output .= PHP_EOL . "Files processed: $fileCount" . PHP_EOL;
$output .= "Lines without a usable serial: $skipped" . PHP_EOL;
if ($skippedLines !== []) {
    $output .= PHP_EOL . 'SKIPPED LINES (file:line: content)' . PHP_EOL;
    $output .= implode(PHP_EOL, $skippedLines) . PHP_EOL;
}

$resultDirectory = __DIR__ . '/results';
if (file_put_contents($resultDirectory . '/top-licenses.txt', $output) === false) {
    fwrite(STDERR, 'Cannot write the results file.' . PHP_EOL);
    exit(1);
}

echo $output;
echo PHP_EOL . 'Saved to results/top-licenses.txt' . PHP_EOL;
