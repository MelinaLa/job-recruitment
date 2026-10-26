<?php

declare(strict_types=1);

function analyzeAccessAttempts(array $files): string
{
    $counts = [];
    $fileCount = count($files);
    $skipped = 0;

    foreach (readLogLines($files) as $entry) {
        $serial = getLicenseSerial($entry['content']);
        if ($serial === null) {
            $skipped++;
            continue;
        }
        $counts[$serial] = ($counts[$serial] ?? 0) + 1;
    }

    uksort($counts, static fn(string $a, string $b): int =>
        ($counts[$b] <=> $counts[$a]) ?: strcmp($a, $b));

    $topLicenses = array_slice($counts, 0, 10, true);
    $output = formatTable('TOP 10 LICENSES BY SERVER ACCESS ATTEMPTS', 'Access attempts', $topLicenses);
    if ($topLicenses === []) {
        $output .= 'No usable license serial numbers found.' . PHP_EOL;
    }
    $output .= PHP_EOL . "Files processed: $fileCount" . PHP_EOL;
    $output .= "Lines without a usable serial: $skipped" . PHP_EOL;

    return $output;
}

function analyzeSharedLicenses(array $files): string
{
    $licenses = [];
    $devicesByLicense = [];
    $withoutDevice = 0;

    foreach (readLogLines($files) as $entry) {
        $serial = getLicenseSerial($entry['content']);
        if ($serial === null) {
            continue;
        }
        $licenses[$serial] = true;

        $mac = getDeviceMac($entry['content']);
        if ($mac === null) {
            $withoutDevice++;
            continue;
        }

        $devicesByLicense[$serial][$mac] = true;
    }

    $sharedLicenses = [];
    foreach ($devicesByLicense as $serial => $devices) {
        if (count($devices) > 1) {
            $sharedLicenses[$serial] = count($devices);
        }
    }
    uksort($sharedLicenses, static fn(string $a, string $b): int =>
        ($sharedLicenses[$b] <=> $sharedLicenses[$a]) ?: strcmp($a, $b));

    $deviceOutput = formatTable(
        'TOP 10 LICENSES OBSERVED ON MULTIPLE DEVICES',
        'Distinct devices',
        array_slice($sharedLicenses, 0, 10, true)
    );
    if ($sharedLicenses === []) {
        $deviceOutput .= 'No licenses observed on multiple identifiable devices.' . PHP_EOL;
    }
    $deviceOutput .= PHP_EOL . 'Licenses observed on multiple devices: ' . count($sharedLicenses) . PHP_EOL;
    $deviceOutput .= 'Requests with a usable serial but no usable MAC: ' . $withoutDevice . PHP_EOL;
    $deviceOutput .= 'Licenses without any identifiable device: ' . count(array_diff_key($licenses, $devicesByLicense)) . PHP_EOL;

    return $deviceOutput;
}

function readLogLines(array $files): Generator
{
    foreach ($files as $file) {
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            throw new RuntimeException('Cannot read: ' . basename($file));
        }

        try {
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                yield ['file' => basename($file), 'number' => ++$lineNumber, 'content' => $line];
            }
            if (!feof($handle)) {
                throw new RuntimeException('Error reading: ' . basename($file));
            }
        } finally {
            fclose($handle);
        }
    }
}

function getLicenseSerial(string $line): ?string
{
    if (preg_match('/(?:^|\s)serial=([^\s]+)/', $line, $matches) && $matches[1] !== '-') {
        return $matches[1];
    }

    return null;
}

function getDeviceMac(string $line): ?string
{
    if (!preg_match('/(?:^|\s)specs=(\S+)/', $line, $matches)) {
        return null;
    }

    $compressed = base64_decode($matches[1], true);
    if ($compressed === false) {
        return null;
    }

    $json = @gzdecode($compressed, 1048577);
    if ($json === false || strlen($json) > 1048576) {
        return null;
    }
    $specs = json_decode($json, false, 64);
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($specs)) {
        return null;
    }
    if (!is_string($specs->mac ?? null)) {
        return null;
    }

    $mac = strtolower(str_replace('-', ':', trim($specs->mac)));
    if (
        !preg_match('/^(?:[0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac)
        || $mac === '00:00:00:00:00:00' || $mac === 'ff:ff:ff:ff:ff:ff'
    ) {
        return null;
    }

    return $mac;
}

function formatTable(string $title, string $countLabel, array $rows): string
{
    $serialWidth = strlen('License serial number');
    $countWidth = strlen($countLabel);
    foreach ($rows as $serial => $count) {
        $serialWidth = max($serialWidth, strlen((string) $serial));
        $countWidth = max($countWidth, strlen((string) $count));
    }

    $rowFormat = '| %8s | %-' . $serialWidth . 's | %' . $countWidth . 's |' . PHP_EOL;
    $border = '+' . str_repeat('-', 10) . '+' . str_repeat('-', $serialWidth + 2)
        . '+' . str_repeat('-', $countWidth + 2) . '+' . PHP_EOL;

    $table = $title . PHP_EOL . PHP_EOL . $border;
    $table .= sprintf($rowFormat, 'Position', 'License serial number', $countLabel) . $border;
    $position = 1;
    foreach ($rows as $serial => $count) {
        $table .= sprintf($rowFormat, $position++, $serial, $count);
    }

    return $table . $border;
}
