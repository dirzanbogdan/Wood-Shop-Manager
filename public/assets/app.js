(() => {
  const key = "gsh3ll_theme";
  const saved = localStorage.getItem(key);
  if (saved === "dark" || saved === "light") {
    document.documentElement.dataset.theme = saved;
  }

  const toggle = document.querySelector("[data-theme-toggle]");
  if (toggle) {
    toggle.addEventListener("click", () => {
      const current = document.documentElement.dataset.theme === "dark" ? "dark" : "light";
      const next = current === "dark" ? "light" : "dark";
      document.documentElement.dataset.theme = next;
      localStorage.setItem(key, next);
    });
  }
})();

(() => {
  const normalizeDecimal = (value) => {
    if (typeof value !== "string") return value;
    let s = value.trim();
    if (s === "") return value;
    s = s.replace(",", ".");
    if (s.startsWith("-.")) return "-0" + s.slice(1);
    if (s.startsWith("+.")) return "0" + s.slice(1);
    if (s.startsWith(".")) return "0" + s;
    return s;
  };

  const shouldNormalize = (el) => {
    if (!el || el.tagName !== "INPUT") return false;
    if (el.type === "number") return true;
    const inputMode = (el.getAttribute("inputmode") || "").toLowerCase();
    if (inputMode === "decimal" || inputMode === "numeric") return true;
    if (el.dataset && el.dataset.decimal === "1") return true;
    return false;
  };

  document.addEventListener(
    "blur",
    (e) => {
      const el = e.target;
      if (!shouldNormalize(el)) return;
      const next = normalizeDecimal(el.value);
      if (next !== el.value) el.value = next;
    },
    true
  );

  document.addEventListener(
    "submit",
    (e) => {
      const form = e.target;
      if (!form || form.tagName !== "FORM") return;
      const inputs = form.querySelectorAll("input");
      for (const el of inputs) {
        if (!shouldNormalize(el)) continue;
        const next = normalizeDecimal(el.value);
        if (next !== el.value) el.value = next;
      }
    },
    true
  );
})();
