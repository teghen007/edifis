import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class AttendanceApi {
  final Ref _ref; AttendanceApi(this._ref);
  Future<String> openSession({required String classId, required String subjectId, required String period}) async {
    final r = await _ref.read(dioProvider).post('/attendance/sessions',
      data: {'class_id': classId, 'subject_id': subjectId, 'period': period});
    return r.data['id'] as String;
  }
  Future<void> scan(String sessionId, String studentId) =>
    _ref.read(dioProvider).post('/attendance/sessions/$sessionId/scan',
      data: {'student_id': studentId, 'source': 'manual_override'});
  Future<void> close(String sessionId) =>
    _ref.read(dioProvider).post('/attendance/sessions/$sessionId/close');
}
final attendanceApiProvider = Provider<AttendanceApi>((ref) => AttendanceApi(ref));
