<?php

namespace Fendinger\PagestreeSection;

use Kirby\Cms\App;
use Kirby\Cms\Blueprint;
use Throwable;

/**
 * Works around Kirby core hardcoding `type: pages` in
 * `PageRules::move()`: parents whose blueprint only contains a
 * `pagestree` section are never accepted as move targets and
 * show up grayed out in the Panel's move dialog.
 *
 * For the two Panel routes involved in moving pages (the page
 * tree request and the move dialog), a synthetic `pages` section
 * is injected next to every `pagestree` section by pre-filling
 * the `Blueprint::$loaded` cache. These routes only return JSON
 * or perform the move, so the injected section is never rendered
 * in the Panel.
 */
class MoveFix
{
    public const SUFFIX = 'PagestreeMoveFix';

    /**
     * Whether the given path belongs to the Panel's page tree
     * request or the page move dialog
     */
    public static function isMoveRoute(string $path): bool
    {
        $slug = App::instance()->option('panel.slug', 'panel');

        if (str_starts_with($path, $slug . '/site/tree') === true) {
            return true;
        }

        return preg_match(
            '!^' . preg_quote($slug, '!') . '/dialogs/pages/[^/]+/move$!',
            $path
        ) === 1;
    }

    /**
     * Patches the site blueprint and all page blueprints
     * in the `Blueprint::$loaded` cache
     */
    public static function patchBlueprints(): void
    {
        $kirby = App::instance();
        $names = ['site'];

        foreach ($kirby->blueprints('pages') as $name) {
            $names[] = 'pages/' . $name;
        }

        foreach ($names as $name) {
            try {
                $props = Blueprint::find($name);
            } catch (Throwable) {
                continue;
            }

            Blueprint::$loaded[$name] = static::inject($props);
        }
    }

    /**
     * Recursively walks tabs/columns/sections and adds a
     * synthetic `pages` section next to every `pagestree`
     * section. Idempotent: re-running overwrites the same keys.
     */
    public static function inject(array $props): array
    {
        if (is_array($props['sections'] ?? null) === true) {
            $add = [];

            foreach ($props['sections'] as $name => $section) {
                // resolve string references and `extends`
                if (
                    is_string($section) === true ||
                    isset($section['extends']) === true
                ) {
                    try {
                        $section = Blueprint::extend($section);
                    } catch (Throwable) {
                        continue;
                    }
                }

                if (($section['type'] ?? null) !== 'pagestree') {
                    continue;
                }

                $fix = ['type' => 'pages'];

                // keep template restrictions and custom parent
                // queries so core's allowlist stays accurate
                foreach (['templates', 'template', 'parent'] as $key) {
                    if (isset($section[$key]) === true) {
                        $fix[$key] = $section[$key];
                    }
                }

                $add[$name . static::SUFFIX] = $fix;
            }

            $props['sections'] = [...$props['sections'], ...$add];
        }

        foreach (['tabs', 'columns'] as $key) {
            if (is_array($props[$key] ?? null) === false) {
                continue;
            }

            foreach ($props[$key] as $childName => $child) {
                if (is_array($child) === true) {
                    $props[$key][$childName] = static::inject($child);
                }
            }
        }

        return $props;
    }
}
