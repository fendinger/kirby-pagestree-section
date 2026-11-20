<template>
  <k-field
    :id="id"
    :class="['k-pagestree-field', $attrs.class]"
    :help="help"
    :input="false"
    :label="label"
    :link="link"
    :name="name"
    :required="!!min"
    :style="$attrs.style"
    :type="type"
  >
    <template v-if="buttons.length > 0" #options>
      <k-button-group :buttons="buttons" size="xs" variant="filled" />
    </template>

    <k-input
      v-if="isSearching"
      :autofocus="true"
      :placeholder="$t('filter') + ' …'"
      :value="searchterm"
      class="k-modellist-field-search"
      icon="search"
      type="text"
      @input="searchterm = $event"
      @keydown.esc="onSearchToggle"
    />

    <k-validator v-bind="validator">
      <k-empty
        v-if="tree.length === 0"
        v-bind="emptyProps"
        @click="canAdd ? onAdd() : null"
      />
      <k-draggable
        v-else
        class="k-pagestree-tree"
        :list="tree"
        :options="dragOptions"
        :handle="true"
        @change="onChange"
      >
        <pagestree-node
          v-for="node in tree"
          :key="node.id"
          :node="node"
          :open-nodes="visibleOpenNodes"
          :selected="selected"
          :selecting="isSelecting"
          :sortable="isTreeSortable"
          @change="onChildChange"
          @select="onNodeSelect"
          @toggle="toggleNode"
        />
      </k-draggable>

      <footer v-if="isPaginated" class="k-collection-footer">
        <span></span>
        <k-pagination
          v-bind="state.pagination"
          :details="true"
          @paginate="onPaginate"
        />
      </footer>
    </k-validator>
  </k-field>
</template>

<script>
import PagestreeNode from "./PagestreeNode.vue";

// Extends the Panel's pages field: it brings the state loading, refresh
// events, batch selection, adding pages and the sorting request along.
export default {
  extends: "k-pagelist-field",
  components: { PagestreeNode },
  props: {
    maxDepth: Number
  },
  data() {
    return {
      openNodes: {}
    };
  },
  computed: {
    dragOptions() {
      return {
        sort: this.isTreeSortable,
        disabled: !this.isTreeSortable,
        draggable: ".k-draggable-item",
        animation: 150
      };
    },
    isPaginated() {
      return (
        this.isSearching === false &&
        this.state.pagination.total > this.state.pagination.limit
      );
    },
    // manual sorting is impossible while the tree is filtered
    isTreeSortable() {
      return this.isSortable && !this.searchterm;
    },
    term() {
      return this.isSearching && this.searchterm
        ? this.searchterm.toLowerCase()
        : null;
    },
    tree() {
      const nodes = this.state.models.map((model) => this.decorate(model));
      return this.term ? this.filterTree(nodes, this.term) : nodes;
    },
    // a search opens every node that has matches
    visibleOpenNodes() {
      if (!this.term) {
        return this.openNodes;
      }

      const open = {};
      this.collectOpenNodes(this.tree, open);
      return open;
    }
  },
  created() {
    this.restoreOpenNodes();
  },
  methods: {
    collectOpenNodes(nodes, open) {
      for (const node of nodes) {
        if (node.children.length > 0) {
          open[node.id] = true;
          this.collectOpenNodes(node.children, open);
        }
      }
    },
    // The same props the pages field gives each of its items, here for
    // a page and all its subpages
    decorate(model) {
      const sortable = model.permissions.sort && this.isSortable;
      const deletable =
        model.permissions.delete &&
        this.state.pagination.total > (this.min ?? 0);
      const flag = {
        ...this.$helper.page.status(
          model.status,
          model.permissions.changeStatus === false,
          model.flag
        ),
        class: "k-page-status-icon-option",
        dialog: model.link + "/changeStatus"
      };

      return {
        ...model,
        buttons: [flag, ...(model.buttons ?? [])],
        data: {
          "data-id": model.id,
          "data-status": model.status,
          "data-template": model.template
        },
        flag,
        deletable,
        options: this.$dropdown(model.link, {
          query: { delete: deletable, sort: sortable, view: "list" }
        }),
        selectable: this.isSelecting && deletable,
        sortable,
        children: (model.children ?? []).map((child) => this.decorate(child))
      };
    },
    // the search runs in the browser, so the filter of the field
    // does not reload anything
    filter() {},
    filterTree(nodes, term) {
      const result = [];

      for (const node of nodes) {
        const children = this.filterTree(node.children, term);

        if ((node.text ?? "").toLowerCase().includes(term) || children.length) {
          result.push({
            ...node,
            children,
            hasChildren: children.length > 0
          });
        }
      }

      return result;
    },
    onNodeSelect(node) {
      const index = this.selected.indexOf(node.id);

      this.onSelect(
        index > -1
          ? this.selected.toSpliced(index, 1)
          : [...this.selected, node.id]
      );
    },
    async onChildChange(event) {
      const action = event.added ? "added" : event.moved ? "moved" : null;

      if (action === null) {
        return;
      }

      const page = event[action].element;
      const position = event[action].newIndex + 1;

      await this.request(() =>
        this.$api.pages.changeStatus(page.id, "listed", position)
      );
    },
    // The search needs the whole tree, so it loads the root level
    // without pagination and filters it in the browser.
    onSearchToggle() {
      this.isSearching = !this.isSearching;
      this.searchterm = null;
      this.reload({ page: 1 });
    },
    async reload(query = {}) {
      this.isProcessing = true;

      try {
        const params = { page: this.state.pagination.page, ...query };

        if (this.isSearching) {
          params.nolimit = true;
          params.page = null;
        }

        this.state = await this.$api.get(this.endpoints.field, params);
        this.error = null;
      } catch (error) {
        this.error = error.message;
        this.$panel.error(error);
      } finally {
        this.isLoading = false;
        this.isProcessing = false;
      }

      await this.$nextTick();
      this.$events.emit("field.loaded", this);
    },
    restoreOpenNodes() {
      try {
        const saved = sessionStorage.getItem(this.storageKey());

        if (saved) {
          this.openNodes = JSON.parse(saved);
        }
      } catch (e) {
        // ignore a missing or broken storage
      }
    },
    storageKey() {
      return "kirby$pagestree$" + this.endpoints.field;
    },
    toggleNode(id) {
      this.openNodes = { ...this.openNodes, [id]: !this.openNodes[id] };

      try {
        sessionStorage.setItem(this.storageKey(), JSON.stringify(this.openNodes));
      } catch (e) {
        // ignore a missing or full storage
      }
    }
  }
};
</script>

<style>
.k-pagestree-tree {
  display: grid;
  gap: 2px;
}
</style>
