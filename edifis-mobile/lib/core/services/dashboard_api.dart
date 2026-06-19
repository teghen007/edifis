import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class MeInfo {
  final String userId, name, role, email, schoolName;
  MeInfo({required this.userId, required this.name, required this.role,
    required this.email, required this.schoolName});
  factory MeInfo.fromJson(Map<String, dynamic> j) => MeInfo(
    userId: j['user_id'] ?? '', name: j['name'] ?? '', role: j['role'] ?? '',
    email: j['email'] ?? '', schoolName: j['school_name'] ?? '');
}

class DashboardCard {
  final String key, label, value, icon;
  DashboardCard({required this.key, required this.label, required this.value, required this.icon});
  factory DashboardCard.fromJson(Map<String, dynamic> j) => DashboardCard(
    key: j['key'] ?? '', label: j['label'] ?? '', value: j['value'] ?? '', icon: j['icon'] ?? '');
}

class DashboardApi {
  final Dio _dio;
  DashboardApi(this._dio);
  Future<MeInfo> me() async => MeInfo.fromJson((await _dio.get('/me')).data);
  Future<List<DashboardCard>> summary() async {
    final data = (await _dio.get('/dashboard/summary')).data;
    return ((data['cards'] ?? []) as List)
        .map((e) => DashboardCard.fromJson(e as Map<String, dynamic>)).toList();
  }
}

final dashboardApiProvider = Provider<DashboardApi>((ref) => DashboardApi(ref.read(dioProvider)));
final meProvider = FutureProvider<MeInfo>((ref) => ref.read(dashboardApiProvider).me());
final dashboardSummaryProvider =
    FutureProvider<List<DashboardCard>>((ref) => ref.read(dashboardApiProvider).summary());
