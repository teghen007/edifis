import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/services/results_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';

class ResultsScreen extends ConsumerStatefulWidget {
  const ResultsScreen({super.key});
  @override
  ConsumerState<ResultsScreen> createState() => _ResultsScreenState();
}

class _ResultsScreenState extends ConsumerState<ResultsScreen> {
  String? _streamId, _termId;
  bool _computing = false;

  Future<void> _compute() async {
    if (_streamId == null || _termId == null) return;
    setState(() => _computing = true);
    try {
      await ref.read(resultsApiProvider).compute(_streamId!, _termId!);
      ref.invalidate(mastersheetProvider);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Results computed'), backgroundColor: AppColors.success));
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Compute failed'), backgroundColor: AppColors.danger));
    } finally { if (mounted) setState(() => _computing = false); }
  }

  @override
  Widget build(BuildContext context) {
    final streams = ref.watch(streamsProvider);
    final terms = ref.watch(termsProvider);
    if (_termId == null) terms.maybeWhen(data: (t) { if (t.isNotEmpty) _termId = t.first.id; }, orElse: () {});
    final sheet = (_streamId != null && _termId != null)
      ? ref.watch(mastersheetProvider((_streamId!, _termId!))) : null;

    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white, title: const Text('Results')),
      body: SingleChildScrollView(padding: const EdgeInsets.all(16), child: Column(children: [
        Row(children: [
          Expanded(child: streams.when(loading: () => const LinearProgressIndicator(), error: (_,__) => const SizedBox.shrink(),
            data: (s) => DropdownButtonFormField<String>(initialValue: _streamId, decoration: const InputDecoration(labelText: 'Stream'),
              items: s.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
              onChanged: (v) => setState(() => _streamId = v)))),
          const SizedBox(width: 12),
          Expanded(child: terms.when(loading: () => const LinearProgressIndicator(), error: (_,__) => const SizedBox.shrink(),
            data: (t) => DropdownButtonFormField<String>(initialValue: _termId, decoration: const InputDecoration(labelText: 'Term'),
              items: t.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
              onChanged: (v) => setState(() => _termId = v)))),
        ]),
        const SizedBox(height: 12),
        GlossyButton(label: _computing ? 'Computing...' : 'Compute Results', icon: Icons.calculate, onTap: _compute),
        const SizedBox(height: 16),
        if (sheet == null) const SizedBox.shrink()
        else sheet.when(loading: () => const CircularProgressIndicator(),
          error: (_,__) => const Text('Error loading mastersheet'),
          data: (ms) => Column(children: [
            Text('${ms.streamName} · ${ms.termName}', style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.ink)),
            const SizedBox(height: 12),
            ...ms.students.map((s) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: GlassCard(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Container(width: 28, height: 28, alignment: Alignment.center,
                    decoration: BoxDecoration(color: AppColors.blue600, borderRadius: BorderRadius.circular(8)),
                    child: Text('${s.position}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12))),
                  const SizedBox(width: 10),
                  Expanded(child: Text(s.name, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink))),
                  Text(s.overallAverage, style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.blue600)),
                  const SizedBox(width: 8),
                  Text(s.grade, style: TextStyle(color: AppColors.muted, fontSize: 12)),
                ]),
                const SizedBox(height: 6),
                Wrap(spacing: 6, runSpacing: 4, children: s.marks.entries.map((e) => Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(color: AppColors.blue50, borderRadius: BorderRadius.circular(6)),
                  child: Text('${e.key}: ${e.value}', style: const TextStyle(fontSize: 11, color: AppColors.blue800)),
                )).toList()),
              ])),
            )),
          ]),
        ),
      ])),
    );
  }
}
