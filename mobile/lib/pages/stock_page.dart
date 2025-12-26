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

  double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    if (v is String) {
      final t = v.trim();
      if (t.isEmpty) return 0;
      return double.tryParse(t.replaceAll(',', '.')) ?? 0;
    }
    return 0;
  }

  int _toInt(dynamic v) => apiInt(v, fallback: 0);

  @override
  void dispose() {
    _qCtrl.dispose();
    super.dispose();
  }

  Future<Map<String, dynamic>> _load() async {
    final limit = _q.isNotEmpty ? 50 : 500;
    final materials = await ApiClient.instance.get(
      'api/v1Materials',
      query: {
        if (_q.isNotEmpty) 'q': _q,
        'limit': limit.toString(),
        'offset': '0',
      },
    );
    final products = await ApiClient.instance.get(
      'api/v1Products',
      query: {
        if (_q.isNotEmpty) 'q': _q,
        'limit': limit.toString(),
        'offset': '0',
      },
    );
    return {'materials': materials, 'products': products};
  }

  void _showCriticalStock(
    BuildContext context, {
    required List<Map<String, dynamic>> criticalMaterials,
    required List<Map<String, dynamic>> outOfStockProducts,
  }) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: ListView(
            children: [
              const SizedBox(height: 8),
              Text(
                'Stocuri critice',
                style: Theme.of(ctx).textTheme.titleLarge,
              ),
              const SizedBox(height: 8),
              if (criticalMaterials.isEmpty && outOfStockProducts.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Center(child: Text('Nu exista stocuri critice.')),
                ),
              if (criticalMaterials.isNotEmpty) ...[
                Text(
                  'Materiale (${criticalMaterials.length})',
                  style: Theme.of(ctx).textTheme.titleMedium,
                ),
                const SizedBox(height: 6),
                ...criticalMaterials.take(50).map((m) {
                  final name = (m['name'] ?? '').toString();
                  final type = (m['type_name'] ?? '').toString();
                  final unit = (m['unit_code'] ?? '').toString();
                  final qty = _toDouble(m['current_qty']);
                  final min = _toDouble(m['min_stock']);
                  return ListTile(
                    leading: const Icon(Icons.warning_amber_rounded),
                    title: Text(name),
                    subtitle: Text(
                      '$type • ${qty.toStringAsFixed(2)} $unit (min ${min.toStringAsFixed(2)})',
                    ),
                  );
                }),
                const SizedBox(height: 12),
              ],
              if (outOfStockProducts.isNotEmpty) ...[
                Text(
                  'Produse fara stoc (${outOfStockProducts.length})',
                  style: Theme.of(ctx).textTheme.titleMedium,
                ),
                const SizedBox(height: 6),
                ...outOfStockProducts.take(50).map((p) {
                  final id = _toInt(p['id']);
                  final name = (p['name'] ?? '').toString();
                  final sku = (p['sku'] ?? '').toString();
                  final stock = _toInt(p['stock_qty']);
                  return ListTile(
                    leading: const Icon(Icons.remove_shopping_cart_outlined),
                    title: Text(name),
                    subtitle: Text('SKU $sku • Stoc $stock'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: id < 1
                        ? null
                        : () async {
                            Navigator.of(ctx).pop();
                            await Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => ProductPage(productId: id),
                              ),
                            );
                          },
                  );
                }),
                const SizedBox(height: 12),
              ],
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

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
                  final msg = err is ApiException
                      ? err.message
                      : err.toString();
                  return Center(child: Text('Eroare: $msg'));
                }
                final data = snapshot.data ?? {};
                final matsBody = data['materials'] as Map<String, dynamic>;
                final prodsBody = data['products'] as Map<String, dynamic>;
                final mats =
                    (matsBody['data'] as Map<String, dynamic>)['materials']
                        as List<dynamic>;
                final prods =
                    (prodsBody['data'] as Map<String, dynamic>)['products']
                        as List<dynamic>;

                final criticalMaterials =
                    mats.whereType<Map<String, dynamic>>().where((m) {
                      final min = _toDouble(m['min_stock']);
                      if (min <= 0) return false;
                      final qty = _toDouble(m['current_qty']);
                      return qty <= min;
                    }).toList()..sort((a, b) {
                      final aMin = _toDouble(a['min_stock']);
                      final bMin = _toDouble(b['min_stock']);
                      final aDelta = _toDouble(a['current_qty']) - aMin;
                      final bDelta = _toDouble(b['current_qty']) - bMin;
                      final c = aDelta.compareTo(bDelta);
                      if (c != 0) return c;
                      return aMin.compareTo(bMin);
                    });

                final outOfStockProducts =
                    prods
                        .whereType<Map<String, dynamic>>()
                        .where((p) => _toInt(p['stock_qty']) <= 0)
                        .toList()
                      ..sort(
                        (a, b) => _toInt(
                          a['stock_qty'],
                        ).compareTo(_toInt(b['stock_qty'])),
                      );

                return ListView(
                  children: [
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Icon(
                                  (criticalMaterials.isNotEmpty ||
                                          outOfStockProducts.isNotEmpty)
                                      ? Icons.warning_amber_rounded
                                      : Icons.check_circle_outline,
                                  color:
                                      (criticalMaterials.isNotEmpty ||
                                          outOfStockProducts.isNotEmpty)
                                      ? cs.error
                                      : cs.primary,
                                ),
                                const SizedBox(width: 8),
                                const Expanded(
                                  child: Text(
                                    'Stocuri critice',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                                TextButton(
                                  onPressed: () => _showCriticalStock(
                                    context,
                                    criticalMaterials: criticalMaterials,
                                    outOfStockProducts: outOfStockProducts,
                                  ),
                                  child: const Text('Detalii'),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                Chip(
                                  label: Text(
                                    'Materiale: ${criticalMaterials.length}',
                                  ),
                                  backgroundColor: cs.errorContainer,
                                  labelStyle: TextStyle(
                                    color: cs.onErrorContainer,
                                  ),
                                ),
                                Chip(
                                  label: Text(
                                    'Produse fara stoc: ${outOfStockProducts.length}',
                                  ),
                                  backgroundColor: cs.secondaryContainer,
                                  labelStyle: TextStyle(
                                    color: cs.onSecondaryContainer,
                                  ),
                                ),
                              ],
                            ),
                            if (criticalMaterials.isEmpty &&
                                outOfStockProducts.isEmpty) ...[
                              const SizedBox(height: 10),
                              const Text('Totul este OK.'),
                            ],
                            if (criticalMaterials.isNotEmpty) ...[
                              const SizedBox(height: 10),
                              ...criticalMaterials.take(2).map((m) {
                                final name = (m['name'] ?? '').toString();
                                final unit = (m['unit_code'] ?? '').toString();
                                final qty = _toDouble(m['current_qty']);
                                final min = _toDouble(m['min_stock']);
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 6),
                                  child: Text(
                                    '• $name: ${qty.toStringAsFixed(2)} $unit (min ${min.toStringAsFixed(2)})',
                                  ),
                                );
                              }),
                            ],
                            if (outOfStockProducts.isNotEmpty) ...[
                              const SizedBox(height: 6),
                              ...outOfStockProducts.take(2).map((p) {
                                final name = (p['name'] ?? '').toString();
                                final sku = (p['sku'] ?? '').toString();
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 6),
                                  child: Text('• $name (SKU $sku): stoc 0'),
                                );
                              }),
                            ],
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
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
                                    MaterialPageRoute(
                                      builder: (_) =>
                                          ProductPage(productId: id),
                                    ),
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
