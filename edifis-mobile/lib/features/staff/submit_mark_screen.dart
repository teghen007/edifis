import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/services/marks_api.dart';
import '../../core/services/students_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';

class SubmitMarkScreen extends ConsumerStatefulWidget {
  const SubmitMarkScreen({super.key});
  @override
  ConsumerState<SubmitMarkScreen> createState() => _SubmitMarkScreenState();
}

class _SubmitMarkScreenState extends ConsumerState<SubmitMarkScreen> {
  String? _classId, _studentId, _subjectId, _sequence;
  final _scoreC = TextEditingController(), _maxC = TextEditingController(text: '20');
  bool _saving = false;
  String? _error;

  @override
  void dispose() { _scoreC.dispose(); _maxC.dispose(); super.dispose(); }

  Future<void> _submit() async {
    if (_classId == null || _studentId == null || _subjectId == null || _sequence == null) {
      setState(() => _error = 'Fill all fields.'); return;
    }
    final score = double.tryParse(_scoreC.text);
    final max = double.tryParse(_maxC.text);
    if (score == null || max == null || score > max) {
      setState(() => _error = 'Score must be ≤ max score.'); return;
    }
    setState(() { _saving = true; _error = null; });
    try {
      await ref.read(marksApiProvider).submit(
        studentId: _studentId!, subjectId: _subjectId!, classId: _classId!,
        sequence: _sequence!, score: score, maxScore: max);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Saved'), backgroundColor: AppColors.success));
        _scoreC.clear();
        setState(() { _saving = false; _error = null; });
      }
    } on DioException catch (e) {
      final msg = (e.response?.data is Map)
        ? ((e.response!.data as Map)['message'] ?? 'Couldn\'t save. Try again.')
        : 'Couldn\'t save. Try again.';
      setState(() => _error = msg.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final classes = ref.watch(classesProvider);
    final subjects = ref.watch(subjectsProvider);
    final students = ref.watch(studentsProvider);
    final filtered = _classId != null
      ? students.maybeWhen(data: (all) => all.where((s) => s.classId == _classId).toList(), orElse: () => <StudentRow>[])
      : <StudentRow>[];

    final seqOptions = List.generate(6, (i) => 'Sequence ${i+1}');

    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white, title: const Text('Record Mark')),
      body: SingleChildScrollView(padding: const EdgeInsets.all(16), child: GlassCard(child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          _dropdown(classes, 'Class', (v) => setState(() { _classId = v; _studentId = null; }), _classId,
            (e) => e.id, (e) => e.name),
          const SizedBox(height: 12),
          _dropdown(AsyncValue.data(filtered), 'Student', (v) => setState(() => _studentId = v), _studentId,
            (e) => e.id, (e) => e.name),
          const SizedBox(height: 12),
          _dropdown(subjects, 'Subject', (v) => setState(() => _subjectId = v), _subjectId,
            (e) => e.id, (e) => '${e.name} (${e.code})'),
          const SizedBox(height: 12),
          _dropdown(AsyncValue.data(seqOptions.map((s) => _SeqWrap(s)).toList()),
            'Sequence', (v) => setState(() => _sequence = v), _sequence, (e) => e.label, (e) => e.label),
          const SizedBox(height: 14),
          Row(children: [
            Expanded(child: TextField(controller: _scoreC, keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Score'))),
            const SizedBox(width: 12),
            Expanded(child: TextField(controller: _maxC, keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Max'))),
          ]),
          if (_error != null) ...[
            const SizedBox(height: 10),
            Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13)),
          ],
          const SizedBox(height: 18),
          _saving
            ? const Center(child: CircularProgressIndicator())
            : Center(child: GlossyButton(label: 'Submit', icon: Icons.check, onTap: _submit)),
        ])),
      ),
    );
  }

  Widget _dropdown<T>(AsyncValue<List<T>> src, String hint, ValueChanged<String> onChanged, String? v,
      String Function(T) idFn, String Function(T) labelFn) {
    return src.when(
      loading: () => const LinearProgressIndicator(),
      error: (e, _) => Text('Error loading $hint', style: const TextStyle(color: AppColors.danger)),
      data: (items) => DropdownButtonFormField<String>(
        initialValue: items.any((i) => idFn(i) == v) ? v : null,
        items: items.map((i) => DropdownMenuItem(value: idFn(i), child: Text(labelFn(i)))).toList(),
        onChanged: (val) { if (val != null) onChanged(val); },
        decoration: InputDecoration(labelText: hint),
      ),
    );
  }
}

class _SeqWrap { final String label; _SeqWrap(this.label); }
