<?php
/**
 * Image-picker modal. Mounted once per dashboard page that needs it.
 *
 * Opens via window.MSD.imagePicker.open() which returns a Promise<string|null>.
 * The modal lists media from /dashboard/api/list-media on open and supports
 * inline upload to /dashboard/api/upload-media.
 */
?>
<div class="image-picker" x-data="imagePicker()" x-show="open" x-cloak
     @keydown.escape.window="cancel()" @click.self="cancel()">
  <div class="image-picker__panel" x-show="open" x-transition>
    <header class="image-picker__head">
      <h3>Pick an image</h3>
      <button type="button" class="image-picker__close" @click="cancel()" aria-label="Close">×</button>
    </header>

    <div class="image-picker__toolbar">
      <label class="btn">
        <span x-text="uploading ? 'Uploading…' : 'Upload new'"></span>
        <input type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
               style="display:none" @change="onUpload($event)">
      </label>
      <input type="search" class="image-picker__search"
             placeholder="Search by name…" x-model="search">
      <div class="image-picker__spacer"></div>
      <button type="button" class="btn" @click="refresh()" :disabled="loading">
        <span x-text="loading ? 'Loading…' : 'Refresh'"></span>
      </button>
    </div>

    <div class="image-picker__grid" x-show="filteredItems().length > 0">
      <template x-for="item in filteredItems()" :key="item.name">
        <button type="button" class="image-picker__tile" @click="pick(item.url)">
          <div class="image-picker__thumb">
            <img :src="thumbFor(item)" :alt="item.name" loading="lazy">
          </div>
          <div class="image-picker__name" x-text="item.name"></div>
        </button>
      </template>
    </div>

    <div class="image-picker__empty" x-show="!loading && filteredItems().length === 0">
      <span x-show="items.length === 0">No media yet — upload one above.</span>
      <span x-show="items.length > 0">No matches for "<span x-text="search"></span>"</span>
    </div>

    <footer class="image-picker__foot">
      <button type="button" class="btn" @click="clearValue()">Clear field</button>
      <div class="image-picker__spacer"></div>
      <button type="button" class="btn" @click="cancel()">Cancel</button>
    </footer>
  </div>
</div>
