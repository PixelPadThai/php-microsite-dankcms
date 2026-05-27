/* MicroSite Dankcms — Typed-form widget factory (Alpine.data) */
/* global Alpine */

/*
 * typedForm()
 * ---------------------------------------------------------------------------
 * Reusable Alpine component that backs the dashboard's schema-driven editors.
 *
 * Responsibilities (factory layer):
 *   - Hold form state: `schema`, `value`, `original`, `depth`.
 *   - Provide pure helpers for dot-path get/set/clone.
 *   - Provide per-widget helpers (toggle, color sync, array add/remove,
 *     auto-grow textarea, image picker bridge).
 *   - Surface lookups for `string_ref` (window.MSD.stringKeys) and
 *     `reference` (window.MSD.refOptions[collection]).
 *   - Detect dirty state via JSON snapshot comparison.
 *
 * Out-of-scope (the consuming view owns these):
 *   - The actual HTML scaffold (a single <template x-for="[name, def] in
 *     fieldEntries(schema)"> that switches on def.type).
 *   - Persistence (POSTing the value back to the server).
 *   - Toasts / error UI / route navigation.
 *
 * Why split this way?
 *   - Phase 2 ships ONE shared factory but views differ (settings vs.
 *     collections vs. single records). Keeping markup in the parent view
 *     lets each editor compose the toolbar, header, save button, etc.
 *     while delegating field rendering to a common template fragment.
 *   - Alpine sees plain JS objects with reactive properties; we expose
 *     `getField` / `setField` so bindings can use computed paths
 *     (`x-model="getField(path)"` won't work directly — views use
 *     `:value` + `@input` pairs through these helpers, OR use
 *     `x-model="value.<literal_path>"` for top-level fields).
 */

(function () {
  'use strict';

  // --- pure helpers (module-scope; not on the component) ---------------------

  function deepClone(o) {
    if (o === null || o === undefined) return o;
    return JSON.parse(JSON.stringify(o));
  }

  function isPlainObject(o) {
    return o !== null && typeof o === 'object' && !Array.isArray(o);
  }

  /**
   * Walk a dot-path. Supports array indexes via "key.0.subkey".
   * Returns undefined if any segment is missing.
   */
  function pathGet(root, path) {
    if (path === '' || path === undefined || path === null) return root;
    const parts = String(path).split('.');
    let cur = root;
    for (const p of parts) {
      if (cur === null || cur === undefined) return undefined;
      cur = cur[p];
    }
    return cur;
  }

  /**
   * Set a value at a dot-path, creating intermediate objects/arrays as
   * needed. A numeric segment that doesn't already exist in the parent as
   * a key creates an array index.
   */
  function pathSet(root, path, val) {
    const parts = String(path).split('.');
    let cur = root;
    for (let i = 0; i < parts.length - 1; i++) {
      const p = parts[i];
      const next = parts[i + 1];
      const wantArray = /^\d+$/.test(next);
      if (cur[p] === undefined || cur[p] === null) {
        cur[p] = wantArray ? [] : {};
      }
      cur = cur[p];
    }
    cur[parts[parts.length - 1]] = val;
    return root;
  }

  /**
   * Build a default value for a schema field def. Used when adding a
   * fresh array item or initialising a missing object branch.
   */
  function defaultFor(def) {
    if (!def || typeof def !== 'object') return '';
    if (def.default !== undefined) return deepClone(def.default);
    switch (def.type) {
      case 'number':    return 0;
      case 'boolean':   return false;
      case 'object': {
        const out = {};
        const fields = def.fields || {};
        for (const k of Object.keys(fields)) out[k] = defaultFor(fields[k]);
        return out;
      }
      case 'array':     return [];
      case 'color':     return '#000000';
      case 'string':
      case 'text':
      case 'email':
      case 'url':
      case 'tel':
      case 'image':
      case 'select':
      case 'string_ref':
      case 'reference':
      default:          return '';
    }
  }

  /**
   * Validate a hex colour (#rgb / #rrggbb, case-insensitive). Used by the
   * color widget's text-input mirror.
   */
  function isHex(s) {
    return typeof s === 'string' && /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(s);
  }

  /** Read a JSON <script> block by id; returns fallback if missing/invalid. */
  function readJsonScript(id, fallback) {
    const el = document.getElementById(id);
    if (!el) return fallback;
    try { return JSON.parse(el.textContent || 'null') ?? fallback; }
    catch (_) { return fallback; }
  }

  // --- factory ---------------------------------------------------------------

  /**
   * Alpine component. Bind via:
   *   x-data="typedForm()"
   *   x-init="hydrate({ schemaScriptId: 'tf-schema-settings',
   *                     valueScriptId:  'tf-value-settings' })"
   *
   * Or pass the objects directly:
   *   x-init="hydrate({ schema: <?= json_encode($schema) ?>,
   *                     value:  <?= json_encode($value) ?> })"
   */
  function typedForm() {
    return {
      // ---- state ----------------------------------------------------------
      schema: {},
      value: {},
      original: {},
      maxDepth: 3,

      /**
       * Inputs:
       *   opts.schema           — direct schema object (preferred for tests)
       *   opts.value            — direct value object
       *   opts.schemaScriptId   — id of a <script type="application/json"> block
       *   opts.valueScriptId    — id of a <script type="application/json"> block
       *   opts.maxDepth         — override recursion cap (default 3)
       */
      hydrate(opts) {
        opts = opts || {};
        const schema = opts.schema !== undefined
          ? opts.schema
          : readJsonScript(opts.schemaScriptId, {});
        const value = opts.value !== undefined
          ? opts.value
          : readJsonScript(opts.valueScriptId, {});
        this.schema   = deepClone(schema || {});
        this.value    = deepClone(value  || {});
        this.original = deepClone(value  || {});
        if (typeof opts.maxDepth === 'number') this.maxDepth = opts.maxDepth;
        // Materialise any missing top-level keys so x-model bindings have
        // something concrete to write into (Alpine cannot create keys on
        // bare access).
        this._materialise(this.schema, this.value);
        this._materialise(this.schema, this.original);
      },

      /** Walk schema and ensure every described field exists on value. */
      _materialise(schema, target, depth) {
        depth = depth || 0;
        if (depth >= this.maxDepth) return;
        for (const [name, def] of this.fieldEntries(schema)) {
          if (target[name] === undefined) target[name] = defaultFor(def);
          if (def.type === 'object' && isPlainObject(target[name])) {
            this._materialise(def.fields || {}, target[name], depth + 1);
          }
        }
      },

      // ---- dirty tracking -------------------------------------------------

      isDirty() {
        return JSON.stringify(this.value) !== JSON.stringify(this.original);
      },

      /** Reset value back to the last hydrated original. */
      reset() {
        this.value = deepClone(this.original);
      },

      /** Mark current value as the new clean baseline (call after save). */
      markClean() {
        this.original = deepClone(this.value);
      },

      // ---- schema iteration -----------------------------------------------

      /**
       * Returns [name, def] pairs in declaration order, skipping the
       * `primary` marker entries (they're metadata, not editable).
       * Hidden fields (def.hidden === true) are filtered out too.
       */
      fieldEntries(schema) {
        const s = schema || this.schema || {};
        const out = [];
        for (const name of Object.keys(s)) {
          const def = s[name];
          if (!def || typeof def !== 'object') continue;
          if (def.hidden === true) continue;
          out.push([name, def]);
        }
        return out;
      },

      // ---- dot-path helpers (the public surface for views) ---------------

      getField(path) {
        return pathGet(this.value, path);
      },

      setField(path, val) {
        pathSet(this.value, path, val);
      },

      // ---- per-widget helpers --------------------------------------------

      /** Toggle a boolean at `path` (used by the switch widget). */
      toggleBool(path) {
        this.setField(path, !this.getField(path));
      },

      /**
       * Color widget: validate hex string from the text mirror and write
       * to both the color picker and the hex input (they share `path`).
       * Returns the canonical value that was written (or the existing one
       * if the new text wasn't a valid hex).
       */
      setColorHex(path, raw) {
        const trimmed = (raw || '').trim();
        if (isHex(trimmed)) {
          // Normalise short #rgb to #rrggbb so <input type=color> accepts it.
          let v = trimmed;
          if (v.length === 4) {
            v = '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
          }
          this.setField(path, v.toLowerCase());
          return v.toLowerCase();
        }
        return this.getField(path);
      },

      /**
       * Auto-grow a textarea: reset height then size to scrollHeight.
       * Wire with @input="autoGrow($event.target)".
       */
      autoGrow(el) {
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = (el.scrollHeight) + 'px';
      },

      // ---- array repeater -------------------------------------------------

      addArrayItem(path, itemDef) {
        const arr = this.getField(path);
        if (!Array.isArray(arr)) {
          this.setField(path, [defaultFor(itemDef)]);
          return;
        }
        arr.push(defaultFor(itemDef));
      },

      removeArrayItem(path, index) {
        const arr = this.getField(path);
        if (!Array.isArray(arr)) return;
        arr.splice(index, 1);
      },

      // ---- string_ref -----------------------------------------------------

      /** All known strings.json keys, supplied by the parent page. */
      stringKeys() {
        const k = window.MSD && window.MSD.stringKeys;
        return Array.isArray(k) ? k : [];
      },

      // ---- reference ------------------------------------------------------

      /**
       * Options for a reference field. The parent page populates
       * window.MSD.refOptions like:
       *   { pages: [{ value: 'home', label: 'Home' }, ...] }
       */
      refOptions(collection) {
        const map = window.MSD && window.MSD.refOptions;
        if (!map || !Array.isArray(map[collection])) return [];
        return map[collection];
      },

      // ---- image picker bridge -------------------------------------------

      /**
       * Emits a `request-image-picker` CustomEvent the dashboard shell
       * (Task 9) listens for. The handler will call back via
       *   el.dispatchEvent(new CustomEvent('image-picked', { detail: { url } }))
       * which the view should @image-picked="setField(path, $event.detail.url)".
       */
      pickImage(path, el) {
        const target = el || (typeof document !== 'undefined' ? document : null);
        if (!target || typeof target.dispatchEvent !== 'function') return;
        target.dispatchEvent(new CustomEvent('request-image-picker', {
          bubbles: true,
          detail: { path, current: this.getField(path) },
        }));
      },

      // ---- depth gate (for the recursive object widget) ------------------

      /**
       * View calls this inside the object/array branch to decide whether
       * to render the inner <template x-for> or print a placeholder.
       */
      canRecurse(currentDepth) {
        return (currentDepth || 0) < this.maxDepth;
      },

      // ---- introspection (exposed for unit tests / debugging) ------------

      _debug() {
        return {
          schema: this.schema,
          value: this.value,
          original: this.original,
          dirty: this.isDirty(),
        };
      },
    };
  }

  // --- registration ----------------------------------------------------------

  if (typeof document !== 'undefined' && document.addEventListener) {
    document.addEventListener('alpine:init', function () {
      if (typeof Alpine !== 'undefined' && Alpine && Alpine.data) {
        Alpine.data('typedForm', typedForm);
      }
    });
  }

  // Expose for tooling / tests in environments without Alpine.
  if (typeof window !== 'undefined') {
    window.MSD = window.MSD || {};
    window.MSD.typedForm = typedForm;
  }
})();
