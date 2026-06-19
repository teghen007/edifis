import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class ParentApi {
  final Dio _dio;
  ParentApi(this._dio);

  Future<List<dynamic>> children() async => (await _dio.get('/parent/children')).data as List;
  Future<Map<String, dynamic>> results(String id) async =>
      (await _dio.get('/parent/children/$id/results')).data as Map<String, dynamic>;
  Future<Map<String, dynamic>> attendance(String id) async =>
      (await _dio.get('/parent/children/$id/attendance')).data as Map<String, dynamic>;
  Future<dynamic> balance(String id) async =>
      (await _dio.get('/parent/children/$id/balance')).data;
  Future<List<dynamic>> calendar() async => (await _dio.get('/parent/calendar')).data as List;
}

final parentApiProvider = Provider<ParentApi>((ref) => ParentApi(ref.read(dioProvider)));
final childrenProvider = FutureProvider<List<dynamic>>((ref) => ref.read(parentApiProvider).children());
final childResultsProvider = FutureProvider.family<Map<String, dynamic>, String>(
    (ref, id) => ref.read(parentApiProvider).results(id));
final childAttendanceProvider = FutureProvider.family<Map<String, dynamic>, String>(
    (ref, id) => ref.read(parentApiProvider).attendance(id));
final childBalanceProvider = FutureProvider.family<dynamic, String>(
    (ref, id) => ref.read(parentApiProvider).balance(id));
