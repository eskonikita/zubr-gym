<?php
/** Native ICU transliteration service for MODX resource aliases; no external translation API. */
class ZubrTransliterate
{
    public function __construct($modx, array $config = []) {}
    public function translate($text, $table = 'ru')
    {
        return transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', (string)$text);
    }
}
