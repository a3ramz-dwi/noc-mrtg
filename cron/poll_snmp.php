<?php declare(strict_types=1);

/**
 * poll_snmp.php — Alias for poller.php
 *
 * This file exists so that crontab entries referencing poll_snmp.php
 * (as documented in DEPLOY.md) work correctly.
 */
require __DIR__ . '/poller.php';
