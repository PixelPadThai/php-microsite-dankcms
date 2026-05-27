<?php
/** @var CMS $cms */
$stringsPath = __DIR__ . '/../../data/strings.json';
$stringsRaw  = is_file($stringsPath) ? (json_decode(file_get_contents($stringsPath), true) ?: []) : [];

// Languages enabled in content.json (e.g. [{code:'en', label:'English', ...}])
$langs = $cms->langs();

// Build the section→keys map server-side too (purely informational; the
// JS rebuilds it from the data anyway). Section = prefix before first `_`.
?>
<header class="dash-page-header">
  <h2>Content</h2>
</header>

<script id="msd-strings-data" type="application/json"><?= json_encode($stringsRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="msd-strings-langs" type="application/json"><?= json_encode(array_values($langs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<div x-data="stringsEditor()" x-cloak class="content-editor">

  <div class="content-tabs" role="tablist">
    <button type="button" role="tab"
            :aria-selected="tab === 'strings'"
            :class="tab === 'strings' ? 'content-tab is-active' : 'content-tab'"
            @click="tab = 'strings'">Strings</button>
    <button type="button" role="tab"
            :aria-selected="tab === 'content'"
            :class="tab === 'content' ? 'content-tab is-active' : 'content-tab'"
            @click="tab = 'content'">Content</button>
  </div>

  <div x-show="tab === 'content'" class="content-placeholder">
    <p>Coming in Phase 2.</p>
  </div>

  <div x-show="tab === 'strings'" class="strings-editor">

    <div class="strings-toolbar">
      <input type="search" x-model="search" placeholder="Search keys or values..." class="strings-search">

      <div class="strings-lang-tabs" role="tablist">
        <template x-for="lang in langs" :key="lang.code">
          <button type="button" role="tab"
                  :aria-selected="activeLang === lang.code"
                  :class="activeLang === lang.code ? 'strings-lang-tab is-active' : 'strings-lang-tab'"
                  @click="activeLang = lang.code"
                  x-text="lang.label"></button>
        </template>
      </div>

      <div class="strings-toolbar__spacer"></div>

      <span class="strings-dirty-badge" x-show="dirtyCount() > 0">
        <span x-text="dirtyCount()"></span> unsaved
      </span>

      <span class="strings-saved-badge" x-show="lastSaved" x-text="'Last saved ' + formatTime(lastSaved)"></span>

      <button type="button"
              class="btn btn-primary"
              :disabled="dirtyCount() === 0 || saving"
              @click="save()">
        <span x-text="saving ? 'Saving...' : 'Save'"></span>
      </button>
    </div>

    <div x-show="search && totalVisible() === 0" class="strings-empty">
      No keys match "<span x-text="search"></span>"
    </div>

    <template x-for="sec in sectionList()" :key="sec[0]">
      <section class="strings-section" x-show="visibleKeys(sec[1]).length > 0">
        <h3 class="strings-section__title" x-text="sec[0]"></h3>

        <template x-for="key in visibleKeys(sec[1])" :key="key">
          <div class="strings-row" :class="rowDirty(key) ? 'is-dirty' : ''">
            <div class="strings-row__head">
              <code class="strings-row__key" x-text="key"></code>
            </div>

            <template x-for="lang in langs" :key="lang.code">
              <div class="strings-row__field">
                <label class="strings-row__lang" x-text="lang.code.toUpperCase()"></label>
                <textarea
                  class="strings-row__textarea"
                  :value="getVal(key, lang.code)"
                  @input="setVal(key, lang.code, $event.target.value)"
                  rows="2"
                  dir="auto"></textarea>
              </div>
            </template>
          </div>
        </template>
      </section>
    </template>

  </div>

  <div class="msd-toast" x-show="toast" x-transition :class="toast ? 'msd-toast--' + toast.kind : ''">
    <span x-text="toast ? toast.text : ''"></span>
  </div>

</div>
