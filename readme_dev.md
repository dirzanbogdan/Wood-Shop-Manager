# README (DEV)

## Scop
Acest fisier descrie modificarile si modul de testare pentru integrarea DEV (API + update control).

## Rulare locala
- Server PHP:
  - `php -S 127.0.0.1:8000 -t public`
- Aplicatia foloseste rutarea prin query param `r`, de forma:
  - `http://127.0.0.1:8000/?r=dashboard/index`
  - `http://127.0.0.1:8000/?r=api/v1Ping`

## API (V1)
### Auth
- Auth este pe sesiune (cookie PHP). Clientul trebuie sa pastreze cookie-ul intre request-uri.
- CSRF:
  - Pentru request-urile `POST` care modifica date, trimite `X-CSRF-Token: <token>`
  - Alternativ, trimite in body JSON campul `csrf_token` (cheia este configurabila prin `security.csrf_key`)

### CORS (pentru Flutter Web)
- CORS este controlat din `config/config.php`:
  - `api.cors_allowed_origins`
  - `api.cors_allow_credentials`
- Pentru test rapid in DEV este permis wildcard `*` (credidentialele sunt dezactivate automat).
- Pentru productie seteaza origin-uri explicite (ex: `https://app.example.com`) si evita `*`.

### Exemplu (curl)
- Login (salveaza cookie + extrage token CSRF din raspuns):
  - `curl -s -c cookies.txt -H "Content-Type: application/json" -d "{\"username\":\"<user>\",\"password\":\"<pass>\"}" "http://127.0.0.1:8000/?r=api/v1Login"`
- Me:
  - `curl -s -b cookies.txt "http://127.0.0.1:8000/?r=api/v1Me"`
- CSRF token:
  - `curl -s -b cookies.txt "http://127.0.0.1:8000/?r=api/v1Csrf"`
- Creare vanzare (exemplu, necesita CSRF + auth):
  - `curl -s -b cookies.txt -H "Content-Type: application/json" -H "X-CSRF-Token: <csrf_token>" -d "{\"product_id\":1,\"qty\":1,\"sale_price\":100}" "http://127.0.0.1:8000/?r=api/v1SalesCreate"`

## Update control (protectie productie)
- In `config/config.php` exista `update.git_branch` (default `main`).
- Din UI, actiunea `git pull` este permisa doar daca branch-ul curent este exact cel configurat.
- Daca repo este in `detached HEAD`, update-ul este blocat.

## Testare Flutter (MVP)
- Seteaza `SessionStore.getBaseUrl()` la domeniul corect (ex: `https://wsmdev.greensh3ll.com` sau `http://127.0.0.1:8000`).
- Daca instanta e servita din sub-folder, `baseUrl` trebuie sa includa path-ul (ex: `http://127.0.0.1:8000/public`).
- Pentru Flutter Web (Chrome) CORS trebuie sa fie activ pe server; altfel vei vedea `ClientException: Failed to fetch`.

## Mobile (Login)
### Parola
- Toggle show/hide parola (icon-ul din dreapta campului).
- La tastare, parola este afisata scurt (aprox. 700ms), apoi revine mascare.

### Password manager (Autofill)
- Campurile au `autofillHints` (`username`, `password`) si sunt in `AutofillGroup`.
- Dupa login reusit, aplicatia finalizeaza contextul de autofill cu `shouldSave: true` (telefonul poate afisa prompt de salvare).

### Amprenta (Biometric)
- Dupa primul login reusit cu user/parola, credidentialele sunt salvate in `flutter_secure_storage`.
- Butonul "Login cu amprenta" devine activ doar daca dispozitivul suporta biometrie si exista credidentiale salvate.
