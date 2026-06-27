import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class StudentRow {
  final String id, name, className, classId;
  final String? photoUrl;
  final bool active;
  StudentRow({required this.id, required this.name, required this.className, required this.classId, required this.active, this.photoUrl});
  factory StudentRow.fromJson(Map<String, dynamic> j) => StudentRow(
    id: j['id'] ?? '', name: j['name'] ?? '', className: j['class_name'] ?? '',
    classId: j['class_id'] ?? '', active: j['active'] ?? true,
    photoUrl: (j['photo_url'] as String?)?.isNotEmpty == true ? j['photo_url'] as String : null);

  bool get hasPhoto => photoUrl != null;
}

final studentsProvider = FutureProvider<List<StudentRow>>((ref) async {
  final res = await ref.read(dioProvider).get('/students');
  return ((res.data ?? []) as List).map((e) => StudentRow.fromJson(e as Map<String, dynamic>)).toList();
});

/// Photo Day: upload (or replace) a single student's photo. Returns the new URL.
Future<String?> uploadStudentPhoto(Dio dio, String studentId, String filePath) async {
  final form = FormData.fromMap({
    'photo': await MultipartFile.fromFile(filePath, filename: 'photo.jpg'),
  });
  final res = await dio.post('/students/$studentId/photo', data: form);
  return res.data?['photo_url'] as String?;
}
