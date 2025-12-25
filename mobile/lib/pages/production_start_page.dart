import 'package:flutter/material.dart';

import '../services/api_client.dart';

class ProductionStartPage extends StatefulWidget {
  const ProductionStartPage({super.key});

  @override
  State<ProductionStartPage> createState() => _ProductionStartPageState();
}

class _ProductionStartPageState extends State<ProductionStartPage> {
  List<Map<String, dynamic>> _products = const [];
  int? _productId;

  final _qtyCtrl = TextEditingController(text: '1');
  final _notesCtrl = TextEditingController();
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
    _notesCtrl.dispose();
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
          _productId = _products.isNotEmpty ? (_products.first['id'] as num?)?.toInt() : null;
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
    if (pid < 1 || qty < 1) return;

    setState(() => _loading = true);
    try {
      await ApiClient.instance.post('api/v1ProductionStart', {
        'product_id': pid,
        'qty': qty,
        if (_notesCtrl.text.trim().isNotEmpty) 'notes': _notesCtrl.text.trim(),
      });
      if (!mounted) return;
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Start productie')),
      body: Padding(
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
                          value: (p['id'] as num?)?.toInt(),
                          child: Text((p['name'] ?? '').toString()),
                        ),
                      )
                      .toList(),
                  onChanged: _loading ? null : (v) => setState(() => _productId = v),
                ),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _qtyCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Cantitate'),
              enabled: !_loading,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _notesCtrl,
              decoration: const InputDecoration(labelText: 'Note'),
              enabled: !_loading,
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: _loading ? null : _submit,
                child: _loading
                    ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Text('Start'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
