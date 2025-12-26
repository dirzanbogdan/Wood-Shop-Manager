import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../services/api_client.dart';
import '../services/session_store.dart';
import 'login_page.dart';
import 'production_page.dart';
import 'sales_page.dart';
import 'stock_page.dart';

class _UpdateInfo {
  _UpdateInfo({
    required this.apkUrl,
    required this.currentVersion,
    required this.currentBuild,
    required this.latestVersion,
    required this.latestBuild,
    required this.error,
  });

  final String apkUrl;
  final String currentVersion;
  final int currentBuild;
  final String? latestVersion;
  final int? latestBuild;
  final String? error;

  bool get hasUpdate => latestBuild != null && latestBuild! > currentBuild;
}

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  int _index = 0;
  String? _appVersion;

  final _pages = const [
    StockPage(),
    ProductionPage(),
    SalesPage(),
  ];

  @override
  void initState() {
    super.initState();
    _loadAppVersion();
  }

  Future<void> _loadAppVersion() async {
    try {
      final pkg = await PackageInfo.fromPlatform();
      if (!mounted) return;
      setState(() => _appVersion = 'v${pkg.version} (${pkg.buildNumber})');
    } catch (_) {}
  }

  Future<void> _logout() async {
    await SessionStore.instance.clearToken();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginPage()),
      (_) => false,
    );
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
    final future = () async {
      final baseUrl = (await SessionStore.instance.getBaseUrl()).trim();
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

      return _UpdateInfo(
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
      builder: (dialogContext) => FutureBuilder<String>(
        future: future.then((v) => v.apkUrl),
        builder: (context, snapshot) {
          final loading = snapshot.connectionState != ConnectionState.done;
          final apkUrl = (snapshot.data ?? '').trim();

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
                      FutureBuilder<_UpdateInfo>(
                        future: future,
                        builder: (context, infoSnapshot) {
                          final infoLoading = infoSnapshot.connectionState != ConnectionState.done;
                          final info = infoSnapshot.data;
                          if (infoLoading) {
                            return const Text('Verific update...');
                          }
                          if (info == null) {
                            return const Text('Nu pot incarca informatii.');
                          }
                          final hasUpdate = info.hasUpdate;
                          return Column(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Versiune instalata: ${info.currentVersion} (${info.currentBuild})'),
                              const SizedBox(height: 8),
                              if (info.error != null) Text('Nu pot verifica update: ${info.error}'),
                              if (info.error == null)
                                Text('Versiune server: ${(info.latestVersion ?? '-')} (${info.latestBuild?.toString() ?? '-'})'),
                              const SizedBox(height: 8),
                              Text(
                                apkUrl.isEmpty ? 'Link indisponibil.' : (hasUpdate ? 'Update disponibil. Link: $apkUrl' : 'Link: $apkUrl'),
                              ),
                            ],
                          );
                        },
                      ),
                      const SizedBox(height: 12),
                      const Text(
                        'Dupa download: deschide fisierul si permite instalarea din "surse necunoscute" cand ti se cere.',
                      ),
                    ],
                  ),
            actions: [
              if (!loading && apkUrl.isNotEmpty)
                TextButton(
                  onPressed: () {
                    SystemLauncher.openUrl(apkUrl);
                    Navigator.pop(dialogContext);
                  },
                  child: const Text('Deschide link'),
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
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_appVersion == null ? 'WSM' : 'WSM ${_appVersion!}'),
        actions: [
          IconButton(onPressed: _showInstallDialog, icon: const Icon(Icons.download)),
          IconButton(onPressed: _logout, icon: const Icon(Icons.logout)),
        ],
      ),
      body: _pages[_index],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.inventory_2), label: 'Stoc'),
          NavigationDestination(icon: Icon(Icons.factory), label: 'Productie'),
          NavigationDestination(icon: Icon(Icons.point_of_sale), label: 'Vanzare'),
        ],
      ),
    );
  }
}
