# Changelog

## v2.22122025.003

- Install: setări regionale (currency/timezone/language) înainte de SuperAdmin
- Install: timezone implicit `Europe/Bucharest` + căutare (datalist)
- Install: checklist live pentru complexitatea parolei
- Monedă: curs BNR oficial EUR/USD (`https://www.bnr.ro/nbrfxrates.xml`)
- Monedă: cache în `settings` (`fx_date`, `fx_eur`, `fx_usd`) + fallback la valorile cache dacă BNR nu răspunde
- Monedă: conversie la salvare în lei pentru câmpuri de sume (pe baza monedei selectate)
- UI: select monedă la câmpurile de sume (materiale, produse, setări costuri)
- Update: backup DB (download `.sql` sau salvare în `storage/backups/`)
- Update: aplicare update din git (`git pull --ff-only`) când există `.git` și `proc_open` este permis
- Update: aplicare update din arhivă GitHub (zip) când git nu este disponibil + aplicare update DB
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

