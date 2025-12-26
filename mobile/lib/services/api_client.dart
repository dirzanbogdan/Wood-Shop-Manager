import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:flutter/services.dart';

import 'session_store.dart';

int apiInt(dynamic v, {int fallback = 0}) {
  if (v == null) return fallback;
  if (v is int) return v;
  if (v is num) return v.toInt();
  if (v is String) {
    final t = v.trim();
    if (t.isEmpty) return fallback;
    final i = int.tryParse(t);
    if (i != null) return i;
    final d = double.tryParse(t.replaceAll(',', '.'));
    if (d != null) return d.toInt();
  }
  return fallback;
}

class ApiException implements Exception {
  ApiException(this.message, {this.code, this.status});
  final String message;
  final String? code;
  final int? status;

  @override
  String toString() => 'ApiException($status, $code): $message';
}

class ApiClient {
  ApiClient._();

  static final ApiClient instance = ApiClient._();

  ApiException _connError(Object e, Uri uri) {
    if (e is SocketException) {
      final msg = e.message;
      final looksLikeDns = msg.contains('Failed host lookup') || msg.contains('No address associated with hostname');
      if (looksLikeDns) {
        return ApiException(
          'Nu pot rezolva domeniul `${uri.host}`. Verifica "Setari conexiune" (Base URL) si conexiunea la internet.',
        );
      }
      return ApiException('Nu ma pot conecta la server (${uri.host}): ${e.message}');
    }
    final raw = e.toString();
    if (raw.contains('Failed host lookup') || raw.contains('No address associated with hostname')) {
      return ApiException(
        'Nu pot rezolva domeniul `${uri.host}`. Verifica "Setari conexiune" (Base URL) si conexiunea la internet.',
      );
    }
    return ApiException('Nu ma pot conecta la server: $e');
  }

  String _snippet(String text, {int max = 220}) {
    final t = text.replaceAll(RegExp(r'\s+'), ' ').trim();
    if (t.length <= max) return t;
    return '${t.substring(0, max)}...';
  }

  Future<Uri> _uri(String route, [Map<String, String>? query]) async {
    final baseUrl = await SessionStore.instance.getBaseUrl();
    final uri = Uri.tryParse(baseUrl);
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      throw ApiException(
        'API Base URL invalid. Deschide "Setari conexiune" si corecteaza.',
      );
    }
    final basePath = uri.path.isEmpty ? '/' : uri.path;
    final path = basePath.endsWith('/') ? basePath : '$basePath/';
    final merged = <String, String>{'r': route, ...(query ?? {})};
    return uri.replace(path: path, queryParameters: merged);
  }

  Future<Map<String, dynamic>> _decode(http.Response res) async {
    final raw = utf8.decode(res.bodyBytes, allowMalformed: true);
    final trimmed = raw.trim();
    if (trimmed.isEmpty) {
      throw ApiException('Raspuns gol (HTTP ${res.statusCode}).', status: res.statusCode);
    }

    dynamic decoded;
    try {
      decoded = jsonDecode(trimmed);
    } on FormatException {
      throw ApiException(
        'Raspuns non-JSON (HTTP ${res.statusCode}): ${_snippet(trimmed)}',
        status: res.statusCode,
      );
    }

    if (decoded is Map<String, dynamic>) {
      return decoded;
    }
    throw ApiException('Raspuns JSON invalid (HTTP ${res.statusCode}).', status: res.statusCode);
  }

  Future<Map<String, String>> _headers({bool json = false}) async {
    final token = await SessionStore.instance.getToken();
    final h = <String, String>{};
    if (json) {
      h['Content-Type'] = 'application/json';
      h['Accept'] = 'application/json';
    }
    if (token != null) {
      h['Authorization'] = 'Bearer $token';
      h['X-Authorization'] = 'Bearer $token';
      h['X-Access-Token'] = token;
    }
    return h;
  }

  Future<Map<String, dynamic>> get(String route, {Map<String, String>? query}) async {
    final uri = await _uri(route, query);
    http.Response res;
    try {
      res = await http.get(uri, headers: await _headers(json: true));
    } catch (e) {
      throw _connError(e, uri);
    }
    final body = await _decode(res);
    if (body['ok'] == true) {
      return body;
    }
    final err = body['error'];
    throw ApiException(
      (err is Map && err['message'] is String) ? err['message'] as String : 'Request failed',
      code: (err is Map && err['code'] is String) ? err['code'] as String : null,
      status: res.statusCode,
    );
  }

  Future<Map<String, dynamic>> post(String route, Map<String, dynamic> payload) async {
    final uri = await _uri(route);
    http.Response res;
    try {
      res = await http.post(
        uri,
        headers: await _headers(json: true),
        body: jsonEncode(payload),
      );
    } catch (e) {
      throw _connError(e, uri);
    }
    final body = await _decode(res);
    if (body['ok'] == true) {
      return body;
    }
    final err = body['error'];
    throw ApiException(
      (err is Map && err['message'] is String) ? err['message'] as String : 'Request failed',
      code: (err is Map && err['code'] is String) ? err['code'] as String : null,
      status: res.statusCode,
    );
  }

  Future<void> tokenLogin(String username, String password) async {
    final res = await post('api/v1TokenLogin', {'username': username, 'password': password});
    final data = res['data'];
    if (data is! Map<String, dynamic>) {
      throw ApiException('Invalid login response');
    }
    final token = data['token'];
    if (token is! String || token.trim().isEmpty) {
      throw ApiException('Missing token in response');
    }
    await SessionStore.instance.setToken(token);
  }

  Future<Map<String, dynamic>> appVersion() async {
    return get('api/v1AppVersion');
  }
}

class SystemLauncher {
  static const MethodChannel _channel = MethodChannel('wsm_mobile/system');

  static Future<void> openUrl(String url) async {
    final u = url.trim();
    if (u.isEmpty) return;
    await _channel.invokeMethod('openUrl', {'url': u});
  }
}
