/**
 * mediaLibrary() — Alpine component for the dashboard Media view.
 *
 * Initial list is rendered server-side into the #msd-media-data data block.
 * Uploads POST multipart/form-data to /dashboard/api/upload-media.
 * Deletes POST JSON to /dashboard/api/delete-media; the response includes
 * a `refs` list when the file is in use, which triggers the force-delete UI.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('mediaLibrary', () => ({
    items: [],
    selected: null,
    refsList: [],
    uploading: 0,
    dragging: false,
    toast: null,

    init() {
      this.items = readJson('msd-media-data') || [];
    },

    thumbUrl(item) {
      if (item.has_thumb) {
        const base = stripExt(item.name);
        return '/data/uploads/' + base + '-thumb.webp';
      }
      return item.url;
    },

    formatSize(bytes) {
      if (!bytes) return '0 B';
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    },

    async refresh() {
      try {
        const res = await fetch(location.pathname, { headers: { 'Accept': 'text/html' } });
        const html = await res.text();
        const doc  = new DOMParser().parseFromString(html, 'text/html');
        const el   = doc.getElementById('msd-media-data');
        if (el) this.items = JSON.parse(el.textContent || '[]');
      } catch (e) {
        // Best effort; show toast.
        this.toastMsg('Refresh failed: ' + e.message, 'error');
      }
    },

    openTile(item) {
      this.selected = item;
      this.refsList = []; // Lazy-load via delete attempt; or refresh via list scan.
      // The server-rendered list already has used_by_count. We don't store
      // ref paths in the list payload; the first delete attempt returns
      // them. For UX, surface refsList only after a blocked delete.
    },

    closeTile() {
      this.selected = null;
      this.refsList = [];
    },

    async copyUrl() {
      if (!this.selected) return;
      try {
        await navigator.clipboard.writeText(window.location.origin + this.selected.url);
        this.toastMsg('URL copied', 'success');
      } catch (e) {
        this.toastMsg('Copy failed', 'error');
      }
    },

    async onDrop(ev) {
      this.dragging = false;
      const files = ev.dataTransfer ? Array.from(ev.dataTransfer.files) : [];
      await this.uploadAll(files);
    },

    async onFileInput(ev) {
      const files = Array.from(ev.target.files || []);
      await this.uploadAll(files);
      ev.target.value = '';
    },

    async uploadAll(files) {
      for (const f of files) {
        if (!f.type.startsWith('image/')) {
          this.toastMsg('Skipped non-image: ' + f.name, 'error');
          continue;
        }
        await this.uploadOne(f);
      }
      if (files.length > 0) await this.refresh();
    },

    async uploadOne(file) {
      this.uploading++;
      try {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('_csrf', csrf());
        const res = await fetch('/dashboard/api/upload-media', {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf() },
          body: fd,
        });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) {
          throw new Error(j.error || ('HTTP ' + res.status));
        }
        this.toastMsg('Uploaded ' + j.name, 'success');
      } catch (e) {
        this.toastMsg('Upload failed: ' + e.message, 'error');
      } finally {
        this.uploading--;
      }
    },

    async deleteSelected(force) {
      if (!this.selected) return;
      const name = this.selected.name;
      try {
        const res = await fetch('/dashboard/api/delete-media', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
          body: JSON.stringify({ name, force: !!force }),
        });
        const j = await res.json().catch(() => ({}));
        if (!res.ok) {
          if (Array.isArray(j.refs) && j.refs.length > 0) {
            this.refsList = j.refs;
            this.toastMsg('Blocked: ' + j.refs.length + ' reference(s) — confirm to force-delete', 'error');
            return;
          }
          throw new Error(j.error || ('HTTP ' + res.status));
        }
        this.toastMsg('Deleted ' + name, 'success');
        this.closeTile();
        await this.refresh();
      } catch (e) {
        this.toastMsg('Delete failed: ' + e.message, 'error');
      }
    },

    toastMsg(text, kind) { this.toast = { text, kind }; setTimeout(() => { this.toast = null; }, 2500); },
  }));
});

function readJson(id) {
  const el = document.getElementById(id);
  if (!el) return null;
  try { return JSON.parse(el.textContent || 'null'); } catch (e) { return null; }
}

function stripExt(name) {
  const i = name.lastIndexOf('.');
  return i > 0 ? name.slice(0, i) : name;
}

function csrf() { return (window.MSD && window.MSD.csrf) || ''; }
