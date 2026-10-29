<?php
/** Run on the target server: --init before SQL import, --apply after import. */
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/Values.php';
$root = str_replace('\\', '/', dirname(__DIR__, 2)) . '/';
$private = $root . 'cms/private/production.php';
if (!is_file($private)) throw new RuntimeException('Copy tools/deploy/production.example.php to cms/private/production.php and fill database credentials first.');
$config = require $private;
foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'table_prefix', 'host'] as $key) {
	if (!isset($config[$key]) || $config[$key] === '' || $config[$key] === 'CHANGE_ME') throw new RuntimeException('Fill production configuration field: ' . $key);
}
if ($config['host'] !== 'zubr-gym.by') throw new RuntimeException('Production host must be zubr-gym.by');
if (!preg_match('/^[a-zA-Z0-9_]+$/D', $config['table_prefix'])) throw new RuntimeException('Invalid table prefix');
if (!in_array('--init', $argv, true) && !in_array('--apply', $argv, true)) exit("Usage: php tools/deploy/configure.php --init|--apply\n");

foreach (['public/core/config', 'public/core/cache', 'public/core/logs', 'public/core/packages', 'public/core/export', 'public/core/import'] as $dir) {
	if (!is_dir($root . $dir) && !mkdir($root . $dir, 0755, true)) throw new RuntimeException('Cannot create ' . $dir);
}
$entries = [
	'public/config.core.php' => "__DIR__ . '/core/'",
	'public/manager/config.core.php' => "dirname(__DIR__) . '/core/'",
	'public/connectors/config.core.php' => "dirname(__DIR__) . '/core/'",
];
foreach ($entries as $file => $expression) {
	if (file_put_contents($root . $file, "<?php\ndefine('MODX_CORE_PATH', {$expression});\ndefine('MODX_CONFIG_KEY', 'config');\n") === false) throw new RuntimeException('Cannot write ' . $file);
}
if (!copy(__DIR__ . '/config.inc.php.dist', $root . 'public/core/config/config.inc.php')) throw new RuntimeException('Cannot write config.inc.php');
if (in_array('--init', $argv, true)) exit("Portable configuration created. Import SQL, then run --apply.\n");

// Resolve deployment markers through PDO before bootstrapping MODX or any packages.
$pdo = new PDO('mysql:host=' . $config['db_host'] . ';port=' . (int)$config['db_port'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4', $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$prefix = $config['table_prefix'];
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
	if (!str_starts_with($table, $prefix)) continue;
	$columns = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
	$primary = array_column(array_filter($columns, fn($c) => $c['Key'] === 'PRI'), 'Field');
	if (!$primary) continue;
	foreach ($columns as $column) {
		if (!preg_match('/char|text|blob/i', $column['Type'])) continue;
		$field = $column['Field'];
		$query = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE `' . $field . '` LIKE ?');
		$query->execute(['%__ZUBR_PROJECT_ROOT__%']);
		foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$value = Zubr\Deploy\replaceValue($row[$field], ['__ZUBR_PROJECT_ROOT__/' => $root]);
			$sql = 'UPDATE `' . $table . '` SET `' . $field . '`=? WHERE ' . implode(' AND ', array_map(fn($key) => '`' . $key . '`=?', $primary));
			$pdo->prepare($sql)->execute([$value, ...array_map(fn($key) => $row[$key], $primary)]);
		}
	}
}
define('MODX_API_MODE', true);
require $root . 'public/index.php';
$modx->initialize('mgr');
foreach ([
	'http_host' => 'zubr-gym.by', 'site_url' => 'https://zubr-gym.by/',
	'url_scheme' => 'https://', 'zubr_production_host' => 'zubr-gym.by',
	'zubr_indexing_enabled' => '1', 'friendly_urls' => '1',
	'friendly_alias_translit_class_path' => $root . 'cms/model/',
	'session_cookie_secure' => '1', 'session_cookie_samesite' => 'Lax',
] as $key => $value) {
	$setting = $modx->getObject(MODX\Revolution\modSystemSetting::class, ['key' => $key]);
	if (!$setting) {
		$setting = $modx->newObject(MODX\Revolution\modSystemSetting::class);
		$setting->fromArray(['key' => $key, 'namespace' => 'core', 'area' => 'site'], '', true, true);
	}
	$setting->set('value', $value);
	if (!$setting->save()) throw new RuntimeException('Cannot save ' . $key);
}
$modx->cacheManager->refresh();
echo "Production configured: https://zubr-gym.by/ . Cache refreshed.\n";
