<template>
  <k-field
    :class="['k-pagestree-field', $attrs.class]"
    :help="help"
    :input="false"
    :label="label"
    :link="link"
    :name="name"
    :required="Boolean(min)"
    :style="$attrs.style"
    :type="type"
  >
    <template v-if="sectionButtons.length > 0" #options>
      <k-button-group :buttons="sectionButtons" size="xs" variant="filled" />
    </template>

    <k-input
      v-if="searching"
      ref="search"
      :placeholder="$t('search') + '…'"
      :value="searchterm"
      class="k-pagestree-search"
      icon="search"
      type="text"
      @input="searchterm = $event"
      @keydown.esc="onSearchToggle"
    />

    <k-empty
      v-if="filteredData.length === 0"
      icon="page"
      @click="onAdd"
    >
      {{ searchterm ? $t("search.results.none") : (empty || $t("pages.empty")) }}
    </k-empty>

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
        :sortable="canSort"
        :open-nodes="searchOpenNodes"
        :selecting="isSelecting"
        :selected="selected"
        @toggle="toggleNode"
        @reload="reload"
        @select="onSelect"
      />
    </k-draggable>

    <footer v-if="hasPagination" class="k-pagestree-footer">
      <k-pagination
        v-bind="pagination"
        :details="true"
        @paginate="onPaginate"
      />
    </footer>
  </k-field>
</template>

<script>
import PagestreeNode from "./PagestreeNode.vue";

export default {
  components: {
    PagestreeNode
  },
  inheritAttrs: false,
  props: {
    batch: Boolean,
    empty: String,
    endpoints: Object,
    help: [String, Object],
    label: String,
    link: String,
    max: Number,
    maxDepth: Number,
    min: Number,
    name: String,
    searchable: Boolean,
    type: String
  },
  emits: ["focus", "input", "submit"],
  data() {
    return {
      add: false,
      data: [],
      isProcessing: false,
      isSelecting: false,
      openNodes: {},
      pagination: {},
      searching: false,
      searchterm: null,
      selected: [],
      sortable: false,
      allData: null
    };
  },
  computed: {
    canAdd() {
      return this.add === true && this.$panel.permissions.pages.create === true;
    },
    canSelect() {
      return this.batch === true && this.data.length > 0;
    },
    canSort() {
      return this.sortable === true && !this.searchterm && this.isSelecting === false;
    },
    sectionButtons() {
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

      const buttons = [];

      if (this.searchable) {
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

      if (this.canAdd) {
        buttons.push({
          icon: "add",
          text: this.$t("add"),
          click: this.onAdd,
          responsive: true
        });
      }

      return buttons;
    },
    hasPagination() {
      return this.pagination.total > this.pagination.limit;
    },
    dragOptions() {
      return {
        sort: this.canSort,
        disabled: this.canSort === false,
        draggable: ".k-pagestree-node",
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
  watch: {
    "$panel.language.code"() {
      this.reload();
    },
    "endpoints.field"() {
      this.reload();
    }
  },
  created() {
    this.restoreOpenNodes();

    this.$panel.events.on("model.update", this.reload);
    this.$panel.events.on("page.changeSlug", this.reload);
    this.$panel.events.on("page.changeStatus", this.reload);
    this.$panel.events.on("page.changeTemplate", this.reload);
    this.$panel.events.on("page.changeTitle", this.reload);
    this.$panel.events.on("page.create", this.reload);
    this.$panel.events.on("page.delete", this.reload);
    this.$panel.events.on("page.sort", this.reload);
    this.$panel.events.on("selecting", this.stopSelectingCollision);
  },
  mounted() {
    this.reload();
  },
  unmounted() {
    this.$panel.events.off("model.update", this.reload);
    this.$panel.events.off("page.changeSlug", this.reload);
    this.$panel.events.off("page.changeStatus", this.reload);
    this.$panel.events.off("page.changeTemplate", this.reload);
    this.$panel.events.off("page.changeTitle", this.reload);
    this.$panel.events.off("page.create", this.reload);
    this.$panel.events.off("page.delete", this.reload);
    this.$panel.events.off("page.sort", this.reload);
    this.$panel.events.off("selecting", this.stopSelectingCollision);
  },
  methods: {
    storageKey() {
      return "kirby$pagestree$" + (this.endpoints?.field || this.name);
    },
    paginationId() {
      return "kirby$pagination$" + (this.endpoints?.field || this.name);
    },
    toggleNode(id) {
      this.openNodes[id] = !this.openNodes[id];
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
      this.$panel.events.emit("selecting", this.name);
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
      const index = this.selected.findIndex((item) => item.id === page.id);

      if (index > -1) {
        this.selected.splice(index, 1);
      } else {
        this.selected.push(page);
      }
    },
    onBatchDelete() {
      if (this.selected.length === 0) {
        return;
      }

      this.$panel.dialog.open({
        component: "k-remove-dialog",
        props: {
          text: this.$t("pages.delete.confirm.selected", {
            count: this.selected.length
          })
        },
        on: {
          submit: async () => {
            this.$panel.dialog.close();

            if (this.selected.length === 0) {
              return;
            }

            this.isProcessing = true;

            try {
              await this.$api.delete(this.endpoints.field + "/delete", {
                ids: this.selected.map((page) => page.id)
              });
              this.$panel.events.emit("model.update");
            } catch (error) {
              this.$panel.notification.error(error);
            } finally {
              this.isProcessing = false;
              this.stopSelecting();
            }
          }
        }
      });
    },
    filterTree(pages, term) {
      const result = [];

      for (const page of pages) {
        const title = String(page.text ?? "");
        const titleMatch = title.toLowerCase().includes(term);
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
    async reload() {
      if (!this.endpoints?.field) {
        return;
      }

      const page = this.pagination.page
        ?? sessionStorage.getItem(this.paginationId())
        ?? null;

      const response = await this.$api.get(this.endpoints.field, {
        page: page
      });

      this.data = response.data || [];
      this.pagination = response.pagination || {};
      this.add = response.add === true;
      this.sortable = response.sortable === true;

      await this.$nextTick();
      this.$panel.events.emit("field.loaded", this);
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
      const response = await this.$api.get(this.endpoints.field, {
        nolimit: true
      });
      this.allData = response.data || [];
    },
    async onSort(event) {
      const action = event.added ? "added" : event.moved ? "moved" : null;

      if (!action) {
        return;
      }

      this.isProcessing = true;

      const item = event[action].element;
      const position = event[action].newIndex + 1 + (this.pagination.offset || 0);

      try {
        await this.$api.pages.changeStatus(item.id, "listed", position);
        this.$panel.notification.success();
        this.$panel.events.emit("page.sort", item);
      } catch (error) {
        this.$panel.error(error);
        await this.reload();
      } finally {
        this.isProcessing = false;
      }
    },
    onAdd() {
      if (this.canAdd === false) {
        return;
      }

      this.$panel.dialog.open(this.endpoints.field + "/create");
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
  --input-color-border: transparent;
  margin-bottom: var(--spacing-3);
}

.k-pagestree-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: var(--spacing-3);
}
</style>
