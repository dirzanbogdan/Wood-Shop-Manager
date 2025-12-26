import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../services/api_client.dart';
import '../services/session_store.dart';
import 'home_page.dart';

class _InstallInfo {
  _InstallInfo({
    required this.baseUrl,
    required this.apkUrl,
    required this.currentVersion,
    required this.currentBuild,
    required this.latestVersion,
    required this.latestBuild,
    required this.error,
  });

  final String baseUrl;
  final String apkUrl;
  final String currentVersion;
  final int currentBuild;
  final String? latestVersion;
  final int? latestBuild;
  final String? error;

  bool get hasUpdate => latestBuild != null && latestBuild! > currentBuild;
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _usernameCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _baseUrlCtrl = TextEditingController();

  bool _loading = false;
  String? _error;

  bool _obscure = true;
  bool _reveal = false;
  Timer? _revealTimer;

  final LocalAuthentication _localAuth = LocalAuthentication();
  final FlutterSecureStorage _secure = const FlutterSecureStorage();
  bool _bioAvailable = false;
  bool _bioHasCreds = false;
  bool _bioLoading = false;

  static const _kBioUser = 'bio_username';
  static const _kBioPass = 'bio_password';

  @override
  void initState() {
    super.initState();
    _loadBaseUrl();
    _initBiometric();
  }

  Future<void> _loadBaseUrl() async {
    final url = await SessionStore.instance.getBaseUrl();
    if (!mounted) return;
    setState(() => _baseUrlCtrl.text = url);
  }

  Future<void> _initBiometric() async {
    bool supported = false;
    bool hasCreds = false;
    try {
      supported =
          await _localAuth.isDeviceSupported() &&
          await _localAuth.canCheckBiometrics;
      final u = await _secure.read(key: _kBioUser);
      final p = await _secure.read(key: _kBioPass);
      hasCreds =
          (u != null && u.trim().isNotEmpty) && (p != null && p.isNotEmpty);
    } catch (_) {
      supported = false;
      hasCreds = false;
    }
    if (!mounted) return;
    setState(() {
      _bioAvailable = supported;
      _bioHasCreds = hasCreds;
    });
  }

  String _apkUrlFromBaseUrl(String baseUrl) {
    final u = Uri.tryParse(baseUrl.trim());
    if (u == null || !u.hasScheme || u.host.isEmpty) return '';
    final clean = u.replace(queryParameters: const {}, fragment: '');
    final s = clean.toString();
    final trimmed = s.endsWith('/') ? s.substring(0, s.length - 1) : s;
    return '$trimmed/downloads/wsm.apk';
  }

  void _showInstallDialog() {
    final directBaseUrl = _baseUrlCtrl.text.trim();
    final future = () async {
      final normalizedDirect = SessionStore.normalizeBaseUrl(directBaseUrl);
      if (directBaseUrl.isNotEmpty && normalizedDirect == null) {
        return _InstallInfo(
          baseUrl: directBaseUrl,
          apkUrl: '',
          currentVersion: '',
          currentBuild: 0,
          latestVersion: null,
          latestBuild: null,
          error: 'API Base URL invalid: $directBaseUrl',
        );
      }

      final baseUrl =
          (normalizedDirect ?? await SessionStore.instance.getBaseUrl()).trim();
      if (baseUrl.isNotEmpty && normalizedDirect != null) {
        await SessionStore.instance.setBaseUrl(baseUrl);
      }

      final apkUrlFallback = baseUrl.isEmpty ? '' : _apkUrlFromBaseUrl(baseUrl);

      final pkg = await PackageInfo.fromPlatform();
      final currentVersion = pkg.version;
      final currentBuild = int.tryParse(pkg.buildNumber) ?? 0;

      String? latestVersion;
      int? latestBuild;
      String apkUrl = apkUrlFallback;
      String? error;

      try {
        final res = await ApiClient.instance.appVersion();
        final data = res['data'];
        if (data is Map<String, dynamic>) {
          latestVersion = (data['latest_version'] is String)
              ? data['latest_version'] as String
              : null;
          latestBuild = apiInt(data['latest_build'], fallback: 0);
          if (latestBuild == 0) latestBuild = null;
          final remoteApkUrl = (data['apk_url'] is String)
              ? (data['apk_url'] as String).trim()
              : '';
          if (remoteApkUrl.isNotEmpty) {
            apkUrl = remoteApkUrl;
          }
        }
      } on ApiException catch (e) {
        error = e.message;
      } catch (e) {
        error = e.toString();
      }

      return _InstallInfo(
        baseUrl: baseUrl,
        apkUrl: apkUrl,
        currentVersion: currentVersion,
        currentBuild: currentBuild,
        latestVersion: latestVersion,
        latestBuild: latestBuild,
        error: error,
      );
    }();

    showDialog<void>(
      context: context,
      builder: (dialogContext) => FutureBuilder<_InstallInfo>(
        future: future,
        builder: (context, snapshot) {
          final loading = snapshot.connectionState != ConnectionState.done;
          final info = snapshot.data;
          final apkUrl = info?.apkUrl ?? '';
          final hasUpdate = info?.hasUpdate == true;

          return AlertDialog(
            title: const Text('Instalare APK'),
            content: loading
                ? const SizedBox(
                    height: 64,
                    child: Center(child: CircularProgressIndicator()),
                  )
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        info == null
                            ? 'Nu pot incarca informatii.'
                            : 'Versiune instalata: ${info.currentVersion} (${info.currentBuild})',
                      ),
                      const SizedBox(height: 8),
                      if (info?.error != null)
                        Text('Nu pot verifica update: ${info!.error}'),
                      if (info?.error == null)
                        Text(
                          'Versiune server: ${(info?.latestVersion ?? '-')} (${info?.latestBuild?.toString() ?? '-'})',
                        ),
                      const SizedBox(height: 8),
                      Text(
                        apkUrl.isEmpty
                            ? 'Seteaza API Base URL ca sa genereze un link.'
                            : (hasUpdate
                                  ? 'Update disponibil. Link: $apkUrl'
                                  : 'Link: $apkUrl'),
                      ),
                      const SizedBox(height: 12),
                      const Text(
                        'Dupa download: deschide fisierul si permite instalarea din "surse necunoscute" cand ti se cere.',
                      ),
                      const SizedBox(height: 12),
                      const Text('Varianta 2: instalezi din PC (ADB).'),
                      const SizedBox(height: 8),
                      const Text('Comanda: adb install -r app-debug.apk'),
                    ],
                  ),
            actions: [
              if (!loading && apkUrl.isNotEmpty)
                TextButton(
                  onPressed: () {
                    SystemLauncher.openUrl(apkUrl);
                    Navigator.pop(dialogContext);
                  },
                  child: Text(hasUpdate ? 'Descarca update' : 'Deschide link'),
                ),
              if (!loading && apkUrl.isNotEmpty)
                TextButton(
                  onPressed: () {
                    final messenger = ScaffoldMessenger.of(this.context);
                    Clipboard.setData(ClipboardData(text: apkUrl));
                    Navigator.pop(dialogContext);
                    messenger.showSnackBar(
                      const SnackBar(content: Text('Link copiat.')),
                    );
                  },
                  child: const Text('Copiaza link'),
                ),
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: const Text('Inchide'),
              ),
            ],
          );
        },
      ),
    );
  }

  @override
  void dispose() {
    _revealTimer?.cancel();
    _usernameCtrl.dispose();
    _passwordCtrl.dispose();
    _baseUrlCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit({
    String? directUsername,
    String? directPassword,
    bool finishAutofill = true,
  }) async {
    final username = (directUsername ?? _usernameCtrl.text).trim();
    final password = directPassword ?? _passwordCtrl.text;
    final baseUrl = _baseUrlCtrl.text.trim();
    if (baseUrl.isNotEmpty) {
      final normalized = SessionStore.normalizeBaseUrl(baseUrl);
      if (normalized == null) {
        setState(
          () => _error =
              'API Base URL invalid. Exemplu: ${SessionStore.defaultBaseUrl}',
        );
        return;
      }
      if (normalized != baseUrl) {
        _baseUrlCtrl.text = normalized;
      }
      await SessionStore.instance.setBaseUrl(normalized);
    }
    if (username.isEmpty || password.isEmpty) {
      setState(() => _error = 'Completeaza username si parola.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await ApiClient.instance.tokenLogin(username, password);
      try {
        await _secure.write(key: _kBioUser, value: username);
        await _secure.write(key: _kBioPass, value: password);
        _bioHasCreds = true;
      } catch (_) {}
      if (finishAutofill) {
        try {
          TextInput.finishAutofillContext(shouldSave: true);
        } catch (_) {}
      }
      if (!mounted) return;
      Navigator.of(
        context,
      ).pushReplacement(MaterialPageRoute(builder: (_) => const HomePage()));
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Eroare neasteptata.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _resetBaseUrl() async {
    final v = SessionStore.defaultBaseUrl;
    _baseUrlCtrl.text = v;
    await SessionStore.instance.setBaseUrl(v);
    if (!mounted) return;
    setState(() => _error = null);
  }

  void _onPasswordChanged(String _) {
    if (!_obscure) return;
    _revealTimer?.cancel();
    setState(() => _reveal = true);
    _revealTimer = Timer(const Duration(milliseconds: 700), () {
      if (!mounted) return;
      setState(() => _reveal = false);
    });
  }

  Future<void> _loginWithBiometric() async {
    if (_loading || _bioLoading || !_bioAvailable || !_bioHasCreds) return;
    setState(() {
      _bioLoading = true;
      _error = null;
    });
    try {
      final ok = await _localAuth.authenticate(
        localizedReason: 'Autentificare cu amprenta',
        options: const AuthenticationOptions(
          biometricOnly: true,
          stickyAuth: true,
        ),
      );
      if (!ok) {
        setState(() => _error = 'Autentificare anulata.');
        return;
      }
      final u = await _secure.read(key: _kBioUser);
      final p = await _secure.read(key: _kBioPass);
      final username = (u ?? '').trim();
      final password = p ?? '';
      if (username.isEmpty || password.isEmpty) {
        setState(() {
          _bioHasCreds = false;
          _error = 'Nu exista credidentiale salvate pentru amprenta.';
        });
        return;
      }
      _usernameCtrl.text = username;
      _passwordCtrl.text = password;
      await _submit(
        directUsername: username,
        directPassword: password,
        finishAutofill: false,
      );
    } catch (_) {
      setState(() => _error = 'Nu pot folosi amprenta pe acest dispozitiv.');
    } finally {
      if (mounted) setState(() => _bioLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

    return Scaffold(
      body: SafeArea(
        child: AutofillGroup(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [cs.primary, cs.primaryContainer],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Row(
                  children: [
                    Container(
                      height: 44,
                      width: 44,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.22),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Icon(Icons.carpenter, color: Colors.white),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Wood Shop Manager',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          Text(
                            'Login',
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.9),
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      TextField(
                        controller: _usernameCtrl,
                        decoration: const InputDecoration(
                          labelText: 'Username',
                          prefixIcon: Icon(Icons.person_outline),
                        ),
                        textInputAction: TextInputAction.next,
                        enabled: !_loading,
                        autofillHints: const [AutofillHints.username],
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _passwordCtrl,
                        decoration: InputDecoration(
                          labelText: 'Parola',
                          prefixIcon: const Icon(Icons.lock_outline),
                          suffixIcon: IconButton(
                            onPressed: _loading
                                ? null
                                : () {
                                    setState(() {
                                      _obscure = !_obscure;
                                      _reveal = false;
                                    });
                                  },
                            icon: Icon(
                              _obscure
                                  ? Icons.visibility
                                  : Icons.visibility_off,
                            ),
                          ),
                        ),
                        obscureText: _obscure && !_reveal,
                        enabled: !_loading,
                        keyboardType: TextInputType.visiblePassword,
                        enableSuggestions: false,
                        autocorrect: false,
                        autofillHints: const [AutofillHints.password],
                        onChanged: _onPasswordChanged,
                        onSubmitted: (_) => _submit(),
                      ),
                      const SizedBox(height: 12),
                      ExpansionTile(
                        tilePadding: EdgeInsets.zero,
                        title: const Text('Setari conexiune'),
                        children: [
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: TextField(
                              controller: _baseUrlCtrl,
                              decoration: const InputDecoration(
                                labelText: 'API Base URL',
                                prefixIcon: Icon(Icons.link),
                              ),
                              enabled: !_loading,
                              keyboardType: TextInputType.url,
                            ),
                          ),
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton(
                              onPressed: _loading ? null : _resetBaseUrl,
                              child: const Text('Reset la implicit'),
                            ),
                          ),
                        ],
                      ),
                      if (_error != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(_error!, style: TextStyle(color: cs.error)),
                              if (_error!.contains('domeniul') ||
                                  _error!.contains('Base URL'))
                                Align(
                                  alignment: Alignment.centerRight,
                                  child: TextButton(
                                    onPressed: _loading ? null : _resetBaseUrl,
                                    child: const Text('Reset Base URL'),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton(
                          onPressed: _loading ? null : _submit,
                          child: _loading
                              ? const SizedBox(
                                  height: 18,
                                  width: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Text('Login'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    children: [
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed:
                              (!_bioAvailable ||
                                  !_bioHasCreds ||
                                  _loading ||
                                  _bioLoading)
                              ? null
                              : _loginWithBiometric,
                          icon: _bioLoading
                              ? const SizedBox(
                                  height: 18,
                                  width: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.fingerprint),
                          label: const Text('Login cu amprenta'),
                        ),
                      ),
                      const SizedBox(height: 4),
                      TextButton(
                        onPressed: _loading ? null : _showInstallDialog,
                        child: const Text('Descarca / instaleaza APK'),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
