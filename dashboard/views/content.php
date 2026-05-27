<?php
/** @var CMS $cms */
$stringsPath = __DIR__ . '/../../data/strings.json';
$contentPath = __DIR__ . '/../../data/content.json';

$stringsRaw  = is_file($stringsPath) ? (json_decode(file_get_contents($stringsPath), true) ?: []) : [];
$contentRaw  = is_file($contentPath) ? (json_decode(file_get_contents($contentPath), true) ?: []) : [];

// Languages enabled in content.json (e.g. [{code:'en', label:'English', ...}])
$langs = $cms->langs();

// String keys for the `string_ref` datalist. Sorted for stable UI.
$stringKeys = array_keys($stringsRaw);
sort($stringKeys);

// Reference options for the `reference` field type. Phase 2 only ships the
// `pages` collection; build {collection_name: [{value, label}, ...]} so future
// schemas can `{ type: 'reference', collection: 'pages' }`.
$refOptions = [];
$collections = $contentRaw['collections'] ?? [];
$schemas     = $contentRaw['_schemas']    ?? [];
foreach ($collections as $cname => $rows) {
    if (!is_array($rows)) continue;
    $schema = $schemas[$cname] ?? [];
    // Find primary-key field name.
    $pk = null;
    foreach ($schema as $fname => $fdef) {
        if (is_array($fdef) && !empty($fdef['primary'])) { $pk = $fname; break; }
    }
    $opts = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $val   = $pk && isset($row[$pk]) ? $row[$pk] : ($row['id'] ?? '');
        $label = $row['title'] ?? ($row['slug'] ?? $val);
        $opts[] = ['value' => (string)$val, 'label' => (string)$label];
    }
    $refOptions[$cname] = $opts;
}
?>
<header class="dash-page-header">
  <h2>Content</h2>
</header>

<script id="msd-strings-data"  type="application/json"><?= json_encode($stringsRaw,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="msd-strings-langs" type="application/json"><?= json_encode(array_values($langs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="msd-content-data"  type="application/json"><?= json_encode($contentRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="msd-string-keys"   type="application/json"><?= json_encode($stringKeys, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="msd-ref-options"   type="application/json"><?= json_encode($refOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<div x-data="{ tab: 'strings' }" x-cloak class="content-editor">

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

  <!-- ============================================================ -->
  <!-- Strings tab (unchanged from Phase 1)                         -->
  <!-- ============================================================ -->
  <div x-show="tab === 'strings'" x-data="stringsEditor()" class="strings-editor">

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

    <div class="msd-toast" x-show="toast" x-transition :class="toast ? 'msd-toast--' + toast.kind : ''">
      <span x-text="toast ? toast.text : ''"></span>
    </div>

  </div>

  <!-- Shared datalist for all string_ref inputs (mounted once). -->
  <datalist id="msd-string-keys-list">
    <?php foreach ($stringKeys as $k): ?>
      <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>"></option>
    <?php endforeach; ?>
  </datalist>

  <!-- ============================================================ -->
  <!-- Content tab (Phase 2)                                        -->
  <!-- ============================================================ -->
  <div x-show="tab === 'content'"
       x-data="contentEditor()"
       class="ce">

    <div class="ce-layout">

      <!-- Left rail ------------------------------------------------- -->
      <aside class="ce-rail" aria-label="Content sections">
        <button type="button"
                class="ce-rail__item"
                :class="scope === 'settings' ? 'is-active' : ''"
                @click="goto('settings')">Settings</button>

        <template x-for="name in collections()" :key="name">
          <button type="button"
                  class="ce-rail__item"
                  :class="scope === ('collection:' + name) ? 'is-active' : ''"
                  @click="goto('collection:' + name)"
                  x-text="name.charAt(0).toUpperCase() + name.slice(1)"></button>
        </template>
      </aside>

      <!-- Right pane ------------------------------------------------ -->
      <section class="ce-pane">

        <!-- Settings scope ------------------------------------------ -->
        <div x-show="scope === 'settings'">
          <div class="ce-toolbar">
            <h3 class="ce-heading">Settings</h3>
            <div class="ce-toolbar__spacer"></div>
            <button type="button"
                    class="btn btn-primary"
                    :disabled="!isDirty() || saving"
                    @click="save()">
              <span x-text="saving ? 'Saving...' : 'Save'"></span>
            </button>
          </div>

          <div class="ce-form">
            <template x-for="entry in fieldEntries(formSchema)" :key="entry[0]">
              <div>
                <?php /* Render a typed field. Reused below for collection records. */ ?>
                <template x-if="true">
                  <div x-data="{ name: entry[0], def: entry[1], path: entry[0] }">
                    <?php require __DIR__ . '/_typed-field.php'; ?>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </div>

        <!-- Collection list scope ----------------------------------- -->
        <div x-show="scope.indexOf('collection:') === 0 && mode === 'list'">
          <div class="ce-toolbar">
            <h3 class="ce-heading" x-text="headingForCollection()"></h3>
            <div class="ce-toolbar__spacer"></div>
            <button type="button" class="btn btn-primary" @click="newRecord()">+ New record</button>
          </div>

          <table class="ce-table" x-show="records().length > 0">
            <thead>
              <tr>
                <template x-for="col in tableColumns()" :key="col">
                  <th x-text="col"></th>
                </template>
              </tr>
            </thead>
            <tbody>
              <template x-for="(rec, idx) in records()" :key="idx">
                <tr class="ce-table__row" @click="editRecord(idx)">
                  <template x-for="col in tableColumns()" :key="col">
                    <td x-text="cellDisplay(rec, col)"></td>
                  </template>
                </tr>
              </template>
            </tbody>
          </table>

          <div class="ce-empty" x-show="records().length === 0">
            No records yet. Click <strong>+ New record</strong> to add one.
          </div>
        </div>

        <!-- Collection record edit scope --------------------------- -->
        <div x-show="scope.indexOf('collection:') === 0 && mode === 'edit'">
          <div class="ce-toolbar">
            <button type="button" class="ce-back" @click="backToList()">&larr; Back to list</button>
            <h3 class="ce-heading">
              <span x-text="headingForCollection()"></span>
              <span class="ce-heading__sub" x-text="recordIsNew ? '— new record' : (currentRecordPk() ? '— ' + currentRecordPk() : '')"></span>
            </h3>
            <div class="ce-toolbar__spacer"></div>
            <button type="button"
                    class="btn btn-primary"
                    :disabled="!isDirty() || saving"
                    @click="save()">
              <span x-text="saving ? 'Saving...' : 'Save'"></span>
            </button>
          </div>

          <div class="ce-form">
            <template x-for="entry in fieldEntries(formSchema)" :key="entry[0]">
              <div>
                <template x-if="true">
                  <div x-data="{ name: entry[0], def: entry[1], path: entry[0] }">
                    <?php require __DIR__ . '/_typed-field.php'; ?>
                  </div>
                </template>
              </div>
            </template>
          </div>

          <div class="ce-danger" x-show="!recordIsNew">
            <button type="button" class="btn ce-delete" @click="deleteRecord()">Delete record</button>
          </div>
        </div>

      </section>
    </div>

    <div class="msd-toast" x-show="toast" x-transition :class="toast ? 'msd-toast--' + toast.kind : ''">
      <span x-text="toast ? toast.text : ''"></span>
    </div>

  </div>

</div>
