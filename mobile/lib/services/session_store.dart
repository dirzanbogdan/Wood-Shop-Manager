import 'package:shared_preferences/shared_preferences.dart';

class SessionStore {
  SessionStore._();

  static final SessionStore instance = SessionStore._();

  static const defaultBaseUrl = 'https://wsmdev.greensh3ll.com';

  static const _kToken = 'token';
  static const _kBaseUrl = 'base_url';

  String? _token;
  String? _baseUrl;
  bool _loaded = false;
  SharedPreferences? _prefs;

  static String? normalizeBaseUrl(String input) {
    final raw = input.trim();
    if (raw.isEmpty) return null;

    var candidate = raw;
    if (!candidate.contains('://')) {
      final lower = candidate.toLowerCase();
      final looksLikeIp = RegExp(
        r'^\d{1,3}(\.\d{1,3}){3}(:\d+)?(/|$)',
      ).hasMatch(lower);
      final isLocal =
          lower.startsWith('localhost') ||
          lower.startsWith('127.0.0.1') ||
          looksLikeIp;
      candidate = '${isLocal ? 'http' : 'https'}://$candidate';
    }

    final uri = Uri.tryParse(candidate);
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) return null;
    final cleaned = uri.replace(queryParameters: const {}, fragment: '');
    return cleaned.toString();
  }

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
    final normalized = normalizeBaseUrl(_baseUrl ?? '');
    return normalized ?? defaultBaseUrl;
  }

  Future<void> setBaseUrl(String baseUrl) async {
    final p = await _p();
    final normalized = normalizeBaseUrl(baseUrl);
    if (normalized == null) {
      _baseUrl = null;
      await p.remove(_kBaseUrl);
      return;
    }
    _baseUrl = normalized;
    await p.setString(_kBaseUrl, normalized);
  }
}
