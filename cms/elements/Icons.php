<?php
namespace Zubr\Site;

final class Icons
{
    public static function prepareTemplate(string $html): string
    {
        return preg_replace_callback('~<head\b[^>]*>(.*?)</head>~is', static function ($match) {
            $head = preg_replace('~<link\b(?=[^>]*\brel\s*=\s*[\'"](?:icon|shortcut icon|apple-touch-icon(?:-precomposed)?|mask-icon|manifest)[\'"])[^>]*>~is', '', $match[1]);
            $head = preg_replace('~<meta\b(?=[^>]*\bname\s*=\s*[\'"](?:theme-color|apple-mobile-web-app-title)[\'"])[^>]*>~is', '', $head);
            $tags = <<<'HTML'
<link rel="icon" href="/boxing-glove.ico?v=2" type="image/x-icon" sizes="16x16 32x32 48x48">
<link rel="icon" href="/boxing-glove.svg?v=2" type="image/svg+xml" sizes="any">
<link rel="icon" href="/boxing-glove-16.png?v=2" type="image/png" sizes="16x16">
<link rel="icon" href="/boxing-glove-32.png?v=2" type="image/png" sizes="32x32">
<link rel="icon" href="/boxing-glove-48.png?v=2" type="image/png" sizes="48x48">
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v=glove-1" sizes="180x180">
<link rel="manifest" href="/site.webmanifest?v=glove-1">
<meta name="theme-color" content="#de0014">
<meta name="apple-mobile-web-app-title" content="ZUBR GYM">
HTML;
            return '<head>' . trim($head) . "\n" . $tags . "\n</head>";
        }, $html);
    }
}
