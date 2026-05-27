window.MSD = window.MSD || {};
(function () {
  var m = document.querySelector('meta[name="csrf-token"]');
  if (m) window.MSD.csrf = m.getAttribute('content');
})();
