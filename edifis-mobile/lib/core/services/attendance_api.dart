import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class AttendanceApi {
  final Ref _ref;
  AttendanceApi(this._ref);

  // --- Daily roll call (section-based, subject-free) ---

  /// Sections (streams) the user can take attendance for.
  Future<List<dynamic>> sections() async =>
      (await _ref.read(dioProvider).get('/attendance/sections')).data as List;

  /// Roster + any marks already taken for a section on a given day/period.
  Future<Map<String, dynamic>> sheet({
    required String streamId,
    required String date,
    String period = 'FULL',
  }) async {
    final r = await _ref.read(dioProvider).get('/attendance/rollcall',
        queryParameters: {'stream_id': streamId, 'date': date, 'period': period});
    return r.data as Map<String, dynamic>;
  }

  /// Submit a roll call. [entries] = [{student_id, status, reason?}].
  Future<Map<String, dynamic>> submitRollCall({
    required String streamId,
    required String date,
    required String period,
    required List<Map<String, dynamic>> entries,
  }) async {
    final r = await _ref.read(dioProvider).post('/attendance/rollcall', data: {
      'stream_id': streamId,
      'date': date,
      'period': period,
      'entries': entries,
    });
    return r.data as Map<String, dynamic>;
  }

  // --- Legacy scan flow (kept for QR-card schools) ---

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
