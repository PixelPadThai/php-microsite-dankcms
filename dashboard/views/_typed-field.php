<?php
/**
 * Typed-field partial.
 *
 * Renders one schema field. Expects an enclosing Alpine scope that provides
 * three reactive locals:
 *   - name : string (field name, e.g. "site_name")
 *   - def  : object (schema definition, e.g. { type: 'string', required: true })
 *   - path : string (dot-path into formValue, e.g. "site_name" or "social.facebook")
 *
 * Parent component must also expose: getField, setField, toggleBool,
 * setColorHex, stringKeys, refOptions, fieldEntries (from contentEditor /
 * typedForm). Switch order goes from most-specific to least-specific so the
 * first matching branch wins.
 */
?>
<div class="tf-field">

  <!-- Boolean: render the toggle inline with the label so it reads as a switch row. -->
  <template x-if="def.type === 'boolean'">
    <label class="tf-toggle">
      <input type="checkbox"
             :checked="getField(path) === true"
             @change="setField(path, $event.target.checked)">
      <span class="tf-label" x-text="def.label || name"></span>
    </label>
  </template>

  <template x-if="def.type !== 'boolean'">
    <label class="tf-label" x-text="def.label || name"></label>
  </template>

  <!-- string -->
  <template x-if="def.type === 'string' || def.type === undefined">
    <input type="text"
           class="tf-input"
           :data-tf-focus="def.primary ? name : null"
           :value="getField(path) ?? ''"
           @input="setField(path, $event.target.value)">
  </template>

  <!-- text (multiline) -->
  <template x-if="def.type === 'text'">
    <textarea class="tf-input tf-textarea"
              :value="getField(path) ?? ''"
              @input="setField(path, $event.target.value)"></textarea>
  </template>

  <!-- email / url / tel -->
  <template x-if="def.type === 'email'">
    <input type="email"
           class="tf-input"
           :value="getField(path) ?? ''"
           @input="setField(path, $event.target.value)">
  </template>
  <template x-if="def.type === 'url'">
    <input type="url"
           class="tf-input"
           :value="getField(path) ?? ''"
           @input="setField(path, $event.target.value)">
  </template>
  <template x-if="def.type === 'tel'">
    <input type="tel"
           class="tf-input"
           :value="getField(path) ?? ''"
           @input="setField(path, $event.target.value)">
  </template>

  <!-- number -->
  <template x-if="def.type === 'number'">
    <input type="number"
           class="tf-input"
           :value="getField(path) ?? 0"
           @input="setField(path, $event.target.value === '' ? 0 : Number($event.target.value))">
  </template>

  <!-- color -->
  <template x-if="def.type === 'color'">
    <div class="tf-color">
      <input type="color"
             :value="getField(path) || '#000000'"
             @input="setField(path, $event.target.value)">
      <input type="text"
             class="tf-input"
             :value="getField(path) ?? ''"
             @input="setColorHex(path, $event.target.value)"
             placeholder="#rrggbb"
             style="max-width: 8rem">
    </div>
  </template>

  <!-- image (Phase 2 wires a picker later; for now show URL field) -->
  <template x-if="def.type === 'image'">
    <input type="text"
           class="tf-input"
           :value="getField(path) ?? ''"
           @input="setField(path, $event.target.value)"
           placeholder="/uploads/...">
  </template>

  <!-- select -->
  <template x-if="def.type === 'select'">
    <select class="tf-input"
            :value="getField(path) ?? ''"
            @change="setField(path, $event.target.value)">
      <template x-for="opt in (def.options || [])" :key="opt.value !== undefined ? opt.value : opt">
        <option :value="opt.value !== undefined ? opt.value : opt"
                x-text="opt.label !== undefined ? opt.label : (opt.value !== undefined ? opt.value : opt)"></option>
      </template>
    </select>
  </template>

  <!-- string_ref: text input with datalist of known strings.json keys.
       The datalist itself is mounted once at the page root (see content.php). -->
  <template x-if="def.type === 'string_ref'">
    <input type="text"
           class="tf-input"
           list="msd-string-keys-list"
           :value="getField(path) ?? ''"
           @input="setField(path, $event.target.value)">
  </template>

  <!-- reference: dropdown sourced from refOptions[def.collection] -->
  <template x-if="def.type === 'reference'">
    <select class="tf-input"
            :value="getField(path) ?? ''"
            @change="setField(path, $event.target.value)">
      <option value="">— none —</option>
      <template x-for="opt in refOptions(def.collection)" :key="opt.value">
        <option :value="opt.value" x-text="opt.label || opt.value"></option>
      </template>
    </select>
  </template>

  <!-- object: render nested fields under an indented column -->
  <template x-if="def.type === 'object'">
    <div class="tf-object">
      <template x-for="sub in fieldEntries(def.fields || {})" :key="sub[0]">
        <div x-data="{ name: sub[0], def: sub[1], path: path + '.' + sub[0] }">
          <div class="tf-field">
            <template x-if="def.type === 'boolean'">
              <label class="tf-toggle">
                <input type="checkbox"
                       :checked="getField(path) === true"
                       @change="setField(path, $event.target.checked)">
                <span class="tf-label" x-text="def.label || name"></span>
              </label>
            </template>
            <template x-if="def.type !== 'boolean'">
              <label class="tf-label" x-text="def.label || name"></label>
            </template>
            <template x-if="def.type === 'string' || def.type === undefined">
              <input type="text" class="tf-input"
                     :value="getField(path) ?? ''"
                     @input="setField(path, $event.target.value)">
            </template>
            <template x-if="def.type === 'url'">
              <input type="url" class="tf-input"
                     :value="getField(path) ?? ''"
                     @input="setField(path, $event.target.value)">
            </template>
            <template x-if="def.type === 'email'">
              <input type="email" class="tf-input"
                     :value="getField(path) ?? ''"
                     @input="setField(path, $event.target.value)">
            </template>
            <template x-if="def.type === 'text'">
              <textarea class="tf-input tf-textarea"
                        :value="getField(path) ?? ''"
                        @input="setField(path, $event.target.value)"></textarea>
            </template>
            <template x-if="def.type === 'number'">
              <input type="number" class="tf-input"
                     :value="getField(path) ?? 0"
                     @input="setField(path, $event.target.value === '' ? 0 : Number($event.target.value))">
            </template>
            <template x-if="def.type === 'color'">
              <div class="tf-color">
                <input type="color"
                       :value="getField(path) || '#000000'"
                       @input="setField(path, $event.target.value)">
                <input type="text" class="tf-input"
                       :value="getField(path) ?? ''"
                       @input="setColorHex(path, $event.target.value)"
                       placeholder="#rrggbb" style="max-width: 8rem">
              </div>
            </template>
            <template x-if="def.type === 'image'">
              <input type="text" class="tf-input"
                     :value="getField(path) ?? ''"
                     @input="setField(path, $event.target.value)"
                     placeholder="/uploads/...">
            </template>
            <template x-if="def.type === 'string_ref'">
              <input type="text" class="tf-input" list="msd-string-keys-list"
                     :value="getField(path) ?? ''"
                     @input="setField(path, $event.target.value)">
            </template>
          </div>
        </div>
      </template>
    </div>
  </template>

  <template x-if="def.help">
    <p class="tf-help" x-text="def.help"></p>
  </template>

</div>
