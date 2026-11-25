<?php

namespace Fendinger\PagestreeSection;

use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Exception\Exception;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\PermissionException;
use Kirby\Form\Field\DisplayField;
use Kirby\Form\Interface\ProvidesAcceptedPageBlueprints;
use Kirby\Form\Mixin;
use Kirby\Panel\Controller\Dialog\PageCreateDialogController;
use Kirby\Reflection\Attributes\Prop;
use Kirby\Toolkit\A;
use Kirby\Toolkit\I18n;
use Throwable;

/**
 * Kirby 6 replacement for the pagestree section.
 * Display fields store nothing, which is what a section did.
 */
class PagestreeField extends DisplayField implements ProvidesAcceptedPageBlueprints
{
    use Mixin\Batch;
    use Mixin\EmptyState;
    use Mixin\Limit;
    use Mixin\Max;
    use Mixin\Sortable;
    use Mixin\SortBy;

    /**
     * Minimum number of pages. Used for the publish check.
     */
    #[Prop]
    protected int|null $min = null;

    /**
     * Templates that may be added, or `false` to disable page creation
     */
    #[Prop(export: false)]
    protected array|string|bool|null $create = null;

    /**
     * Reverse the sort order
     */
    #[Prop(export: false)]
    protected bool $flip = false;

    /**
     * Image or icon shown for each page
     */
    #[Prop(export: false)]
    protected array|string|false $image = [];

    /**
     * Info text template
     */
    #[Prop(export: false)]
    protected array|string|null $info = null;

    /**
     * Maximum nesting depth. `null` means unlimited.
     */
    #[Prop]
    protected int|null $maxDepth = null;

    /**
     * Query for the parent whose children are listed
     */
    #[Prop(export: false)]
    protected string|null $parent = null;

    /**
     * Filters pages by a query. Manual sorting is disabled.
     */
    #[Prop(export: false)]
    protected string|null $query = null;

    /**
     * Shows the filter button. Filtering itself happens in the browser
     * so parents stay visible when a child matches.
     */
    #[Prop(export: 'searchable', getter: 'searchable')]
    protected bool $search = false;

    /**
     * Filters pages by status: `draft`, `unlisted`, `listed`, `published`, `all`
     */
    #[Prop(export: false)]
    protected string $status = 'all';

    /**
     * Filters the list by one template or a list of templates
     */
    #[Prop(aliases: ['template'], export: false)]
    protected array|string|null $templates = null;

    /**
     * Templates that are excluded from the list
     */
    #[Prop(export: false)]
    protected array|string|null $templatesIgnore = null;

    /**
     * Display text template
     */
    #[Prop(export: false)]
    protected array|string|null $text = '{{ page.title }}';

    /**
     * @var list<array>|null
     */
    protected array|null $treeCache = null;

    public function acceptedPageBlueprints(): array
    {
        if ($this->create === false) {
            return [];
        }

        $filter = $this->blueprintFilter();
        $names  = match ($this->create) {
            null, true, '', [] => $filter['only'] ?? array_values($this->kirby()->blueprints()),
            default             => A::wrap($this->create),
        };

        return array_values(array_diff($names, $filter['except']));
    }

    public function add(): bool
    {
        if ($this->create === false || $this->isFull() === true) {
            return false;
        }

        return true;
    }

    public function api(): array
    {
        $field = $this;

        return [
            [
                'pattern' => '',
                'method'  => 'GET',
                'action'  => fn (): array => $field->state(),
            ],
            [
                'pattern' => 'delete',
                'method'  => 'DELETE',
                'action'  => function () use ($field): bool {
                    // Kirby binds this closure to the API object.
                    return $field->deleteSelected($this->requestBody('ids'));
                },
            ],
        ];
    }

    /**
     * @return array{only: list<string>|null, except: list<string>}
     */
    protected function blueprintFilter(): array
    {
        $templates = $this->templates;
        $only      = null;

        if (is_string($templates) === true && $templates !== '') {
            $only = [$templates];
        } elseif (is_array($templates) === true && $templates !== []) {
            $only = array_values($templates);
        }

        return [
            'only'   => $only,
            'except' => array_values(A::wrap($this->templatesIgnore)),
        ];
    }

    /**
     * @param list<string> $ids
     */
    public function deleteSelected(array $ids): bool
    {
        if ($ids === []) {
            return true;
        }

        if ($this->batch() === false) {
            throw new PermissionException(
                message: 'The field does not support batch actions'
            );
        }

        $min = $this->min() ?? 0;

        if ($this->total() - count($ids) < $min) {
            throw new Exception(
                message: $this->i18n('error.field.pagelist.min.' . I18n::form($min), [
                    'min'   => $min,
                    'field' => $this->label(),
                ])
            );
        }

        foreach ($ids as $id) {
            $page = $this->kirby()->page($id);

            if ($page && $page->guards()->isAvailable('delete') === true) {
                $page->delete();
            }
        }

        $this->treeCache = null;

        return true;
    }

    public function dialogs(): array
    {
        return [
            'create' => [
                'action' => fn () => new PageCreateDialogController(
                    parent: $this->parentModel(),
                    blueprints: $this->acceptedPageBlueprints()
                ),
            ],
        ];
    }

    public function errors(): array
    {
        if ($this->isActive() === false) {
            return [];
        }

        $errors = [];

        if ($this->validateMax() === false) {
            $errors['max'] = $this->i18n('error.field.pagelist.max.' . I18n::form($this->max), [
                'max'   => $this->max,
                'field' => $this->label(),
            ]);
        }

        if ($this->validateMin() === false) {
            $errors['min'] = $this->i18n('error.field.pagelist.min.' . I18n::form($this->min), [
                'min'   => $this->min,
                'field' => $this->label(),
            ]);
        }

        return $errors;
    }

    public function hasPageBlueprint(string|null $blueprint = null): bool
    {
        try {
            $parent = $this->parentModel();
        } catch (InvalidArgumentException) {
            return false;
        }

        // The list shows the children of another model, so this
        // model is not a move target for the pages it displays.
        if ($parent->is($this->model()) === false) {
            return false;
        }

        if ($blueprint === null) {
            return true;
        }

        $filter = $this->blueprintFilter();

        return
            ($filter['only'] === null || in_array($blueprint, $filter['only'], true) === true) &&
            in_array($blueprint, $filter['except'], true) === false;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function image(): array|false
    {
        if (is_string($this->image) === true) {
            return ['query' => $this->image];
        }

        return $this->image;
    }

    /**
     * Translated info template. Query placeholders are resolved
     * per page, not against the field's own model.
     */
    public function info(): string|null
    {
        $info = $this->i18n($this->info);

        if ($info === null || $info === '') {
            return null;
        }

        return $info;
    }

    public function isFull(): bool
    {
        if ($this->max === null) {
            return false;
        }

        return $this->total() >= $this->max;
    }

    public function link(): string|null
    {
        try {
            $model  = $this->model()->panel()->url(true);
            $parent = $this->parentModel()->panel()->url(true);
        } catch (Throwable) {
            return null;
        }

        if ($model !== $parent) {
            return $parent;
        }

        return null;
    }

    public function min(): int|null
    {
        return $this->min;
    }

    /**
     * `headline` is the section name for `label`. Kirby 6 turns
     * sections into fields and renames it there; this keeps a
     * `headline` option working when the type is used as a field.
     */
    protected function register(array $args): void
    {
        if (
            array_key_exists('label', $args) === false &&
            array_key_exists('headline', $args) === true
        ) {
            $args['label'] = $args['headline'];
        }

        unset($args['headline']);

        parent::register($args);
    }

    /**
     * @throws InvalidArgumentException If the parent is neither the site nor a page
     */
    public function parentModel(): Page|Site
    {
        if ($this->parent === null) {
            $parent = $this->model();
        } else {
            $parent = $this->model()->query($this->parent);
        }

        if (
            $parent instanceof Page === false &&
            $parent instanceof Site === false
        ) {
            throw new InvalidArgumentException(
                message: 'The parent is invalid. You must choose the site or a page as parent.'
            );
        }

        return $parent;
    }

    public function props(): array
    {
        return [
            ...parent::props(),
            'link' => $this->link(),
        ];
    }

    public function searchable(): bool
    {
        return $this->search;
    }

    public function sortable(): bool
    {
        if ($this->sortable === false) {
            return false;
        }

        if ($this->query !== null || $this->sortBy !== null || $this->flip === true) {
            return false;
        }

        return in_array($this->status(), ['listed', 'published', 'all'], true);
    }

    public function state(): array
    {
        $nolimit = $this->kirby()->request()->get('nolimit');
        $tree    = $this->tree();
        $limit   = $this->limit();
        $page    = (int)($this->kirby()->request()->get('page') ?: 1);

        if ($page < 1) {
            $page = 1;
        }

        $data = $tree;

        if (!$nolimit && $limit > 0) {
            $data = array_slice($tree, ($page - 1) * $limit, $limit);
        }

        return [
            'add'        => $this->add(),
            'data'       => $data,
            'pagination' => [
                'limit'  => $limit,
                'offset' => ($page - 1) * $limit,
                'page'   => $page,
                'total'  => count($tree),
            ],
            'sortable'   => $this->sortable(),
        ];
    }

    /**
     * @return 'all'|'draft'|'listed'|'published'|'unlisted'
     */
    public function status(): string
    {
        return match ($this->status) {
            'draft', 'drafts' => 'draft',
            'listed'          => 'listed',
            'published'       => 'published',
            'unlisted'        => 'unlisted',
            default           => 'all',
        };
    }

    /**
     * Translated text template. Query placeholders are resolved
     * per page, not against the field's own model.
     */
    public function text(): string
    {
        return $this->i18n($this->text) ?? '{{ page.title }}';
    }

    public function total(): int
    {
        return count($this->tree());
    }

    /**
     * @return list<array>
     */
    public function tree(): array
    {
        if ($this->treeCache !== null) {
            return $this->treeCache;
        }

        try {
            $parent = $this->parentModel();
        } catch (InvalidArgumentException) {
            return $this->treeCache = [];
        }

        $filter = $this->blueprintFilter();

        return $this->treeCache = Tree::build($parent, 0, [
            'flip'            => $this->flip,
            'maxDepth'        => $this->maxDepth,
            'query'           => $this->query,
            'sortBy'          => $this->sortBy,
            'status'          => $this->status(),
            'templates'       => $filter['only'] ?? [],
            'templatesIgnore' => $filter['except'],
        ], function ($child, array $childTree): array {
            $guards   = $child->guards();
            $statuses = $child->blueprint()->status() ?? [];
            $flag     = $statuses[$child->status()] ?? [];
            $info   = $this->info();

            return [
                'children'    => $childTree,
                'flag'        => [
                    'icon'  => $flag['icon'] ?? null,
                    'label' => $flag['label'] ?? null,
                    'theme' => $flag['theme'] ?? null,
                ],
                'hasChildren' => $childTree !== [],
                'id'          => $child->id(),
                'image'       => $child->panel()->image($this->image(), 'list'),
                'info'        => $info === null ? null : $child->toSafeHtmlString($info),
                'link'        => $child->panel()->url(true),
                'permissions' => [
                    'changeStatus' => $guards->isAvailable('changeStatus'),
                    'delete'       => $guards->isAvailable('delete'),
                    'sort'         => $guards->isAvailable('sort'),
                ],
                'status'      => $child->status(),
                'template'    => $child->intendedTemplate()->name(),
                'text'        => $child->toSafeHtmlString($this->text()),
            ];
        });
    }

    protected function validateMax(): bool
    {
        return $this->max === null || $this->total() <= $this->max;
    }

    protected function validateMin(): bool
    {
        return $this->min() === null || $this->total() >= $this->min();
    }
}
