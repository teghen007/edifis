import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import '../../core/network/dio_client.dart';
import '../../core/services/results_api.dart';
import '../../core/services/students_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/hint_banner.dart';

class ConductScreen extends ConsumerStatefulWidget {
  const ConductScreen({super.key});
  @override
  ConsumerState<ConductScreen> createState() => _ConductScreenState();
}

class _ConductScreenState extends ConsumerState<ConductScreen> {
  String? _streamId, _termId;
  final _grades = <String, String>{};
  final _comments = <String, TextEditingController>{};
  final _conductOptions = ['Excellent', 'Good', 'Fair', 'Poor'];
  bool _saving = false;

  @override
  void dispose() {
    for (final c in _comments.values) c.dispose();
    super.dispose();
  }

  Future<void> _save(String studentId, String studentName) async {
    final grade = _grades[studentId];
    if (grade == null || _termId == null || _streamId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select stream, term and a grade first.'), backgroundColor: AppColors.warning));
      return;
    }
    setState(() => _saving = true);
    try {
      await ref.read(dioProvider).post('/conduct', data: {
        'student_id': studentId,
        'term_id': _termId,
        'stream_id': _streamId,
        'conduct_grade': grade,
        'comment': _comments[studentId]?.text ?? '',
      });
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Saved conduct for $studentName'), backgroundColor: AppColors.success));
    } on DioException {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Save failed. Try again.'), backgroundColor: AppColors.danger));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final streams = ref.watch(streamsProvider);
    final terms = ref.watch(termsProvider);
    final students = ref.watch(studentsProvider);
    if (_termId == null) terms.maybeWhen(data: (t) { if (t.isNotEmpty) _termId = t.first.id; }, orElse: () {});

    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white,
        title: const Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(LucideIcons.shieldAlert, size: 22), SizedBox(width: 8), Text('Conduct')])),
      body: SingleChildScrollView(padding: const EdgeInsets.all(16), child: Column(children: [
        const HintBanner('Choose the class and term, then set each student\'s conduct grade. It shows on their report card.'),
        GlassCard(child: Column(children: [
          streams.when(loading: () => const LinearProgressIndicator(), error: (_,__) => const SizedBox.shrink(),
            data: (s) => DropdownButtonFormField<String>(initialValue: _streamId, decoration: const InputDecoration(labelText: 'Stream'),
              items: s.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
              onChanged: (v) => setState(() => _streamId = v))),
          const SizedBox(height: 12),
          terms.when(loading: () => const LinearProgressIndicator(), error: (_,__) => const SizedBox.shrink(),
            data: (t) => DropdownButtonFormField<String>(initialValue: _termId, decoration: const InputDecoration(labelText: 'Term'),
              items: t.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
              onChanged: (v) => setState(() => _termId = v))),
        ])),
        const SizedBox(height: 16),
        if (_streamId != null)
          students.when(loading: () => const Center(child: CircularProgressIndicator()),
            error: (_,__) => const Text('Error loading students'),
            data: (all) {
              // /students returns class_id; match by the selected stream's class name.
              final streamName = streams.maybeWhen(
                data: (s) => s.firstWhere((e) => e.id == _streamId,
                  orElse: () => StreamRow('', '')).name,
                orElse: () => '');
              final list = all.where((s) => s.className == streamName).toList();
              if (list.isEmpty) return const Padding(padding: EdgeInsets.all(20),
                child: Text('No students in this stream.', style: TextStyle(color: AppColors.muted)));
              return Column(children: list.map((s) {
                _comments.putIfAbsent(s.id, () => TextEditingController());
                return Padding(padding: const EdgeInsets.only(bottom: 12),
                  child: GlassCard(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(s.name, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink)),
                    const SizedBox(height: 10),
                    Row(children: [
                      Expanded(child: DropdownButtonFormField<String>(
                        initialValue: _grades[s.id], decoration: const InputDecoration(labelText: 'Conduct', isDense: true),
                        items: _conductOptions.map((g) => DropdownMenuItem(value: g, child: Text(g))).toList(),
                        onChanged: (v) => setState(() { if (v != null) _grades[s.id] = v; }))),
                      const SizedBox(width: 12),
                      Expanded(child: TextField(controller: _comments[s.id],
                        decoration: const InputDecoration(labelText: 'Comment', isDense: true))),
                    ]),
                    const SizedBox(height: 10),
                    Align(alignment: Alignment.centerRight, child: _saving
                      ? const SizedBox(height: 24, width: 24, child: CircularProgressIndicator(strokeWidth: 2))
                      : TextButton.icon(onPressed: () => _save(s.id, s.name),
                          icon: const Icon(LucideIcons.save, size: 18),
                          label: const Text('Save'))),
                  ])));
              }).toList());
            }),
      ])),
    );
  }
}
