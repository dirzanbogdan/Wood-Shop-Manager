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

(() => {
  const form = document.querySelector("[data-taxes-form]");
  if (!form) return;

  const srlEls = form.querySelectorAll("[data-taxes-srl]");
  const otherEls = form.querySelectorAll("[data-taxes-other]");
  const entityEls = form.querySelectorAll('input[name="entity_type"]');

  const setEnabled = (nodes, enabled) => {
    for (const el of nodes) {
      if (el instanceof HTMLElement) {
        el.style.opacity = enabled ? "1" : "0.5";
        el.style.filter = enabled ? "" : "grayscale(1)";
      }
      const inputs = el.querySelectorAll("input,select,textarea,button");
      for (const inp of inputs) {
        if (inp instanceof HTMLInputElement || inp instanceof HTMLSelectElement || inp instanceof HTMLTextAreaElement || inp instanceof HTMLButtonElement) {
          inp.disabled = !enabled;
        }
      }
    }
  };

  const ensureTaxTypeChecked = () => {
    const enabledTax = form.querySelectorAll('input[name="tax_type"]:not(:disabled)');
    const hasChecked = Array.from(enabledTax).some((r) => r instanceof HTMLInputElement && r.checked);
    if (hasChecked) return;
    const first = enabledTax[0];
    if (first && first instanceof HTMLInputElement) first.checked = true;
  };

  const update = () => {
    const entity = form.querySelector('input[name="entity_type"]:checked');
    const val = entity && entity instanceof HTMLInputElement ? entity.value : "srl";
    const isSrl = val === "srl";
    setEnabled(srlEls, isSrl);
    setEnabled(otherEls, !isSrl);
    ensureTaxTypeChecked();
  };

  for (const el of entityEls) {
    el.addEventListener("change", update);
  }
  update();
})();

(() => {
  const root = document.querySelector(".page-reports");
  if (!root) return;

  const normalizeText = (s) => (s || "").toString().trim().toLowerCase();

  const parseNumber = (s) => {
    const raw = (s || "").toString().replace(/\s+/g, "").replace(",", ".");
    const cleaned = raw.replace(/[^0-9.\-]/g, "");
    const n = parseFloat(cleaned);
    return Number.isFinite(n) ? n : null;
  };

  const enhance = (table) => {
    const thead = table.querySelector("thead");
    const tbody = table.querySelector("tbody");
    if (!thead || !tbody) return;
    const headerRow = thead.querySelector("tr");
    if (!headerRow) return;
    const ths = Array.from(headerRow.querySelectorAll("th"));
    if (ths.length === 0) return;

    if (thead.querySelector("[data-filter-row]")) return;

    const filterRow = document.createElement("tr");
    filterRow.dataset.filterRow = "1";
    for (let i = 0; i < ths.length; i++) {
      const th = document.createElement("th");
      const input = document.createElement("input");
      input.type = "text";
      input.placeholder = "Filtru";
      input.style.padding = "6px 8px";
      input.style.borderRadius = "8px";
      input.style.fontSize = "13px";
      input.dataset.filterCol = String(i);
      th.appendChild(input);
      filterRow.appendChild(th);
      input.addEventListener("input", () => applyFilter(table));
    }
    thead.appendChild(filterRow);

    for (let i = 0; i < ths.length; i++) {
      const th = ths[i];
      th.style.cursor = "pointer";
      th.addEventListener("click", (e) => {
        const target = e.target;
        if (target && target instanceof HTMLInputElement) return;
        toggleSort(table, i);
      });
    }
  };

  const getRows = (table) => Array.from(table.querySelectorAll("tbody tr"));

  const toggleSort = (table, colIndex) => {
    const currentIndex = table.dataset.sortIndex ? parseInt(table.dataset.sortIndex, 10) : -1;
    const currentDir = table.dataset.sortDir === "desc" ? "desc" : "asc";
    const nextDir = currentIndex === colIndex ? (currentDir === "asc" ? "desc" : "asc") : "asc";
    table.dataset.sortIndex = String(colIndex);
    table.dataset.sortDir = nextDir;
    applySort(table);
  };

  const applySort = (table) => {
    const tbody = table.querySelector("tbody");
    if (!tbody) return;
    const idx = table.dataset.sortIndex ? parseInt(table.dataset.sortIndex, 10) : -1;
    if (idx < 0) return;
    const dir = table.dataset.sortDir === "desc" ? -1 : 1;
    const rows = getRows(table);

    rows.sort((a, b) => {
      const aCell = a.children[idx];
      const bCell = b.children[idx];
      const aText = aCell ? aCell.textContent : "";
      const bText = bCell ? bCell.textContent : "";
      const aNum = parseNumber(aText);
      const bNum = parseNumber(bText);
      if (aNum !== null && bNum !== null) {
        if (aNum === bNum) return 0;
        return aNum < bNum ? -1 * dir : 1 * dir;
      }
      const av = normalizeText(aText);
      const bv = normalizeText(bText);
      return av.localeCompare(bv, undefined, { numeric: true, sensitivity: "base" }) * dir;
    });

    for (const r of rows) tbody.appendChild(r);
  };

  const applyFilter = (table) => {
    const filters = Array.from(table.querySelectorAll("thead [data-filter-row] input[data-filter-col]"));
    if (filters.length === 0) return;
    const values = filters.map((el) => normalizeText(el.value));
    const rows = getRows(table);
    for (const row of rows) {
      let ok = true;
      for (let i = 0; i < values.length; i++) {
        const q = values[i];
        if (!q) continue;
        const cell = row.children[i];
        const txt = normalizeText(cell ? cell.textContent : "");
        if (!txt.includes(q)) {
          ok = false;
          break;
        }
      }
      row.style.display = ok ? "" : "none";
    }
    applySort(table);
  };

  const tables = root.querySelectorAll("table");
  for (const t of tables) enhance(t);
})();
