import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import '../../core/services/fees_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';

class FeesOverviewScreen extends ConsumerWidget {
  const FeesOverviewScreen({super.key});

  String _money(int v) {
    final s = v.abs().toString();
    final b = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) b.write(',');
      b.write(s[i]);
    }
    return b.toString();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ov = ref.watch(feesOverviewProvider);
    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white,
        title: const Text('Fees Overview')),
      body: ov.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, __) => const Center(child: Text('Could not load fees overview.', style: TextStyle(color: AppColors.muted))),
        data: (d) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(feesOverviewProvider),
          child: ListView(padding: const EdgeInsets.all(16), children: [
            // Stat tiles
            Row(children: [
              Expanded(child: _stat('Outstanding', '${_money(d.outstanding)} XAF', LucideIcons.wallet, AppColors.danger)),
              const SizedBox(width: 12),
              Expanded(child: _stat('Collected', '${_money(d.collected)} XAF', LucideIcons.banknote, AppColors.success)),
            ]),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: _stat('Total billed', '${_money(d.charged)} XAF', LucideIcons.receipt, AppColors.blue600)),
              const SizedBox(width: 12),
              Expanded(child: _stat('Students owing', '${d.debtorsCount}', LucideIcons.users, AppColors.gold)),
            ]),
            const SizedBox(height: 16),

            // Donut: collected vs outstanding
            GlassCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Collected vs Outstanding', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.ink)),
              const SizedBox(height: 12),
              SizedBox(height: 180, child: Row(children: [
                Expanded(child: (d.collected + d.outstanding) == 0
                  ? const Center(child: Text('No fee activity yet', style: TextStyle(color: AppColors.muted)))
                  : PieChart(PieChartData(
                      sectionsSpace: 3, centerSpaceRadius: 46,
                      sections: [
                        PieChartSectionData(value: d.collected.toDouble(), color: AppColors.blue300, radius: 46, showTitle: false),
                        PieChartSectionData(value: d.outstanding.toDouble(), color: AppColors.blue800, radius: 46, showTitle: false),
                      ]))),
                Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
                  _legend(AppColors.blue300, 'Collected', '${_money(d.collected)} XAF'),
                  const SizedBox(height: 12),
                  _legend(AppColors.blue800, 'Outstanding', '${_money(d.outstanding)} XAF'),
                ]),
              ])),
            ])),
            const SizedBox(height: 16),

            // Top debtors as horizontal bars
            GlassCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Top outstanding balances', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.ink)),
              const SizedBox(height: 14),
              if (d.topDebtors.isEmpty)
                const Padding(padding: EdgeInsets.symmetric(vertical: 12), child: Text('No outstanding balances 🎉', style: TextStyle(color: AppColors.muted)))
              else
                ...d.topDebtors.map((dr) => _bar(dr.name, dr.balance, d.topDebtors.first.balance)),
            ])),
          ]),
        ),
      ),
    );
  }

  Widget _stat(String label, String value, IconData icon, Color color) => GlassCard(
    padding: const EdgeInsets.all(14),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 38, height: 38,
        decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(11)),
        child: Icon(icon, color: color, size: 20)),
      const SizedBox(height: 10),
      Text(value, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: AppColors.ink)),
      Text(label, style: const TextStyle(fontSize: 12, color: AppColors.muted)),
    ]));

  Widget _legend(Color c, String label, String value) => Row(mainAxisSize: MainAxisSize.min, children: [
    Container(width: 12, height: 12, decoration: BoxDecoration(color: c, borderRadius: BorderRadius.circular(3))),
    const SizedBox(width: 8),
    Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: const TextStyle(fontSize: 11.5, color: AppColors.muted)),
      Text(value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.ink)),
    ]),
  ]);

  Widget _bar(String name, int value, int max) {
    final frac = max > 0 ? (value / max).clamp(0.0, 1.0) : 0.0;
    return Padding(padding: const EdgeInsets.only(bottom: 12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Expanded(child: Text(name, style: const TextStyle(fontSize: 13, color: AppColors.ink), overflow: TextOverflow.ellipsis)),
        Text('${_money(value)} XAF', style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.bold, color: AppColors.blue700)),
      ]),
      const SizedBox(height: 5),
      ClipRRect(borderRadius: BorderRadius.circular(6), child: LinearProgressIndicator(
        value: frac, minHeight: 9, backgroundColor: AppColors.blue50,
        valueColor: const AlwaysStoppedAnimation(AppColors.blue600))),
    ]));
  }
}
