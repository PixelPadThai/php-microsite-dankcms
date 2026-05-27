/**
 * Alpine components for the Settings page.
 *
 * Three components:
 *   themePicker()    — color input + live OKLCH preview via /dashboard/api/preview-theme
 *   languageManager() — add/remove/toggle/relabel languages via /dashboard/api/manage-languages
 *   maintenanceToggle() — bound to settings._maintenance, posts via /dashboard/api/save-content
 *
 * Site basics (typed form on _schemas.settings) is rendered by the existing
 * contentEditor() component pre-scoped to 'settings' — see dashboard/views/settings.php.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('themePicker', () => ({
    currentHex: '',
    pickedHex: '',
    previewing: false,
    saving: false,
    toast: null,

    init() {
      const data = readData('msd-content-data');
      this.currentHex = ((data.settings && data.settings.brand_primary) || '#0CC4B4').toUpperCase();
      this.pickedHex  = this.currentHex;
      this.applyPreview(this.pickedHex);
    },

    isDirty() { return (this.pickedHex || '').toUpperCase() !== (this.currentHex || '').toUpperCase(); },

    async onPickerInput(hex) {
      this.pickedHex = (hex || '').toUpperCase();
      await this.applyPreview(this.pickedHex);
    },

    async applyPreview(hex) {
      this.previewing = true;
      try {
        const res = await fetch('/dashboard/api/preview-theme?hex=' + encodeURIComponent(hex));
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const css = await res.text();
        let style = document.getElementById('msd-theme-preview-style');
        if (!style) {
          style = document.createElement('style');
          style.id = 'msd-theme-preview-style';
          document.head.appendChild(style);
        }
        style.textContent = css;
      } catch (e) {
        this.toastMsg('Preview failed: ' + e.message, 'error');
      } finally {
        this.previewing = false;
      }
    },

    async apply() {
      if (!this.isDirty() || this.saving) return;
      this.saving = true;
      try {
        const data = deepClone(readData('msd-content-data'));
        data.settings = data.settings || {};
        data.settings.brand_primary = this.pickedHex;
        const res = await fetch('/dashboard/api/save-content', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
          body: JSON.stringify(data),
        });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) throw new Error(j.error || ('HTTP ' + res.status));
        this.currentHex = this.pickedHex;
        writeData('msd-content-data', data);
        this.toastMsg('Theme saved', 'success');
      } catch (e) {
        this.toastMsg('Save failed: ' + e.message, 'error');
      } finally {
        this.saving = false;
      }
    },

    reset() {
      this.pickedHex = this.currentHex;
      this.applyPreview(this.pickedHex);
    },

    toastMsg(text, kind) { this.toast = { text, kind }; setTimeout(() => { this.toast = null; }, 2500); },
  }));

  Alpine.data('languageManager', () => ({
    langs: [],
    original: [],
    newCode: '',
    newLabel: '',
    saving: false,
    toast: null,

    init() {
      const data = readData('msd-content-data');
      const list = (data._meta && data._meta.languages) || [];
      this.langs    = deepClone(list);
      this.original = deepClone(list);
    },

    isDirty() { return JSON.stringify(this.langs) !== JSON.stringify(this.original); },

    addLanguage() {
      const code  = (this.newCode || '').trim().toLowerCase();
      const label = (this.newLabel || '').trim() || code;
      if (!/^[a-z]{2}(-[a-z]{2})?$/.test(code)) { this.toastMsg('Code must be 2 letters (e.g. "en", "th")', 'error'); return; }
      if (this.langs.some(l => l.code === code)) { this.toastMsg('Code already in list', 'error'); return; }
      this.langs.push({ code, label, enabled: true, default: false });
      this.newCode = ''; this.newLabel = '';
    },

    removeLanguage(idx) {
      const l = this.langs[idx];
      if (!l) return;
      if (l.default) { this.toastMsg("Can't remove the default language", 'error'); return; }
      if (!confirm('Remove "' + l.label + '" and drop all of its translations?')) return;
      this.langs.splice(idx, 1);
    },

    setDefault(idx) {
      this.langs.forEach((l, i) => { l.default = (i === idx); if (l.default) l.enabled = true; });
    },

    async save() {
      if (!this.isDirty() || this.saving) return;
      this.saving = true;
      try {
        const res = await fetch('/dashboard/api/manage-languages', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
          body: JSON.stringify({ languages: this.langs }),
        });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) throw new Error(j.error || ('HTTP ' + res.status));
        this.original = deepClone(this.langs);
        const data = deepClone(readData('msd-content-data'));
        data._meta = data._meta || {};
        data._meta.languages = deepClone(this.langs);
        writeData('msd-content-data', data);
        this.toastMsg('Languages saved', 'success');
      } catch (e) {
        this.toastMsg('Save failed: ' + e.message, 'error');
      } finally {
        this.saving = false;
      }
    },

    toastMsg(text, kind) { this.toast = { text, kind }; setTimeout(() => { this.toast = null; }, 2500); },
  }));

  Alpine.data('maintenanceToggle', () => ({
    enabled: false,
    original: false,
    saving: false,
    toast: null,

    init() {
      const data = readData('msd-content-data');
      this.enabled  = !!(data.settings && data.settings._maintenance);
      this.original = this.enabled;
    },

    isDirty() { return this.enabled !== this.original; },

    async save() {
      if (!this.isDirty() || this.saving) return;
      this.saving = true;
      try {
        const data = deepClone(readData('msd-content-data'));
        data.settings = data.settings || {};
        data.settings._maintenance = this.enabled;
        const res = await fetch('/dashboard/api/save-content', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
          body: JSON.stringify(data),
        });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) throw new Error(j.error || ('HTTP ' + res.status));
        this.original = this.enabled;
        writeData('msd-content-data', data);
        this.toastMsg(this.enabled ? 'Maintenance ON — public site shows the holding page' : 'Maintenance OFF', 'success');
      } catch (e) {
        this.toastMsg('Save failed: ' + e.message, 'error');
      } finally {
        this.saving = false;
      }
    },

    toastMsg(text, kind) { this.toast = { text, kind }; setTimeout(() => { this.toast = null; }, 2500); },
  }));
});

function readData(id) {
  const el = document.getElementById(id);
  if (!el) return {};
  try { return JSON.parse(el.textContent || '{}'); } catch (e) { return {}; }
}

function writeData(id, obj) {
  const el = document.getElementById(id);
  if (el) el.textContent = JSON.stringify(obj);
}

function csrf() { return (window.MSD && window.MSD.csrf) || ''; }

function deepClone(v) { return JSON.parse(JSON.stringify(v)); }
