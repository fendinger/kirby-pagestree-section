<img alt="Kirby Pagestree Section" src="https://github.com/user-attachments/assets/3fcad2c1-a7e9-420a-b795-c5585a95158d" style="max-width: 100%;"/>

# Kirby Pagestree Section

A Kirby CMS Panel plugin that displays pages as a collapsible tree view — similar to the native pages list, but with recursive rendering of all subpages as a nested, expandable tree.

The same release runs on Kirby 5 and Kirby 6. Kirby 5 keeps the `pagestree` section. Kirby 6 has no custom sections, so the plugin registers a `pagestree` field there and still accepts existing `sections` blueprints.

## Features

- Collapsible tree view with toggle arrows
- Native Kirby look & feel using `k-item` components
- Drag & drop sorting for listed pages (sort handle at the left edge)
- Full context menu matching the native pages section (Open, Preview, Rename, Change URL, Change Status, Change Position, Change Template, Move, Duplicate, Delete)
- Status indicators with native Kirby themes (draft, unlisted, listed)
- Client-side search/filter across all tree levels (parents stay visible when children match)
- Pagination for root-level pages
- Multi-language support
- Open/closed state persisted in sessionStorage
- Same blueprint options as the native [pages section](https://getkirby.com/docs/reference/panel/sections/pages), plus `maxDepth`
- Automatic reload after panel actions (rename, delete, status change, etc.)

## Requirements

- Kirby 5 or Kirby 6
- PHP 8.2 to 8.6

## Installation

### Composer

```
composer require fendinger/kirby-pagestree-section
```

### Manual

Download and copy this plugin to `/site/plugins/kirby-pagestree-section`.

## Usage

Add it to any blueprint. This works on Kirby 5 and Kirby 6:

```yaml
sections:
  tree:
    type: pagestree
    label: Page Tree
```

On Kirby 6 you can also place it directly under `fields`. `label` replaces `headline` there; `headline` is still accepted.

```yaml
fields:
  tree:
    type: pagestree
    label: Page Tree
```

### Full example

```yaml
sections:
  tree:
    type: pagestree
    label: Site Tree
    parent: site
    maxDepth: 4
    status: all
    sortable: true
    search: true
    limit: 20
    templates:
      - default
      - blog-post
    create:
      - default
      - blog-post
    text: "{{ page.title }}"
    info: "{{ page.date.toDate('d.m.Y') }}"
    empty: No pages yet
    help: Drag listed pages to reorder them
```

## Kirby 5 and Kirby 6

One package covers both versions. Install the same plugin; there is no second package for Kirby 6.

Kirby 5 registers `pagestree` as a section. Kirby 6 no longer has custom sections, so the plugin registers a field of the same type. A blueprint written for Kirby 5 keeps working: while Kirby 6 loads it, a `sections` entry with `type: pagestree` is mapped onto that field.

`label` is the Kirby 6 name for the title above the tree. `headline` still works on both versions and is used as `label` when `label` is omitted.

`columns`, `layout` and `size` belong to the native pages section. The tree ignores them.

A page or the site can be chosen in the move dialog when its blueprint lists its own children with `pagestree`. A `parent` query that points at another model does not make the current page a move target.

## Notes

- Pagination applies to the root level only. Child lists are complete.
- Search filters in the browser, so a matching child keeps its parents visible. Sorting pauses while a search term is set.
- Drag and drop is available for listed pages. It stays off with `sortable: false`, with `sortBy`, `query` or `flip`, and when `status` is `draft` or `unlisted`.
- `maxDepth` limits how deep the tree is built. `null` means no limit.

## Properties

The options below match the native [pages section](https://getkirby.com/docs/reference/panel/sections/pages), plus `maxDepth`. `columns`, `layout` and `size` are ignored.

| Property | Type | Default | Description |
|---|---|---|---|
| `maxDepth` | `int` | `null` | Maximum nesting depth (`null` = unlimited) |

### Inherited from pages section

| Property | Type | Default | Description |
|---|---|---|---|
| `batch` | `bool` | `false` | Enable batch delete |
| `columns` | `array` | – | *Not applicable (tree layout)* |
| `create` | `mixed` | `null` | Templates allowed for new pages, or `false` to disable |
| `empty` | `string` | – | Text for the empty state |
| `flip` | `bool` | `false` | Reverse sort order |
| `headline` | `string` | – | Title above the tree. On Kirby 6 this fills `label` when `label` is omitted |
| `help` | `string` | – | Help text below the tree |
| `image` | `mixed` | `[]` | Image/icon configuration |
| `info` | `string` | – | Info text template |
| `label` | `string` | – | Title above the tree. Preferred name on Kirby 6 |
| `layout` | `string` | `list` | *Not applicable (tree layout)* |
| `limit` | `int` | `20` | Number of root pages per page |
| `max` | `int` | `null` | Maximum number of pages |
| `min` | `int` | `null` | Minimum number of pages |
| `parent` | `string` | – | Parent page query |
| `query` | `string` | `null` | Filter pages by query |
| `search` | `bool` | `false` | Enable client-side filter |
| `size` | `string` | `auto` | *Not applicable (tree layout)* |
| `sortable` | `bool` | `true` | Enable drag & drop sorting |
| `sortBy` | `string` | `null` | Auto-sort field (disables manual sorting) |
| `status` | `string` | `all` | Filter by status: `draft`, `unlisted`, `listed`, `published`, `all` |
| `template` | `string` | `null` | Filter by single template |
| `templates` | `array` | `null` | Filter by multiple templates |
| `templatesIgnore` | `array` | `null` | Exclude templates |
| `text` | `string` | `{{ page.title }}` | Display text template |

## Development

The Panel bundle depends on the Kirby version. Kirby 5 loads a classic script, Kirby 6 an ES module. `npm run build` writes both into `dist/k5` and `dist/k6`. When the site boots, the plugin copies the matching files to `index.js` and `index.css`. Those two files are generated and are not part of the repository.

```
npm run dev       # Kirby 5
npm run dev:k6    # Kirby 6
npm run build     # dist/k5 and dist/k6
```

## Support

If you find this plugin useful, you can support the development:

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-support-yellow?style=flat-square&logo=buy-me-a-coffee)](https://buymeacoffee.com/fendinger)

## License

MIT

## Author

[fendinger.de](https://fendinger.de)
