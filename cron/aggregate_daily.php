<?php declare(strict_types=1);

/**
 * aggregate_daily.php — Run daily aggregation only.
 *
 * Delegates to aggregate.php with a period argument so crontab entries
 * documented in DEPLOY.md work correctly.
 */
$_SERVER['argv'][1] = 'daily';
require __DIR__ . '/aggregate.php';
