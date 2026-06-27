import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/services/season_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/hint_banner.dart';

class SeasonScreen extends ConsumerStatefulWidget {
  const SeasonScreen({super.key});
  @override
  ConsumerState<SeasonScreen> createState() => _S();
}

class _S extends ConsumerState<SeasonScreen> {
  bool _busy = false;

  Future<void> _act(Future<void> Function(SeasonApi) fn, String success, {String? confirm}) async {
    if (confirm != null) {
      final ok = await showDialog<bool>(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Please confirm'),
          content: Text(confirm),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Continue')),
          ],
        ),
      );
      if (ok != true) return;
    }
    setState(() => _busy = true);
    try {
      await fn(ref.read(seasonApiProvider));
      ref.invalidate(seasonProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(success), backgroundColor: AppColors.success));
      }
    } on DioException catch (e) {
      final msg = e.response?.data is Map ? (e.response?.data['message'] ?? 'Action blocked') : 'Action blocked';
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$msg'), backgroundColor: AppColors.danger));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final season = ref.watch(seasonProvider);
    return Scaffold(
      appBar: AppBar(
          backgroundColor: AppColors.blue700, foregroundColor: Colors.white,
          title: const Text('Academic Season')),
      body: season.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, __) => const Center(child: Text('Could not load the season.', style: TextStyle(color: AppColors.muted))),
        data: (s) {
          if (!(s['has_season'] == true)) {
            return const Center(child: Padding(padding: EdgeInsets.all(24),
              child: Text('No active academic year is set yet.', textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.muted))));
          }
          final year = s['year'] as Map;
          final current = s['current_term'] as Map?;
          final terms = (s['terms'] as List?) ?? const [];
          return ListView(padding: const EdgeInsets.all(16), children: [
            const HintBanner('Run the school year here: open each sequence, advance through the terms, then end the year.'),
            GlassCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(year['name'] ?? '', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.ink)),
              const SizedBox(height: 4),
              Text(
                current != null
                    ? '${current['name']} · sequence ${s['current_sequence']} of 2 · evaluation ${s['global_sequence']} of 6'
                    : (s['can_close_year'] == true ? 'All terms closed — ready to end the year.' : 'No active term.'),
                style: const TextStyle(color: AppColors.muted, fontSize: 13)),
            ])),
            const SizedBox(height: 14),
            // Term stepper
            Row(children: [
              for (final t in terms) Expanded(child: _termPill(t as Map)),
            ]),
            const SizedBox(height: 22),
            if (_busy) const Center(child: CircularProgressIndicator())
            else ...[
              if (s['can_open_next_sequence'] == true)
                _btn('Open next sequence', Icons.skip_next, AppColors.blue600,
                  () => _act((a) => a.openNextSequence(), 'Next sequence opened.')),
              if (s['can_advance'] == true)
                _btn('Close ${current?['name']} & advance', Icons.fast_forward, const Color(0xFFD97706),
                  () => _act((a) => a.advanceTerm(), 'Term closed and next term opened.',
                    confirm: 'Close ${current?['name']} and open the next term? This computes results (you can reopen it later).')),
              if (s['can_close_year'] == true)
                _btn('End academic year', Icons.flag, AppColors.danger,
                  () => _act((a) => a.closeYear(), 'Year ended — promotions deliberated, new year opened.',
                    confirm: 'End the academic year? This runs promotion deliberation for every class and opens the next year.')),
            ],
          ]);
        },
      ),
    );
  }

  Widget _termPill(Map t) {
    final status = t['status'] as String? ?? 'upcoming';
    final color = status == 'active' ? AppColors.blue600 : (status == 'closed' ? AppColors.success : AppColors.border);
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 4),
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 10),
      decoration: BoxDecoration(
        border: Border.all(color: color, width: 1.5),
        borderRadius: BorderRadius.circular(12)),
      child: Column(children: [
        Text(t['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.ink, fontSize: 13)),
        const SizedBox(height: 2),
        Text(
          status == 'active' ? 'seq ${t['current_sequence']}/2' : (status == 'closed' ? 'Closed' : 'Upcoming'),
          style: TextStyle(color: color, fontSize: 11.5)),
      ]),
    );
  }

  Widget _btn(String label, IconData icon, Color color, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: SizedBox(
        width: double.infinity,
        child: FilledButton.icon(
          style: FilledButton.styleFrom(backgroundColor: color, padding: const EdgeInsets.symmetric(vertical: 14)),
          onPressed: onTap,
          icon: Icon(icon, size: 18),
          label: Text(label),
        ),
      ),
    );
  }
}
