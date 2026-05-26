(function () {
  try {
    if (localStorage.getItem('msd_theme') === 'dark') {
      document.documentElement.classList.add('dark');
    }
  } catch (e) {}
})();
