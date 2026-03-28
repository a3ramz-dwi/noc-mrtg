<?php declare(strict_types=1);

namespace NOC\MRTG;

/**
 * MrtgDataParser — Parses MRTG .log files and formats data for Chart.js.
 *
 * MRTG log files contain time-series traffic data at four resolution tiers:
 *   • Daily   — one entry per 5 minutes  (600 samples ≈ 50 hours)
 *   • Weekly  — one entry per 30 minutes (600 samples ≈ 12.5 days)
 *   • Monthly — one entry per 2 hours    (600 samples ≈ 50 days)
 *   • Yearly  — one entry per day        (600 samples ≈ 1.6 years)
 *
 * Each log line has the format:
 *   <unix_timestamp> <avg_bytes_in> <avg_bytes_out> <max_bytes_in> <max_bytes_out>
 *
 * The first line contains only the timestamp of the most recent MRTG run.
 * Subsequent lines are in reverse-chronological order (most recent first).
 *
 * @package NOC\MRTG
 * @version 1.0.0
 */
final class MrtgDataParser
{
    /** Number of samples per resolution tier in a standard MRTG log */
    private const DAILY_SAMPLES   = 600;
    private const WEEKLY_SAMPLES  = 600;
    private const MONTHLY_SAMPLES = 600;
    private const YEARLY_SAMPLES  = 600;

    /** Sample interval durations in seconds */
    private const INTERVAL_DAILY   = 300;    //  5 minutes
    private const INTERVAL_WEEKLY  = 1_800;  // 30 minutes
    private const INTERVAL_MONTHLY = 7_200;  //  2 hours
    private const INTERVAL_YEARLY  = 86_400; //  1 day

    /** Column indices within a parsed log data line */
    private const COL_TIMESTAMP = 0;
    private const COL_BYTES_IN  = 1;
    private const COL_BYTES_OUT = 2;
    private const COL_MAX_IN    = 3;
    private const COL_MAX_OUT   = 4;

    public function __construct(
        private readonly string $mrtgDir,
    ) {}

    // -------------------------------------------------------------------------
    // Log file parsing
    // -------------------------------------------------------------------------

    /**
     * Parse a complete MRTG .log file and return all raw data lines.
     *
     * Each element in the returned array is a five-element array:
     *   [timestamp (int), bytes_in (float), bytes_out (float), max_in (float), max_out (float)]
     *
     * The current-timestamp header line is omitted from the result.
     *
     * @param  string $logFile  Absolute path to the MRTG .log file.
     * @return array<int,array{int,float,float,float,float}>  Parsed data rows.
     * @throws \InvalidArgumentException  If the file does not exist or is unreadable.
     */
    public function parseLog(string $logFile): array
    {
        if (!is_file($logFile) || !is_readable($logFile)) {
            throw new \InvalidArgumentException("MRTG log file not found or unreadable: {$logFile}");
        }

        $handle = @fopen($logFile, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException("Cannot open MRTG log file: {$logFile}");
        }

        $rows       = [];
        $lineNumber = 0;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            ++$lineNumber;

            // The first non-empty line is the MRTG-run timestamp header; skip it.
            if ($lineNumber === 1 && !str_contains($line, ' ')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            if ($parts === false || count($parts) < 3) {
                continue;
            }

            $rows[] = [
                (int)   $parts[self::COL_TIMESTAMP],
                (float) ($parts[self::COL_BYTES_IN]  ?? 0),
                (float) ($parts[self::COL_BYTES_OUT] ?? 0),
                (float) ($parts[self::COL_MAX_IN]    ?? 0),
                (float) ($parts[self::COL_MAX_OUT]   ?? 0),
            ];
        }

        fclose($handle);

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Resolution-specific parsers
    // -------------------------------------------------------------------------

    /**
     * Return the daily-resolution data slice (last 600 × 5-minute samples).
     *
     * @param  string $logFile  Absolute path to the MRTG .log file.
     * @return array<int,array{int,float,float,float,float}>
     */
    public function parseDailyData(string $logFile): array
    {
        $all = $this->parseLog($logFile);
        return array_slice($all, 0, self::DAILY_SAMPLES);
    }

    /**
     * Return the weekly-resolution data slice (samples 601–1200).
     *
     * @param  string $logFile
     * @return array<int,array{int,float,float,float,float}>
     */
    public function parseWeeklyData(string $logFile): array
    {
        $all = $this->parseLog($logFile);
        return array_slice($all, self::DAILY_SAMPLES, self::WEEKLY_SAMPLES);
    }

    /**
     * Return the monthly-resolution data slice (samples 1201–1800).
     *
     * @param  string $logFile
     * @return array<int,array{int,float,float,float,float}>
     */
    public function parseMonthlyData(string $logFile): array
    {
        $all = $this->parseLog($logFile);
        return array_slice(
            $all,
            self::DAILY_SAMPLES + self::WEEKLY_SAMPLES,
            self::MONTHLY_SAMPLES,
        );
    }

    /**
     * Return the yearly-resolution data slice (samples 1801–2400).
     *
     * @param  string $logFile
     * @return array<int,array{int,float,float,float,float}>
     */
    public function parseYearlyData(string $logFile): array
    {
        $all = $this->parseLog($logFile);
        return array_slice(
            $all,
            self::DAILY_SAMPLES + self::WEEKLY_SAMPLES + self::MONTHLY_SAMPLES,
            self::YEARLY_SAMPLES,
        );
    }

    // -------------------------------------------------------------------------
    // Point-in-time helpers
    // -------------------------------------------------------------------------

    /**
     * Return the most recent bytes_in and bytes_out values from the log.
     *
     * @param  string $logFile  Absolute path to the MRTG .log file.
     * @return array{bytes_in:float,bytes_out:float,timestamp:int}|null
     *         Most recent measurement, or null if the file is empty.
     */
    public function getLatestValues(string $logFile): ?array
    {
        $data = $this->parseDailyData($logFile);

        if (empty($data)) {
            return null;
        }

        $latest = $data[0];

        return [
            'timestamp' => $latest[self::COL_TIMESTAMP],
            'bytes_in'  => $latest[self::COL_BYTES_IN],
            'bytes_out' => $latest[self::COL_BYTES_OUT],
        ];
    }

    // -------------------------------------------------------------------------
    // Path construction
    // -------------------------------------------------------------------------

    /**
     * Construct the absolute path to an MRTG log file for a given target.
     *
     * MRTG creates log files named after the Target[] key.  This method maps
     * targetId and type to the naming convention used by MrtgConfigGenerator:
     *   interface → if_r{routerId}_{ifIndex}.log
     *   queue     → q_r{routerId}_{queueIndex}.log
     *   pppoe     → ppp_r{routerId}_{pppoeId}.log
     *
     * @param  string     $targetId  The MRTG Target[] key (e.g. 'if_r1_3').
     * @param  string     $type      'interface', 'queue', or 'pppoe'.
     * @return string                Absolute path to the .log file.
     */
    public function getLogFilePath(string $targetId, string $type): string
    {
        $subDir = $this->resolveSubDir($targetId);

        return rtrim($this->mrtgDir, '/') . ($subDir !== '' ? '/' . $subDir : '') . '/' . $targetId . '.log';
    }

    // -------------------------------------------------------------------------
    // Chart.js formatting
    // -------------------------------------------------------------------------

    /**
     * Format raw MRTG data into a structure consumable by Chart.js.
     *
     * Returns:
     * ```json
     * {
     *   "labels":    [ISO-8601 strings …],
     *   "bytesIn":   [float …],
     *   "bytesOut":  [float …],
     *   "bitsIn":    [float …],
     *   "bitsOut":   [float …]
     * }
     * ```
     *
     * Data is sorted into ascending chronological order before formatting
     * because MRTG stores samples most-recent-first.
     *
     * @param  array<int,array{int,float,float,float,float}> $rawData
     * @return array{labels:list<string>,bytesIn:list<float>,bytesOut:list<float>,bitsIn:list<float>,bitsOut:list<float>}
     */
    public function formatDataForChart(array $rawData): array
    {
        // Sort ascending (oldest first) for Chart.js time-series display.
        usort($rawData, static fn (array $a, array $b) => $a[0] <=> $b[0]);

        $labels   = [];
        $bytesIn  = [];
        $bytesOut = [];
        $bitsIn   = [];
        $bitsOut  = [];

        foreach ($rawData as $row) {
            $labels[]   = date('c', $row[self::COL_TIMESTAMP]);
            $bytesIn[]  = $row[self::COL_BYTES_IN];
            $bytesOut[] = $row[self::COL_BYTES_OUT];
            $bitsIn[]   = $row[self::COL_BYTES_IN]  * 8.0;
            $bitsOut[]  = $row[self::COL_BYTES_OUT] * 8.0;
        }

        return [
            'labels'   => $labels,
            'bytesIn'  => $bytesIn,
            'bytesOut' => $bytesOut,
            'bitsIn'   => $bitsIn,
            'bitsOut'  => $bitsOut,
        ];
    }

    // -------------------------------------------------------------------------
    // Statistical helpers
    // -------------------------------------------------------------------------

    /**
     * Find the peak inbound and outbound values in a data set.
     *
     * @param  array<int,array{int,float,float,float,float}> $data
     * @return array{peak_in:float,peak_out:float,peak_in_ts:int,peak_out_ts:int}
     */
    public function calculatePeak(array $data): array
    {
        $peakIn    = 0.0;
        $peakOut   = 0.0;
        $peakInTs  = 0;
        $peakOutTs = 0;

        foreach ($data as $row) {
            if ($row[self::COL_BYTES_IN] > $peakIn) {
                $peakIn   = $row[self::COL_BYTES_IN];
                $peakInTs = $row[self::COL_TIMESTAMP];
            }

            if ($row[self::COL_BYTES_OUT] > $peakOut) {
                $peakOut   = $row[self::COL_BYTES_OUT];
                $peakOutTs = $row[self::COL_TIMESTAMP];
            }
        }

        return [
            'peak_in'     => $peakIn,
            'peak_out'    => $peakOut,
            'peak_in_ts'  => $peakInTs,
            'peak_out_ts' => $peakOutTs,
        ];
    }

    /**
     * Calculate the mean inbound and outbound values across a data set.
     *
     * Zero-value rows (gaps in the log file) are excluded from the average
     * to avoid artificially deflating results for sparse data sets.
     *
     * @param  array<int,array{int,float,float,float,float}> $data
     * @return array{avg_in:float,avg_out:float,sample_count:int}
     */
    public function calculateAverage(array $data): array
    {
        $sumIn  = 0.0;
        $sumOut = 0.0;
        $count  = 0;

        foreach ($data as $row) {
            if ($row[self::COL_BYTES_IN] > 0 || $row[self::COL_BYTES_OUT] > 0) {
                $sumIn  += $row[self::COL_BYTES_IN];
                $sumOut += $row[self::COL_BYTES_OUT];
                ++$count;
            }
        }

        if ($count === 0) {
            return ['avg_in' => 0.0, 'avg_out' => 0.0, 'sample_count' => 0];
        }

        return [
            'avg_in'       => $sumIn  / $count,
            'avg_out'      => $sumOut / $count,
            'sample_count' => $count,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the sub-directory within $mrtgDir for a given target ID.
     *
     * MRTG's WorkDir/LogDir configuration places all log files for a router
     * in the same directory.  The router ID is encoded in the target ID
     * prefix (e.g. "if_r3_10" → routerId = 3).
     *
     * @param  string $targetId  Target key, e.g. "if_r3_10".
     * @return string            Sub-directory name, e.g. "3".
     */
    private function resolveSubDir(string $targetId): string
    {
        if (preg_match('/_r(\d+)_/', $targetId, $m)) {
            return $m[1];
        }

        // Fallback: place directly in mrtgDir root.
        return '';
    }
}
