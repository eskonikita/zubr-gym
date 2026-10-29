<?php
namespace Zubr\Site;

use MODX\Revolution\modX;

/** Page-specific differences for the single shared head chunk. */
final class PageHead
{
    public function __construct(private modX $modx) {}

    public function render(string $part): string
    {
        $template = (int)$this->modx->resource?->get('template');

        if ($part === 'frameSources') {
            return $template === 21 ? 'https://yandex.by https://yandex.ru https://yandex.com' : "'none'";
        }

        if ($part === 'preload') {
            if ($template !== 2) return '';
            $blocks = json_decode((string)$this->modx->resource?->getTVValue('home_blocks'), true);
            $image = (string)($blocks[0]['hero_image'] ?? '');
            if ($image === '') $image = 'zubr/images/bison-hero.webp';
            if (!preg_match('~^(?:https?:)?//~i', $image)) $image = '/' . ltrim($image, '/');
            return '<link rel="preload" href="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" as="image" fetchpriority="high">';
        }

        if ($part === 'bodyClass') {
            $classes = [
                2 => '',
                3 => 'coaches-page error-page',
                4 => 'coaches-page club-page',
                5 => 'coaches-page directions-page',
                6 => 'coaches-page audience-page',
                7 => 'coaches-page club-page info-page',
                8 => 'coaches-page legal-page',
                10 => 'coaches-page',
                11 => 'coaches-page first-visit-page',
                12 => 'coaches-page gallery-page',
                13 => 'coaches-page coach-profile-page',
                14 => 'coaches-page discipline-page discipline-page--' . preg_replace('/[^a-z0-9-]/i', '', (string)$this->modx->resource?->get('alias')),
                16 => 'coaches-page discipline-page audience-detail',
                20 => 'coaches-page blog-page',
                21 => 'coaches-page contacts-page',
                22 => 'coaches-page faq-page',
                23 => 'coaches-page prices-page',
                24 => 'coaches-page schedule-page',
                25 => 'coaches-page blog-page blog-post',
                28 => 'coaches-page error-page',
            ];
            return htmlspecialchars($classes[$template] ?? 'coaches-page', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        }

        $assets = [];
        if ($part === 'styles') {
            if ($template !== 2) $assets[] = '/zubr/coaches.css?v=intro-wrap-5';
            $specific = [
                4 => ['/zubr/club.css?v=dynamic-hall-11'],
                5 => ['/zubr/directions-page.css?v=1'],
                6 => ['/zubr/audience.css?v=4'],
                7 => ['/zubr/club.css?v=dynamic-info-3'],
                8 => ['/zubr/legal.css?v=1'],
                11 => ['/zubr/first-visit.css?v=1'],
                12 => ['/zubr/gallery.css?v=intro-wrap-2'],
                13 => ['/zubr/coach-profile.css?v=shared-breadcrumbs-3'],
                14 => ['/zubr/discipline.css?v=1'],
                16 => ['/zubr/discipline.css?v=1', '/zubr/audience-detail.css?v=2'],
                20 => ['/zubr/blog.css?v=pdo-blog-8'],
                21 => ['/zubr/contacts.css?v=map-consent-3', '/zubr/map-consent.js?v=cookie-2'],
                22 => ['/zubr/faq.css?v=full-width-2'],
                23 => ['/zubr/prices.css?v=membership-cards-1'],
                24 => ['/zubr/schedule.css?v=mobile-filter-scroll-3', '/zubr/schedule.js?v=three-filters-1'],
                25 => ['/zubr/blog.css?v=pdo-blog-8'],
            ];
            $assets = array_merge($assets, $specific[$template] ?? []);
        } elseif ($part === 'afterOrganization') {
            if ($template === 14) $assets[] = '/zubr/direction-story.css?v=1';
        }

        return implode("\n\t\t", array_map(static function ($url) {
            $url = htmlspecialchars($url, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
            return str_contains($url, '.js?')
                ? '<script src="'.$url.'" defer></script>'
                : '<link rel="stylesheet" href="'.$url.'">';
        }, $assets));
    }
}
