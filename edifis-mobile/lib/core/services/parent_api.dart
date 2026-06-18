import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

final parentApiProvider = Provider<ParentApi>((ref) => ParentApi(ref.read(dioProvider)));

class ParentApi {
  final Dio _dio;
  ParentApi(this._dio);

  Future<List<dynamic>> getChildren() async {
    final res = await _dio.get('/parent/children');
    return res.data;
  }

  Future<Map<String, dynamic>> getBalance(String studentId) async {
    final res = await _dio.get('/parent/children/$studentId/balance');
    return res.data;
  }

  Future<Map<String, dynamic>> getResults(String studentId) async {
    final res = await _dio.get('/parent/children/$studentId/results');
    return res.data;
  }

  Future<Map<String, dynamic>> getAttendance(String studentId) async {
    final res = await _dio.get('/parent/children/$studentId/attendance');
    return res.data;
  }

  Future<List<dynamic>> getCalendar() async {
    final res = await _dio.get('/parent/calendar');
    return res.data;
  }
}
