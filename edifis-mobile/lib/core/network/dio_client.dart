import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final dioProvider = Provider<Dio>((ref) {
  final dio = Dio(BaseOptions(
    baseUrl: const String.fromEnvironment(
      'EDIFIS_API_BASE',
      defaultValue: 'https://pssnkwen.edifis.cm/api',
    ),
    connectTimeout: const Duration(seconds: 10),
    receiveTimeout: const Duration(seconds: 30),
    headers: {'Accept': 'application/json'},
  ));

  dio.interceptors.add(LogInterceptor(requestBody: true, responseBody: true));

  return dio;
});
