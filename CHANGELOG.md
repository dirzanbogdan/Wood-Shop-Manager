# Changelog

## v2.22122025.003

- Install: setări regionale (currency/timezone/language) înainte de SuperAdmin
- Install: checklist live pentru complexitatea parolei
- Curs valutar: conversie cu curs BNR (EUR/USD) + cache în `settings` (fx_*)
- Formulare: selectare monedă la câmpurile de sume + salvare automată în lei
- Fix: eliminat `curl_close()` (deprecated în PHP 8.4)

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

