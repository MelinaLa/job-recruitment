<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/functions.php';

function testCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function reportRows(string $report): array
{
    preg_match_all('/^\|\s*\d+\s*\|\s*([^|]+?)\s*\|\s*(\d+)\s*\|/m', $report, $matches, PREG_SET_ORDER);

    return array_map(static fn(array $match): array => [trim($match[1]), (int) $match[2]], $matches);
}

function withTestLogs(array $contents, callable $test): void
{
    $files = [];
    try {
        foreach ($contents as $content) {
            $file = tempnam(sys_get_temp_dir(), 'recruitment-test-');
            if ($file === false) {
                throw new RuntimeException('Cannot create test log.');
            }
            $files[] = $file;
            if (file_put_contents($file, $content) === false) {
                throw new RuntimeException('Cannot write test log.');
            }
        }
        $test($files);
    } finally {
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

function deviceEntry(string $serial, string $mac): string
{
    return 'serial=' . $serial . ' specs='
        . base64_encode(gzencode(json_encode(['mac' => $mac], JSON_THROW_ON_ERROR))) . PHP_EOL;
}
