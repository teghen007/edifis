import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class TtEntry {
  final String id, className, subjectName, teacherName, dayOfWeek, periodStart, periodEnd, room;
  final bool isApproved;
  TtEntry({required this.id, required this.className, required this.subjectName, required this.teacherName,
    required this.dayOfWeek, required this.periodStart, required this.periodEnd, required this.room, required this.isApproved});
  factory TtEntry.fromJson(Map<String, dynamic> j) => TtEntry(
    id: j['id'] ?? '', className: j['class_name'] ?? '', subjectName: j['subject_name'] ?? '',
    teacherName: j['teacher_name'] ?? '', dayOfWeek: '${j['day_of_week'] ?? ''}',
    periodStart: j['period_start'] ?? '', periodEnd: j['period_end'] ?? '', room: j['room'] ?? '',
    isApproved: j['is_approved'] ?? false);
}

final timetableProvider = FutureProvider<List<TtEntry>>((ref) async {
  final r = await ref.read(dioProvider).get('/timetable');
  return ((r.data ?? []) as List).map((e) => TtEntry.fromJson(e as Map<String, dynamic>)).toList();
});

class TimetableApi {
  final Ref _ref; TimetableApi(this._ref);
  Future<void> approve(String id) => _ref.read(dioProvider).post('/timetable/$id/approve');
}
final timetableApiProvider = Provider<TimetableApi>((ref) => TimetableApi(ref));
