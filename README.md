<img alt="Kirby Pagestree Section" src="https://github.com/user-attachments/assets/3fcad2c1-a7e9-420a-b795-c5585a95158d" style="max-width: 100%;"/>

# Kirby Pagestree Section

A Kirby CMS Panel plugin that provides a `pagestree` section type. It displays pages as a collapsible tree view — similar to the native `pages` section, but with recursive rendering of all subpages as a nested, expandable tree.

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
- Supports all properties from the native [pages section](https://getkirby.com/docs/reference/panel/sections/pages)
- Automatic reload after panel actions (rename, delete, status change, etc.)

## Installation

### Composer

```
composer require fendinger/kirby-pagestree-section
```

### Manual

Download and copy this plugin to `/site/plugins/kirby-pagestree-section`.

## Usage

Add the section to any blueprint:

```yaml
sections:
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

## Properties

All properties from the native [pages section](https://getkirby.com/docs/reference/panel/sections/pages) are supported, plus one additional property:

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
| `headline` | `string` | – | Section headline |
| `help` | `string` | – | Help text below the section |
| `image` | `mixed` | `[]` | Image/icon configuration |
| `info` | `string` | – | Info text template |
| `label` | `string` | – | Section label (alias for headline) |
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

## Support

If you find this plugin useful, you can support the development:

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-support-yellow?style=flat-square&logo=buy-me-a-coffee)](https://buymeacoffee.com/fendinger)

## License

MIT

## Author

[fendinger.de](https://fendinger.de)
