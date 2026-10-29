<?php
namespace Zubr\Site;

/** Replace approved legacy literals with standard ClientConfig tags; keep static preview literal. */
final class Contacts
{
    public static function prepareTemplate(string $html): string
    {
        $definitions = json_decode(file_get_contents(__DIR__.'/../contact-settings.json'), true, 512, JSON_THROW_ON_ERROR);
        $map = [];
        foreach($definitions['groups'] as $group) foreach($group['fields'] as $field) {
            if(in_array($field['key'], ['zubr_contact_instagram', 'zubr_org_currency'], true)) continue;
            $map[$field['value']] = '[[++'.$field['key'].':htmlent]]';
            $map[htmlspecialchars($field['value'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8')] = '[[++'.$field['key'].':htmlent]]';
        }
        $map['tel:+375295805148'] = 'tel:[[++zubr_contact_phone:ZubrPhone]]';
        $map['https://www.instagram.com/zubr.gym/'] = 'https://www.instagram.com/[[++zubr_contact_instagram:htmlent]]/';
        $map['https://instagram.com/zubr.gym/'] = $map['https://www.instagram.com/zubr.gym/'];
        $map['@zubr.gym'] = '@[[++zubr_contact_instagram:htmlent]]';
        $map['ГГО ОО БФКиТБ'] = '[[++zubr_org_short_name:htmlent]]';
        $map['г. Гродно, ул. Репина, 38'] = '[[++zubr_contact_address:htmlent]]';
        $map['на улице Репина, 38'] = 'по адресу [[++zubr_contact_address:htmlent]]';
        $map['Репина, 38'] = '[[++zubr_contact_address:ZubrStreet:htmlent]]';
        $map['ул. Репина, 38'] = '[[++zubr_contact_address:ZubrStreet:htmlent]]';
        $map['на ул. Репина, 38'] = 'по адресу [[++zubr_contact_address:htmlent]]';
        $map['https://yandex.by/maps/org/zubr_gym/114715839953/?utm_medium=mapframe&amp;utm_source=maps'] = '[[++zubr_contact_map_url:htmlent]]';
        $map['https://yandex.by/maps/org/zubr_gym/114715839953/?utm_medium=mapframe&utm_source=maps'] = '[[++zubr_contact_map_url:htmlent]]';
        // Avoid converting every price currency and every unrelated use of "Выходной".
        $html = preg_replace('~(<dt>Валюта счёта</dt><dd>)BYN(</dd>)~', '$1[[++zubr_org_currency:htmlent]]$2', $html);
        return strtr($html, $map);
    }

    public static function expand(string $text, \MODX\Revolution\modX $modx): string
    {
        // Expand only our contact settings, never evaluate arbitrary snippets in metadata.
        return preg_replace_callback('~\[\[\+\+(zubr_(?:contact|org)_[a-z_]+)(?::(ZubrStreet|ZubrPhone))?(?::htmlent)?\]\]~',
            static function ($m) use ($modx) {
                $value = (string)$modx->getOption($m[1], null, '');
                return match ($m[2] ?? '') {
                    'ZubrStreet' => trim(preg_replace('/^[^,]+,\s*/u', '', $value)),
                    'ZubrPhone' => '+' . preg_replace('/[^0-9]/', '', $value),
                    default => $value,
                };
            }, $text);
    }
}
