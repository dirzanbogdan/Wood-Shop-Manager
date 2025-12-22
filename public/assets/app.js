(() => {
  const key = "gsh3ll_theme";
  const saved = localStorage.getItem(key);
  if (saved === "dark" || saved === "light") {
    document.documentElement.dataset.theme = saved;
  }

  const toggle = document.querySelector("[data-theme-toggle]");
  if (!toggle) return;

  toggle.addEventListener("click", () => {
    const current = document.documentElement.dataset.theme === "dark" ? "dark" : "light";
    const next = current === "dark" ? "light" : "dark";
    document.documentElement.dataset.theme = next;
    localStorage.setItem(key, next);
  });
})();

