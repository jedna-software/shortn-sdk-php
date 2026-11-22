<?php

declare(strict_types=1);

// Offline unit tests: `composer test` or `php tests/run.php [name filter]`.

require_once __DIR__ . '/bootstrap.php';

foreach (glob(__DIR__ . '/unit/*Test.php') ?: [] as $file) {
    require_once $file;
}

exit(\Shortn\Sdk\Tests\Harness::run($argv[1] ?? null));
