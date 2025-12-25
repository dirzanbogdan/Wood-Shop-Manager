class SessionStore {
  SessionStore._();

  static final SessionStore instance = SessionStore._();

  String? _token;
  String? _baseUrl;

  Future<String?> getToken() async {
    final raw = _token;
    return (raw != null && raw.trim().isNotEmpty) ? raw : null;
  }

  Future<bool> hasToken() async => (await getToken()) != null;

  Future<void> setToken(String token) async {
    _token = token.trim();
  }

  Future<void> clearToken() async {
    _token = null;
  }

  Future<String> getBaseUrl() async {
    final v = _baseUrl;
    if (v != null && v.trim().isNotEmpty) {
      return v.trim();
    }
    return 'https://wsmdev.greensh3ll.com';
  }

  Future<void> setBaseUrl(String baseUrl) async {
    _baseUrl = baseUrl.trim();
  }
}
