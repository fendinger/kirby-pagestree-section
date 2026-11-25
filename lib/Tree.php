<?php

namespace Fendinger\PagestreeSection;

/**
 * Builds the nested page list shared by the Kirby 5 section
 * and the Kirby 6 field.
 */
class Tree
{
    /**
     * @param array{
     *   maxDepth?: int|null,
     *   query?: string|null,
     *   status?: string,
     *   templates?: array,
     *   templatesIgnore?: array,
     *   searchterm?: string|null,
     *   sortBy?: string|null,
     *   flip?: bool
     * } $config
     */
    public static function build(object $parent, int $depth, array $config, callable $node): array
    {
        $maxDepth = $config['maxDepth'] ?? null;

        if ($maxDepth !== null && $depth >= $maxDepth) {
            return [];
        }

        $query = $config['query'] ?? null;

        if ($query && $depth === 0) {
            $children = $parent->query($query);
        } else {
            $status = $config['status'] ?? 'all';

            if ($status === 'listed') {
                $children = $parent->children()->listed();
            } elseif ($status === 'draft') {
                $children = $parent->drafts();
            } elseif ($status === 'unlisted') {
                $children = $parent->children()->unlisted();
            } elseif ($status === 'published') {
                $children = $parent->children();
            } else {
                $children = $parent->childrenAndDrafts();
            }
        }

        if (!$children) {
            return [];
        }

        $templates = $config['templates'] ?? [];

        if ($templates !== []) {
            $children = $children->filter(function ($child) use ($templates) {
                return in_array($child->intendedTemplate()->name(), $templates, true);
            });
        }

        $ignore = $config['templatesIgnore'] ?? [];

        if ($ignore !== []) {
            $children = $children->filter(function ($child) use ($ignore) {
                return in_array($child->intendedTemplate()->name(), $ignore, true) === false;
            });
        }

        $searchterm = $config['searchterm'] ?? null;

        if ($searchterm) {
            $children = $children->search($searchterm);
        }

        if (!empty($config['sortBy'])) {
            $parts    = explode(' ', $config['sortBy']);
            $children = $children->sortBy($parts[0], $parts[1] ?? 'asc');
        }

        if (($config['flip'] ?? false) === true && !$searchterm) {
            $children = $children->flip();
        }

        if (!$children) {
            return [];
        }

        $result = [];

        foreach ($children as $child) {
            if (!$child) {
                continue;
            }

            // Built eagerly so the expand arrow reflects what is actually
            // left after template filtering. A page whose only child is
            // filtered out must not offer an arrow that opens nothing.
            $childTree = static::build($child, $depth + 1, $config, $node);
            $result[]  = $node($child, $childTree);
        }

        return $result;
    }
}
