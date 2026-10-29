<?php
namespace Zubr\Site;
require_once __DIR__ . '/Icons.php';
require_once __DIR__ . '/Contacts.php';

use MODX\Revolution\{modX, modResource, modDocument, modTemplate, modTemplateVar, modTemplateVarResource};

/** Shared resource SEO, canonical URLs, crawl directives and service documents. */
final class Seo
{
    private array $tvs = [];
    public function __construct(private modX $modx) {}

    public static function prepareTemplate(string $html): string
    {
        if (!preg_match('~<head\b[^>]*>(.*?)</head>~is', $html, $match)) return $html;
        $head = preg_replace('~<title\b[^>]*>.*?</title\s*>~is', '', $match[1]);
        $head = preg_replace('~<meta\b(?=[^>]*\b(?:name|property)\s*=\s*[\'"](?:description|robots|og:[^\'"]*|twitter:[^\'"]*)[\'"])[^>]*>~is', '', $head);
        $head = preg_replace('~<link\b(?=[^>]*\brel\s*=\s*[\'"]canonical[\'"])[^>]*>~is', '', $head);
        $head = str_replace('[[!ZubrSeoHead]]', '', $head);
        return Contacts::prepareTemplate(Icons::prepareTemplate(str_replace($match[0], '<head>' . trim($head) . "\n[[!ZubrSeoHead]]\n</head>", $html)));
    }

    public function productionHost(): string
    {
        return (string)$this->modx->getOption('zubr_production_host', null, 'zubr-gym.by');
    }
    public function isProduction(): bool
    {
        $host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
        return in_array($host, [$this->productionHost(), 'www.' . $this->productionHost()], true);
    }
    public function baseUrl(): string
    {
        if ($this->isProduction()) return 'https://' . $this->productionHost() . '/';
        // Use a configured base, never an arbitrary incoming Host header.
        return rtrim((string)$this->modx->getOption('site_url', null, MODX_SITE_URL), '/') . '/';
    }
    public function indexingEnabled(): bool
    {
        return (bool)$this->modx->getOption('zubr_indexing_enabled', null, false);
    }
    private function value(string $name, modResource $resource): string
    {
        if (!array_key_exists($name, $this->tvs)) $this->tvs[$name] = $this->modx->getObject(modTemplateVar::class, ['name' => $name]);
        $tv = $this->tvs[$name];
        if (!$tv) return '';
        // Read current saved values, including an explicitly unchecked checkbox, independently of page cache.
        $saved = $this->modx->getObject(modTemplateVarResource::class, ['tmplvarid' => $tv->get('id'), 'contentid' => $resource->get('id')]);
        return (string)($saved ? $saved->get('value') : $tv->get('default_text'));
    }
    private function serviceIds(): array
    {
        return array_map('intval', [
            $this->modx->getOption('error_page'), $this->modx->getOption('site_unavailable_page'),
            $this->modx->getOption('zubr_robots_resource'), $this->modx->getOption('zubr_sitemap_resource'),
        ]);
    }
    public function indexable(modResource $resource): bool
    {
        return $resource->get('class_key') === modDocument::class && (bool)$resource->get('published') && !$resource->get('deleted')
            && !in_array((int)$resource->get('id'), $this->serviceIds(), true)
            && (int)$resource->get('template') > 0 && $this->value('seo_index', $resource) === '1';
    }
    public function pageCount(modResource $resource): int
    {
        if ($resource->get('uri') !== 'info/blog/') return 1;
        $total = $this->modx->getCount(modResource::class, [
            'parent' => $resource->get('id'), 'context_key' => 'web',
            'template' => 25, 'class_key' => modDocument::class,
            'published' => 1, 'deleted' => 0,
        ]);
        $limit = max(1, (int)$this->modx->getOption('zubr_blog_page_size', null, 6));
        return max(1, (int)ceil($total / $limit));
    }
    public function pageNumber(modResource $resource): int
    {
        if ($resource->get('uri') !== 'info/blog/') return 1;
        $value = $_GET['page'] ?? '1';
        if (!is_scalar($value) || !preg_match('/^[0-9]{1,9}$/D', (string)$value)) return 1;
        return max(1, min($this->pageCount($resource), (int)$value));
    }
    public function url(modResource $resource, int $page = 1): string
    {
        $path = (int)$resource->get('id') === (int)$this->modx->getOption('site_start') ? '' : ltrim((string)$resource->get('uri'), '/');
        return $this->baseUrl() . $path . ($resource->get('uri') === 'info/blog/' && $page > 1 ? '?page=' . $page : '');
    }
    private function plain(string $value): string
    {
        $value = Contacts::expand($value, $this->modx);
        return trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    }
    private function escape(string $value): string
    {
        return str_replace(['[', ']'], ['&#91;', '&#93;'], htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
    private function image(string $value): array
    {
        $fallback = (string)$this->modx->getOption('zubr_seo_default_image', null, '/zubr/images/social-share.jpg');
        $value = trim($value) ?: $fallback;
        if (preg_match('~^https?://~i', $value)) {
            $parts = parse_url($value);
            if (!$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return $this->image('/zubr/images/social-share.jpg');
            return ['url' => $value];
        }
        if (str_contains($value, '..') || str_contains($value, ':') || str_starts_with($value, '//')) return $this->image('/zubr/images/social-share.jpg');
        $relative = ltrim(str_replace('\\', '/', $value), '/');
        $result = ['url' => $this->baseUrl() . $relative];
        $file = MODX_BASE_PATH . $relative;
        if (is_file($file) && ($dimensions = @getimagesize($file))) $result += ['width' => $dimensions[0], 'height' => $dimensions[1], 'type' => $dimensions['mime']];
        return $result;
    }
    private function timestamp(mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) return (int)$value;
        return is_string($value) ? (strtotime($value) ?: 0) : 0;
    }
    public function head(): string
    {
        $r = $this->modx->resource;
        if (!$r) return '';
        $title = $this->plain($this->value('seo_title', $r)) ?: $this->plain((string)($r->get('longtitle') ?: $r->get('pagetitle')));
        $description = $this->plain($this->value('seo_description', $r)) ?: $this->plain((string)$r->get('description'));
        $page = $this->pageNumber($r);
        if ($page > 1) { $title .= ' — страница ' . $page; if ($description !== '') $description .= ' Страница ' . $page . '.'; }
        $url = $this->url($r, $page);
        $image = $this->image($this->value('seo_image', $r));
        $robots = $this->indexingEnabled() && $this->indexable($r) ? 'index, follow' : 'noindex, nofollow';
        $article = str_starts_with((string)$r->get('uri'), 'info/blog/') && $r->get('uri') !== 'info/blog/';
        $meta = fn(string $key, string $value, string $attr = 'name') => '<meta ' . $attr . '="' . $key . '" content="' . $this->escape($value) . '">';
        $tags = ['<title>' . $this->escape($title) . '</title>', $meta('description', $description), $meta('robots', $robots)];
        if (!in_array((int)$r->get('id'), [(int)$this->modx->getOption('error_page'), (int)$this->modx->getOption('site_unavailable_page')], true)) $tags[] = '<link rel="canonical" href="' . $this->escape($url) . '">';
        foreach (['og:type' => $article ? 'article' : 'website', 'og:site_name' => 'ZUBR GYM', 'og:locale' => 'ru_BY', 'og:title' => $title, 'og:description' => $description, 'og:url' => $url, 'og:image' => $image['url'], 'og:image:alt' => $title] as $key => $value) $tags[] = $meta($key, $value, 'property');
        foreach (['width', 'height', 'type'] as $key) if (isset($image[$key])) $tags[] = $meta('og:image:' . $key, (string)$image[$key], 'property');
        foreach (['twitter:card' => 'summary_large_image', 'twitter:title' => $title, 'twitter:description' => $description, 'twitter:image' => $image['url'], 'twitter:image:alt' => $title] as $key => $value) $tags[] = $meta($key, $value);
        if ($article) {
            $published = $this->timestamp($r->get('publishedon')) ?: $this->timestamp($r->get('createdon'));
            if ($published > 0) $tags[] = $meta('article:published_time', date(DATE_ATOM, $published), 'property');
            if (($modified = $this->timestamp($r->get('editedon'))) > 0) $tags[] = $meta('article:modified_time', date(DATE_ATOM, $modified), 'property');
        }
        return implode("\n", $tags);
    }
    private function redirect(modResource $resource, array $query = []): void
    {
        unset($query['q'], $query['id']);
        if ($resource->get('uri') === 'info/blog/') {
            $page = $this->pageNumber($resource);
            unset($query['page']);
            if ($page > 1) $query['page'] = $page;
        }
        $url = $this->url($resource);
        if ($query) $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $this->modx->sendRedirect($url, ['responseCode' => 'HTTP/1.1 301 Moved Permanently']);
    }
    public function handleRequest(): void
    {
        if ($this->modx->context->get('key') !== 'web' || PHP_SAPI === 'cli') return;
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true) || isset($_GET['preview'])) return;
        $rawPath = explode('?', $_SERVER['REQUEST_URI'] ?? '/')[0];
        $path = rawurldecode($rawPath);
        if (str_contains($path, "\0")) return;
        $clean = '/' . trim(preg_replace('~/+~', '/', $path), '/');
        $resource = null;
        if (($clean === '/index.php' || $clean === '/') && isset($_GET['id']) && is_scalar($_GET['id']) && ctype_digit((string)$_GET['id'])) $resource = $this->modx->getObject(modResource::class, ['id' => (int)$_GET['id'], 'context_key' => 'web', 'published' => 1, 'deleted' => 0]);
        if (!$resource && $clean === '/index.php') {
            if (isset($_GET['q']) && is_string($_GET['q']) && $_GET['q'] !== '') $clean = '/' . trim($_GET['q'], '/');
            else $resource = $this->modx->getObject(modResource::class, (int)$this->modx->getOption('site_start'));
        }
        if (!$resource && $clean !== '/') $resource = $this->modx->getObject(modResource::class, ['uri:IN' => [ltrim($clean, '/'), ltrim($clean, '/') . '/'], 'context_key' => 'web', 'published' => 1, 'deleted' => 0]);
        if ($resource) {
            $expected = parse_url($this->url($resource), PHP_URL_PATH);
            if ($rawPath !== $expected || $path !== $rawPath || isset($_GET['id'])) $this->redirect($resource, $_GET);
        }
    }
    public function loaded(): void
    {
        if ($this->modx->context->get('key') !== 'web' || PHP_SAPI === 'cli' || !$this->modx->resource) return;
        $r = $this->modx->resource;
        $id = (int)$r->get('id');
        if ($id === (int)$this->modx->getOption('error_page')) http_response_code(404);
        if ($id === (int)$this->modx->getOption('site_unavailable_page')) { http_response_code(503); header('Retry-After: 300'); }
        $robots = $this->indexingEnabled() && $this->indexable($r) ? 'index, follow' : 'noindex, nofollow';
        header('X-Robots-Tag: ' . $robots);
        if ($r->get('uri') === 'info/blog/' && isset($_GET['page'])) {
            $page = $this->pageNumber($r);
            if (!is_scalar($_GET['page']) || (string)$_GET['page'] !== (string)$page || $page === 1) $this->redirect($r, $_GET);
        }
    }
    public function robots(): string
    {
        if (!$this->indexingEnabled()) return "User-agent: *\nDisallow: /\n";
        return "User-agent: *\nDisallow: /manager/\nDisallow: /connectors/\nDisallow: /core/\nDisallow: /setup/\nDisallow: /404/\nDisallow: /503/\n\nSitemap: " . $this->baseUrl() . "sitemap.xml\n";
    }
    public function sitemap(): string
    {
        $ids = [];
        foreach ($this->modx->getIterator(modResource::class, ['context_key' => 'web', 'published' => 1, 'deleted' => 0, 'class_key' => modDocument::class]) as $r) if ($this->indexable($r)) $ids[] = (int)$r->get('id');
        $rows = $ids ? $this->modx->runSnippet('pdoSitemap', ['parents' => 0, 'resources' => implode(',', $ids), 'context' => 'web', 'showHidden' => 1, 'showUnsearchable' => 1, 'return' => 'data', 'forceXML' => false, 'cache' => 0, 'limit' => 0]) : [];
        if (!is_array($rows)) throw new \RuntimeException('pdoSitemap did not return sitemap data');
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
        foreach ($rows as $row) {
            $r = $this->modx->getObject(modResource::class, (int)$row['id']);
            if (!$r) continue;
            $node = $xml->addChild('url');
            $node->addChild('loc', htmlspecialchars($this->url($r), ENT_XML1, 'UTF-8'));
            $time = $this->timestamp($r->get('editedon')) ?: $this->timestamp($r->get('createdon'));
            if ($time > 0) $node->addChild('lastmod', date(DATE_ATOM, $time));
        }
        return $xml->asXML();
    }
}
