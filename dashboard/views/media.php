<?php
/** @var CMS $cms */
require_once __DIR__ . '/../../class/Media.php';
$media   = new Media(
    __DIR__ . '/../../data/uploads',
    __DIR__ . '/../../data/content.json',
    __DIR__ . '/../../data/backups'
);
$initial = $media->list();
?>
<header class="dash-page-header">
  <h2>Media</h2>
</header>

<script id="msd-media-data" type="application/json"><?= json_encode($initial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<div class="media-page" x-data="mediaLibrary()" x-cloak>

  <div class="media-toolbar">
    <label class="media-drop" :class="dragging ? 'is-drag' : ''"
           @dragover.prevent="dragging = true"
           @dragleave.prevent="dragging = false"
           @drop.prevent="onDrop($event)">
      <input type="file" multiple accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
             class="media-drop__input" @change="onFileInput($event)">
      <span class="media-drop__label">Drop images here or click to upload</span>
      <span class="media-drop__hint" x-show="uploading > 0" x-text="'Uploading ' + uploading + '…'"></span>
    </label>
  </div>

  <div class="media-grid" x-show="items.length > 0">
    <template x-for="item in items" :key="item.name">
      <button type="button" class="media-tile" @click="openTile(item)">
        <div class="media-tile__thumb">
          <img :src="thumbUrl(item)" :alt="item.name" loading="lazy">
        </div>
        <div class="media-tile__meta">
          <div class="media-tile__name" x-text="item.name"></div>
          <div class="media-tile__sub">
            <span x-text="formatSize(item.size)"></span>
            <span class="media-tile__badge"
                  :class="item.used_by_count > 0 ? 'media-tile__badge--used' : 'media-tile__badge--unused'"
                  x-text="item.used_by_count > 0 ? ('used ' + item.used_by_count + '×') : 'unused'"></span>
          </div>
        </div>
      </button>
    </template>
  </div>

  <div class="media-empty" x-show="items.length === 0">
    No media yet. Drop an image above to upload.
  </div>

  <!-- Tile modal -->
  <div class="media-modal" x-show="selected" x-transition @keydown.escape.window="closeTile()" @click.self="closeTile()">
    <div class="media-modal__panel" x-show="selected">
      <button type="button" class="media-modal__close" @click="closeTile()" aria-label="Close">×</button>

      <div class="media-modal__preview">
        <img x-show="selected" :src="selected ? selected.url : ''" :alt="selected ? selected.name : ''">
      </div>

      <div class="media-modal__body">
        <h3 class="media-modal__name" x-text="selected ? selected.name : ''"></h3>

        <dl class="media-modal__props">
          <dt>URL</dt>
          <dd>
            <input type="text" readonly :value="selected ? selected.url : ''" @focus="$event.target.select()" class="media-modal__url">
            <button type="button" class="btn" @click="copyUrl()">Copy</button>
          </dd>
          <dt>Size</dt><dd x-text="selected ? formatSize(selected.size) : ''"></dd>
          <dt>Type</dt><dd x-text="selected ? selected.mime : ''"></dd>
          <dt>WebP</dt><dd x-text="selected && selected.has_webp ? 'yes' : 'no'"></dd>
          <dt>Thumb</dt><dd x-text="selected && selected.has_thumb ? 'yes' : 'no'"></dd>
          <dt>Used</dt><dd x-text="selected ? (selected.used_by_count + ' reference(s)') : ''"></dd>
        </dl>

        <div class="media-modal__refs" x-show="refsList.length > 0">
          <h4>References</h4>
          <ul>
            <template x-for="r in refsList" :key="r.path">
              <li><code x-text="r.path"></code></li>
            </template>
          </ul>
        </div>

        <div class="media-modal__actions">
          <button type="button" class="btn ce-delete" @click="deleteSelected(false)" x-show="refsList.length === 0">Delete</button>
          <button type="button" class="btn ce-delete" @click="deleteSelected(true)" x-show="refsList.length > 0">
            Force delete + null <span x-text="refsList.length"></span> reference(s)
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="msd-toast" x-show="toast" x-transition :class="toast ? 'msd-toast--' + toast.kind : ''">
    <span x-text="toast ? toast.text : ''"></span>
  </div>

</div>
