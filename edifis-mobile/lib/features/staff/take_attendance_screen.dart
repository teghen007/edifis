import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import '../../core/services/attendance_api.dart';
import '../../core/services/marks_api.dart';
import '../../core/services/results_api.dart';
import '../../core/services/students_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';

class TakeAttendanceScreen extends ConsumerStatefulWidget {
  const TakeAttendanceScreen({super.key});
  @override
  ConsumerState<TakeAttendanceScreen> createState() => _S();
}

class _S extends ConsumerState<TakeAttendanceScreen> {
  String? _classId, _subjectId, _period;
  String? _sessionId;
  final Set<String> _present = {};
  bool _busy = false;
  String? _error;
  static const _periods = ['Morning','Afternoon','Period 1','Period 2','Period 3','Period 4','Period 5','Period 6'];

  Future<void> _start() async {
    if (_classId == null || _subjectId == null || _period == null) { setState(() => _error = 'Pick class, subject and period.'); return; }
    setState(() { _busy = true; _error = null; });
    try {
      final id = await ref.read(attendanceApiProvider).openSession(classId: _classId!, subjectId: _subjectId!, period: _period!);
      setState(() { _sessionId = id; _busy = false; });
    } on DioException catch (e) {
      final code = e.response?.statusCode;
      setState(() { _busy = false; _error = (code == 403) ? 'Only class teachers can take attendance.' : "Couldn't start the session."; });
    }
  }

  Future<void> _finish() async {
    setState(() { _busy = true; _error = null; });
    try {
      for (final sid in _present) { await ref.read(attendanceApiProvider).scan(_sessionId!, sid); }
      await ref.read(attendanceApiProvider).close(_sessionId!);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text('Attendance saved — ${_present.length} present'), backgroundColor: AppColors.success));
        context.pop();
      }
    } on DioException {
      setState(() { _busy = false; _error = "Couldn't save attendance. Try again."; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white,
        title: Text(_sessionId == null ? 'Take Attendance' : 'Mark Present')),
      body: _sessionId == null ? _setup() : _roster(),
    );
  }

  Widget _setup() {
    final assign = ref.watch(myAssignmentsProvider).valueOrNull;
    final allClasses = ref.watch(classesProvider).valueOrNull ?? const [];
    final allSubjects = ref.watch(subjectsProvider).valueOrNull ?? const [];
    if (assign == null) return const Center(child: CircularProgressIndicator());

    // Teachers only see the classes/subjects they're assigned to; principals see all.
    final streamNames = assign.streams.map((s) => s.name).toSet();
    final subjectIds = assign.subjects.map((s) => s.id).toSet();
    final classes = assign.scoped ? allClasses.where((c) => streamNames.contains(c.name)).toList() : allClasses.toList();
    final subjects = assign.scoped ? allSubjects.where((s) => subjectIds.contains(s.id)).toList() : allSubjects.toList();

    if (assign.scoped && classes.isEmpty) {
      return const Center(child: Padding(padding: EdgeInsets.all(24),
        child: Text('You have no assigned classes yet. Ask the admin to assign you.',
          textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted))));
    }

    return SingleChildScrollView(padding: const EdgeInsets.all(16), child: GlassCard(child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        DropdownButtonFormField<String>(
          initialValue: _classId, decoration: const InputDecoration(labelText: 'Class'),
          items: classes.map((c) => DropdownMenuItem(value: c.id, child: Text(c.name))).toList(),
          onChanged: (v) => setState(() => _classId = v)),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _subjectId, decoration: const InputDecoration(labelText: 'Subject'),
          items: subjects.map((s) => DropdownMenuItem(value: s.id, child: Text('${s.name} (${s.code})'))).toList(),
          onChanged: (v) => setState(() => _subjectId = v)),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(initialValue: _period, decoration: const InputDecoration(labelText: 'Period'),
          items: _periods.map((p) => DropdownMenuItem(value: p, child: Text(p))).toList(),
          onChanged: (v) => setState(() => _period = v)),
        if (_error != null) ...[const SizedBox(height: 10), Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13))],
        const SizedBox(height: 18),
        _busy ? const Center(child: CircularProgressIndicator())
              : Center(child: GlossyButton(label: 'Start session', icon: Icons.play_arrow, onTap: _start)),
      ])));
  }

  Widget _roster() {
    final students = ref.watch(studentsProvider);
    return students.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e,_) => const Center(child: Text('Error loading students')),
      data: (all) {
        final list = all.where((s) => s.classId == _classId).toList();
        return Column(children: [
          Container(width: double.infinity, color: AppColors.blue50, padding: const EdgeInsets.all(12),
            child: Text('${_present.length} of ${list.length} marked present',
              textAlign: TextAlign.center, style: const TextStyle(color: AppColors.blue800, fontWeight: FontWeight.w600))),
          if (_error != null) Padding(padding: const EdgeInsets.all(8), child: Text(_error!, style: const TextStyle(color: AppColors.danger))),
          Expanded(child: list.isEmpty
            ? const Center(child: Text('No students in this class.', style: TextStyle(color: AppColors.muted)))
            : ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                itemCount: list.length, separatorBuilder: (_,__) => const SizedBox(height: 8),
                itemBuilder: (c, i) {
                  final s = list[i]; final on = _present.contains(s.id);
                  return GestureDetector(
                    onTap: () => setState(() => on ? _present.remove(s.id) : _present.add(s.id)),
                    child: GlassCard(padding: const EdgeInsets.all(14), child: Row(children: [
                      Expanded(child: Text(s.name, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink))),
                      Icon(on ? LucideIcons.circleCheck : LucideIcons.circle, color: on ? AppColors.success : AppColors.border),
                    ])));
                })),
          Padding(padding: const EdgeInsets.all(16), child: _busy
            ? const Center(child: CircularProgressIndicator())
            : GlossyButton(label: 'Finish & close (${_present.length})', icon: Icons.check, onTap: _finish)),
        ]);
      });
  }
}
