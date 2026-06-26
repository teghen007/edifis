import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../../core/auth/auth_state.dart';
import '../../core/services/dashboard_api.dart';
import '../../core/services/school_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';

class StaffHomeScreen extends ConsumerWidget {
  final String roleName;
  const StaffHomeScreen({super.key, required this.roleName});

  String _greeting() {
    final h = DateTime.now().hour;
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
  }

  IconData _icon(String key) {
    switch (key) {
      case 'users': return LucideIcons.users;
      case 'calendar-check': return LucideIcons.calendarCheck;
      case 'wallet': return LucideIcons.wallet;
      case 'clipboard-check': return LucideIcons.clipboardCheck;
      case 'book-open': return LucideIcons.bookOpen;
      case 'award': return LucideIcons.award;
      case 'bell': return LucideIcons.bell;
      default: return LucideIcons.circle;
    }
  }

  /// Role-aware circular quick-action shortcuts shown near the top of the dashboard.
  List<Widget> _actionsFor(BuildContext c, String role) {
    final a = <Widget>[];
    if (role == 'principal' || role == 'vice_principal') {
      a.add(_qa(c, LucideIcons.sparkles, 'Ask AI', AppColors.gold, '/vacuum'));
      a.add(_qa(c, LucideIcons.award, 'Results', AppColors.blue700, '/results'));
    }
    if (role == 'bursar' || role == 'principal') {
      a.add(_qa(c, LucideIcons.chartPie, 'Fees', AppColors.blue600, '/fees-overview'));
      a.add(_qa(c, LucideIcons.wallet, 'Fees sheet', AppColors.blue400, '/fees-excel'));
    }
    if (role == 'class_master' || role == 'principal') {
      a.add(_qa(c, LucideIcons.userCheck, 'Enrolment', AppColors.blue500, '/enrollment-excel'));
    }
    if (role == 'discipline_master' || role == 'principal') {
      a.add(_qa(c, LucideIcons.shieldAlert, 'Conduct', AppColors.blue400, '/conduct'));
    }
    if (['subject_teacher', 'class_master', 'principal'].contains(role)) {
      a.add(_qa(c, LucideIcons.fileSpreadsheet, 'Mark sheet', AppColors.blue500, '/marks-excel'));
      a.add(_qa(c, LucideIcons.plus, 'Record mark', AppColors.blue600, '/submit-mark'));
    }
    if (['principal', 'vice_principal', 'class_master', 'subject_teacher'].contains(role)) {
      a.add(_qa(c, LucideIcons.calendarCheck, 'Attendance', AppColors.blue400, '/take-attendance'));
    }
    return a;
  }

  Widget _qa(BuildContext c, IconData icon, String label, Color color, String route) {
    return Padding(
      padding: const EdgeInsets.only(right: 14),
      child: GestureDetector(
        onTap: () => c.push(route),
        child: SizedBox(width: 66, child: Column(children: [
          Container(
            width: 56, height: 56,
            decoration: BoxDecoration(
              gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight,
                colors: [color, Color.lerp(color, Colors.white, .28)!]),
              shape: BoxShape.circle,
              boxShadow: [BoxShadow(color: color.withValues(alpha: .35), blurRadius: 12, offset: const Offset(0, 5))]),
            child: Icon(icon, color: Colors.white, size: 23)),
          const SizedBox(height: 7),
          Text(label, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 11.5, color: AppColors.ink, height: 1.1)),
        ])),
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(meProvider);
    final summary = ref.watch(dashboardSummaryProvider);

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(meProvider);
          ref.invalidate(dashboardSummaryProvider);
          await Future.wait([
            ref.read(meProvider.future),
            ref.read(dashboardSummaryProvider.future),
          ]);
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
                    me.maybeWhen(data: (m) => m.name, orElse: () => roleName),
                    style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 2),
                  Text(
                    me.maybeWhen(
                      data: (m) => '${m.role[0].toUpperCase()}${m.role.substring(1)} · ${m.schoolName}',
                      orElse: () => roleName),
                    style: const TextStyle(color: AppColors.blue100, fontSize: 13)),
                  ref.watch(schoolProfileProvider).maybeWhen(data: (p) {
                    if (p.name.isEmpty) return const SizedBox.shrink();
                    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(p.name, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w500)),
                      if (p.motto.isNotEmpty)
                        Text(p.motto, style: const TextStyle(color: AppColors.blue100, fontSize: 10)),
                    ]);
                  }, orElse: () => const SizedBox.shrink()),
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
          me.maybeWhen(
            data: (m) {
              final actions = _actionsFor(context, m.role);
              if (actions.isEmpty) return const SliverToBoxAdapter(child: SizedBox.shrink());
              return SliverToBoxAdapter(child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 18, 0, 2),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Padding(padding: EdgeInsets.only(right: 16),
                    child: Text('Quick actions', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.ink, fontSize: 15))),
                  const SizedBox(height: 14),
                  SingleChildScrollView(scrollDirection: Axis.horizontal, child: Row(children: actions)),
                ])).animate().fadeIn(duration: 350.ms, delay: 120.ms));
            },
            orElse: () => const SliverToBoxAdapter(child: SizedBox.shrink())),
          summary.when(
            loading: () => const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(child: Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator()))),
            error: (e, _) => SliverFillRemaining(
              hasScrollBody: false,
              child: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(LucideIcons.wifiOff, size: 40, color: AppColors.muted),
                const SizedBox(height: 12),
                const Text('Couldn\'t load your dashboard.', style: TextStyle(color: AppColors.muted)),
                const SizedBox(height: 12),
                FilledButton(onPressed: () => ref.invalidate(dashboardSummaryProvider),
                  child: const Text('Retry')),
              ]))),
            data: (cards) => cards.isEmpty
              ? const SliverFillRemaining(hasScrollBody: false,
                  child: Center(child: Text('No data yet.', style: TextStyle(color: AppColors.muted))))
              : SliverPadding(
                  padding: const EdgeInsets.all(16),
                  sliver: SliverGrid(
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2, mainAxisSpacing: 14, crossAxisSpacing: 14, childAspectRatio: 1.15),
                    delegate: SliverChildBuilderDelegate(
                      (context, i) {
                        final c = cards[i];
                        final card = GlassCard(child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Container(
                              width: 42, height: 42,
                              decoration: BoxDecoration(
                                gradient: const LinearGradient(
                                  colors: [AppColors.blue600, AppColors.blue400]),
                                borderRadius: BorderRadius.circular(12)),
                              child: Icon(_icon(c.icon), color: Colors.white, size: 22)),
                            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(c.value, style: const TextStyle(
                                fontSize: 22, fontWeight: FontWeight.bold, color: AppColors.ink)),
                              const SizedBox(height: 2),
                              Text(c.label, style: const TextStyle(
                                fontSize: 12.5, color: AppColors.muted)),
                            ]),
                          ])).animate().fadeIn(duration: 300.ms, delay: (i * 70).ms)
                              .slideY(begin: .15, end: 0, curve: Curves.easeOut, duration: 300.ms);
                        return GestureDetector(
                          onTap: () {
                            if (c.key == 'students') {
                              context.push('/students');
                            } else if (c.key == 'fees' || c.key == 'outstanding' || c.key == 'fees_collected') {
                              context.push('/fees');
                            } else if (c.key == 'attendance') {
                              context.push('/take-attendance');
                            } else if (c.key == 'pending_approvals' || c.key == 'timetable' || c.key == 'approvals') {
                              context.push('/timetable');
                            }
                          },
                          child: card,
                        );
                      },
                      childCount: cards.length))),
          ),
        ]),
      ),
    );
  }
}
