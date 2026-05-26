<body class="app-shell"
      x-data="{
        dark: document.documentElement.classList.contains('dark'),
        toggleDark() {
          this.dark = !this.dark;
          document.documentElement.classList.toggle('dark', this.dark);
          localStorage.setItem('msd_theme', this.dark ? 'dark' : 'light');
        }
      }">

<header>
  <div class="container u-flex u-items-center u-justify-between" style="padding-block:var(--space-3)">
    <a href="/" style="font-weight:700;font-size:1.125rem"><?= htmlspecialchars($cms->setting('site_name') ?? 'My Site') ?></a>
    <nav class="u-flex u-gap-3 u-items-center">
      <a href="/"><?= htmlspecialchars($cms->str('nav_home')) ?></a>
      <button @click="toggleDark()" class="btn btn-icon btn-ghost" :title="dark ? 'Light mode' : 'Dark mode'">
        <span x-text="dark ? '☀' : '☾'"></span>
      </button>
    </nav>
  </div>
</header>

<main class="app-main">
  <div class="container">
