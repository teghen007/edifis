import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import '../../core/services/timetable_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';

const _days = {'1':'Monday','2':'Tuesday','3':'Wednesday','4':'Thursday','5':'Friday','6':'Saturday','7':'Sunday'};

class TimetableScreen extends ConsumerWidget {
  const TimetableScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tt = ref.watch(timetableProvider);
    return DefaultTabController(length: 2, child: Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white,
        title: const Text('Timetable'),
        bottom: const TabBar(indicatorColor: Colors.white, labelColor: Colors.white,
          unselectedLabelColor: Colors.white70, tabs: [Tab(text: 'Approvals'), Tab(text: 'Week')])),
      body: tt.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(LucideIcons.wifiOff, size: 40, color: AppColors.muted),
          const SizedBox(height: 12), const Text("Couldn't load the timetable.", style: TextStyle(color: AppColors.muted)),
          const SizedBox(height: 12),
          FilledButton(onPressed: () => ref.invalidate(timetableProvider), child: const Text('Retry'))])),
        data: (all) => TabBarView(children: [
          _approvals(context, ref, all.where((e) => !e.isApproved).toList()),
          _week(all),
        ]),
      ),
    ));
  }

  Widget _approvals(BuildContext context, WidgetRef ref, List<TtEntry> pending) {
    if (pending.isEmpty) {
      return const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(LucideIcons.circleCheck, size: 44, color: AppColors.success),
        SizedBox(height: 10), Text('All caught up — nothing to approve.', style: TextStyle(color: AppColors.muted))]));
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16), itemCount: pending.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (c, i) {
        final e = pending[i];
        return GlassCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${e.className} · ${e.subjectName}', style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.ink)),
          const SizedBox(height: 2),
          Text('${_days[e.dayOfWeek] ?? e.dayOfWeek} · ${e.periodStart}–${e.periodEnd}${e.room.isNotEmpty ? ' · ${e.room}' : ''}',
            style: const TextStyle(color: AppColors.muted, fontSize: 13)),
          Text(e.teacherName, style: const TextStyle(color: AppColors.muted, fontSize: 13)),
          const SizedBox(height: 12),
          Align(alignment: Alignment.centerRight, child: FilledButton.icon(
            icon: const Icon(LucideIcons.check, size: 18),
            label: const Text('Approve'),
            onPressed: () async {
              try {
                await ref.read(timetableApiProvider).approve(e.id);
                ref.invalidate(timetableProvider);
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Approved'), backgroundColor: AppColors.success));
                }
              } catch (_) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text("Couldn't approve. Try again."), backgroundColor: AppColors.danger));
                }
              }
            })),
        ]));
      });
  }

  Widget _week(List<TtEntry> all) {
    final sorted = [...all]..sort((a, b) => (a.dayOfWeek + a.periodStart).compareTo(b.dayOfWeek + b.periodStart));
    final byDay = <String, List<TtEntry>>{};
    for (final e in sorted) { byDay.putIfAbsent(e.dayOfWeek, () => []).add(e); }
    final keys = byDay.keys.toList()..sort();
    return ListView(padding: const EdgeInsets.all(16), children: [
      for (final d in keys) ...[
        Padding(padding: const EdgeInsets.fromLTRB(4, 8, 4, 8),
          child: Text(_days[d] ?? 'Day $d', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppColors.blue800))),
        for (final e in byDay[d]!) Padding(padding: const EdgeInsets.only(bottom: 8),
          child: GlassCard(padding: const EdgeInsets.all(12), child: Row(children: [
            Container(width: 4, height: 38, decoration: BoxDecoration(
              color: e.isApproved ? AppColors.success : AppColors.warning, borderRadius: BorderRadius.circular(2))),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${e.subjectName} · ${e.className}', style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink)),
              Text('${e.periodStart}–${e.periodEnd} · ${e.teacherName}', style: const TextStyle(color: AppColors.muted, fontSize: 12.5)),
            ])),
            if (!e.isApproved) const Text('pending', style: TextStyle(color: AppColors.warning, fontSize: 11, fontWeight: FontWeight.w600)),
          ]))),
      ],
    ]);
  }
}
