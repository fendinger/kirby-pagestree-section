<template>
  <div class="k-pagestree-node">
    <button
      v-if="page.hasChildren"
      class="k-pagestree-node-toggle"
      type="button"
      @click.stop="$emit('toggle', page.id)"
    >
      <k-icon :type="isOpen ? 'angle-down' : 'angle-right'" />
    </button>
    <span v-else class="k-pagestree-node-toggle"></span>

    <k-item
      :buttons="[statusButton]"
      :image="itemImage"
      :info="page.info"
      :link="selecting ? false : page.link"
      :options="dropdownOptions"
      :selectable="page.permissions.delete"
      :selected="isSelected"
      :selecting="selecting"
      :sortable="sortable && page.status === 'listed'"
      :text="page.text"
      class="k-pagestree-node-item"
      data-layout="list"
      @select="$emit('select', page)"
    />

    <k-draggable
      v-if="isOpen && page.children && page.children.length > 0"
      class="k-pagestree-node-children"
      :list="page.children"
      :options="dragOptions"
      :handle="true"
      @change="onSort"
    >
      <pagestree-node
        v-for="child in page.children"
        :key="child.id"
        :page="child"
        :depth="depth + 1"
        :sortable="sortable"
        :open-nodes="openNodes"
        :selecting="selecting"
        :selected="selected"
        @toggle="$emit('toggle', $event)"
        @reload="$emit('reload')"
        @select="$emit('select', $event)"
      />
    </k-draggable>
  </div>
</template>

<script>
export default {
  name: "pagestree-node",
  props: {
    page: Object,
    depth: { type: Number, default: 0 },
    sortable: { type: Boolean, default: true },
    openNodes: { type: Object, default: () => ({}) },
    selecting: { type: Boolean, default: false },
    selected: { type: Array, default: () => [] }
  },
  emits: ["reload", "select", "toggle"],
  computed: {
    isOpen() {
      return !!this.openNodes[this.page.id];
    },
    isSelected() {
      return this.selected.some((item) => item.id === this.page.id);
    },
    itemImage() {
      if (this.page.image) {
        return this.page.image;
      }

      return { icon: "page", back: "pattern" };
    },
    statusButton() {
      return {
        ...this.$helper.page.status(
          this.page.status,
          this.page.permissions.changeStatus === false,
          this.page.flag || {}
        ),
        class: "k-page-status-icon-option",
        dialog: this.page.link + "/changeStatus"
      };
    },
    dragOptions() {
      return {
        sort: this.sortable,
        disabled: this.sortable === false,
        draggable: ".k-pagestree-node",
        animation: 150
      };
    },
    dropdownOptions() {
      return this.$dropdown(this.page.link, {
        query: {
          delete: this.page.permissions.delete,
          sort: this.page.permissions.sort && this.sortable,
          view: "list"
        }
      });
    }
  },
  methods: {
    async onSort(event) {
      const action = event.added ? "added" : event.moved ? "moved" : null;

      if (!action) {
        return;
      }

      const item = event[action].element;
      const position = event[action].newIndex + 1;

      try {
        await this.$api.pages.changeStatus(item.id, "listed", position);
        this.$panel.notification.success();
        this.$panel.events.emit("page.sort", item);
      } catch (error) {
        this.$panel.error(error);
        this.$emit("reload");
      }
    }
  }
};
</script>

<style>
.k-pagestree-node {
  position: relative;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
}

.k-pagestree-node-toggle {
  width: var(--input-height, 2.25rem);
  height: var(--input-height, 2.25rem);
  flex: 0 0 var(--input-height, 2.25rem);
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--item-color-back);
  border: none;
  border-radius: var(--rounded);
  cursor: pointer;
  padding: 0;
  margin-inline-end: 2px;
  color: var(--item-color-icon);
}

.k-pagestree-node-toggle:hover {
  color: var(--color-text);
}

.k-pagestree-node-item {
  flex: 1;
  min-width: 0;
}

/*
 * Kirby 6 pulls the whole item left by the sort-handle width.
 * That covered the toggle. Keep the item beside the arrow and
 * hang only the handle past it, at the row's left edge.
 */
.k-pagestree-node > .k-pagestree-node-item.k-item[data-layout="list"] {
  margin-inline-start: 0;
}

.k-pagestree-node-item.k-item .k-item-sort-handle {
  position: absolute;
  z-index: 2;
  inset-block: 0;
  height: auto;
  --button-height: 100%;
  inset-inline-start: calc(
    -1 * (var(--item-sort-button-width, 1.75rem) + var(--input-height, 2.25rem) + 2px)
  );
}

.k-pagestree-node-children {
  width: 100%;
  display: grid;
  gap: 2px;
  margin-top: 2px;
  padding-inline-start: 1.5rem;
}

.k-pagestree-node:has(> .k-pagestree-node-toggle:hover) > .k-pagestree-node-item .k-item-sort-handle {
  opacity: 1;
}

.k-page-status-icon-option .k-button-text {
  display: none;
}
</style>
