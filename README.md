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
  - produse cu marjă mică (estimativ)
- Materie primă:
  - CRUD materiale, arhivare
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
  - stoc materie primă / stoc produse finite
  - consum materie primă (interval)
  - consum energie (interval)
  - ore lucrate (interval)
  - cost producție lunar (interval)
  - profit estimat pe produs (pe baza costului mediu/unit)
- Setări:
  - cost energie, cost orar operator (stocate în lei; introducere cu monedă selectabilă)
  - administrare unități de măsură (adăugare/editare)
  - nomenclatoare: furnizori, tipuri materiale, categorii produse
- Monedă:
  - curs BNR pentru EUR/USD (afișare și conversie la salvare)
  - selectare monedă în formulare pentru sume (ex: prețuri în USD/EUR)
- Update (SuperAdmin):
  - backup DB (download pe PC sau salvare pe server)
  - listă modificări incluse
  - aplicare update din git (`git pull --ff-only`) dacă `proc_open` este permis și există `.git`

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
