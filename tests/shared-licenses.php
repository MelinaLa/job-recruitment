<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$first = '02:aa:bb:cc:dd:01';
$second = '02:aa:bb:cc:dd:02';
withTestLogs([
    deviceEntry('B', $first) . deviceEntry('A', $first)
        . deviceEntry('A', '02-AA-BB-CC-DD-01') . deviceEntry('A', $second)
        . deviceEntry('SINGLE', $first) . "serial=MISSING\nserial=BAD specs=invalid\n",
    deviceEntry('A', $first) . deviceEntry('B', $second)
        . deviceEntry('SINGLE', $first) . deviceEntry('ZERO', '00:00:00:00:00:00')
        . deviceEntry('BROADCAST', 'ff:ff:ff:ff:ff:ff') . deviceEntry('BADMAC', 'not-a-mac')
        . "serial=A specs=invalid\n" . deviceEntry('-', $second) . "no metadata\n",
], static function (array $files): void {
    $report = analyzeSharedLicenses($files);
    testCheck(reportRows($report) === [['A', 2], ['B', 2]],
        'Deduplicate normalized MACs across files per license, exclude single-device licenses and sort ties by serial.');
    testCheck(str_contains($report, 'Licenses observed on multiple devices: 2'), 'Incorrect shared license count.');
    testCheck(str_contains($report, 'Requests with a usable serial but no usable MAC: 6'), 'Incorrect unusable MAC count.');
    testCheck(str_contains($report, 'Licenses without any identifiable device: 5'),
        'A license with both valid and invalid observations still has an identifiable device.');
});

$log = '';
for ($i = 1; $i <= 11; $i++) {
    for ($device = 1; $device <= $i + 1; $device++) {
        $log .= deviceEntry(sprintf('L%02d', $i), sprintf('02:00:00:00:00:%02x', $device));
    }
}
$log .= str_repeat(deviceEntry('BUSY', $first), 100);
withTestLogs([$log], static function (array $files): void {
    $expected = [];
    for ($i = 11; $i >= 2; $i--) {
        $expected[] = [sprintf('L%02d', $i), $i + 1];
    }
    $report = analyzeSharedLicenses($files);
    testCheck(reportRows($report) === $expected, 'Rank the top ten by distinct devices, not requests.');
    testCheck(str_contains($report, 'Licenses observed on multiple devices: 11'), 'Summary must include licenses outside the top ten.');
});

foreach ([[], [''], [deviceEntry('SINGLE', $first)]] as $contents) {
    withTestLogs($contents, static function (array $files): void {
        $report = analyzeSharedLicenses($files);
        testCheck(reportRows($report) === [], 'No shared licenses should produce no rows.');
        testCheck(str_contains($report, 'No licenses observed on multiple identifiable devices.'), 'Missing empty-result message.');
    });
}

echo 'Shared license checks passed.' . PHP_EOL;
