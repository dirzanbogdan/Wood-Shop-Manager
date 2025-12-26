import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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

  @override
  void initState() {
    super.initState();
    _loadBaseUrl();
  }

  Future<void> _loadBaseUrl() async {
    final url = await SessionStore.instance.getBaseUrl();
    if (!mounted) return;
    setState(() => _baseUrlCtrl.text = url);
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
      final baseUrl = directBaseUrl.isNotEmpty ? directBaseUrl : (await SessionStore.instance.getBaseUrl()).trim();
      if (baseUrl.isNotEmpty && directBaseUrl.isNotEmpty) {
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
          latestVersion = (data['latest_version'] is String) ? data['latest_version'] as String : null;
          latestBuild = apiInt(data['latest_build'], fallback: 0);
          if (latestBuild == 0) latestBuild = null;
          final remoteApkUrl = (data['apk_url'] is String) ? (data['apk_url'] as String).trim() : '';
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
                      if (info?.error != null) Text('Nu pot verifica update: ${info!.error}'),
                      if (info?.error == null)
                        Text(
                          'Versiune server: ${(info?.latestVersion ?? '-') } (${info?.latestBuild?.toString() ?? '-'})',
                        ),
                      const SizedBox(height: 8),
                      Text(
                        apkUrl.isEmpty
                            ? 'Seteaza API Base URL ca sa genereze un link.'
                            : (hasUpdate ? 'Update disponibil. Link: $apkUrl' : 'Link: $apkUrl'),
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
                    messenger.showSnackBar(const SnackBar(content: Text('Link copiat.')));
                  },
                  child: const Text('Copiaza link'),
                ),
              TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Inchide')),
            ],
          );
        },
      ),
    );
  }

  @override
  void dispose() {
    _usernameCtrl.dispose();
    _passwordCtrl.dispose();
    _baseUrlCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final username = _usernameCtrl.text.trim();
    final password = _passwordCtrl.text;
    final baseUrl = _baseUrlCtrl.text.trim();
    if (baseUrl.isNotEmpty) {
      await SessionStore.instance.setBaseUrl(baseUrl);
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
      if (!mounted) return;
      Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const HomePage()));
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Eroare neasteptata.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('WSM Login')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            TextField(
              controller: _usernameCtrl,
              decoration: const InputDecoration(labelText: 'Username'),
              textInputAction: TextInputAction.next,
              enabled: !_loading,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _passwordCtrl,
              decoration: const InputDecoration(labelText: 'Parola'),
              obscureText: true,
              enabled: !_loading,
              onSubmitted: (_) => _submit(),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _baseUrlCtrl,
              decoration: const InputDecoration(labelText: 'API Base URL'),
              enabled: !_loading,
              keyboardType: TextInputType.url,
            ),
            const SizedBox(height: 16),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: _loading ? null : _submit,
                child: _loading
                    ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Text('Login'),
              ),
            ),
            TextButton(
              onPressed: _loading ? null : _showInstallDialog,
              child: const Text('Descarca / instaleaza APK'),
            ),
          ],
        ),
      ),
    );
  }
}
