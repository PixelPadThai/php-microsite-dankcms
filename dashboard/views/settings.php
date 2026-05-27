<?php
/** @var CMS $cms */
$stringsPath = __DIR__ . '/../../data/strings.json';
$contentPath = __DIR__ . '/../../data/content.json';
$stringsRaw  = is_file($stringsPath) ? (json_decode(file_get_contents($stringsPath), true) ?: []) : [];
$contentRaw  = is_file($contentPath) ? (json_decode(file_get_contents($contentPath), true) ?: []) : [];

$stringKeys = array_keys($stringsRaw);
sort($stringKeys);
$refOptions = [];
?>
<header class="dash-page-header">
  <h2>Settings</h2>
</header>

<script id="msd-strings-data"  type="application/json"><?= json_encode($stringsRaw,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="msd-strings-langs" type="application/json"><?= json_encode(array_values($cms->langs()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="msd-content-data"  type="application/json"><?= json_encode($contentRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="msd-string-keys"   type="application/json"><?= json_encode($stringKeys, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="msd-ref-options"   type="application/json"><?= json_encode($refOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<datalist id="msd-string-keys-list">
  <?php foreach ($stringKeys as $k): ?>
    <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>"></option>
  <?php endforeach; ?>
</datalist>

<div class="settings-page" x-cloak>

  <!-- Site basics ============================================== -->
  <section class="settings-section">
    <h3 class="settings-section__title">Site basics</h3>
    <p class="settings-section__lede">Edit the top-level settings for your site.</p>

    <div x-data="contentEditor()" class="ce">
      <div class="ce-pane">
        <div x-show="scope === 'settings'">
          <div class="ce-toolbar">
            <div class="ce-toolbar__spacer"></div>
            <button type="button" class="btn btn-primary"
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
        </div>
      </div>
      <div class="msd-toast" x-show="toast" x-transition :class="toast ? 'msd-toast--' + toast.kind : ''">
        <span x-text="toast ? toast.text : ''"></span>
      </div>
    </div>
  </section>

  <!-- Theme ==================================================== -->
  <section class="settings-section" x-data="themePicker()">
    <h3 class="settings-section__title">Theme</h3>
    <p class="settings-section__lede">Pick a brand color. The preview applies a 10-step OKLCH scale instantly across the dashboard; click Apply to save it to the public site too.</p>

    <div class="settings-theme">
      <div class="settings-theme__picker">
        <label class="settings-theme__label" for="settings-theme-color">Brand primary</label>
        <input id="settings-theme-color" type="color"
               :value="pickedHex.toLowerCase()"
               @input="onPickerInput($event.target.value)">
        <code class="settings-theme__hex" x-text="pickedHex"></code>
        <span class="settings-theme__hint" x-show="previewing">previewing…</span>
      </div>

      <div class="settings-theme__swatches">
        <template x-for="step in [50,100,200,300,400,500,600,700,800,900]" :key="step">
          <div class="settings-theme__swatch">
            <div class="settings-theme__swatch-color"
                 :style="'background: var(--color-primary-' + step + ');'"></div>
            <span class="settings-theme__swatch-label" x-text="step"></span>
          </div>
        </template>
      </div>

      <div class="settings-theme__actions">
        <button type="button" class="btn"
                :disabled="!isDirty() || saving"
                @click="reset()">Reset</button>
        <button type="button" class="btn btn-primary"
                :disabled="!isDirty() || saving"
                @click="apply()">
          <span x-text="saving ? 'Saving...' : 'Apply'"></span>
        </button>
      </div>
    </div>

    <div class="msd-toast" x-show="toast" x-transition :class="toast ? 'msd-toast--' + toast.kind : ''">
      <span x-text="toast ? toast.text : ''"></span>
    </div>
  </section>

  <!-- Languages ================================================ -->
  <section class="settings-section" x-data="languageManager()">
    <h3 class="settings-section__title">Languages</h3>
    <p class="settings-section__lede">One language is always the default. Disabling a language removes its routes from the public site; removing a language also drops all of its translations from strings.json.</p>

    <table class="settings-langs">
      <thead>
        <tr>
          <th>Code</th>
          <th>Label</th>
          <th>Enabled</th>
          <th>Default</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <template x-for="(l, idx) in langs" :key="l.code">
          <tr>
            <td><code x-text="l.code"></code></td>
            <td><input type="text" :value="l.label" @input="l.label = $event.target.value"></td>
            <td><input type="checkbox" :checked="l.enabled || l.default" :disabled="l.default" @change="l.enabled = $event.target.checked || l.default"></td>
            <td><input type="radio" name="settings-lang-default" :checked="l.default" @change="setDefault(idx)"></td>
            <td>
              <button type="button" class="btn settings-langs__remove" :disabled="l.default" @click="removeLanguage(idx)">Remove</button>
            </td>
          </tr>
        </template>
      </tbody>
    </table>

    <div class="settings-langs__add">
      <input type="text" maxlength="5" placeholder='Code (e.g. "th")' x-model="newCode">
      <input type="text" placeholder="Label (e.g. ไทย)" x-model="newLabel">
      <button type="button" class="btn" @click="addLanguage()">+ Add</button>
    </div>

    <div class="settings-section__actions">
      <button type="button" class="btn btn-primary"
              :disabled="!isDirty() || saving"
              @click="save()">
        <span x-text="saving ? 'Saving...' : 'Save languages'"></span>
      </button>
    </div>

    <div class="msd-toast" x-show="toast" x-transition :class="toast ? 'msd-toast--' + toast.kind : ''">
      <span x-text="toast ? toast.text : ''"></span>
    </div>
  </section>

  <!-- Maintenance ============================================== -->
  <section class="settings-section" x-data="maintenanceToggle()">
    <h3 class="settings-section__title">Maintenance mode</h3>
    <p class="settings-section__lede">When ON, public pages return a holding page (HTTP 503). The dashboard and <code>/health.json</code> stay reachable.</p>

    <label class="settings-maint">
      <input type="checkbox" x-model="enabled">
      <span>Maintenance mode</span>
    </label>

    <div class="settings-section__actions">
      <button type="button" class="btn btn-primary"
              :disabled="!isDirty() || saving"
              @click="save()">
        <span x-text="saving ? 'Saving...' : 'Save'"></span>
      </button>
    </div>

    <div class="msd-toast" x-show="toast" x-transition :class="toast ? 'msd-toast--' + toast.kind : ''">
      <span x-text="toast ? toast.text : ''"></span>
    </div>
  </section>

</div>
