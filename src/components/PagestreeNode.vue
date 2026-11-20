<template>
  <div class="k-pagestree-node k-draggable-item">
    <!-- Toggle / spacer before the item -->
    <button
      v-if="node.hasChildren"
      class="k-pagestree-node-toggle"
      type="button"
      @click.stop="$emit('toggle', node.id)"
    >
      <k-icon :type="isOpen ? 'angle-down' : 'angle-right'" />
    </button>
    <span v-else class="k-pagestree-node-toggle"></span>

    <k-item
      v-bind="itemProps"
      :link="selecting ? false : node.link"
      :selected="selected.includes(node.id)"
      :selecting="selecting"
      class="k-pagestree-node-item"
      layout="list"
      @select="$emit('select', node)"
    />

    <!-- Children -->
    <k-draggable
      v-if="isOpen && node.children.length > 0"
      class="k-pagestree-node-children"
      :list="node.children"
      :options="dragOptions"
      :handle="true"
      @change="$emit('change', $event)"
    >
      <pagestree-node
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :open-nodes="openNodes"
        :selected="selected"
        :selecting="selecting"
        :sortable="sortable"
        @change="$emit('change', $event)"
        @select="$emit('select', $event)"
        @toggle="$emit('toggle', $event)"
      />
    </k-draggable>
  </div>
</template>

<script>
export default {
  name: "pagestree-node",
  props: {
    node: { type: Object, required: true },
    openNodes: { type: Object, default: () => ({}) },
    selected: { type: Array, default: () => [] },
    selecting: Boolean,
    sortable: Boolean
  },
  emits: ["change", "select", "toggle"],
  computed: {
    dragOptions() {
      return {
        sort: this.sortable,
        disabled: !this.sortable,
        draggable: ".k-draggable-item",
        animation: 150
      };
    },
    isOpen() {
      return this.openNodes[this.node.id] === true;
    },
    // everything but the tree itself belongs to the `k-item`
    itemProps() {
      const { children, hasChildren, ...item } = this.node;
      return item;
    }
  }
};
</script>

<style>
.k-pagestree-node {
  position: relative;
  display: flex;
  align-items: stretch;
  flex-wrap: wrap;
}

/* Toggle / spacer: same size as the k-item image area */
.k-pagestree-node-toggle {
  width: var(--field-input-height, 2.25rem);
  height: var(--field-input-height, 2.25rem);
  flex: 0 0 var(--field-input-height, 2.25rem);
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
  color: var(--color-text, inherit);
}

/* Item fills remaining space */
.k-pagestree-node-item {
  flex: 1;
  min-width: 0;
}

/* Move sort-button left past the toggle area so it appears at the field edge */
.k-pagestree-node-item .k-sort-button {
  left: calc(-1 * var(--button-width) - var(--field-input-height, 2.25rem)) !important;
}

/* Children: full width, indented */
.k-pagestree-node-children {
  width: 100%;
  display: grid;
  gap: 2px;
  margin-top: 2px;
  padding-inline-start: 1.5rem;
}

/* Show sort handle when hovering the toggle */
.k-pagestree-node:has(> .k-pagestree-node-toggle:hover) > .k-pagestree-node-item .k-sort-button {
  opacity: 1 !important;
}

/* Status flag: hide text label */
.k-page-status-icon-option .k-button-text {
  display: none;
}
</style>
