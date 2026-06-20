import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';
import 'package:edifis/core/network/dio_client.dart';

class ClassRow { final String id, name; final int level;
  ClassRow({required this.id, required this.name, required this.level});
  factory ClassRow.fromJson(Map<String,dynamic> j) => ClassRow(id: j['id']??'', name: j['name']??'', level: (j['level']??0) is int ? j['level'] as int : int.tryParse('${j['level']}')??0);
}
class SubjectRow { final String id, name, code;
  SubjectRow({required this.id, required this.name, required this.code});
  factory SubjectRow.fromJson(Map<String,dynamic> j) => SubjectRow(id: j['id']??'', name: j['name']??'', code: j['code']??'');
}

final classesProvider = FutureProvider<List<ClassRow>>((ref) async {
  final r = await ref.read(dioProvider).get('/classes');
  return ((r.data ?? []) as List).map((e)=>ClassRow.fromJson(e as Map<String,dynamic>)).toList();
});
final subjectsProvider = FutureProvider<List<SubjectRow>>((ref) async {
  final r = await ref.read(dioProvider).get('/subjects');
  return ((r.data ?? []) as List).map((e)=>SubjectRow.fromJson(e as Map<String,dynamic>)).toList();
});

class MarksApi {
  final Ref _ref; MarksApi(this._ref);
  Future<void> submit({required String studentId, required String subjectId, required String classId,
      required String sequence, required double score, required double maxScore}) async {
    await _ref.read(dioProvider).post('/academics/marks', data: {
      'id': const Uuid().v4(), 'revision': '1',
      'student_id': studentId, 'subject_id': subjectId, 'class_id': classId,
      'sequence': sequence, 'score': score, 'max_score': maxScore, 'published': true,
    });
  }
}
final marksApiProvider = Provider<MarksApi>((ref) => MarksApi(ref));
