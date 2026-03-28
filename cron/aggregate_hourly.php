<?php declare(strict_types=1);

/**
 * aggregate_hourly.php — Run hourly aggregation only.
 *
 * Delegates to aggregate.php with a period argument so crontab entries
 * documented in DEPLOY.md work correctly.
 */
$_SERVER['argv'][1] = 'hourly';
require __DIR__ . '/aggregate.php';
