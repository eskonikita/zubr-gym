<?php
/** Replace only paths of the two optimized PNGs; never import complete templates. */
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 2) . '/';
define('MODX_API_MODE', true);
require $root . 'public/index.php';
$modx->initialize('mgr');

$paths = [
	'zubr/images/bison-hero.png' => 'zubr/images/bison-hero.webp',
	'zubr/images/belarus-kickboxing-federation-transparent.png' => 'zubr/images/belarus-kickboxing-federation-transparent.webp',
];
$models = [
	MODX\Revolution\modTemplate::class => ['content'],
	MODX\Revolution\modChunk::class => ['snippet'],
	MODX\Revolution\modResource::class => ['content'],
	MODX\Revolution\modTemplateVar::class => ['default_text'],
	MODX\Revolution\modTemplateVarResource::class => ['value'],
	MODX\Revolution\modSystemSetting::class => ['value'],
];
$changed = 0;
foreach ($models as $class => $fields) {
	foreach ($modx->getCollection($class) as $object) {
		$dirty = false;
		foreach ($fields as $field) {
			$old = (string)$object->get($field);
			// JSON TVs can contain either plain or escaped slashes.
			$new = strtr($old, $paths);
			foreach ($paths as $from => $to) {
				$new = str_replace(str_replace('/', '\\/', $from), str_replace('/', '\\/', $to), $new);
			}
			if ($new !== $old) {
				$object->set($field, $new);
				$dirty = true;
			}
		}
		if ($dirty) {
			if (!$object->save()) throw new RuntimeException('Cannot save optimized image reference');
			$changed++;
		}
	}
}
$modx->cacheManager->refresh();
echo "Updated image paths in {$changed} CMS records.\n";
