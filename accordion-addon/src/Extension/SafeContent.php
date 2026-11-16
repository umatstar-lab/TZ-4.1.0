<?php
/** Accordion input and output policy. GPL-2.0-or-later. */
namespace TemPlaza\Component\TZ_Portfolio\AddOn\Content\Accordion\Extension;
defined('_JEXEC') or die;
use Joomla\CMS\Filter\InputFilter;

final class SafeContent
{
    public static function text($value): string
    {
        return is_scalar($value) ? strip_tags((string) $value) : '';
    }
    public static function escape($value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    public static function html($value): string
    {
        // Deliberately exclude scripts, styles, embedded documents, forms and event attributes.
        $filter = InputFilter::getInstance(
            ['p','br','div','span','strong','b','em','i','u','s','sub','sup','blockquote','pre','code','ul','ol','li','a','img','h1','h2','h3','h4','h5','h6','hr','table','thead','tbody','tfoot','tr','th','td','caption'],
            ['href','src','alt','title','width','height','colspan','rowspan','scope','class'], 0, 0, 1
        );
        return $filter->clean(is_scalar($value) ? (string) $value : '', 'html');
    }
    public static function classes($value): string
    {
        $classes = preg_split('/\s+/', trim(self::text($value))) ?: [];
        return implode(' ', array_filter($classes, static fn($c) => (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]{0,99}$/D', $c)));
    }
    public static function length($value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        return preg_match('/^-?(?:\d{1,4}(?:\.\d{1,3})?|\.\d{1,3})(?:px|rem|em|%|vh|vw)?$/D', $value) ? $value : '';
    }
    public static function color($value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        return preg_match('/^(?:#[0-9a-f]{3,8}|(?:rgb|rgba|hsl|hsla)\([0-9.,%\s]+\)|transparent|inherit|currentColor)$/iD', $value) ? $value : '';
    }
    public static function data($value): array
    {
        if (is_string($value)) $value = self::json($value);
        $raw = is_object($value) ? get_object_vars($value) : (is_array($value) ? $value : []);
        $out = [];
        foreach (['title','title_element','custom_class','gutter'] as $key) $out[$key] = self::text($raw[$key] ?? '');
        if (!in_array($out['title_element'], ['h1','h2','h3','h4','h5','h6'], true)) $out['title_element'] = 'h3';
        $out['custom_class'] = self::classes($out['custom_class']);
        if (!in_array($out['gutter'], ['', 'gx-1','gx-2','gx-3','gx-4','gx-5'], true)) $out['gutter'] = '';
        foreach (['title_color','title_color_hover'] as $key) $out[$key] = self::color($raw[$key] ?? '');
        foreach (['title_margin_top','title_margin_bottom'] as $key) {
            $v = $raw[$key] ?? '';
            $out[$key] = is_scalar($v) && preg_match('/^-?\d{1,4}(?:\.\d{1,3})?$/D', (string) $v) ? (string) $v : '';
        }
        $font = self::json($raw['title_font'] ?? ''); $safeFont = [];
        foreach (['fontFamily'=>'/^[a-zA-Z0-9 -]{1,80}$/D', 'fontWeight'=>'/^(?:[1-9]00|normal|bold)$/D', 'fontStyle'=>'/^(?:normal|italic|oblique)$/D', 'textTransform'=>'/^(?:none|uppercase|lowercase|capitalize)$/D', 'textDecoration'=>'/^(?:none|underline|line-through|overline)$/D'] as $key=>$pattern) {
            if (isset($font[$key]) && is_scalar($font[$key]) && preg_match($pattern, (string) $font[$key])) $safeFont[$key] = (string) $font[$key];
        }
        foreach (['fontSize','lineHeight','letterSpacing'] as $key) {
            $v = self::length($font[$key] ?? '');
            if ($v !== '') $safeFont[$key] = $v;
        }
        $out['title_font'] = json_encode($safeFont, JSON_THROW_ON_ERROR);
        $margin = self::json($raw['accordion_margin'] ?? ''); $safeMargin = [];
        foreach (['md','sm','xs'] as $size) foreach (['top','right','bottom','left'] as $side) {
            $entry = $margin[$size] ?? [];
            if (!is_array($entry)) continue;
            $v = self::length($entry[$side] ?? '');
            if ($v !== '') $safeMargin[$size][$side] = $v;
        }
        $out['accordion_margin'] = json_encode($safeMargin, JSON_THROW_ON_ERROR);
        $out['accordion_items'] = [];
        foreach ((array) ($raw['accordion_items'] ?? []) as $item) {
            if (!is_array($item) && !is_object($item)) continue;
            $item = (array) $item;
            $out['accordion_items']['accordion_items' . count($out['accordion_items'])] = ['title'=>self::text($item['title'] ?? ''), 'content'=>self::html($item['content'] ?? '')];
        }
        return $out;
    }
    private static function json($value): array
    {
        if (is_array($value)) return $value;
        if (is_object($value)) return json_decode(json_encode($value), true) ?: [];
        $decoded = is_string($value) ? json_decode($value, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
