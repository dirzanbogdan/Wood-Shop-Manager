import 'package:flutter/material.dart';

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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('WSM'),
        actions: [
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

