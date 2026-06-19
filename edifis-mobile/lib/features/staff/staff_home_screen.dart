import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../../core/auth/auth_state.dart';
import '../../core/services/dashboard_api.dart';
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
                        return GlassCard(child: Column(
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
                      },
                      childCount: cards.length))),
          ),
        ]),
      ),
    );
  }
}
