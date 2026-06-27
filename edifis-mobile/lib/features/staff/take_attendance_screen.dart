import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/services/attendance_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';
import '../../shared/widgets/hint_banner.dart';

final _sectionsProvider =
    FutureProvider.autoDispose<List<dynamic>>((ref) => ref.read(attendanceApiProvider).sections());

const _statusMeta = {
  'present': ('P', AppColors.success),
  'absent': ('A', AppColors.danger),
  'late': ('L', Color(0xFFD97706)),
  'excused': ('E', AppColors.blue700),
};

class TakeAttendanceScreen extends ConsumerStatefulWidget {
  const TakeAttendanceScreen({super.key});
  @override
  ConsumerState<TakeAttendanceScreen> createState() => _S();
}

class _S extends ConsumerState<TakeAttendanceScreen> {
  String? _streamId;
  String _period = 'FULL';
  final Map<String, String> _status = {};
  final Map<String, String> _reason = {};
  List<dynamic> _students = [];
  bool _loading = false, _submitting = false, _loaded = false;
  String? _error;
  DateTime _date = DateTime.now();

  String get _dateStr => _date.toIso8601String().split('T').first;

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime.now().subtract(const Duration(days: 60)),
      lastDate: DateTime.now(),
    );
    if (picked != null) {
      setState(() {
        _date = picked;
        _loaded = false;
      });
    }
  }

  void _markAll(String status) => setState(() {
        for (final s in _students) {
          _status[s['id']] = status;
        }
      });

  Future<void> _loadRoster() async {
    if (_streamId == null) {
      setState(() => _error = 'Pick a class first.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final sheet = await ref
          .read(attendanceApiProvider)
          .sheet(streamId: _streamId!, date: _dateStr, period: _period);
      _students = sheet['students'] as List;
      _status.clear();
      _reason.clear();
      for (final s in _students) {
        _status[s['id']] = (s['status'] as String?) ?? 'present';
        if (s['reason'] != null) _reason[s['id']] = s['reason'] as String;
      }
      setState(() {
        _loaded = true;
        _loading = false;
      });
    } on DioException catch (e) {
      setState(() {
        _loading = false;
        _error = e.response?.statusCode == 403
            ? "You can't take attendance for this class."
            : "Couldn't load the class list. Try again.";
      });
    }
  }

  Future<void> _submit() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final entries = _students.map((s) {
        final id = s['id'] as String;
        return {
          'student_id': id,
          'status': _status[id] ?? 'present',
          if (_reason[id] != null && _reason[id]!.isNotEmpty) 'reason': _reason[id],
        };
      }).toList();
      final res = await ref.read(attendanceApiProvider).submitRollCall(
          streamId: _streamId!, date: _dateStr, period: _period, entries: entries);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text('Roll call saved — ${res['present']} present, ${res['absent']} absent'),
          backgroundColor: AppColors.success));
        context.pop();
      }
    } on DioException {
      setState(() {
        _submitting = false;
        _error = "Couldn't save the roll call. Try again.";
      });
    }
  }

  int _count(String st) => _status.values.where((v) => v == st).length;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
          backgroundColor: AppColors.blue700,
          foregroundColor: Colors.white,
          title: const Text('Roll Call')),
      body: Column(children: [
        _picker(),
        if (_loaded) Expanded(child: _roster()),
      ]),
    );
  }

  Widget _picker() {
    final sections = ref.watch(_sectionsProvider);
    return Padding(
      padding: const EdgeInsets.all(16),
      child: GlassCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const HintBanner('Pick a class, pull the list, mark each student, and submit. No subject needed.'),
          sections.when(
            loading: () => const Padding(padding: EdgeInsets.all(8), child: LinearProgressIndicator()),
            error: (_, __) => const Text('Could not load classes.', style: TextStyle(color: AppColors.danger)),
            data: (list) => DropdownButtonFormField<String>(
              initialValue: _streamId,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Class'),
              items: list
                  .map((s) => DropdownMenuItem(value: s['id'] as String, child: Text(s['name'] as String)))
                  .toList(),
              onChanged: (v) => setState(() {
                _streamId = v;
                _loaded = false;
              }),
            ),
          ),
          const SizedBox(height: 12),
          Wrap(spacing: 8, children: [
            for (final p in const [('FULL', 'Full day'), ('AM', 'Morning'), ('PM', 'Afternoon')])
              ChoiceChip(
                label: Text(p.$2),
                selected: _period == p.$1,
                onSelected: (_) => setState(() {
                  _period = p.$1;
                  _loaded = false;
                }),
              ),
          ]),
          const SizedBox(height: 10),
          InkWell(
            onTap: _pickDate,
            borderRadius: BorderRadius.circular(8),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Row(children: [
                const Icon(Icons.calendar_today, size: 16, color: AppColors.blue700),
                const SizedBox(width: 8),
                Text(_dateStr, style: const TextStyle(color: AppColors.ink, fontWeight: FontWeight.w600)),
                const SizedBox(width: 6),
                const Text('(tap to change)', style: TextStyle(color: AppColors.muted, fontSize: 12)),
              ]),
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 10),
            Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13)),
          ],
          const SizedBox(height: 14),
          _loading
              ? const Center(child: CircularProgressIndicator())
              : Center(child: GlossyButton(label: 'Pull class list', icon: Icons.groups, onTap: _loadRoster)),
        ]),
      ),
    );
  }

  Widget _roster() {
    if (_students.isEmpty) {
      return const Center(
          child: Text('No students in this class.', style: TextStyle(color: AppColors.muted)));
    }
    return Column(children: [
      Container(
        width: double.infinity,
        color: AppColors.blue50,
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
        child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Expanded(child: Text(
            'Present ${_count('present')}  ·  Absent ${_count('absent')}  ·  Late ${_count('late')}',
            style: const TextStyle(color: AppColors.blue800, fontWeight: FontWeight.w600, fontSize: 13),
          )),
          TextButton(
            onPressed: () => _markAll('present'),
            style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 8), minimumSize: Size.zero),
            child: const Text('All present', style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600)),
          ),
        ]),
      ),
      Expanded(
        child: ListView.separated(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
          itemCount: _students.length,
          separatorBuilder: (_, __) => const SizedBox(height: 6),
          itemBuilder: (c, i) {
            final s = _students[i];
            final id = s['id'] as String;
            final st = _status[id] ?? 'present';
            return GlassCard(
              padding: const EdgeInsets.fromLTRB(14, 10, 8, 10),
              child: Row(children: [
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(s['name'] as String,
                        style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink)),
                    if (st != 'present')
                      GestureDetector(
                        onTap: () => _editReason(id, s['name'] as String),
                        child: Padding(
                          padding: const EdgeInsets.only(top: 2),
                          child: Text(
                            _reason[id]?.isNotEmpty == true ? _reason[id]! : 'Add reason',
                            style: TextStyle(
                                fontSize: 12,
                                color: _reason[id]?.isNotEmpty == true ? AppColors.muted : AppColors.blue700),
                          ),
                        ),
                      ),
                  ]),
                ),
                for (final entry in _statusMeta.entries) _statusDot(id, entry.key, entry.value.$1, entry.value.$2, st),
              ]),
            );
          },
        ),
      ),
      SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: _submitting
              ? const Center(child: CircularProgressIndicator())
              : GlossyButton(label: 'Submit roll call', icon: Icons.check, onTap: _submit),
        ),
      ),
    ]);
  }

  Widget _statusDot(String id, String value, String letter, Color color, String current) {
    final on = current == value;
    return GestureDetector(
      onTap: () => setState(() {
        _status[id] = value;
        if (value == 'present') _reason.remove(id);
      }),
      child: Container(
        width: 30,
        height: 30,
        margin: const EdgeInsets.only(left: 4),
        decoration: BoxDecoration(
          color: on ? color : Colors.transparent,
          shape: BoxShape.circle,
          border: Border.all(color: on ? color : AppColors.border, width: 1.4),
        ),
        alignment: Alignment.center,
        child: Text(letter,
            style: TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 13,
                color: on ? Colors.white : AppColors.muted)),
      ),
    );
  }

  Future<void> _editReason(String id, String name) async {
    final ctrl = TextEditingController(text: _reason[id] ?? '');
    final result = await showDialog<String>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('Reason — $name'),
        content: TextField(
          controller: ctrl,
          autofocus: true,
          decoration: const InputDecoration(hintText: 'e.g. Sick, parent informed'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, ctrl.text.trim()), child: const Text('Save')),
        ],
      ),
    );
    if (result != null) setState(() => _reason[id] = result);
  }
}
