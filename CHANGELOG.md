# Changelog

## v3.27122025.002

- Update: upload build-uri (APK/AAB/IPA/EXE/MSIX) în `public/downloads` cu arhivare automată
- Update: configurare `config/local.php` direct din UI (base URL, branch update, CORS, token API)
- Update: nu mai blochează update-ul când există modificări locale doar în `mobile/*` (auto skip-worktree)
- Update: mesaje mai clare când upload-ul depășește limitele PHP (`post_max_size` / `upload_max_filesize`)
- UI: în listele de unități apare codul + denumirea (ex: `kg (Kilogram)`)

## v3.27122025.001

- API v1: răspuns standardizat `ok/data/error/meta` + erori consistente
- API v1: autentificare Bearer (token login) + suport header `Authorization`
- API v1: CORS configurabil din config
- Update: păstrează `public/downloads/wsm.apk` la update (backup/restore)
- Mobile (WSM): aplicație Flutter MVP + validare Base URL + erori copiable

## v2.22122025.004

- Input: acceptă valori zecimale de forma `.23` și normalizează la `0.23` (Excel-like)
- Input: normalizează și formele `-.23` → `-0.23`, `+.23` → `0.23`
- Materie primă: câmp nou „Cod produs” în formular
- Materie primă: buton icon pentru deschiderea „URL achizitie” din listă
- Materie primă: „Cod produs” unic + opțiuni la conflict (suprascrie / schimbă codul)
- Setări: card nou „Taxe” (tip entitate + tip impozit)
- Rapoarte: meniu orizontal în paginile de raport (navigare rapidă între rapoarte)
- Rapoarte: denumiri scurte în meniuri (Stoc materiale, Produse finite, Consum materiale, Consum energie, Ore, Cost productie, Profit)
- Rapoarte: rânduri cu culori alternante pentru lizibilitate
- Rapoarte: filtrare + sortare direct în tabel (client-side)
- Rapoarte: filtru „Produs” pentru consum materie primă / energie / ore / cost lunar
- Raport „Profit estimat”: coloană „Impozit”, „Pret/Unit”, + „Cant vanduta”, „Valoare vanzare”, „Profit Net”
- Fix: Raport „Profit” nu mai returnează eroare 500 (PDO params)
- Dashboard: „profit mic” definit ca marjă <= 0 sau < 10% din preț

## v2.22122025.003

- Install: setări regionale (currency/timezone/language) înainte de SuperAdmin
- Install: timezone implicit `Europe/Bucharest` + căutare (datalist)
- Install: checklist live pentru complexitatea parolei
- Monedă: curs BNR oficial EUR/USD (`https://www.bnr.ro/nbrfxrates.xml`)
- Monedă: cache în `settings` (`fx_date`, `fx_eur`, `fx_usd`) + fallback la valorile cache dacă BNR nu răspunde
- Monedă: conversie la salvare în lei pentru câmpuri de sume (pe baza monedei selectate)
- UI: select monedă la câmpurile de sume (materiale, produse, setări costuri)
- UI: afișare date în format dd/mm/yyyy
- UI: câmpurile de dată acceptă și format dd/mm/yyyy
- Update: backup DB (download `.sql` sau salvare în `storage/backups/`)
- Update: aplicare update din git (`git pull --ff-only`) când există `.git` și `proc_open` este permis
- Update: compatibilitate îmbunătățită pentru `git pull` rulat din GUI (PATH/HOME/safe.directory)
- Update: dropdown-urile “Modificari incluse” sunt închise implicit
- Update: aplicare update din arhivă GitHub (zip) când git nu este disponibil + aplicare update DB
- Update: log complet pentru rulările git (exit code + STDOUT + STDERR), afișat multi-line
- Update: cleanup automat dacă repo rămâne “dirty” după update (`git reset --hard HEAD`)
- Update: detectare erori de permisiuni pe `.git` (ex: `.git/logs`) + sugestie de rezolvare / fallback zip
- Fix: eliminat `curl_close()` (deprecated în PHP 8.4+)

## v2.22122025.002

- Update: afișare changelog în pagina Update (dropdown per versiune)
- Setări: timezone implicit Europe/Bucharest + editabil
- Setări: ștergere unități (doar dacă nu sunt folosite)
- Rețetă/BOM: timp utilaje introdus în minute (stocat în DB ca ore)

SQL:
- ALTER TABLE bom_machines MODIFY hours DECIMAL(10,4) NOT NULL;
- ALTER TABLE production_machine_usage MODIFY hours_used DECIMAL(10,4) NOT NULL;
- INSERT IGNORE INTO settings (`key`, `value`) VALUES ('timezone', 'Europe/Bucharest');

## v2.22122025.001

- Versiune calculată automat: V<major>.<ddmmyyyy>.<ttt> (afișată în footer)
- Materie primă: câmp URL achiziție
- Setări: administrare unități (adăugare/editare)
- Update: backup DB + aplicare update din git (SuperAdmin)

SQL:
- ALTER TABLE materials ADD COLUMN purchase_url VARCHAR(500) NULL AFTER purchase_date;
