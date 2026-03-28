<?php declare(strict_types=1);

/**
 * Database Configuration
 *
 * Returns a PDO-ready configuration array consumed by NOC\Core\Database.
 * Constants are expected to be defined before this file is loaded
 * (see config/app.php).
 *
 * @package NOC\Config
 * @version 1.0.0
 */

return [
    'host'     => defined('DB_HOST')    ? DB_HOST    : 'localhost',
    'port'     => defined('DB_PORT')    ? DB_PORT    : 3306,
    'dbname'   => defined('DB_NAME')    ? DB_NAME    : 'noc_manager',
    'username' => defined('DB_USER')    ? DB_USER    : 'noc_user',
    'password' => defined('DB_PASS')    ? DB_PASS    : '',
    'charset'  => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',

    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::ATTR_TIMEOUT            => 5,
    ],
];
