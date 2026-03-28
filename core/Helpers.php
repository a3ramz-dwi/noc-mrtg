<?php declare(strict_types=1);

/**
 * Global helper / utility functions for the NOC MRTG Manager.
 *
 * These are plain functions (no namespace) so they can be called from
 * anywhere — controllers, views, CLI scripts — without use statements.
 *
 * @package NOC\Core
 * @version 1.0.0
 */

if (!function_exists('formatBytes')) {
    /**
     * Format a byte count into a human-readable string.
     *
     * @param  int|float $bytes
     * @param  int       $precision  Decimal places (default 2).
     * @return string                e.g. "1.42 GB"
     */
    function formatBytes(int|float $bytes, int $precision = 2): string
    {
        if ($bytes < 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, $precision) . ' ' . $units[$index];
    }
}

if (!function_exists('formatSpeed')) {
    /**
     * Format a bits-per-second value into a human-readable string.
     *
     * @param  int|float $bps
     * @return string          e.g. "100.00 Mbps"
     */
    function formatSpeed(int|float $bps): string
    {
        if ($bps < 0) {
            return '0 bps';
        }

        $units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
        $index = 0;

        while ($bps >= 1000 && $index < count($units) - 1) {
            $bps /= 1000;
            $index++;
        }

        return round($bps, 2) . ' ' . $units[$index];
    }
}

if (!function_exists('formatUptime')) {
    /**
     * Convert a duration in seconds into a human-readable uptime string.
     *
     * @param  int $seconds
     * @return string         e.g. "3d 4h 12m 5s"
     */
    function formatUptime(int $seconds): string
    {
        if ($seconds < 0) {
            $seconds = 0;
        }

        $days    = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours   = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $parts = [];
        if ($days > 0)    { $parts[] = "{$days}d"; }
        if ($hours > 0)   { $parts[] = "{$hours}h"; }
        if ($minutes > 0) { $parts[] = "{$minutes}m"; }
        $parts[] = "{$seconds}s";

        return implode(' ', $parts);
    }
}

if (!function_exists('sanitize')) {
    /**
     * Sanitize a single string value for safe display in HTML.
     *
     * Trims whitespace and applies htmlspecialchars with UTF-8 encoding.
     *
     * @param  mixed  $input
     * @return string
     */
    function sanitize(mixed $input): string
    {
        if (!is_string($input)) {
            $input = (string) $input;
        }

        return htmlspecialchars(trim($input), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('sanitizeArray')) {
    /**
     * Recursively sanitize all string values in an array.
     *
     * @param  array $input
     * @return array
     */
    function sanitizeArray(array $input): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            $result[$key] = match (true) {
                is_array($value)  => sanitizeArray($value),
                is_string($value) => sanitize($value),
                default           => $value,
            };
        }

        return $result;
    }
}

if (!function_exists('validateIp')) {
    /**
     * Validate an IPv4 or IPv6 address.
     *
     * @param  string $ip
     * @return bool
     */
    function validateIp(string $ip): bool
    {
        return filter_var(trim($ip), FILTER_VALIDATE_IP) !== false;
    }
}

if (!function_exists('validateCommunity')) {
    /**
     * Validate an SNMP community string.
     *
     * Must be 1–64 characters of printable ASCII excluding whitespace.
     *
     * @param  string $str
     * @return bool
     */
    function validateCommunity(string $str): bool
    {
        $str = trim($str);
        return strlen($str) >= 1
            && strlen($str) <= 64
            && (bool) preg_match('/^[!-~]+$/', $str); // printable non-space ASCII
    }
}

if (!function_exists('generateToken')) {
    /**
     * Generate a cryptographically secure random token.
     *
     * @param  int $length  Number of random bytes; the returned hex string is 2× this.
     * @return string
     */
    function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes(max(1, $length)));
    }
}

if (!function_exists('timeDiff')) {
    /**
     * Return a human-readable relative time string for a UNIX timestamp.
     *
     * @param  int $timestamp
     * @return string            e.g. "3 minutes ago", "2 hours ago"
     */
    function timeDiff(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 0) {
            return 'just now';
        }

        return match (true) {
            $diff < 60     => 'just now',
            $diff < 3600   => intdiv($diff, 60) . ' minute' . (intdiv($diff, 60) !== 1 ? 's' : '') . ' ago',
            $diff < 86400  => intdiv($diff, 3600) . ' hour' . (intdiv($diff, 3600) !== 1 ? 's' : '') . ' ago',
            $diff < 604800 => intdiv($diff, 86400) . ' day' . (intdiv($diff, 86400) !== 1 ? 's' : '') . ' ago',
            $diff < 2592000 => intdiv($diff, 604800) . ' week' . (intdiv($diff, 604800) !== 1 ? 's' : '') . ' ago',
            $diff < 31536000 => intdiv($diff, 2592000) . ' month' . (intdiv($diff, 2592000) !== 1 ? 's' : '') . ' ago',
            default          => intdiv($diff, 31536000) . ' year' . (intdiv($diff, 31536000) !== 1 ? 's' : '') . ' ago',
        };
    }
}

if (!function_exists('maskPassword')) {
    /**
     * Mask a password or secret string for safe display.
     *
     * Shows the first two and last two characters; everything else is
     * replaced with asterisks (minimum mask length 4).
     *
     * @param  string $str
     * @return string
     */
    function maskPassword(string $str): string
    {
        $len = strlen($str);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($str, 0, 2)
            . str_repeat('*', max(4, $len - 4))
            . substr($str, -2);
    }
}

if (!function_exists('bytesToBits')) {
    /**
     * Convert a byte count to bits.
     *
     * @param  int|float $bytes
     * @return int|float
     */
    function bytesToBits(int|float $bytes): int|float
    {
        return $bytes * 8;
    }
}

if (!function_exists('macFormat')) {
    /**
     * Normalise and format a MAC address in colon-delimited uppercase notation.
     *
     * Accepts hex strings with or without delimiters (colons, hyphens, dots).
     *
     * @param  string $mac
     * @return string       e.g. "AA:BB:CC:DD:EE:FF", or the original string on failure.
     */
    function macFormat(string $mac): string
    {
        // Strip common delimiters.
        $clean = strtoupper(preg_replace('/[:\-\.]/', '', trim($mac)) ?? '');

        if (strlen($clean) !== 12 || !ctype_xdigit($clean)) {
            return $mac; // Return as-is if unrecognisable.
        }

        return implode(':', str_split($clean, 2));
    }
}

if (!function_exists('snmpTypeToString')) {
    /**
     * Convert an IANAifType integer to a human-readable interface type string.
     *
     * Common values per RFC 2863 / IANA-IF-TYPE-MIB.
     *
     * @param  int $type
     * @return string
     */
    function snmpTypeToString(int $type): string
    {
        return match ($type) {
            1   => 'other',
            6   => 'ethernetCsmacd',
            9   => 'iso88025TokenRing',
            15  => 'fddi',
            20  => 'basicISDN',
            21  => 'primaryISDN',
            23  => 'ppp',
            24  => 'softwareLoopback',
            37  => 'atm',
            53  => 'propVirtual',
            77  => 'lapF',
            131 => 'tunnel',
            135 => 'ieee8023adLag',
            161 => 'ieee8023adLag',
            166 => 'mpls',
            default => "type({$type})",
        };
    }
}

if (!function_exists('statusColor')) {
    /**
     * Return a Bootstrap 5 colour/text class for a generic operational status.
     *
     * Accepts string labels ("up", "down", "active", "inactive", "warning",
     * "unknown") or integer SNMP ifOperStatus values (1 = up, 2 = down, etc.).
     *
     * @param  string|int $status
     * @return string               Bootstrap class, e.g. 'text-success', 'text-danger'.
     */
    function statusColor(string|int $status): string
    {
        // Normalise integer SNMP ifOperStatus to string.
        if (is_int($status)) {
            $status = match ($status) {
                1       => 'up',
                2       => 'down',
                3       => 'testing',
                4       => 'unknown',
                5       => 'dormant',
                6       => 'notPresent',
                7       => 'lowerLayerDown',
                default => 'unknown',
            };
        }

        return match (strtolower(trim($status))) {
            'up', 'active', 'online', 'running', 'enabled', '1'
                => 'text-success',
            'down', 'inactive', 'offline', 'disabled', 'error', '2'
                => 'text-danger',
            'warning', 'degraded', 'testing', '3'
                => 'text-warning',
            'dormant', 'notpresent', 'lowerlayerdown', '5', '6', '7'
                => 'text-secondary',
            default
                => 'text-muted',
        };
    }
}
