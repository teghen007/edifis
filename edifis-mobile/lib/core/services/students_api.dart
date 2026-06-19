import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class StudentRow {
  final String id, name, className;
  final bool active;
  StudentRow({required this.id, required this.name, required this.className, required this.active});
  factory StudentRow.fromJson(Map<String, dynamic> j) => StudentRow(
    id: j['id'] ?? '', name: j['name'] ?? '', className: j['class_name'] ?? '', active: j['active'] ?? true);
}

final studentsProvider = FutureProvider<List<StudentRow>>((ref) async {
  final res = await ref.read(dioProvider).get('/students');
  return ((res.data ?? []) as List).map((e) => StudentRow.fromJson(e as Map<String, dynamic>)).toList();
});
