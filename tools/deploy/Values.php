<?php
namespace Zubr\Deploy;

/** Rewrite values without corrupting PHP serialized lengths or JSON escaping. */
function replaceValue(mixed $value, array $replacements): mixed
{
	if (is_array($value)) {
		foreach ($value as $key => $item) $value[$key] = replaceValue($item, $replacements);
		return $value;
	}
	if (!is_string($value)) return $value;
	if (preg_match('/^(?:a|s|i|b|d):|^N;$/', $value)) {
		$parsed = @unserialize($value, ['allowed_classes' => false]);
		if ($parsed !== false || $value === 'b:0;') return serialize(replaceValue($parsed, $replacements));
	}
	if (str_starts_with($value, '{') || str_starts_with($value, '[')) {
		$parsed = json_decode($value, true);
		if (is_array($parsed)) return json_encode(replaceValue($parsed, $replacements), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	}
	return strtr($value, $replacements);
}
