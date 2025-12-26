# Changelog (DEV)

## 2025-12-26

### Modificat
- API: CORS permite wildcard `*` (pentru test rapid Flutter Web)
- Flutter: afiseaza erori utile cand serverul intoarce non-JSON / CORS block
- Flutter: pastreaza path-ul din `baseUrl` (ex: `.../public`)
- Flutter: extins `SessionStore` cu cookie + CSRF (pregatire pentru fallback pe sesiune)
- Update: `public/downloads/wsm.apk` este protejat la update (backup/restore) daca se strica fisierul
- Update: detectie APK invalid (fisier prea mic) + mesaj cu solutie (upload manual)
- Mobile: camp parola cu show/hide si afisare temporara la tastare
- Mobile: autofill pentru username/parola + salvare in password manager dupa login
- Mobile: login cu amprenta (biometric) dupa primul login cu user/parola
- Versiuni mobile: `1.0.2+3` (server `config/config.php` + APK `pubspec.yaml`)

## 2025-12-25

### Adaugat
- API JSON `v1` pentru client mobile (controller `ApiController`)
  - Auth pe sesiune (cookie PHP), compatibil cu web
  - CSRF pentru endpoint-urile mutabile via `X-CSRF-Token` sau camp in JSON
  - Endpoint-uri:
    - `GET /?r=api/v1Ping`
    - `GET /?r=api/v1Csrf`
    - `POST /?r=api/v1Login`
    - `POST /?r=api/v1Logout`
    - `GET /?r=api/v1Me`
    - `GET /?r=api/v1Materials`
    - `GET /?r=api/v1Products`
    - `GET /?r=api/v1Bom&product_id=<id>`
    - `GET /?r=api/v1ProductionOrders`
    - `POST /?r=api/v1ProductionStart`
    - `POST /?r=api/v1ProductionFinalize`
    - `GET /?r=api/v1Sales`
    - `POST /?r=api/v1SalesCreate`

### Modificat
- `v1Login` returneaza si token-ul CSRF (`csrf_key`, `csrf_token`) pentru flow mobil simplificat
- UI Update: `git pull` este blocat daca repo nu este pe branch-ul configurat in `config.php` (`update.git_branch`, default `main`)
- UI Update: afiseaza branch-ul configurat pentru update in pagina `Update`

