import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class TermRow { final String id, name; TermRow(this.id, this.name);
  factory TermRow.fromJson(Map<String,dynamic> j)=>TermRow(j['id']??'', j['name']??''); }
class StreamRow { final String id, name; StreamRow(this.id, this.name);
  factory StreamRow.fromJson(Map<String,dynamic> j)=>StreamRow(j['id']??'', (j['class_name']??j['name']??'').toString()); }
class SubjectResult { final String subject, average, grade, remark;
  SubjectResult(this.subject,this.average,this.grade,this.remark);
  factory SubjectResult.fromJson(Map<String,dynamic> j)=>SubjectResult(j['subject_name']??'', '${j['average']??''}', j['grade']??'', j['remark']??''); }
class ReportCard {
  final String studentName, streamName, termName, overallAverage, grade; final int position, outOf; final List<SubjectResult> subjects;
  ReportCard({required this.studentName,required this.streamName,required this.termName,required this.overallAverage,required this.grade,required this.position,required this.outOf,required this.subjects});
  factory ReportCard.fromJson(Map<String,dynamic> j)=>ReportCard(
    studentName:j['student_name']??'', streamName:j['stream_name']??'', termName:j['term_name']??'',
    overallAverage:'${j['overall_average']??''}', grade:j['grade']??'',
    position:(j['position']??0) is int?j['position']??0:int.tryParse('${j['position']}')??0,
    outOf:(j['out_of']??0) is int?j['out_of']??0:int.tryParse('${j['out_of']}')??0,
    subjects:((j['subjects']??[]) as List).map((e)=>SubjectResult.fromJson(e)).toList());
}
class MsStudent { final String name, overallAverage, grade; final int position; final Map<String,dynamic> marks;
  MsStudent(this.name,this.overallAverage,this.grade,this.position,this.marks);
  factory MsStudent.fromJson(Map<String,dynamic> j)=>MsStudent(j['name']??'', '${j['overall_average']??''}', j['grade']??'',
    (j['position']??0) is int?j['position']??0:int.tryParse('${j['position']}')??0, Map<String,dynamic>.from(j['marks']??{})); }
class Mastersheet { final String streamName, termName; final List<String> subjects; final List<MsStudent> students;
  Mastersheet(this.streamName,this.termName,this.subjects,this.students);
  factory Mastersheet.fromJson(Map<String,dynamic> j)=>Mastersheet(j['stream_name']??'', j['term_name']??'',
    List<String>.from(j['subjects']??[]), ((j['students']??[]) as List).map((e)=>MsStudent.fromJson(e)).toList()); }

final termsProvider = FutureProvider<List<TermRow>>((ref) async {
  final r = await ref.read(dioProvider).get('/terms');
  return ((r.data??[]) as List).map((e)=>TermRow.fromJson(e)).toList(); });
final streamsProvider = FutureProvider<List<StreamRow>>((ref) async {
  final r = await ref.read(dioProvider).get('/streams');
  return ((r.data??[]) as List).map((e)=>StreamRow.fromJson(e)).toList(); });
final reportCardProvider = FutureProvider.family<ReportCard, (String,String)>((ref, k) async {
  final r = await ref.read(dioProvider).get('/results/report-card', queryParameters: {'student_id':k.$1,'term_id':k.$2});
  return ReportCard.fromJson(r.data as Map<String,dynamic>); });
final mastersheetProvider = FutureProvider.family<Mastersheet, (String,String)>((ref, k) async {
  final r = await ref.read(dioProvider).get('/results/mastersheet', queryParameters: {'stream_id':k.$1,'term_id':k.$2});
  return Mastersheet.fromJson(r.data as Map<String,dynamic>); });

class ResultsApi { final Ref _ref; ResultsApi(this._ref);
  Future<Map<String,dynamic>> compute(String streamId, String termId) async =>
    (await _ref.read(dioProvider).post('/results/compute', data:{'stream_id':streamId,'term_id':termId})).data as Map<String,dynamic>; }
final resultsApiProvider = Provider<ResultsApi>((ref)=>ResultsApi(ref));
