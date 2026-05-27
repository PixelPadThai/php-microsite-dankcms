/* MicroSite Dankcms — Content tab editor (Alpine.data) */
/* global Alpine */

/*
 * contentEditor()
 * ---------------------------------------------------------------------------
 * Backs the Content tab on /dashboard/content. It owns:
 *
 *   - The full content.json snapshot (hydrated from #msd-content-data).
 *   - Left-rail navigation state: scope = 'settings' | 'collection:<name>'.
 *   - Selected record state when editing inside a collection.
 *   - A nested typed-form state (re-uses the helpers from typedForm.js by
 *     copying its data factory). When the user switches scope or record,
 *     we re-hydrate the form against the new schema + value pair.
 *
 *   - Save: builds the FULL updated content.json (applying the in-progress
 *     edit at the right path) and POSTs the whole payload to
 *     /dashboard/api/save-content. The server endpoint lands in Task 3.
 *   - Delete: same endpoint, payload with the record removed.
 *
 * Why one component (not two)?
 *   - Save needs the full content.json no matter which sub-view is open,
 *     so the content snapshot lives at the top.
 *   - Sub-scopes only differ in which slice of the snapshot binds to the
 *     typed-form. Folding them into one factory keeps re-hydration local.
 */

(function () {
  'use strict';

  function deepClone(o) {
    if (o === null || o === undefined) return o;
    return JSON.parse(JSON.stringify(o));
  }

  function readJsonScript(id, fallback) {
    const el = document.getElementById(id);
    if (!el) return fallback;
    try { return JSON.parse(el.textContent || 'null') ?? fallback; }
    catch (_) { return fallback; }
  }

  /** Find the schema field marked as primary (or null). */
  function primaryKey(schema) {
    if (!schema || typeof schema !== 'object') return null;
    for (const name of Object.keys(schema)) {
      const def = schema[name];
      if (def && typeof def === 'object' && def.primary === true) return name;
    }
    return null;
  }

  /** Lowercase-first-letter inverse: 'pages' -> 'Pages'. */
  function sentenceCase(s) {
    if (!s) return '';
    return s.charAt(0).toUpperCase() + s.slice(1);
  }

  /** Build an empty record by walking the schema's default-for rules. */
  function emptyRecord(schema) {
    const out = {};
    if (!schema || typeof schema !== 'object') return out;
    for (const name of Object.keys(schema)) {
      const def = schema[name];
      if (!def || typeof def !== 'object') continue;
      if (def.hidden === true) continue;
      if (def.default !== undefined) { out[name] = deepClone(def.default); continue; }
      switch (def.type) {
        case 'number':  out[name] = 0; break;
        case 'boolean': out[name] = false; break;
        case 'array':   out[name] = []; break;
        case 'object':  out[name] = emptyRecord(def.fields || {}); break;
        default:        out[name] = ''; break;
      }
    }
    return out;
  }

  function contentEditor() {
    return {
      // ---- top-level snapshot ----------------------------------------------
      content: {},          // full content.json (mutated in place on save-build)
      contentOriginal: {},  // snapshot for unsaved-changes warnings
      maxDepth: 3,

      // ---- navigation state -----------------------------------------------
      tab: 'strings',                 // outer tab (Strings | Content)
      scope: 'settings',              // 'settings' | 'collection:<name>'
      mode: 'list',                   // 'list' | 'edit'  (only meaningful in collection scope)
      collectionName: '',             // 'pages' (when scope is collection:*)
      recordIndex: -1,                // index into collections[name] when editing existing
      recordIsNew: false,             // true when + New record

      // ---- typed-form state (mirrors typedForm.js's shape) ---------------
      formSchema: {},
      formValue: {},
      formOriginal: {},

      // ---- ui ------------------------------------------------------------
      saving: false,
      toast: null,
      _toastId: 0,

      // ---- lifecycle -----------------------------------------------------

      init() {
        // Bridge JSON islands to window.MSD so typed-form helpers can find
        // them. Do this here (not in dashboard-init.js) because the data
        // blocks only exist on /dashboard/content.
        window.MSD = window.MSD || {};
        const keys = readJsonScript('msd-string-keys', []);
        const refs = readJsonScript('msd-ref-options', {});
        window.MSD.stringKeys = Array.isArray(keys) ? keys : [];
        window.MSD.refOptions = (refs && typeof refs === 'object') ? refs : {};

        const data = readJsonScript('msd-content-data', {});
        this.content = deepClone(data);
        this.contentOriginal = deepClone(data);
        this._loadSettingsScope();
      },

      // ---- navigation ----------------------------------------------------

      collections() {
        const c = this.content && this.content.collections;
        if (!c || typeof c !== 'object') return [];
        return Object.keys(c);
      },

      /** User clicked a left-rail entry. Guards against losing edits. */
      goto(target) {
        if (this.isDirty() && !confirm('Discard unsaved changes?')) return;
        if (target === 'settings') {
          this._loadSettingsScope();
        } else if (target.indexOf('collection:') === 0) {
          this._loadCollectionList(target.slice('collection:'.length));
        }
      },

      _loadSettingsScope() {
        this.scope = 'settings';
        this.mode = 'edit';
        this.collectionName = '';
        this.recordIndex = -1;
        this.recordIsNew = false;
        const schema = (this.content._schemas && this.content._schemas.settings) || {};
        const value  = this.content.settings || {};
        this._hydrateForm(schema, value);
      },

      _loadCollectionList(name) {
        this.scope = 'collection:' + name;
        this.mode = 'list';
        this.collectionName = name;
        this.recordIndex = -1;
        this.recordIsNew = false;
        // Form is unused in list mode, but reset so isDirty()=false.
        this.formSchema = {};
        this.formValue = {};
        this.formOriginal = {};
      },

      /** Click a row in the collection table → open record edit form. */
      editRecord(index) {
        if (this.isDirty() && !confirm('Discard unsaved changes?')) return;
        const name = this.collectionName;
        const rec = (this.content.collections[name] || [])[index];
        if (!rec) return;
        this.recordIndex = index;
        this.recordIsNew = false;
        this.mode = 'edit';
        const schema = (this.content._schemas && this.content._schemas[name]) || {};
        this._hydrateForm(schema, rec);
      },

      newRecord() {
        if (this.isDirty() && !confirm('Discard unsaved changes?')) return;
        const name = this.collectionName;
        const schema = (this.content._schemas && this.content._schemas[name]) || {};
        this.recordIndex = -1;
        this.recordIsNew = true;
        this.mode = 'edit';
        this._hydrateForm(schema, emptyRecord(schema));
        // Focus the primary-key field after Alpine paints.
        this.$nextTick(() => {
          const pk = primaryKey(schema);
          if (!pk) return;
          const el = document.querySelector('[data-tf-focus="' + pk + '"]');
          if (el && typeof el.focus === 'function') el.focus();
        });
      },

      backToList() {
        if (this.isDirty() && !confirm('Discard unsaved changes?')) return;
        this.mode = 'list';
        this.recordIndex = -1;
        this.recordIsNew = false;
        this.formSchema = {};
        this.formValue = {};
        this.formOriginal = {};
      },

      // ---- typed-form bridge ---------------------------------------------

      _hydrateForm(schema, value) {
        this.formSchema = deepClone(schema || {});
        this.formValue = deepClone(value || {});
        this.formOriginal = deepClone(value || {});
      },

      fieldEntries(schema) {
        const s = schema || this.formSchema || {};
        const out = [];
        for (const name of Object.keys(s)) {
          const def = s[name];
          if (!def || typeof def !== 'object') continue;
          if (def.hidden === true) continue;
          out.push([name, def]);
        }
        return out;
      },

      getField(path) {
        if (path === '' || path === undefined || path === null) return this.formValue;
        const parts = String(path).split('.');
        let cur = this.formValue;
        for (const p of parts) {
          if (cur === null || cur === undefined) return undefined;
          cur = cur[p];
        }
        return cur;
      },

      setField(path, val) {
        const parts = String(path).split('.');
        let cur = this.formValue;
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
      },

      toggleBool(path) { this.setField(path, !this.getField(path)); },

      setColorHex(path, raw) {
        const trimmed = (raw || '').trim();
        if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(trimmed)) {
          let v = trimmed;
          if (v.length === 4) v = '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
          this.setField(path, v.toLowerCase());
          return v.toLowerCase();
        }
        return this.getField(path);
      },

      stringKeys() {
        const k = window.MSD && window.MSD.stringKeys;
        return Array.isArray(k) ? k : [];
      },

      refOptions(collection) {
        const map = window.MSD && window.MSD.refOptions;
        if (!map || !Array.isArray(map[collection])) return [];
        return map[collection];
      },

      // ---- dirty tracking -------------------------------------------------

      isDirty() {
        return JSON.stringify(this.formValue) !== JSON.stringify(this.formOriginal);
      },

      // ---- table view helpers --------------------------------------------

      /** First few interesting columns for the table header. */
      tableColumns() {
        const name = this.collectionName;
        const schema = (this.content._schemas && this.content._schemas[name]) || {};
        const pk = primaryKey(schema);
        const out = [];
        if (pk) out.push(pk);
        for (const candidate of ['slug', 'title_key', 'published']) {
          if (schema[candidate] && candidate !== pk) {
            if (out.length < 4) out.push(candidate);
          }
        }
        return out;
      },

      records() {
        const name = this.collectionName;
        const list = this.content && this.content.collections && this.content.collections[name];
        return Array.isArray(list) ? list : [];
      },

      cellDisplay(rec, key) {
        const v = rec ? rec[key] : undefined;
        if (v === undefined || v === null) return '';
        if (typeof v === 'boolean') return v ? 'true' : 'false';
        return String(v);
      },

      currentRecordPk() {
        const name = this.collectionName;
        const schema = (this.content._schemas && this.content._schemas[name]) || {};
        const pk = primaryKey(schema);
        if (!pk) return '';
        return this.formValue[pk] || '';
      },

      headingForCollection() {
        return sentenceCase(this.collectionName);
      },

      // ---- save / delete --------------------------------------------------

      /**
       * Build the full content.json with the current edit applied.
       * Pure builder — does not mutate this.content until save succeeds.
       */
      buildPayload() {
        const out = deepClone(this.content);
        if (this.scope === 'settings') {
          out.settings = deepClone(this.formValue);
          return out;
        }
        // collection scope
        const name = this.collectionName;
        if (!out.collections || !Array.isArray(out.collections[name])) {
          out.collections = out.collections || {};
          out.collections[name] = [];
        }
        const schema = (out._schemas && out._schemas[name]) || {};
        const pk = primaryKey(schema);
        const rec = deepClone(this.formValue);
        if (this.recordIsNew) {
          out.collections[name].push(rec);
        } else {
          if (this.recordIndex >= 0 && this.recordIndex < out.collections[name].length) {
            out.collections[name][this.recordIndex] = rec;
          } else if (pk) {
            // Fallback: locate by pk.
            const idx = out.collections[name].findIndex(r => r && r[pk] === rec[pk]);
            if (idx >= 0) out.collections[name][idx] = rec;
            else out.collections[name].push(rec);
          } else {
            out.collections[name].push(rec);
          }
        }
        return out;
      },

      async save() {
        if (this.saving) return;
        this.saving = true;
        try {
          const payload = this.buildPayload();
          const res = await fetch('/dashboard/api/save-content', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': (window.MSD && window.MSD.csrf) || '',
            },
            body: JSON.stringify(payload),
          });
          const j = await res.json().catch(() => ({}));
          if (!res.ok || !j.ok) {
            throw new Error(j.error || ('HTTP ' + res.status));
          }
          // Success: snapshot the saved content as the new baseline.
          this.content = deepClone(payload);
          this.contentOriginal = deepClone(payload);
          this.formOriginal = deepClone(this.formValue);
          // If we just created a new record, rebind to its index so further
          // edits update in place rather than appending another row.
          if (this.scope.indexOf('collection:') === 0 && this.recordIsNew) {
            this.recordIsNew = false;
            this.recordIndex = (this.content.collections[this.collectionName] || []).length - 1;
          }
          this.toastMsg('Saved', 'success');
        } catch (e) {
          this.toastMsg('Save failed: ' + e.message, 'error');
        } finally {
          this.saving = false;
        }
      },

      async deleteRecord() {
        if (this.scope.indexOf('collection:') !== 0) return;
        if (this.recordIsNew || this.recordIndex < 0) return;
        const name = this.collectionName;
        const list = (this.content.collections && this.content.collections[name]) || [];
        const rec = list[this.recordIndex];
        if (!rec) return;
        const label = this.currentRecordPk() || ('record #' + this.recordIndex);
        if (!confirm('Delete ' + label + '? This cannot be undone.')) return;

        const payload = deepClone(this.content);
        if (payload.collections && Array.isArray(payload.collections[name])) {
          payload.collections[name].splice(this.recordIndex, 1);
        }

        this.saving = true;
        try {
          const res = await fetch('/dashboard/api/save-content', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': (window.MSD && window.MSD.csrf) || '',
            },
            body: JSON.stringify(payload),
          });
          const j = await res.json().catch(() => ({}));
          if (!res.ok || !j.ok) {
            throw new Error(j.error || ('HTTP ' + res.status));
          }
          this.content = deepClone(payload);
          this.contentOriginal = deepClone(payload);
          this._loadCollectionList(name);
          this.toastMsg('Deleted', 'success');
        } catch (e) {
          this.toastMsg('Delete failed: ' + e.message, 'error');
        } finally {
          this.saving = false;
        }
      },

      // ---- toasts ---------------------------------------------------------

      toastMsg(text, kind) {
        const id = ++this._toastId;
        this.toast = { text, kind: kind || 'info', id };
        setTimeout(() => {
          if (this.toast && this.toast.id === id) this.toast = null;
        }, 3200);
      },
    };
  }

  // --- registration ----------------------------------------------------------

  if (typeof document !== 'undefined' && document.addEventListener) {
    document.addEventListener('alpine:init', function () {
      if (typeof Alpine !== 'undefined' && Alpine && Alpine.data) {
        Alpine.data('contentEditor', contentEditor);
      }
    });
  }

  if (typeof window !== 'undefined') {
    window.MSD = window.MSD || {};
    window.MSD.contentEditor = contentEditor;
  }
})();
