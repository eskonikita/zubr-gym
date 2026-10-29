<?php
/** Export all application tables for production, without sessions or local paths. */
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/Values.php';
$root = str_replace('\\', '/', dirname(__DIR__, 2)) . '/';
$output = $root . 'artifacts/zubr-gym-production.sql.gz';
foreach ($argv as $arg) if (str_starts_with($arg, '--output=')) $output = substr($arg, 9);
if (!str_ends_with($output, '.sql.gz')) throw new RuntimeException('Output must end in .sql.gz');
if (!is_dir(dirname($output))) mkdir(dirname($output), 0700, true);
define('MODX_API_MODE', true);
require $root . 'public/index.php';
$modx->initialize('mgr');
$pdo = $modx->getConnection()->pdo;
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$prefix = $modx->getOption('table_prefix');
if (!preg_match('/^[a-zA-Z0-9_]+$/D', $prefix)) throw new RuntimeException('Invalid table prefix');
$skipData = array_map(fn($table) => $prefix . $table, ['session', 'manager_log', 'event_log', 'active_users', 'register_messages', 'register_queues', 'register_topics']);
$replacements = [
	$root => '__ZUBR_PROJECT_ROOT__/',
	str_replace('/', '\\', rtrim($root, '/')) . '/' => '__ZUBR_PROJECT_ROOT__/',
	str_replace('/', '\\', $root) => '__ZUBR_PROJECT_ROOT__/',
	'http://zubr-gym.local' => 'https://zubr-gym.by',
	'https://zubr-gym.local' => 'https://zubr-gym.by',
	'zubr-gym.local' => 'zubr-gym.by',
];
$settings = [
	'site_url' => 'https://zubr-gym.by/', 'http_host' => 'zubr-gym.by',
	'url_scheme' => 'https://', 'zubr_production_host' => 'zubr-gym.by',
	'zubr_indexing_enabled' => '1', 'session_cookie_secure' => '1',
	'session_cookie_samesite' => 'Lax',
];
$handle = gzopen($output . '.tmp', 'wb9');
if (!$handle) throw new RuntimeException('Cannot create database export');
$write = function(string $sql) use ($handle): void {
	if (gzwrite($handle, $sql . "\n") === false) throw new RuntimeException('Export write failed');
};
$tables = array_values(array_filter($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), fn($name) => str_starts_with($name, $prefix)));
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
$write('-- ZUBR GYM production export: https://zubr-gym.by/');
$write('-- Import into an empty database, then run: php tools/deploy/configure.php --apply');
$write('SET NAMES utf8mb4; SET FOREIGN_KEY_CHECKS=0; SET SQL_MODE=\'NO_AUTO_VALUE_ON_ZERO\';');
$counts = [];
foreach ($tables as $table) {
	$create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
	$write("DROP TABLE IF EXISTS `{$table}`;");
	$write($create . ';');
	$counts[$table] = 0;
	if (in_array($table, $skipData, true)) continue;
	$query = $pdo->query("SELECT * FROM `{$table}`");
	while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
		foreach ($row as $key => $value) $row[$key] = Zubr\Deploy\replaceValue($value, $replacements);
		if (in_array($table, [$prefix . 'system_settings', $prefix . 'context_setting'], true) && isset($settings[$row['key']])) $row['value'] = $settings[$row['key']];
		$fields = implode(',', array_map(fn($key) => '`' . $key . '`', array_keys($row)));
		$values = implode(',', array_map(fn($value) => $value === null ? 'NULL' : $pdo->quote((string)$value), array_values($row)));
		$write("INSERT INTO `{$table}` ({$fields}) VALUES ({$values});");
		$counts[$table]++;
	}
}
$write('SET FOREIGN_KEY_CHECKS=1;');
$pdo->commit();
gzclose($handle);
if (!rename($output . '.tmp', $output)) throw new RuntimeException('Cannot finalize SQL export');
file_put_contents($output . '.json', json_encode(['host' => 'https://zubr-gym.by/', 'created_utc' => gmdate('c'), 'table_prefix' => $prefix, 'tables' => $counts, 'sha256' => hash_file('sha256', $output), 'bytes' => filesize($output), 'transient_tables_empty' => $skipData], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo count($counts) . ' tables exported; ' . filesize($output) . " compressed bytes. Local database unchanged.\n";
