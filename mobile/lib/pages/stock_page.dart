import 'package:flutter/material.dart';

import '../services/api_client.dart';
import 'product_page.dart';

class StockPage extends StatefulWidget {
  const StockPage({super.key});

  @override
  State<StockPage> createState() => _StockPageState();
}

class _StockPageState extends State<StockPage> {
  final _qCtrl = TextEditingController();
  String _q = '';

  @override
  void dispose() {
    _qCtrl.dispose();
    super.dispose();
  }

  Future<Map<String, dynamic>> _load() async {
    final materials = await ApiClient.instance.get('api/v1Materials', query: {
      if (_q.isNotEmpty) 'q': _q,
      'limit': '50',
      'offset': '0',
    });
    final products = await ApiClient.instance.get('api/v1Products', query: {
      if (_q.isNotEmpty) 'q': _q,
      'limit': '50',
      'offset': '0',
    });
    return {'materials': materials, 'products': products};
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: TextField(
                controller: _qCtrl,
                decoration: InputDecoration(
                  labelText: 'Cautare',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: IconButton(
                    onPressed: () {
                      _qCtrl.clear();
                      setState(() => _q = '');
                    },
                    icon: const Icon(Icons.clear),
                  ),
                ),
                onChanged: (v) => setState(() => _q = v.trim()),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Expanded(
            child: FutureBuilder(
              future: _load(),
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  final err = snapshot.error;
                  final msg = err is ApiException ? err.message : err.toString();
                  return Center(child: Text('Eroare: $msg'));
                }
                final data = snapshot.data ?? {};
                final matsBody = data['materials'] as Map<String, dynamic>;
                final prodsBody = data['products'] as Map<String, dynamic>;
                final mats = (matsBody['data'] as Map<String, dynamic>)['materials'] as List<dynamic>;
                final prods = (prodsBody['data'] as Map<String, dynamic>)['products'] as List<dynamic>;

                return ListView(
                  children: [
                    Card(
                      child: ExpansionTile(
                        initiallyExpanded: true,
                        title: Text('Materiale (${mats.length})'),
                        children: mats.map((m) {
                          final mm = m as Map<String, dynamic>;
                          return ListTile(
                            title: Text((mm['name'] ?? '').toString()),
                            subtitle: Text(
                              '${(mm['type_name'] ?? '').toString()} • ${(mm['current_qty'] ?? '').toString()} ${(mm['unit_code'] ?? '').toString()}',
                            ),
                          );
                        }).toList(),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Card(
                      child: ExpansionTile(
                        initiallyExpanded: true,
                        title: Text('Produse (${prods.length})'),
                        children: prods.map((p) {
                          final pp = p as Map<String, dynamic>;
                          final id = apiInt(pp['id']);
                          return ListTile(
                            title: Text((pp['name'] ?? '').toString()),
                            subtitle: Text(
                              'SKU ${(pp['sku'] ?? '').toString()} • Stoc ${(pp['stock_qty'] ?? '').toString()}',
                            ),
                            trailing: const Icon(Icons.chevron_right),
                            onTap: id < 1
                                ? null
                                : () => Navigator.of(context).push(
                                      MaterialPageRoute(builder: (_) => ProductPage(productId: id)),
                                    ),
                          );
                        }).toList(),
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
