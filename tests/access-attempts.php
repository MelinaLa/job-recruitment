<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

withTestLogs([
    "serial=B\nserial=A\nserial=A\nserial=-\nno metadata\n",
    "serial=B\nserial=C specs=invalid\nserial=\nnotserial=IGNORED\n",
], static function (array $files): void {
    $report = analyzeAccessAttempts($files);
    testCheck(reportRows($report) === [['A', 2], ['B', 2], ['C', 1]],
        'Count every access across files, break ties by serial and accept accesses without valid specs.');
    testCheck(str_contains($report, 'Files processed: 2'), 'Incorrect file count.');
    testCheck(str_contains($report, 'Lines without a usable serial: 4'), 'Incorrect skipped line count.');
});

$log = '';
for ($i = 1; $i <= 11; $i++) {
    $log .= str_repeat(sprintf("serial=L%02d\n", $i), $i);
}
withTestLogs([$log], static function (array $files): void {
    $expected = [];
    for ($i = 11; $i >= 2; $i--) {
        $expected[] = [sprintf('L%02d', $i), $i];
    }
    testCheck(reportRows(analyzeAccessAttempts($files)) === $expected,
        'Return exactly the ten highest counts in descending order.');
});

foreach ([[], ['']] as $contents) {
    withTestLogs($contents, static function (array $files): void {
        $report = analyzeAccessAttempts($files);
        testCheck(reportRows($report) === [], 'Empty input must not produce rows.');
        testCheck(str_contains($report, 'No usable license serial numbers found.'), 'Missing empty-input message.');
    });
}

echo 'Access attempt checks passed.' . PHP_EOL;
