<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/functions.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function logEntry(string $serial, array $specs): string
{
    return 'serial=' . $serial . ' specs=' . base64_encode(gzencode(json_encode($specs, JSON_THROW_ON_ERROR))) . PHP_EOL;
}

$specs = ['cpu' => 'Intel(R) Atom(TM) CPU E3815 @ 1.46GHz/', 'machine' => 'x86_64', 'mem' => '3922292kB'];
$variant = ['cpu' => ' intel atom E3815 @ 1.46GHz/ ', 'machine' => 'X86_64', 'mem' => '3924344kB'];
$class = getHardwareClass((object) $specs);
check($class === 'intel atom e3815 / x86_64 / 4 GiB RAM', 'Unexpected hardware profile.');
check(getHardwareClass((object) $variant) === $class, 'Equivalent hardware should share a class.');
check(getHardwareClass(null) === 'Unknown', 'Missing specs should be unknown.');
check(getHardwareClass((object) ['cpu' => []]) === 'Unknown', 'Invalid CPU should be unknown.');
check(getDeviceSpecs('specs=not-base64!') === null, 'Invalid base64 must be rejected.');
check(getDeviceSpecs('specs=' . base64_encode('not gzip')) === null, 'Invalid gzip must be rejected.');
check(getDeviceSpecs('specs=' . base64_encode(gzencode('[]'))) === null, 'Specs must be an object.');
check(getDeviceMac(logEntry('A', $specs + ['mac' => '02-AA-BB-CC-DD-EE'])) === '02:aa:bb:cc:dd:ee', 'MAC decoding regressed.');

$files = [tempnam(sys_get_temp_dir(), 'hardware-test-'), tempnam(sys_get_temp_dir(), 'hardware-test-')];
try {
    file_put_contents($files[0], logEntry('A', $specs) . logEntry('A', $variant) . logEntry('B', $specs));
    file_put_contents($files[1], logEntry('A', $specs)
        . logEntry('A', array_replace($specs, ['mem' => '8011088kB']))
        . 'serial=C specs=invalid' . PHP_EOL . 'serial=-' . PHP_EOL);
    $report = analyzeHardwareClasses($files);
    check((bool) preg_match('/4 GiB RAM\s*\|\s*2\s*\|/', $report), 'Repeated licenses must count once per class across files.');
    check((bool) preg_match('/8 GiB RAM\s*\|\s*1\s*\|/', $report), 'Different RAM classes must remain separate.');
    check((bool) preg_match('/Unknown\s*\|\s*1\s*\|/', $report), 'Invalid specs must be reported.');
    check(str_contains($report, 'Distinct licenses across all classes: 3'), 'Overall license count is incorrect.');
    check(str_contains($report, 'Lines without a usable serial: 1'), 'Missing serial count is incorrect.');
    check(str_contains(analyzeHardwareClasses([]), 'No usable license serial numbers found.'), 'Empty input must be handled.');
} finally {
    foreach ($files as $file) {
        unlink($file);
    }
}

echo 'Hardware class checks passed.' . PHP_EOL;
