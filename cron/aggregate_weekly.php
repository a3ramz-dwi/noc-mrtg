<?php declare(strict_types=1);

/**
 * aggregate_weekly.php — Run weekly aggregation only.
 *
 * Delegates to aggregate.php with a period argument so crontab entries
 * documented in DEPLOY.md work correctly.
 */
$_SERVER['argv'][1] = 'weekly';
require __DIR__ . '/aggregate.php';
