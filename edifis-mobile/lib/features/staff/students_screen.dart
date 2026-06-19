import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import '../../core/services/students_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';

class StudentsScreen extends ConsumerStatefulWidget {
  const StudentsScreen({super.key});
  @override
  ConsumerState<StudentsScreen> createState() => _StudentsScreenState();
}

class _StudentsScreenState extends ConsumerState<StudentsScreen> {
  String _q = '';
  String _initials(String n) {
    final p = n.trim().split(RegExp(r'\s+'));
    return (p.isEmpty ? '?' : (p.first.isNotEmpty ? p.first[0] : '') + (p.length > 1 && p.last.isNotEmpty ? p.last[0] : '')).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final students = ref.watch(studentsProvider);
    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white, title: const Text('Students')),
      body: students.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(LucideIcons.wifiOff, size: 40, color: AppColors.muted),
          const SizedBox(height: 12),
          const Text("Couldn't load students.", style: TextStyle(color: AppColors.muted)),
          const SizedBox(height: 12),
          FilledButton(onPressed: () => ref.invalidate(studentsProvider), child: const Text('Retry')),
        ])),
        data: (all) {
          final list = _q.isEmpty ? all
            : all.where((s) => s.name.toLowerCase().contains(_q.toLowerCase())).toList();
          return Column(children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: TextField(
                onChanged: (v) => setState(() => _q = v),
                decoration: const InputDecoration(
                  hintText: 'Search students', prefixIcon: Icon(LucideIcons.search)),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Align(alignment: Alignment.centerLeft,
                child: Text('${list.length} student${list.length == 1 ? '' : 's'}',
                  style: const TextStyle(color: AppColors.muted, fontSize: 13))),
            ),
            Expanded(child: list.isEmpty
              ? const Center(child: Text('No students found.', style: TextStyle(color: AppColors.muted)))
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
                  itemCount: list.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (c, i) {
                    final s = list[i];
                    return GlassCard(padding: const EdgeInsets.all(14), child: Row(children: [
                      Container(width: 44, height: 44, alignment: Alignment.center,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(colors: [AppColors.blue600, AppColors.blue400]),
                          borderRadius: BorderRadius.circular(12)),
                        child: Text(_initials(s.name), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold))),
                      const SizedBox(width: 14),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(s.name, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink)),
                        Text(s.className.isEmpty ? 'No class set' : s.className,
                          style: const TextStyle(color: AppColors.muted, fontSize: 12.5)),
                      ])),
                    ]));
                  }),
            ),
          ]);
        },
      ),
    );
  }
}
