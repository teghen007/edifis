import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../../core/auth/auth_state.dart';
import '../../core/services/dashboard_api.dart';
import '../../core/services/parent_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';

class ParentDashboardScreen extends ConsumerStatefulWidget {
  const ParentDashboardScreen({super.key});
  @override
  ConsumerState<ParentDashboardScreen> createState() => _ParentDashboardScreenState();
}

class _ParentDashboardScreenState extends ConsumerState<ParentDashboardScreen> {
  String? _selectedId;

  String _greeting() {
    final h = DateTime.now().hour;
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
  }

  int _balanceValue(dynamic bal) {
    if (bal is Map) {
      return int.tryParse('${bal['balance'] ?? bal['amount'] ?? '0'}') ?? 0;
    }
    return 0;
  }

  String _formatBal(int val) {
    if (val >= 1000000) return '${(val / 1000000).toStringAsFixed(1)}M';
    if (val >= 1000) return '${(val / 1000).toStringAsFixed(0)}K';
    return '$val';
  }

  @override
  Widget build(BuildContext context) {
    final me = ref.watch(meProvider);
    final children = ref.watch(childrenProvider);

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(meProvider);
          ref.invalidate(childrenProvider);
          if (_selectedId != null) {
            ref.invalidate(childResultsProvider(_selectedId!));
            ref.invalidate(childAttendanceProvider(_selectedId!));
            ref.invalidate(childBalanceProvider(_selectedId!));
          }
          await ref.read(childrenProvider.future);
        },
        child: CustomScrollView(slivers: [
          SliverToBoxAdapter(
            child: Container(
              decoration: const BoxDecoration(gradient: AppGradients.hero),
              padding: const EdgeInsets.fromLTRB(20, 60, 20, 28),
              child: Row(children: [
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(_greeting(),
                    style: const TextStyle(color: AppColors.blue200, fontSize: 14)),
                  const SizedBox(height: 2),
                  Text(
                    me.maybeWhen(data: (m) => m.name, orElse: () => 'Parent'),
                    style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold)),
                ]).animate().fadeIn(duration: 400.ms).slideX(begin: -.08, end: 0)),
                IconButton(
                  icon: const Icon(LucideIcons.logOut, color: Colors.white),
                  onPressed: () async {
                    await ref.read(authProvider.notifier).logout();
                    if (context.mounted) context.go('/login');
                  }),
              ]),
            ),
          ),
          children.when(
            loading: () => const SliverFillRemaining(hasScrollBody: false,
              child: Center(child: CircularProgressIndicator())),
            error: (e, _) => SliverFillRemaining(hasScrollBody: false,
              child: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(LucideIcons.wifiOff, size: 40, color: AppColors.muted),
                const SizedBox(height: 12),
                const Text('Couldn\'t load your children.', style: TextStyle(color: AppColors.muted)),
                const SizedBox(height: 12),
                FilledButton(onPressed: () => ref.invalidate(childrenProvider),
                  child: const Text('Retry')),
              ]))),
            data: (kids) {
              if (kids.isEmpty) {
                return const SliverFillRemaining(hasScrollBody: false,
                  child: Center(child: Text('No children linked to your account yet.',
                    style: TextStyle(color: AppColors.muted))));
              }
              _selectedId ??= kids.first['id'] as String;

              final selected = kids.firstWhere((k) => k['id'] == _selectedId, orElse: () => kids.first);
              final results = ref.watch(childResultsProvider(_selectedId!));
              final attendance = ref.watch(childAttendanceProvider(_selectedId!));
              final balance = ref.watch(childBalanceProvider(_selectedId!));

              return SliverList(delegate: SliverChildListDelegate([
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                  child: kids.length > 1
                    ? Wrap(spacing: 8, children: kids.map((k) => ChoiceChip(
                        label: Text(k['name'] ?? k['given_name'] ?? k['id'] as String),
                        selected: k['id'] == _selectedId,
                        selectedColor: AppColors.blue100,
                        onSelected: (_) => setState(() => _selectedId = k['id'] as String),
                      )).toList())
                    : Text(selected['name'] ?? selected['given_name'] ?? 'Your child',
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.ink)),
                ),
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: results.when(
                    loading: () => const Center(child: CircularProgressIndicator()),
                    error: (e, _) => _errorTile('Results unavailable'),
                    data: (r) {
                      final avg = r['average'] ?? 0;
                      final att = attendance.maybeWhen(data: (a) => a['attendance_events'] ?? 0, orElse: () => 0);
                      final bal = _balanceValue(balance.maybeWhen(data: (b) => b, orElse: () => 0));
                      return Column(children: [
                        Row(children: [
                          Expanded(child: _summaryTile(LucideIcons.award, '$avg / 20', 'Average')),
                          const SizedBox(width: 14),
                          Expanded(child: _summaryTile(LucideIcons.calendarCheck, '$att days', 'Attendance')),
                        ]),
                        const SizedBox(height: 14),
                        Row(children: [
                          Expanded(child: _summaryTile(LucideIcons.wallet,
                            '${_formatBal(bal)} XAF', 'Balance')),
                          const SizedBox(width: 14),
                          const Expanded(child: GlassCard(child: SizedBox(height: 88))),
                        ]),
                        const SizedBox(height: 16),
                        if (r['marks'] is List && (r['marks'] as List).isNotEmpty)
                          ...((r['marks'] as List).take(8).map((m) => _markRow(m))),
                      ]);
                    },
                  ),
                ),
              ]));
            },
          ),
        ]),
      ),
    );
  }

  Widget _summaryTile(IconData icon, String value, String label) {
    return GlassCard(child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Container(width: 42, height: 42,
          decoration: BoxDecoration(gradient: const LinearGradient(
            colors: [AppColors.blue600, AppColors.blue400]),
            borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: Colors.white, size: 22)),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(value, style: const TextStyle(
            fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.ink)),
          Text(label, style: const TextStyle(fontSize: 12, color: AppColors.muted)),
        ]),
      ]));
  }

  Widget _errorTile(String msg) => GlassCard(child: Center(child: Text(msg,
    style: const TextStyle(color: AppColors.muted))));

  Widget _markRow(dynamic m) {
    final subject = m['subject_id'] ?? m['subject'] ?? m['name'] ?? 'Subject';
    final score = m['score'] ?? 0;
    final max = m['max_score'] ?? 20;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: GlassCard(padding: const EdgeInsets.all(12), child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text('$subject', style: const TextStyle(color: AppColors.ink)),
          Text('$score / $max', style: const TextStyle(
            fontWeight: FontWeight.bold, color: AppColors.blue600)),
        ])),
    );
  }
}
