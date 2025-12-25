import 'package:flutter/material.dart';

import '../services/api_client.dart';
import 'production_start_page.dart';

class ProductionPage extends StatefulWidget {
  const ProductionPage({super.key});

  @override
  State<ProductionPage> createState() => _ProductionPageState();
}

class _ProductionPageState extends State<ProductionPage> {
  Future<List<Map<String, dynamic>>> _load() async {
    final res = await ApiClient.instance.get('api/v1ProductionOrders', query: {'limit': '100', 'offset': '0'});
    final data = res['data'];
    final orders = (data is Map<String, dynamic>) ? data['orders'] : null;
    if (orders is List) {
      return orders.cast<Map<String, dynamic>>();
    }
    return [];
  }

  Future<void> _finalize(int id) async {
    try {
      await ApiClient.instance.post('api/v1ProductionFinalize', {'production_order_id': id});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Finalizat.')));
      setState(() {});
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async => setState(() {}),
        child: FutureBuilder(
          future: _load(),
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return const Center(child: Text('Eroare la incarcare.'));
            }
            final items = snapshot.data ?? const <Map<String, dynamic>>[];
            if (items.isEmpty) {
              return ListView(
                children: const [
                  SizedBox(height: 120),
                  Center(child: Text('Nu exista comenzi.')),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(8),
              itemCount: items.length,
              separatorBuilder: (context, index) => const Divider(height: 1),
              itemBuilder: (context, idx) {
                final o = items[idx];
                final id = (o['id'] as num?)?.toInt() ?? 0;
                final status = (o['status'] ?? '').toString();
                final title = (o['product_name'] ?? '').toString();
                final qty = (o['qty'] ?? '').toString();
                final started = (o['started_at'] ?? '').toString();
                final canFinalize = status == 'Pornita' && id > 0;

                return ListTile(
                  title: Text(title),
                  subtitle: Text('Qty $qty • $status • $started'),
                  trailing: canFinalize ? const Icon(Icons.check_circle_outline) : null,
                  onTap: canFinalize
                      ? () => showDialog(
                            context: context,
                            builder: (_) => AlertDialog(
                              title: const Text('Finalizeaza comanda?'),
                              content: Text('ID $id'),
                              actions: [
                                TextButton(onPressed: () => Navigator.pop(context), child: const Text('Nu')),
                                FilledButton(
                                  onPressed: () {
                                    Navigator.pop(context);
                                    _finalize(id);
                                  },
                                  child: const Text('Da'),
                                ),
                              ],
                            ),
                          )
                      : null,
                );
              },
            );
          },
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ProductionStartPage()));
          if (!mounted) return;
          setState(() {});
        },
        icon: const Icon(Icons.play_arrow),
        label: const Text('Start'),
      ),
    );
  }
}
