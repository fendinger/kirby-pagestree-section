<?php

use Kirby\Cms\Blueprint;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\A;
use Kirby\Toolkit\I18n;

return [
    'mixins' => [
        'batch',
        'details',
        'empty',
        'headline',
        'help',
        'layout',
        'min',
        'max',
        'pagination',
        'parent',
        'search',
        'sort',
    ],
    'props' => [
        /**
         * Optional array of templates that should only be allowed to add
         * or `false` to completely disable page creation
         */
        'create' => function ($create = null) {
            return $create;
        },
        /**
         * Filters pages by a query. Sorting will be disabled
         */
        'query' => function (string|null $query = null) {
            return $query;
        },
        /**
         * Maximum nesting depth (null = unlimited)
         */
        'maxDepth' => function (int|null $maxDepth = null) {
            return $maxDepth;
        },
        /**
         * Filters pages by their status.
         * Available: `draft`, `unlisted`, `listed`, `published`, `all`.
         */
        'status' => function (string $status = '') {
            if ($status === 'drafts') {
                $status = 'draft';
            }

            if (in_array($status, ['all', 'draft', 'published', 'listed', 'unlisted'], true) === false) {
                $status = 'all';
            }

            return $status;
        },
        /**
         * Filters the list by single template.
         */
        'template' => function (string|array|null $template = null) {
            return $template;
        },
        /**
         * Filters the list by templates and sets template options
         * when adding new pages to the section.
         */
        'templates' => function ($templates = null) {
            return A::wrap($templates ?? $this->template);
        },
        /**
         * Excludes the selected templates.
         */
        'templatesIgnore' => function ($templates = null) {
            return A::wrap($templates);
        },
    ],
    'computed' => [
        'parent' => function () {
            $parent = $this->parentModel();

            if (
                $parent instanceof Site === false &&
                $parent instanceof Page === false
            ) {
                throw new InvalidArgumentException(
                    message: 'The parent is invalid. You must choose the site or a page as parent.'
                );
            }

            return $parent;
        },
        'rootPages' => function () {
            $parent = $this->parent();

            if (!$parent) {
                return [];
            }

            return $this->buildTree($parent, 0);
        },
        'total' => function () {
            return count($this->rootPages());
        },
        'pages' => function () {
            $root = $this->rootPages();

            // Skip pagination when nolimit=true (used for search)
            $nolimit = $this->kirby()->request()->get('nolimit');
            if ($nolimit) {
                return $root;
            }

            $limit = $this->limit();
            $page  = $this->page() ?? 1;

            if ($limit) {
                $offset = ($page - 1) * $limit;
                return array_slice($root, $offset, $limit);
            }

            return $root;
        },
        'pagination' => function () {
            return $this->pagination();
        },
        'add' => function () {
            if ($this->create === false) {
                return false;
            }

            if ($this->isFull() === true) {
                return false;
            }

            return true;
        },
        'errors' => function () {
            $errors = [];

            if ($this->validateMax() === false) {
                $errors['max'] = I18n::template('error.section.pages.max.' . I18n::form($this->max), [
                    'max'     => $this->max,
                    'section' => $this->headline
                ]);
            }

            if ($this->validateMin() === false) {
                $errors['min'] = I18n::template('error.section.pages.min.' . I18n::form($this->min), [
                    'min'     => $this->min,
                    'section' => $this->headline
                ]);
            }

            if (empty($errors) === true) {
                return [];
            }

            return [
                $this->name => [
                    'label'   => $this->headline,
                    'message' => $errors,
                ]
            ];
        },
        'blueprints' => function () {
            $blueprints = [];
            $templates  = empty($this->create) === false ? A::wrap($this->create) : $this->templates;

            if (empty($templates) === true) {
                foreach (glob($this->kirby()->root('blueprints') . '/pages/*.yml') as $file) {
                    $templates[] = basename($file, '.yml');
                }
            }

            foreach ($templates as $template) {
                try {
                    $props = Blueprint::load('pages/' . $template);
                    $blueprints[] = [
                        'name'  => basename($props['name']),
                        'title' => $props['title'] ?? ucfirst($template),
                    ];
                } catch (\Throwable) {
                    $blueprints[] = [
                        'name'  => basename($template),
                        'title' => ucfirst($template),
                    ];
                }
            }

            return $blueprints;
        },
    ],
    'methods' => [
        'buildTree' => function ($parent, int $depth): array {
            if ($this->maxDepth !== null && $depth >= $this->maxDepth) {
                return [];
            }

            // Query-Filter (alternative to status)
            if ($this->query && $depth === 0) {
                $children = $parent->query($this->query);
            } else {
                if ($this->status === 'listed') {
                    $children = $parent->children()->listed();
                } elseif ($this->status === 'draft') {
                    $children = $parent->drafts();
                } elseif ($this->status === 'unlisted') {
                    $children = $parent->children()->unlisted();
                } elseif ($this->status === 'published') {
                    $children = $parent->children();
                } else {
                    $children = $parent->childrenAndDrafts();
                }
            }

            // Template-Filter
            if (!empty($this->templates)) {
                $children = $children->filter(function ($child) {
                    return in_array($child->intendedTemplate()->name(), $this->templates);
                });
            }

            // Template-Ignore-Filter
            if (!empty($this->templatesIgnore)) {
                $children = $children->filter(function ($child) {
                    return !in_array($child->intendedTemplate()->name(), $this->templatesIgnore);
                });
            }

            // Search
            if ($searchterm = $this->searchterm()) {
                $children = $children->search($searchterm);
            }

            // Sortierung
            if ($this->sortBy) {
                $parts = explode(' ', $this->sortBy);
                $children = $children->sortBy($parts[0], $parts[1] ?? 'asc');
            }

            // Flip
            if ($this->flip === true && !$this->searchterm()) {
                $children = $children->flip();
            }

            $result = [];

            if (!$children) {
                return $result;
            }

            foreach ($children as $child) {
                if (!$child) {
                    continue;
                }
                $hasChildren = $child->hasChildren() || $child->hasDrafts();

                $result[] = [
                    'id'          => $child->id(),
                    'title'       => $child->content()->title()->value(),
                    'slug'        => $child->slug(),
                    'status'      => $child->status(),
                    'template'    => $child->intendedTemplate()->name(),
                    'hasChildren' => $hasChildren,
                    'children'    => $hasChildren ? $this->buildTree($child, $depth + 1) : [],
                    'url'         => $child->panel()->url(),
                    'previewUrl'  => $child->previewUrl(),
                    'panelUrl'    => $child->panel()->url(true),
                    'num'         => $child->num(),
                    'image'       => $this->pageImage($child),
                    'info'        => $this->pageInfo($child),
                    'text'        => $this->pageText($child),
                    'permissions' => [
                        'changeSlug'     => $child->permissions()->can('changeSlug'),
                        'changeStatus'   => $child->permissions()->can('changeStatus'),
                        'changeTemplate' => $child->permissions()->can('changeTemplate'),
                        'changeTitle'    => $child->permissions()->can('changeTitle'),
                        'delete'         => $child->permissions()->can('delete'),
                        'duplicate'      => $child->permissions()->can('duplicate'),
                        'move'           => $child->permissions()->can('move'),
                        'sort'           => $child->permissions()->can('sort'),
                        'preview'        => $child->permissions()->can('preview'),
                    ]
                ];
            }

            return $result;
        },
        'countTree' => function (array $pages): int {
            $count = count($pages);
            foreach ($pages as $page) {
                if (!empty($page['children'])) {
                    $count += $this->countTree($page['children']);
                }
            }
            return $count;
        },
        'pageImage' => function ($page): array|null {
            return $page->panel()->image($this->image, $this->layout);
        },
        'pageInfo' => function ($page): string|null {
            if ($this->info === null) {
                return null;
            }

            return $page->toSafeString($this->info);
        },
        'pageText' => function ($page): string {
            return $page->toSafeString($this->text);
        },
    ],
    'api' => function () {
        return [
            [
                'pattern' => 'delete',
                'method'  => 'DELETE',
                'action'  => function () {
                    $section = $this->section();

                    if ($section->batch() === false) {
                        throw new \Kirby\Exception\PermissionException(
                            message: 'The section does not support batch actions'
                        );
                    }

                    $ids   = $this->requestBody('ids');
                    $kirby = $this->kirby();

                    foreach ($ids as $id) {
                        $page = $kirby->page($id);
                        if ($page && $page->permissions()->can('delete')) {
                            $page->delete();
                        }
                    }

                    return true;
                }
            ]
        ];
    },
    'toArray' => function () {
        return [
            'data'    => $this->pages,
            'errors'  => $this->errors,
            'options' => [
                'add'      => $this->add,
                'batch'    => $this->batch,
                'columns'  => $this->columnsWithTypes(),
                'empty'    => $this->empty,
                'headline' => $this->headline,
                'help'     => $this->help,
                'layout'   => $this->layout,
                'link'     => $this->link(),
                'max'      => $this->max,
                'maxDepth' => $this->maxDepth,
                'min'      => $this->min,
                'search'   => $this->search,
                'size'     => $this->size,
                'sortable' => $this->sortable,
            ],
            'pagination' => $this->pagination,
        ];
    }
];
