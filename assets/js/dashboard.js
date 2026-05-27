/* MicroSite Dankcms — Dashboard editor components (Alpine.data) */
/* global Alpine */

function deepClone(o) {
  return JSON.parse(JSON.stringify(o));
}

function sectionOf(key) {
  const i = key.indexOf('_');
  return i === -1 ? key : key.slice(0, i);
}

function formatTime(d) {
  if (!d) return '';
  const dt = d instanceof Date ? d : new Date(d);
  return dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function readJsonScript(id, fallback) {
  const el = document.getElementById(id);
  if (!el) return fallback;
  try { return JSON.parse(el.textContent || 'null') ?? fallback; }
  catch (_) { return fallback; }
}

function stringsEditor() {
  return {
    tab: 'strings',
    data: {},
    original: {},
    langs: [],
    activeLang: 'en',
    search: '',
    saving: false,
    lastSaved: null,
    toast: null,
    _toastId: 0,

    init() {
      const initial = readJsonScript('msd-strings-data', {});
      const langs   = readJsonScript('msd-strings-langs', []);
      this.langs    = Array.isArray(langs) ? langs : [];
      if (this.langs.length > 0) this.activeLang = this.langs[0].code;
      this.data     = deepClone(initial);
      this.original = deepClone(initial);
    },

    keys() {
      return Object.keys(this.data);
    },

    sectionList() {
      const map = {};
      for (const k of this.keys()) {
        const s = sectionOf(k);
        if (!map[s]) map[s] = [];
        map[s].push(k);
      }
      return Object.entries(map);
    },

    getVal(key, lang) {
      const entry = this.data[key] || {};
      return entry[lang] ?? '';
    },

    setVal(key, lang, val) {
      if (!this.data[key]) this.data[key] = {};
      this.data[key][lang] = val;
    },

    isDirty(key, lang) {
      const cur = (this.data[key] || {})[lang] ?? '';
      const orig = (this.original[key] || {})[lang] ?? '';
      return cur !== orig;
    },

    rowDirty(key) {
      for (const lang of this.langs) {
        if (this.isDirty(key, lang.code)) return true;
      }
      return false;
    },

    dirtyCount() {
      let n = 0;
      for (const k of this.keys()) {
        for (const lang of this.langs) {
          if (this.isDirty(k, lang.code)) n++;
        }
      }
      return n;
    },

    visibleKeys(keys) {
      if (!this.search) return keys;
      const q = this.search.toLowerCase();
      return keys.filter(k => {
        if (k.toLowerCase().includes(q)) return true;
        const entry = this.data[k] || {};
        for (const lang of this.langs) {
          const v = entry[lang.code] ?? '';
          if (typeof v === 'string' && v.toLowerCase().includes(q)) return true;
        }
        const oEntry = this.original[k] || {};
        for (const lang of this.langs) {
          const v = oEntry[lang.code] ?? '';
          if (typeof v === 'string' && v.toLowerCase().includes(q)) return true;
        }
        return false;
      });
    },

    totalVisible() {
      if (!this.search) return this.keys().length;
      let n = 0;
      for (const [, keys] of this.sectionList()) n += this.visibleKeys(keys).length;
      return n;
    },

    async save() {
      if (this.dirtyCount() === 0 || this.saving) return;
      this.saving = true;
      try {
        const res = await fetch('/dashboard/api/save-strings', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': (window.MSD && window.MSD.csrf) || '',
          },
          body: JSON.stringify(this.data),
        });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) {
          throw new Error(j.error || ('HTTP ' + res.status));
        }
        this.original = deepClone(this.data);
        this.lastSaved = j.savedAt || new Date().toISOString();
        this.toastMsg('Saved', 'success');
      } catch (e) {
        this.toastMsg('Save failed: ' + e.message, 'error');
      } finally {
        this.saving = false;
      }
    },

    toastMsg(text, kind) {
      const id = ++this._toastId;
      this.toast = { text, kind: kind || 'info', id };
      setTimeout(() => {
        if (this.toast && this.toast.id === id) this.toast = null;
      }, 3200);
    },

    formatTime,
  };
}

function backupsView() {
  return {
    toast: '',
    toastClass: '',
    _toastId: 0,
    async restore(name) {
      if (!confirm('Restore ' + name + '?\nCurrent file is backed up first.')) return;
      try {
        const res = await fetch('/dashboard/api/restore-backup', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': (window.MSD && window.MSD.csrf) || '',
          },
          body: JSON.stringify({ name }),
        });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) throw new Error(j.error || ('HTTP ' + res.status));
        this._toast('Restored. Reloading…', 'toast-success');
        setTimeout(() => location.reload(), 800);
      } catch (e) {
        this._toast('Error: ' + e.message, 'toast-error');
      }
    },
    _toast(msg, cls) {
      const id = ++this._toastId;
      this.toast = msg;
      this.toastClass = cls || '';
      setTimeout(() => { if (this._toastId === id) this.toast = ''; }, 3200);
    },
  };
}

document.addEventListener('alpine:init', () => {
  Alpine.data('stringsEditor', stringsEditor);
  Alpine.data('backupsView', backupsView);
});
