import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/session_store.dart';
import 'login_page.dart';
import 'production_page.dart';
import 'sales_page.dart';
import 'stock_page.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  int _index = 0;

  final _pages = const [
    StockPage(),
    ProductionPage(),
    SalesPage(),
  ];

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
    showDialog<void>(
      context: context,
      builder: (dialogContext) => FutureBuilder<String>(
        future: SessionStore.instance.getBaseUrl(),
        builder: (context, snapshot) {
          final loading = snapshot.connectionState != ConnectionState.done;
          final baseUrl = (snapshot.data ?? '').trim();
          final apkUrl = baseUrl.isEmpty ? '' : _apkUrlFromBaseUrl(baseUrl);

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
                      Text(apkUrl.isEmpty ? 'Link indisponibil.' : 'Link: $apkUrl'),
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
        title: const Text('WSM'),
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
