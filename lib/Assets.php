<?php

namespace Fendinger\PagestreeSection;

/**
 * Kirby 5 concatenates index.js as a classic script.
 * Kirby 6 loads the same filename as an ES module.
 * The two builds cannot be one file, so the matching
 * bundle is copied into place when the plugin boots.
 */
class Assets
{
    public static function publish(int $major): void
    {
        $variant = $major >= 6 ? 'k6' : 'k5';
        $root    = dirname(__DIR__);

        foreach (['js', 'css'] as $extension) {
            static::copy(
                $root . '/dist/' . $variant . '/index.' . $extension,
                $root . '/index.' . $extension
            );
        }
    }

    protected static function copy(string $source, string $target): void
    {
        if (is_file($source) === false) {
            return;
        }

        if (
            is_file($target) === true &&
            hash_file('sha256', $source) === hash_file('sha256', $target)
        ) {
            return;
        }

        $tmp = $target . '.tmp';

        if (copy($source, $tmp) === false) {
            return;
        }

        if (rename($tmp, $target) === false) {
            @unlink($tmp);
        }
    }
}
