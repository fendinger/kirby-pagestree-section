<template>
  <div class="k-pagestree-node k-draggable-item">
    <!-- Toggle / spacer before the item -->
    <button
      v-if="page.hasChildren"
      class="k-pagestree-node-toggle"
      @click.stop="$emit('toggle', page.id)"
    >
      <k-icon :type="isOpen ? 'angle-down' : 'angle-right'" />
    </button>
    <span v-else class="k-pagestree-node-toggle"></span>

    <!-- Native k-item -->
    <k-item
      :image="itemImage"
      :text="page.text"
      :info="page.info"
      :link="selecting ? false : pageLink"
      :sortable="sortable && page.status === 'listed'"
      :selecting="selecting"
      :selectable="page.permissions.delete"
      :buttons="[statusButton]"
      :options="dropdownOptions"
      class="k-pagestree-node-item"
      data-layout="list"
      @select="$emit('select', page)"
    />

    <!-- Children -->
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
        :max-depth="maxDepth"
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
    maxDepth: { type: Number, default: null },
    sortable: { type: Boolean, default: true },
    openNodes: { type: Object, default: () => ({}) },
    selecting: { type: Boolean, default: false },
    selected: { type: Array, default: () => [] }
  },
  computed: {
    isOpen() {
      return !!this.openNodes[this.page.id];
    },
    isSelected() {
      return this.selected.some(s => s.id === this.page.id);
    },
    pageLink() {
      return "/pages/" + this.page.id.replace(/\//g, "+");
    },
    itemImage() {
      if (this.page.image) {
        return this.page.image;
      }
      return { icon: "page", back: "pattern" };
    },
    statusIcon() {
      return "status-" + this.page.status;
    },
    statusTheme() {
      const themes = {
        draft: "negative-icon",
        unlisted: "info-icon",
        listed: "positive-icon"
      };
      return themes[this.page.status] || "positive-icon";
    },
    statusTitle() {
      const label = this.$t("page.status") + ": " + this.$t("page.status." + this.page.status);
      if (!this.page.permissions.changeStatus) {
        return label + " (" + this.$t("disabled") + ")";
      }
      return label;
    },
    statusButton() {
      return {
        class: "k-page-status-icon-option",
        icon: this.statusIcon,
        theme: this.statusTheme,
        title: this.statusTitle,
        disabled: !this.page.permissions.changeStatus,
        dialog: this.page.panelUrl + "/changeStatus",
        size: "xs",
        style: "--icon-size: 15px"
      };
    },
    dragOptions() {
      return {
        sort: this.sortable,
        disabled: !this.sortable,
        draggable: ".k-draggable-item",
        animation: 150
      };
    },
    dropdownOptions() {
      const p = this.page.permissions;
      const panelUrl = this.page.panelUrl;

      return [
        {
          icon: "open",
          text: this.$t("open"),
          link: this.page.previewUrl,
          target: "_blank",
          disabled: !p.preview,
        },
        {
          icon: "window",
          text: this.$t("preview"),
          link: panelUrl + "/preview/changes",
          disabled: !p.preview,
        },
        "-",
        {
          icon: "title",
          text: this.$t("rename"),
          dialog: {
            url: panelUrl + "/changeTitle",
            query: { select: "title" }
          },
          disabled: !p.changeTitle,
        },
        {
          icon: "url",
          text: this.$t("page.changeSlug"),
          dialog: {
            url: panelUrl + "/changeTitle",
            query: { select: "slug" }
          },
          disabled: !p.changeSlug,
        },
        {
          icon: "preview",
          text: this.$t("page.changeStatus"),
          dialog: panelUrl + "/changeStatus",
          disabled: !p.changeStatus,
        },
        {
          icon: "sort",
          text: this.$t("page.sort"),
          dialog: panelUrl + "/changeSort",
          disabled: !p.sort,
        },
        {
          icon: "template",
          text: this.$t("page.changeTemplate"),
          dialog: panelUrl + "/changeTemplate",
          disabled: !p.changeTemplate,
        },
        "-",
        {
          icon: "parent",
          text: this.$t("page.move"),
          dialog: panelUrl + "/move",
          disabled: !p.move,
        },
        {
          icon: "copy",
          text: this.$t("duplicate"),
          dialog: panelUrl + "/duplicate",
          disabled: !p.duplicate,
        },
        "-",
        {
          icon: "trash",
          text: this.$t("delete"),
          dialog: panelUrl + "/delete",
          disabled: !p.delete,
        },
      ];
    }
  },
  methods: {
    async onSort(e) {
      let action = null;
      if (e.added) action = "added";
      if (e.moved) action = "moved";
      if (!action) return;

      const item = e[action].element;
      const position = e[action].newIndex + 1;

      try {
        await this.$api.pages.changeStatus(item.id, "listed", position);
        this.$panel.notification.success();
        this.$panel.events.emit("page.sort", item);
      } catch (err) {
        this.$panel.error(err);
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

/* Move sort-button left past the toggle area so it appears at section edge */
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
