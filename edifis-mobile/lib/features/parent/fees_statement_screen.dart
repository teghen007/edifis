import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/services/parent_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';

class FeesStatementScreen extends ConsumerWidget {
  final String studentId, studentName;
  const FeesStatementScreen({super.key, required this.studentId, required this.studentName});

  String _money(int v) {
    final s = v.abs().toString();
    final b = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) b.write(',');
      b.write(s[i]);
    }
    return '${v < 0 ? '−' : ''}${b.toString()} XAF';
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final fees = ref.watch(childFeesProvider(studentId));

    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white,
        title: Text(studentName.isEmpty ? 'Fee Statement' : studentName)),
      body: fees.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, __) => const Center(child: Text('Could not load fee statement.', style: TextStyle(color: AppColors.muted))),
        data: (d) {
          final balance = (d['balance'] is int) ? d['balance'] as int : int.tryParse('${d['balance'] ?? 0}') ?? 0;
          final items = (d['items'] ?? []) as List;
          return SingleChildScrollView(padding: const EdgeInsets.all(16), child: Column(children: [
            GlassCard(child: Column(children: [
              const Text('Balance', style: TextStyle(fontSize: 12, color: AppColors.muted, letterSpacing: .5)),
              const SizedBox(height: 6),
              Text(_money(balance),
                style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold,
                  color: balance > 0 ? AppColors.danger : AppColors.success)),
              const SizedBox(height: 4),
              Text(balance > 0 ? 'Amount owed to the school' : 'No outstanding balance',
                style: const TextStyle(fontSize: 12, color: AppColors.muted)),
            ])),
            const SizedBox(height: 16),
            if (items.isEmpty)
              const Padding(padding: EdgeInsets.all(24),
                child: Text('No fee activity yet.', style: TextStyle(color: AppColors.muted)))
            else
              ...items.map((m) {
                final amount = (m['amount'] is int) ? m['amount'] as int : int.tryParse('${m['amount'] ?? 0}') ?? 0;
                final isCharge = amount >= 0;
                return Padding(padding: const EdgeInsets.only(bottom: 8),
                  child: GlassCard(padding: const EdgeInsets.all(14), child: Row(children: [
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${m['label'] ?? 'Item'}', style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink)),
                      if (m['date'] != null) Text('${m['date']}', style: const TextStyle(fontSize: 12, color: AppColors.muted)),
                    ])),
                    Text(_money(amount),
                      style: TextStyle(fontWeight: FontWeight.bold,
                        color: isCharge ? AppColors.danger : AppColors.success)),
                  ])));
              }),
          ]));
        },
      ),
    );
  }
}
