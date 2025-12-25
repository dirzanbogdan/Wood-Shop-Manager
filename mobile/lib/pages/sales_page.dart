import 'package:flutter/material.dart';

import '../services/api_client.dart';

class SalesPage extends StatefulWidget {
  const SalesPage({super.key});

  @override
  State<SalesPage> createState() => _SalesPageState();
}

class _SalesPageState extends State<SalesPage> {
  List<Map<String, dynamic>> _products = const [];
  int? _productId;

  final _qtyCtrl = TextEditingController(text: '1');
  final _priceCtrl = TextEditingController();
  final _customerCtrl = TextEditingController();
  final _channelCtrl = TextEditingController();

  bool _loading = false;
  String? _loadError;

  @override
  void initState() {
    super.initState();
    _loadProducts();
  }

  @override
  void dispose() {
    _qtyCtrl.dispose();
    _priceCtrl.dispose();
    _customerCtrl.dispose();
    _channelCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadProducts() async {
    try {
      final res = await ApiClient.instance.get('api/v1Products', query: {'limit': '200', 'offset': '0'});
      final data = res['data'];
      final list = (data is Map<String, dynamic>) ? data['products'] : null;
      if (list is List) {
        setState(() {
          _products = list.cast<Map<String, dynamic>>();
          _productId = _products.isNotEmpty ? apiInt(_products.first['id'], fallback: 0) : null;
          _loadError = null;
        });
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _loadError = e.message);
    } catch (e) {
      if (!mounted) return;
      setState(() => _loadError = e.toString());
    }
  }

  Future<void> _submit() async {
    final pid = _productId ?? 0;
    final qty = int.tryParse(_qtyCtrl.text.trim()) ?? 0;
    final price = double.tryParse(_priceCtrl.text.trim().replaceAll(',', '.')) ?? 0;
    if (pid < 1 || qty < 1 || price <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Date invalide.')));
      return;
    }

    setState(() => _loading = true);
    try {
      await ApiClient.instance.post('api/v1SalesCreate', {
        'product_id': pid,
        'qty': qty,
        'sale_price': price,
        if (_customerCtrl.text.trim().isNotEmpty) 'customer_name': _customerCtrl.text.trim(),
        if (_channelCtrl.text.trim().isNotEmpty) 'channel': _channelCtrl.text.trim(),
      });
      if (!mounted) return;
      _qtyCtrl.text = '1';
      _priceCtrl.clear();
      _customerCtrl.clear();
      _channelCtrl.clear();
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vanzare salvata.')));
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        children: [
          if (_loadError != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(_loadError!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
          InputDecorator(
            decoration: const InputDecoration(labelText: 'Produs'),
            child: DropdownButtonHideUnderline(
              child: DropdownButton<int>(
                isExpanded: true,
                value: _productId,
                items: _products
                    .map(
                      (p) => DropdownMenuItem<int>(
                        value: apiInt(p['id'], fallback: 0),
                        child: Text((p['name'] ?? '').toString()),
                      ),
                    )
                    .toList(),
                onChanged: _loading ? null : (v) => setState(() => _productId = v),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _qtyCtrl,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Qty'),
                  enabled: !_loading,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: _priceCtrl,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'Pret'),
                  enabled: !_loading,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _customerCtrl,
            decoration: const InputDecoration(labelText: 'Client (optional)'),
            enabled: !_loading,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _channelCtrl,
            decoration: const InputDecoration(labelText: 'Canal (optional)'),
            enabled: !_loading,
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: _loading ? null : _submit,
              child: _loading
                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Salveaza vanzare'),
            ),
          ),
        ],
      ),
    );
  }
}
