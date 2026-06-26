import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import '../../core/services/results_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';

class PerformanceScreen extends ConsumerWidget {
  const PerformanceScreen({super.key});

  static const _gradeColors = {
    'A': AppColors.blue800, 'B': AppColors.blue600, 'C': AppColors.blue400,
    'D': AppColors.gold, 'E': AppColors.warning, 'F': AppColors.danger,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ov = ref.watch(performanceOverviewProvider);
    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white,
        title: const Text('Performance')),
      body: ov.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, __) => const Center(child: Text('Could not load performance.', style: TextStyle(color: AppColors.muted))),
        data: (d) {
          if (!d.hasData) {
            return const Center(child: Padding(padding: EdgeInsets.all(24),
              child: Text('No results computed yet. Compute a term first.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted))));
          }
          final maxGrade = (d.gradeDist.values.isEmpty ? 1 : d.gradeDist.values.reduce((a, b) => a > b ? a : b)).toDouble();
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(performanceOverviewProvider),
            child: ListView(padding: const EdgeInsets.all(16), children: [
              Text(d.termName, style: const TextStyle(color: AppColors.muted, fontSize: 13)),
              const SizedBox(height: 10),
              Row(children: [
                Expanded(child: _stat('School average', '${d.schoolAverage} / 20', LucideIcons.award, AppColors.blue600)),
                const SizedBox(width: 12),
                Expanded(child: _stat('Pass rate', '${d.passRate}%', LucideIcons.trendingUp, d.passRate >= 50 ? AppColors.success : AppColors.danger)),
              ]),
              const SizedBox(height: 12),
              _stat('Students ranked', '${d.studentsRanked}', LucideIcons.users, AppColors.gold),
              const SizedBox(height: 16),

              // Grade distribution bar chart
              GlassCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Grade distribution', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.ink)),
                const SizedBox(height: 16),
                SizedBox(height: 170, child: BarChart(BarChartData(
                  alignment: BarChartAlignment.spaceAround,
                  maxY: maxGrade + 1,
                  borderData: FlBorderData(show: false),
                  gridData: const FlGridData(show: false),
                  titlesData: FlTitlesData(
                    leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: true, getTitlesWidget: (v, _) {
                      final keys = d.gradeDist.keys.toList();
                      final i = v.toInt();
                      return Padding(padding: const EdgeInsets.only(top: 6),
                        child: Text(i >= 0 && i < keys.length ? keys[i] : '',
                          style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.muted, fontSize: 12)));
                    }))),
                  barGroups: [
                    for (var i = 0; i < d.gradeDist.entries.length; i++)
                      BarChartGroupData(x: i, barRods: [BarChartRodData(
                        toY: d.gradeDist.values.elementAt(i).toDouble(),
                        color: _gradeColors[d.gradeDist.keys.elementAt(i)] ?? AppColors.blue500,
                        width: 22, borderRadius: const BorderRadius.vertical(top: Radius.circular(6)))]),
                  ],
                ))),
              ])),
              const SizedBox(height: 16),

              // Average by class
              GlassCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Average by class', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.ink)),
                const SizedBox(height: 14),
                if (d.byStream.isEmpty) const Text('—', style: TextStyle(color: AppColors.muted))
                else ...d.byStream.map((s) => _bar(s.stream, s.average, 20)),
              ])),
              const SizedBox(height: 16),

              // Top students
              GlassCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Top students', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.ink)),
                const SizedBox(height: 12),
                ...d.topStudents.asMap().entries.map((e) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 7),
                  child: Row(children: [
                    Container(width: 26, height: 26, alignment: Alignment.center,
                      decoration: BoxDecoration(color: AppColors.blue50, borderRadius: BorderRadius.circular(8)),
                      child: Text('${e.key + 1}', style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.blue700, fontSize: 12))),
                    const SizedBox(width: 12),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(e.value.name, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink)),
                      if (e.value.stream.isNotEmpty) Text(e.value.stream, style: const TextStyle(fontSize: 11.5, color: AppColors.muted)),
                    ])),
                    Text('${e.value.average} / 20', style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.blue700)),
                  ]))),
              ])),
            ]),
          );
        },
      ),
    );
  }

  Widget _stat(String label, String value, IconData icon, Color color) => GlassCard(
    padding: const EdgeInsets.all(14),
    child: Row(children: [
      Container(width: 40, height: 40,
        decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(11)),
        child: Icon(icon, color: color, size: 21)),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(value, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: AppColors.ink)),
        Text(label, style: const TextStyle(fontSize: 12, color: AppColors.muted)),
      ])),
    ]));

  Widget _bar(String name, double value, double max) {
    final frac = max > 0 ? (value / max).clamp(0.0, 1.0) : 0.0;
    return Padding(padding: const EdgeInsets.only(bottom: 12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(name, style: const TextStyle(fontSize: 13, color: AppColors.ink)),
        Text('$value / 20', style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.bold, color: AppColors.blue700)),
      ]),
      const SizedBox(height: 5),
      ClipRRect(borderRadius: BorderRadius.circular(6), child: LinearProgressIndicator(
        value: frac, minHeight: 9, backgroundColor: AppColors.blue50,
        valueColor: const AlwaysStoppedAnimation(AppColors.blue600))),
    ]));
  }
}
