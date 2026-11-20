<?php

namespace Fendinger\PagestreeSection;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Form\Field\PageListField;
use Kirby\Panel\Collector\PagesCollector;
use Kirby\Reflection\Attributes\Prop;

/**
 * Lists pages as a collapsible tree: like the pages field, but every page
 * carries its subpages, which are filtered and sorted the same way.
 *
 * Extending the pages field keeps all its options (`templates`, `create`,
 * `status`, `text`, `info`, `image`, `search`, `limit`, `batch`, …) and
 * its dialogs. It also makes pages with this field valid targets for the
 * move dialog, as Kirby asks fields for the page blueprints they accept.
 */
class PagestreeField extends PageListField
{
    /**
     * Maximum nesting depth (`null` = unlimited)
     */
    #[Prop]
    protected int|null $maxDepth = null;

    public function maxDepth(): int|null
    {
        return $this->maxDepth;
    }

    /**
     * The props of a page for the Panel, with its subpages
     *
     * @return array<string, mixed>
     */
    protected function node(Page $page, int $depth): array
    {
        $children = $this->children($page, $depth + 1);

        return [
            ...$this->toItem($page),
            // Built eagerly (and not gated on `hasChildren()`) so the arrow
            // reflects what is left after filtering: a page whose only child
            // is filtered out must not show an arrow that opens nothing.
            'children'    => $children,
            'hasChildren' => $children !== []
        ];
    }

    /**
     * The subpages of a page, filtered and sorted like the root level
     *
     * @return list<array<string, mixed>>
     */
    protected function children(Page $parent, int $depth): array
    {
        if ($this->maxDepth !== null && $depth >= $this->maxDepth) {
            return [];
        }

        $collector = new PagesCollector(
            blueprints: $this->blueprints(),
            flip:       $this->flip(),
            parent:     $parent,
            sortBy:     $this->sortBy(),
            status:     $this->status(),
        );

        return $this->nodes($collector->models(), $depth);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function nodes(Pages $pages, int $depth): array
    {
        $nodes = [];

        foreach ($pages as $page) {
            $nodes[] = $this->node($page, $depth);
        }

        return $nodes;
    }

    /**
     * With `nolimit` in the request, the root level is not paginated.
     * The Panel asks for that to search through the whole tree.
     */
    public function data(): array
    {
        $paginated = $this->kirby()->request()->get('nolimit') === null;

        return $this->nodes($this->collector()->models(paginated: $paginated), 0);
    }
}
