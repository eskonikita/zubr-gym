<?php
/** Gallery editor: one MIGX row has exactly a photo and alternative text. */
if (PHP_SAPI !== 'cli' || !in_array('--apply', $argv, true)) exit("Usage: php tools/modx/configure-gallery-fields.php --apply\n");
$root = dirname(__DIR__, 2) . '/';
define('MODX_API_MODE', true);
require $root . 'public/index.php';
$modx->initialize('mgr');
use MODX\Revolution\{modCategory, modChunk, modTemplate, modTemplateVar, modTemplateVarTemplate, modTemplateVarResource, modResource};
$template = $modx->getObject(modTemplate::class, 12);
$html = $template->get('content');
$tv = $modx->getObject(modTemplateVar::class, ['name' => 'gallery_photos']);
$new = !$tv;
if ($new) $tv = $modx->newObject(modTemplateVar::class);
$source = $modx->getObject(\MODX\Revolution\Sources\modMediaSource::class, ['name' => 'Gallery images']);
if (!$source) {
    $source = $modx->newObject(\MODX\Revolution\Sources\modFileMediaSource::class);
    $source->fromArray(['name' => 'Gallery images', 'description' => 'Фотографии галереи; URL от корня сайта.', 'class_key' => \MODX\Revolution\Sources\modFileMediaSource::class]);
    $source->set('properties', [
        'basePath' => ['name' => 'basePath', 'value' => '', 'type' => 'textfield'],
        'basePathRelative' => ['name' => 'basePathRelative', 'value' => true, 'type' => 'combo-boolean'],
        'baseUrl' => ['name' => 'baseUrl', 'value' => '/', 'type' => 'textfield'],
        'baseUrlRelative' => ['name' => 'baseUrlRelative', 'value' => false, 'type' => 'combo-boolean'],
    ]);
    if (!$source->save()) throw new RuntimeException('Gallery media source save failed');
}
$category = $modx->getObject(modCategory::class, ['category' => 'Галерея']);
if (!$category) {
    $category = $modx->newObject(modCategory::class);
    $category->set('category', 'Галерея');
    if (!$category->save()) throw new RuntimeException('Category save failed');
}
$tv->fromArray([
    'name' => 'gallery_photos', 'caption' => 'Фотографии галереи', 'type' => 'migx',
    'category' => $category->get('id'), 'default_text' => '', 'display' => 'default',
    'description' => 'Фото и alt. Alt также используется как подпись при просмотре. Порядок строк — порядок фотографий.',
    'input_properties' => [
        'formtabs' => json_encode([['caption' => 'Фотография', 'fields' => [
            ['field' => 'photo', 'caption' => 'Фото', 'inputTVtype' => 'image', 'sourceFrom' => 'config', 'sources' => ['web' => (int)$source->get('id')]],
            ['field' => 'alt', 'caption' => 'Alt — описание фотографии', 'inputTVtype' => 'text'],
            ['field' => 'shape', 'caption' => 'Форма в слайдере главной', 'inputTVtype' => 'listbox', 'inputOptionValues' => 'Вертикальная==portrait||Широкая==wide', 'default' => 'portrait', 'description' => 'На страницу галереи не влияет.'],
        ]]], JSON_UNESCAPED_UNICODE),
        'columns' => json_encode([
            ['header' => 'Фото', 'dataIndex' => 'photo', 'width' => 180, 'renderer' => 'this.renderImage'],
            ['header' => 'Alt', 'dataIndex' => 'alt', 'width' => 440],
        ], JSON_UNESCAPED_UNICODE),
        'btntext' => 'Добавить фото',
    ],
]);
if (!$tv->save()) throw new RuntimeException('TV save failed');
$criteria = ['tmplvarid' => $tv->get('id'), 'templateid' => 12];
if (!$modx->getObject(modTemplateVarTemplate::class, $criteria)) {
    $link = $modx->newObject(modTemplateVarTemplate::class);
    $link->fromArray($criteria, '', true, true);
    if (!$link->save()) throw new RuntimeException('TV assignment failed');
}
// Seed from the actual current template, rather than overwriting edited gallery data on repeat runs.
if ($new) {
    preg_match_all('~<button\b[^>]*data-gallery="([^"]+)"[^>]*data-caption="([^"]*)"~', $html, $matches, PREG_SET_ORDER);
    if (!$matches) throw new RuntimeException('Initial gallery photos missing');
    $rows = [];
    foreach ($matches as $index => $match) $rows[] = [
        'MIGX_id' => $index + 1,
        'photo' => html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'),
        'alt' => html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'),
    ];
    foreach ($modx->getCollection(modResource::class, ['template' => 12, 'deleted' => 0]) as $resource) {
        if (!$resource->setTVValue('gallery_photos', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) throw new RuntimeException('Photo data save failed');
    }
}
// Image pickers store relative paths; native sources prepend baseUrl during rendering.
foreach ($modx->getCollection(modResource::class, ['template' => 12, 'deleted' => 0]) as $resource) {
    $rows = json_decode((string)$resource->getTVValue('gallery_photos'), true);
    $changed = false;
    foreach ($rows as &$row) if (isset($row['photo']) && str_starts_with($row['photo'], '/') && !str_starts_with($row['photo'], '//')) {
        $row['photo'] = substr($row['photo'], 1);
        $changed = true;
    }
    unset($row);
    if ($changed) $resource->setTVValue('gallery_photos', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
$common = $modx->getObject(modCategory::class, ['category' => 'Общие']);
foreach (['galleryPhoto', 'photoGallery'] as $name) {
    $chunk = $modx->getObject(modChunk::class, ['name' => $name]) ?: $modx->newObject(modChunk::class);
    $chunk->fromArray(['name' => $name, 'category' => $common->get('id'), 'snippet' => file_get_contents($root . 'cms/chunks/' . $name . '.html')]);
    if (!$chunk->save()) throw new RuntimeException('Chunk save failed');
}
if (!str_contains($html, '[[$photoGallery]]')) {
    $html = preg_replace('~<section class="section" aria-label="Фотографии ZUBR GYM">.*?</section>~s', '[[$photoGallery]]', $html, -1, $count);
    if ($count !== 1) throw new RuntimeException('Unexpected gallery structure');
    $template->set('content', $html);
    if (!$template->save()) throw new RuntimeException('Template save failed');
}
$file = $root . 'cms/template-registry.json';
$registry = json_decode(file_get_contents($file), true);
$registry['fields']['gallery_photos'] = (int)$tv->get('id');
$registry['chunks'] = array_values(array_unique(array_merge($registry['chunks'], ['galleryPhoto', 'photoGallery'])));
file_put_contents($file, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$modx->cacheManager->refresh();
echo "Gallery MIGX photo/alt fields applied.\n";
