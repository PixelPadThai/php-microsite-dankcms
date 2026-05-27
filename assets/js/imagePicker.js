/**
 * imagePicker() — Alpine component for the global image-picker modal.
 *
 * The modal markup lives in dashboard/views/_partials/image-picker.php.
 * Callers open the picker via MSD.imagePicker.open(); it resolves with
 *   - a string URL when the user picks one,
 *   - the empty string when the user clicks "Clear field",
 *   - null when the user cancels.
 */
window.MSD = window.MSD || {};
window.MSD.imagePicker = {
  resolver: null,
  open() {
    document.dispatchEvent(new CustomEvent('msd:image-picker-open'));
    return new Promise(r => { this.resolver = r; });
  },
  _resolve(value) {
    const r = this.resolver;
    this.resolver = null;
    document.dispatchEvent(new CustomEvent('msd:image-picker-close'));
    if (r) r(value);
  },
};

document.addEventListener('alpine:init', () => {
  Alpine.data('imagePicker', () => ({
    open: false,
    items: [],
    search: '',
    loading: false,
    uploading: false,

    init() {
      document.addEventListener('msd:image-picker-open', () => {
        this.open = true;
        this.search = '';
        this.refresh();
      });
    },

    async refresh() {
      this.loading = true;
      try {
        const res = await fetch('/dashboard/api/list-media');
        const j   = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) throw new Error(j.error || ('HTTP ' + res.status));
        this.items = Array.isArray(j.items) ? j.items : [];
      } catch (e) {
        this.items = [];
      } finally {
        this.loading = false;
      }
    },

    filteredItems() {
      const q = (this.search || '').trim().toLowerCase();
      if (!q) return this.items;
      return this.items.filter(it => it.name.toLowerCase().includes(q));
    },

    thumbFor(item) {
      if (item.has_thumb) {
        const i = item.name.lastIndexOf('.');
        const base = i > 0 ? item.name.slice(0, i) : item.name;
        return '/data/uploads/' + base + '-thumb.webp';
      }
      return item.url;
    },

    async onUpload(ev) {
      const file = ev.target.files && ev.target.files[0];
      ev.target.value = '';
      if (!file) return;
      this.uploading = true;
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
        if (!res.ok || !j.ok) throw new Error(j.error || ('HTTP ' + res.status));
        await this.refresh();
      } catch (e) {
        alert('Upload failed: ' + e.message);
      } finally {
        this.uploading = false;
      }
    },

    pick(url) {
      this.open = false;
      window.MSD.imagePicker._resolve(url);
    },

    clearValue() {
      this.open = false;
      window.MSD.imagePicker._resolve('');
    },

    cancel() {
      this.open = false;
      window.MSD.imagePicker._resolve(null);
    },
  }));
});

function csrf() { return (window.MSD && window.MSD.csrf) || ''; }
