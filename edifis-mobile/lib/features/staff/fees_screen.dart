import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import '../../core/services/fees_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';

class FeesScreen extends ConsumerStatefulWidget {
  const FeesScreen({super.key});
  @override
  ConsumerState<FeesScreen> createState() => _FeesScreenState();
}

class _FeesScreenState extends ConsumerState<FeesScreen> {
  String _q = '';
  final _fmt = NumberFormat('#,###', 'en');

  @override
  Widget build(BuildContext context) {
    final balances = ref.watch(balancesProvider);
    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white, title: const Text('Fees')),
      body: balances.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(LucideIcons.wifiOff, size: 40, color: AppColors.muted),
          const SizedBox(height: 12),
          const Text("Couldn't load fees.", style: TextStyle(color: AppColors.muted)),
          const SizedBox(height: 12),
          FilledButton(onPressed: () => ref.invalidate(balancesProvider), child: const Text('Retry')),
        ])),
        data: (all) {
          final totalOut = all.fold<int>(0, (s, b) => s + (b.balance > 0 ? b.balance : 0));
          final owing = all.where((b) => b.balance > 0).length;
          final list = _q.isEmpty ? all
            : all.where((b) => b.name.toLowerCase().contains(_q.toLowerCase())).toList();
          return RefreshIndicator(
            onRefresh: () async { ref.invalidate(balancesProvider); await ref.read(balancesProvider.future); },
            child: ListView(children: [
              Container(
                decoration: const BoxDecoration(gradient: AppGradients.hero),
                padding: const EdgeInsets.fromLTRB(20, 40, 20, 24),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('Total outstanding', style: TextStyle(color: AppColors.blue200, fontSize: 13)),
                  const SizedBox(height: 4),
                  Text('${_fmt.format(totalOut)} XAF',
                    style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 2),
                  Text('$owing of ${all.length} students owing', style: const TextStyle(color: AppColors.blue100, fontSize: 13)),
                ]),
              ),
              Padding(padding: const EdgeInsets.all(16), child: TextField(
                onChanged: (v) => setState(() => _q = v),
                decoration: const InputDecoration(hintText: 'Search students', prefixIcon: Icon(LucideIcons.search)))),
              ...list.map((b) => Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
                child: GlassCard(padding: const EdgeInsets.all(14), child: Row(children: [
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(b.name, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink)),
                    Text(b.className.isEmpty ? '—' : b.className, style: const TextStyle(color: AppColors.muted, fontSize: 12.5)),
                  ])),
                  b.balance > 0
                    ? Text('${_fmt.format(b.balance)} ${b.currency}',
                        style: const TextStyle(color: AppColors.danger, fontWeight: FontWeight.bold))
                    : const Text('Cleared', style: TextStyle(color: AppColors.success, fontWeight: FontWeight.w600)),
                ]))),
              ),
              const SizedBox(height: 20),
            ]),
          );
        },
      ),
    );
  }
}
