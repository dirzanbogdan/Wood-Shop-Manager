# GreenSh3ll Wood Shop Manager

Versiune: afișată în footer (ex: `v2.22122025.003`)

Format versiune: `V<major>.<ddmmyyyy>.<ttt>`
- `major`: versiunea majoră (configurabilă în `config/config.php`)
- `ddmmyyyy`: data release (configurabilă în `config/config.php`; fallback: data curentă UTC)
- `ttt`: versiunea minoră (configurabilă în `config/config.php`, padding la 3 cifre; începe de la `001`)

## Cerinte

- PHP `8.4+`
- MySQL `8+` / MariaDB compatibil
- Extensii PHP: `pdo_mysql`

## Functionalitati

- Autentificare: login/logout, roluri `SuperAdmin` / `Admin` / `Operator`
- Dashboard:
  - materiale cu stoc critic
  - comenzi de producție în lucru
  - consum energie ultimele 30 zile
  - produse cu marjă mică (estimativ; marjă <= 0 sau < 10% din preț)
- Materie primă:
  - CRUD materiale, arhivare
  - cod produs (opțional)
  - stoc curent, cost unitar, stoc minim
  - data achiziției + URL achiziție
  - istoric mișcări (intrare/ieșire/ajustare)
- Utilaje:
  - CRUD utilaje, putere kW, activ/inactiv
- Produse:
  - CRUD produse (SKU unic), preț, ore estimate/manoperă, status, stoc
  - vânzări (scade stoc, înregistrează în `sales`)
- Rețete / BOM:
  - asociere materiale pe produs (cantitate + waste%)
  - asociere utilaje pe produs (ore)
- Producție:
  - pornire comandă producție (verifică rețeta)
  - finalizare comandă:
    - consumă materiale (actualizează stoc + mișcări)
    - înregistrează consum utilaje (kWh + cost)
    - calculează cost manoperă + cost total + cost/unit
    - crește stocul de produse finite
  - anulare comandă (doar pentru comenzi pornite)
- Rapoarte (cu export CSV):
  - stoc materiale / produse finite
  - consum materiale (interval + filtru produs)
  - consum energie (interval + filtru produs)
  - ore (interval + filtru produs)
  - cost producție (interval + filtru produs)
  - profit pe produs (pe baza costului mediu/unit + impozit)
- Setări:
  - cost energie, cost orar operator (stocate în lei; introducere cu monedă selectabilă)
  - taxe: tip entitate + tip impozit (folosit în raportul „Profit estimat”)
  - administrare unități de măsură (adăugare/editare)
  - nomenclatoare: furnizori, tipuri materiale, categorii produse
- Monedă:
  - curs BNR pentru EUR/USD (afișare și conversie la salvare)
  - selectare monedă în formulare pentru sume (ex: prețuri în USD/EUR)
  - salvare automată a valorilor în lei în baza de date
- Update (SuperAdmin):
  - backup DB (download pe PC sau salvare pe server)
  - listă modificări incluse
  - aplicare update din git (`git pull --ff-only`) dacă `proc_open` este permis și există `.git`
  - afișare log complet la update (exit code + output)
  - cleanup automat dacă repo rămâne “dirty” după update
  - aplicare update din arhiva GitHub (zip) dacă git nu este disponibil

## Instalare

1. Urca proiectul pe hosting.
2. Acceseaza `https://domeniul-tau/install.php` si completeaza conexiunea DB + setari regionale + utilizatorul `SuperAdmin`.
3. Login: `/?r=auth/login`

Configuratia locala se salveaza in `config/local.php` (nu intra in Git).

## Update (SuperAdmin)

Pagina: `/?r=update/index`

Etape:

1. Backup DB:
   - Descarcare `.sql` pe PC
   - Salvare `.sql` pe server in `storage/backups/`
2. Evidentiere modificari:
   - Lista modificarilor incluse in versiunea curenta

Optional (daca proiectul este clonat cu git pe server si `proc_open` este permis):
- Butonul “Aplica update (git pull)” face `git pull --ff-only`.
- Daca apare eroare de permisiuni pe `.git` (ex: `.git/logs/...: Permission denied`), userul web nu are drepturi de scriere; foloseste update din zip sau seteaza permisiuni corecte.
- Output-ul complet al comenzilor este afișat în UI (multi-line).

Alternativ (daca git nu este disponibil pe server):
- Butonul “Aplica update (GitHub zip)” descarca arhiva `main.zip` si aplica update-ul.
