import 'package:flutter/material.dart';

import '../services/api_client.dart';

class ProductPage extends StatefulWidget {
  const ProductPage({super.key, required this.productId});

  final int productId;

  @override
  State<ProductPage> createState() => _ProductPageState();
}

class _ProductPageState extends State<ProductPage> {
  Future<Map<String, dynamic>> _load() async {
    final res = await ApiClient.instance.get('api/v1Bom', query: {'product_id': widget.productId.toString()});
    final data = res['data'];
    if (data is! Map<String, dynamic>) {
      throw ApiException('Invalid response');
    }
    return data;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Produs')),
      body: FutureBuilder(
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
          final product = (data['product'] as Map<String, dynamic>?);
          final materials = (data['materials'] as List<dynamic>? ?? const []);
          final machines = (data['machines'] as List<dynamic>? ?? const []);

          return ListView(
            padding: const EdgeInsets.all(12),
            children: [
              if (product != null)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text((product['name'] ?? '').toString(), style: Theme.of(context).textTheme.titleLarge),
                        const SizedBox(height: 6),
                        Text('SKU: ${(product['sku'] ?? '').toString()}'),
                        Text('Manopera/uc: ${(product['manpower_hours'] ?? '').toString()}'),
                      ],
                    ),
                  ),
                ),
              const SizedBox(height: 12),
              Text('BOM - Materiale', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 6),
              ...materials.map((m) {
                final mm = m as Map<String, dynamic>;
                return ListTile(
                  title: Text((mm['material_name'] ?? '').toString()),
                  subtitle: Text('${(mm['qty'] ?? '').toString()} ${(mm['unit_code'] ?? '').toString()}'),
                );
              }),
              const SizedBox(height: 12),
              Text('BOM - Utilaje', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 6),
              ...machines.map((mc) {
                final m = mc as Map<String, dynamic>;
                return ListTile(
                  title: Text((m['machine_name'] ?? '').toString()),
                  subtitle: Text('Ore: ${(m['hours'] ?? '').toString()} • kW: ${(m['power_kw'] ?? '').toString()}'),
                );
              }),
            ],
          );
        },
      ),
    );
  }
}
