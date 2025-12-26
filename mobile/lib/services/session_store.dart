import 'package:shared_preferences/shared_preferences.dart';

class SessionStore {
  SessionStore._();

  static final SessionStore instance = SessionStore._();

  static const _kToken = 'token';
  static const _kBaseUrl = 'base_url';

  String? _token;
  String? _baseUrl;
  bool _loaded = false;
  SharedPreferences? _prefs;

  Future<SharedPreferences> _p() async {
    final p = _prefs;
    if (p != null) return p;
    final created = await SharedPreferences.getInstance();
    _prefs = created;
    return created;
  }

  Future<void> _load() async {
    if (_loaded) return;
    final p = await _p();
    _token = p.getString(_kToken);
    _baseUrl = p.getString(_kBaseUrl);
    _loaded = true;
  }

  Future<String?> getToken() async {
    await _load();
    final raw = _token;
    return (raw != null && raw.trim().isNotEmpty) ? raw : null;
  }

  Future<bool> hasToken() async => (await getToken()) != null;

  Future<void> setToken(String token) async {
    _token = token.trim();
    final p = await _p();
    await p.setString(_kToken, _token!);
  }

  Future<void> clearToken() async {
    _token = null;
    final p = await _p();
    await p.remove(_kToken);
  }

  Future<String> getBaseUrl() async {
    await _load();
    final v = _baseUrl;
    if (v != null && v.trim().isNotEmpty) {
      return v.trim();
    }
    return 'https://wsmdev.greensh3ll.com';
  }

  Future<void> setBaseUrl(String baseUrl) async {
    _baseUrl = baseUrl.trim();
    final p = await _p();
    await p.setString(_kBaseUrl, _baseUrl!);
  }
}
