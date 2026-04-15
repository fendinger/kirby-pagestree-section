<template>
  <k-section
    class="k-pagestree-section"
    :buttons="sectionButtons"
    :headline="options.headline"
  >

    <!-- Search input -->
    <div v-if="searching" class="k-pagestree-search" @keydown.escape="onSearchToggle">
      <k-input
        ref="search"
        :value="searchterm"
        :placeholder="$t('search') + '…'"
        type="text"
        icon="search"
        @input="searchterm = $event"
      />
    </div>

    <!-- Empty State -->
    <k-empty
      v-if="filteredData.length === 0"
      icon="page"
      @click="onAdd"
    >
      {{ searchterm ? $t('search.results.none') : (options.empty || $t('pages.empty')) }}
    </k-empty>

    <!-- Tree -->
    <k-draggable
      v-else
      class="k-pagestree-tree"
      :list="filteredData"
      :options="dragOptions"
      :handle="true"
      @change="onSort"
    >
      <pagestree-node
        v-for="page in filteredData"
        :key="page.id"
        :page="page"
        :depth="0"
        :max-depth="options.maxDepth"
        :sortable="options.sortable && !searchterm && !isSelecting"
        :open-nodes="searchOpenNodes"
        :selecting="isSelecting"
        :selected="selected"
        @toggle="toggleNode"
        @reload="reload"
        @select="onSelect"
      />
    </k-draggable>

    <!-- Footer: Help + Pagination (same as k-collection) -->
    <footer v-if="hasPagination || options.help" class="k-collection-footer">
      <k-text v-if="options.help" class="k-help k-collection-help" :html="options.help" />
      <span v-else></span>
      <k-pagination
        v-if="hasPagination"
        v-bind="pagination"
        :details="true"
        @paginate="onPaginate"
      />
    </footer>
  </k-section>
</template>

<script>
import PagestreeNode from "./PagestreeNode.vue";

export default {
  components: {
    PagestreeNode
  },
  data() {
    return {
      data: [],
      errors: {},
      options: {
        add: false,
        batch: false,
        empty: "No pages yet",
        headline: "",
        help: null,
        layout: "list",
        link: null,
        max: null,
        maxDepth: null,
        min: null,
        search: false,
        size: "auto",
        sortable: true,
      },
      pagination: {},
      openNodes: {},
      isProcessing: false,
      searching: false,
      searchterm: null,
      allData: null,
      isSelecting: false,
      selected: []
    };
  },
  computed: {
    canSelect() {
      return this.options.batch && this.data.length > 0;
    },
    sectionButtons() {
      // Batch mode: show delete + cancel buttons
      if (this.isSelecting) {
        return [
          {
            icon: "trash",
            text: this.$t("delete") + ` (${this.selected.length})`,
            disabled: this.selected.length === 0,
            theme: "negative",
            click: this.onBatchDelete,
            responsive: true
          },
          {
            icon: "cancel",
            text: this.$t("cancel"),
            click: this.onSelectToggle,
            responsive: true
          }
        ];
      }

      // Normal mode
      const buttons = [];
      if (this.options.search) {
        buttons.push({
          icon: "filter",
          text: this.$t("filter"),
          click: this.onSearchToggle,
          responsive: true
        });
      }
      if (this.canSelect) {
        buttons.push({
          icon: "checklist",
          click: this.onSelectToggle,
          title: this.$t("select"),
          responsive: true
        });
      }
      if (this.options.add) {
        buttons.push({
          icon: "add",
          text: this.$t("add"),
          click: this.onAdd,
          responsive: true,
          variant: "filled",
          size: "xs"
        });
      }
      return buttons;
    },
    sectionApiUrl() {
      return this.parent + "/sections/" + this.name;
    },
    hasPagination() {
      return this.pagination.total > this.pagination.limit;
    },
    dragOptions() {
      return {
        sort: this.options.sortable && !this.searchterm && !this.isSelecting,
        disabled: !this.options.sortable || !!this.searchterm || this.isSelecting,
        draggable: ".k-draggable-item",
        animation: 150
      };
    },
    filteredData() {
      if (!this.searchterm) {
        return this.data;
      }
      if (!this.allData) {
        return this.data;
      }
      return this.filterTree(this.allData, this.searchterm.toLowerCase());
    },
    searchOpenNodes() {
      if (!this.searchterm) {
        return this.openNodes;
      }
      const nodes = {};
      this.collectOpenNodes(this.filteredData, nodes);
      return nodes;
    }
  },
  async created() {
    this.restoreOpenNodes();
    await this.reload();

    this.$events.on("page.changeStatus", this.reload);
    this.$events.on("page.sort", this.reload);
    this.$events.on("model.reload", this.reload);
    this.$events.on("model.update", this.reload);
    this.$events.on("success", this.reload);
    this.$events.on("selecting", this.stopSelectingCollision);

    this._unwatchLanguage = this.$watch(
      () => this.$panel.language.code,
      () => this.reload()
    );
  },
  destroyed() {
    this.$events.off("page.changeStatus", this.reload);
    this.$events.off("page.sort", this.reload);
    this.$events.off("model.reload", this.reload);
    this.$events.off("model.update", this.reload);
    this.$events.off("success", this.reload);
    this.$events.off("selecting", this.stopSelectingCollision);
    if (this._unwatchLanguage) this._unwatchLanguage();
  },
  methods: {
    storageKey() {
      return "kirby$pagestree$" + this.parent + "/" + this.name;
    },
    toggleNode(id) {
      this.$set(this.openNodes, id, !this.openNodes[id]);
      sessionStorage.setItem(this.storageKey(), JSON.stringify(this.openNodes));
    },
    restoreOpenNodes() {
      try {
        const saved = sessionStorage.getItem(this.storageKey());
        if (saved) {
          this.openNodes = JSON.parse(saved);
        }
      } catch (e) {
        // ignore
      }
    },

    // --- Batch selection ---
    onSelectToggle() {
      if (this.isSelecting) {
        this.stopSelecting();
      } else {
        this.startSelecting();
      }
    },
    startSelecting() {
      this.isSelecting = true;
      this.selected = [];
      this.$events.emit("selecting", this.name);
    },
    stopSelecting() {
      this.isSelecting = false;
      this.selected = [];
    },
    stopSelectingCollision(name) {
      if (name !== this.name) {
        this.stopSelecting();
      }
    },
    onSelect(page) {
      const index = this.selected.findIndex(s => s.id === page.id);
      if (index > -1) {
        this.selected.splice(index, 1);
      } else {
        this.selected.push(page);
      }
    },
    onBatchDelete() {
      if (this.selected.length === 0) return;

      this.$panel.dialog.open({
        component: "k-remove-dialog",
        props: {
          text: this.$t("pages.delete.confirm.selected", { count: this.selected.length })
        },
        on: {
          submit: async () => {
            this.$panel.dialog.close();
            if (this.selected.length === 0) return;

            this.isProcessing = true;
            try {
              await this.$api.delete(
                this.sectionApiUrl + "/delete",
                { ids: this.selected.map(p => p.id) }
              );
              this.$panel.events.emit("model.update");
            } catch (err) {
              this.$panel.notification.error(err);
            } finally {
              this.isProcessing = false;
              this.stopSelecting();
            }
          }
        }
      });
    },

    // --- Search ---
    filterTree(pages, term) {
      const result = [];
      for (const page of pages) {
        const titleMatch = (page.text || page.title || "").toLowerCase().includes(term);
        const filteredChildren = page.children
          ? this.filterTree(page.children, term)
          : [];

        if (titleMatch || filteredChildren.length > 0) {
          result.push({
            ...page,
            children: filteredChildren,
            hasChildren: filteredChildren.length > 0
          });
        }
      }
      return result;
    },
    collectOpenNodes(pages, nodes) {
      for (const page of pages) {
        if (page.children && page.children.length > 0) {
          nodes[page.id] = true;
          this.collectOpenNodes(page.children, nodes);
        }
      }
    },

    // --- Data loading ---
    paginationId() {
      return "kirby$pagination$" + this.parent + "/" + this.name;
    },

    async reload() {
      const page = this.pagination.page
        ?? sessionStorage.getItem(this.paginationId())
        ?? null;

      const response = await this.$api.get(
        this.parent + "/sections/" + this.name,
        { page: page }
      );

      this.data = response.data || [];
      this.errors = response.errors || {};
      this.pagination = response.pagination || {};

      if (response.options) {
        this.options = { ...this.options, ...response.options };
      }
    },

    onPaginate(pagination) {
      sessionStorage.setItem(this.paginationId(), pagination.page);
      this.pagination = pagination;
      this.reload();
    },

    async onSearchToggle() {
      this.searching = !this.searching;
      this.searchterm = null;

      if (this.searching) {
        await this.loadAllPages();
        this.$nextTick(() => {
          this.$refs.search?.focus();
        });
      } else {
        this.allData = null;
      }
    },

    async loadAllPages() {
      const response = await this.$api.get(
        this.sectionApiUrl,
        { nolimit: true }
      );
      this.allData = response.data || [];
    },

    // --- Sorting ---
    async onSort(e) {
      let action = null;
      if (e.added) action = "added";
      if (e.moved) action = "moved";
      if (!action) return;

      this.isProcessing = true;
      const item = e[action].element;
      const position = e[action].newIndex + 1;

      try {
        await this.$api.pages.changeStatus(item.id, "listed", position);
        this.$panel.notification.success();
        this.$events.emit("page.sort", item);
      } catch (err) {
        this.$panel.error(err);
        await this.reload();
      } finally {
        this.isProcessing = false;
      }
    },

    // --- Add ---
    onAdd() {
      if (!this.options.add) return;
      this.$dialog("pages/create", {
        query: {
          parent: this.options.link ?? this.parent,
          view: this.parent,
          section: this.name
        }
      });
    }
  }
};
</script>

<style>
.k-pagestree-tree {
  display: grid;
  gap: 2px;
}

.k-pagestree-search {
  margin-bottom: var(--spacing-2);
}
</style>
