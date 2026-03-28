<?php declare(strict_types=1);

/**
 * aggregate_monthly.php — Run monthly aggregation only.
 *
 * Delegates to aggregate.php with a period argument so crontab entries
 * documented in DEPLOY.md work correctly.
 */
$_SERVER['argv'][1] = 'monthly';
require __DIR__ . '/aggregate.php';
