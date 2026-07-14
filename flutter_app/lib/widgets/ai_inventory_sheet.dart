import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../repositories/ai_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import 'hero_icon.dart';

/// AI Generate Inventory sheet — describe → AI suggests items → select → bulk
/// create. Mirrors mobile/views/inventory.php openAIGenerateInventory(): posts
/// to api/ai.php generate_inventory then create_inventory_items. Pops the number
/// of items created so the caller can refresh the list + toast.
class AiInventorySheet extends ConsumerStatefulWidget {
  const AiInventorySheet({super.key});

  @override
  ConsumerState<AiInventorySheet> createState() => _AiInventorySheetState();
}

class _AiInventorySheetState extends ConsumerState<AiInventorySheet> {
  static const _categories = [
    'General',
    'Office Supplies',
    'Electronics',
    'Furniture',
    'Raw Materials',
    'Packaging',
    'Maintenance',
    'Groceries',
  ];

  final _desc = TextEditingController();
  String _category = 'General';
  bool _generating = false;
  bool _saving = false;
  String? _error;
  List<Map<String, dynamic>> _items = const [];
  final Set<int> _selected = {};

  @override
  void dispose() {
    _desc.dispose();
    super.dispose();
  }

  Future<void> _generate() async {
    final desc = _desc.text.trim();
    if (desc.isEmpty) {
      setState(() => _error = 'Enter a description first');
      return;
    }
    setState(() {
      _generating = true;
      _error = null;
    });
    try {
      final items = await ref
          .read(aiRepositoryProvider)
          .generateInventory(description: desc, category: _category);
      if (!mounted) return;
      setState(() {
        _items = items;
        _selected
          ..clear()
          ..addAll(List.generate(items.length, (i) => i));
        _generating = false;
        if (items.isEmpty) _error = 'The AI returned no items — try again';
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _generating = false;
        _error = _clean(e);
      });
    }
  }

  Future<void> _save() async {
    final chosen = <Map<String, dynamic>>[
      for (var i = 0; i < _items.length; i++)
        if (_selected.contains(i)) _items[i],
    ];
    if (chosen.isEmpty) return;
    setState(() => _saving = true);
    try {
      final created =
          await ref.read(aiRepositoryProvider).createInventoryItems(chosen);
      if (!mounted) return;
      Navigator.of(context).pop(created);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = _clean(e);
      });
    }
  }

  String _clean(Object e) {
    final s = e.toString().replaceFirst('Exception: ', '').trim();
    return s.isEmpty ? 'Something went wrong' : s;
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    final hasResults = _items.isNotEmpty;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: SafeArea(
        child: ConstrainedBox(
          constraints: BoxConstraints(
              maxHeight: MediaQuery.of(context).size.height * 0.82),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 18, 12, 8),
                child: Row(
                  children: [
                    const HeroIcon(HeroPaths.sparkles, size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        hasResults
                            ? 'Review suggestions'
                            : 'AI Generate Inventory',
                        style: TextStyle(
                            fontFamily: 'Geist',
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                            color: p.textPrimary),
                      ),
                    ),
                    IconButton(
                      icon: const HeroIcon(HeroPaths.xMark, size: 20),
                      onPressed: () => Navigator.of(context).pop(),
                    ),
                  ],
                ),
              ),
              Divider(height: 1, color: p.border),
              Flexible(child: hasResults ? _results(p) : _inputForm(p)),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
                  child: Text(_error!,
                      style: const TextStyle(
                          fontSize: 12, color: Color(0xFFDC2626))),
                ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
                child: SizedBox(
                  width: double.infinity,
                  child: hasResults
                      ? FilledButton(
                          onPressed:
                              (_saving || _selected.isEmpty) ? null : _save,
                          child: Text(_saving
                              ? 'Saving…'
                              : 'Add ${_selected.length} to inventory'),
                        )
                      : FilledButton.icon(
                          onPressed: _generating ? null : _generate,
                          icon: _generating
                              ? const SizedBox(
                                  width: 16,
                                  height: 16,
                                  child: CircularProgressIndicator(
                                      strokeWidth: 2))
                              : const HeroIcon(HeroPaths.sparkles, size: 16),
                          label:
                              Text(_generating ? 'Generating…' : 'Generate'),
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _inputForm(AppPalette p) {
    return ListView(
      shrinkWrap: true,
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
      children: [
        Text('DESCRIBE WHAT YOU NEED',
            style: AppType.label(context, color: p.textMuted)),
        const SizedBox(height: 8),
        TextField(
          controller: _desc,
          maxLines: 3,
          autofocus: true,
          textCapitalization: TextCapitalization.sentences,
          decoration: InputDecoration(
            hintText: 'e.g. office supplies for a 20-person team',
            border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.border)),
            enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.border)),
          ),
        ),
        const SizedBox(height: 16),
        Text('CATEGORY', style: AppType.label(context, color: p.textMuted)),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final c in _categories)
              GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => setState(() => _category = c),
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: _category == c ? p.ink : Colors.transparent,
                    border:
                        Border.all(color: _category == c ? p.ink : p.border),
                    borderRadius: BorderRadius.circular(AppRadii.pill),
                  ),
                  child: Text(c,
                      style: TextStyle(
                          fontFamily: 'Geist',
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: _category == c ? p.onInk : p.textPrimary)),
                ),
              ),
          ],
        ),
      ],
    );
  }

  Widget _results(AppPalette p) {
    final allSelected = _selected.length == _items.length;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 4),
          child: Row(
            children: [
              Expanded(
                child: Text('${_items.length} SUGGESTED',
                    style: AppType.label(context, color: p.textMuted)),
              ),
              GestureDetector(
                onTap: () => setState(() {
                  if (allSelected) {
                    _selected.clear();
                  } else {
                    _selected
                      ..clear()
                      ..addAll(List.generate(_items.length, (i) => i));
                  }
                }),
                child: Text(allSelected ? 'Clear all' : 'Select all',
                    style: TextStyle(
                        fontFamily: 'Geist',
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: p.ink)),
              ),
            ],
          ),
        ),
        Flexible(
          child: ListView.builder(
            shrinkWrap: true,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: _items.length,
            itemBuilder: (_, i) => _SuggestTile(
              item: _items[i],
              selected: _selected.contains(i),
              onToggle: () => setState(() {
                if (!_selected.remove(i)) _selected.add(i);
              }),
            ),
          ),
        ),
      ],
    );
  }
}

class _SuggestTile extends StatelessWidget {
  const _SuggestTile(
      {required this.item, required this.selected, required this.onToggle});
  final Map<String, dynamic> item;
  final bool selected;
  final VoidCallback onToggle;

  double _num(dynamic v) {
    if (v is num) return v.toDouble();
    return double.tryParse(v?.toString() ?? '') ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final name = (item['name'] ?? 'Item').toString();
    final category = (item['category'] ?? '').toString();
    final qty = _num(item['quantity']).toInt();
    final price = _num(item['price']);
    final desc = (item['description'] ?? '').toString();
    final currency = NumberFormat.simpleCurrency();
    final metaParts = <String>[
      if (category.isNotEmpty) category.toUpperCase(),
      'QTY $qty',
      currency.format(price),
    ];

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onToggle,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: selected ? p.ink.withValues(alpha: 0.05) : p.surface,
          border: Border.all(color: selected ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 20,
              height: 20,
              margin: const EdgeInsets.only(top: 2),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: selected ? p.ink : Colors.transparent,
                border: Border.all(color: selected ? p.ink : p.border),
                borderRadius: BorderRadius.circular(6),
              ),
              child: selected
                  ? HeroIcon(HeroPaths.check, size: 13, color: p.onInk)
                  : null,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name,
                      style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: p.textPrimary)),
                  const SizedBox(height: 3),
                  Text(metaParts.join(' · '),
                      style: TextStyle(
                          fontFamily: 'Geist',
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.5,
                          color: p.textFaint)),
                  if (desc.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(desc,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 12, color: p.textMuted)),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
