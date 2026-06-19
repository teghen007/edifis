import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final dioProvider = Provider<Dio>((ref) {
  final dio = Dio(BaseOptions(
    baseUrl: const String.fromEnvironment(
      'EDIFIS_API_BASE',
      // Production: the live domain is myedifis.com (NOT edifis.cm).
      // Per-school build — override at build time with
      //   --dart-define=EDIFIS_API_BASE=https://<school>.myedifis.com/api
      defaultValue: 'https://pssnkwen.myedifis.com/api',
    ),
    connectTimeout: const Duration(seconds: 10),
    receiveTimeout: const Duration(seconds: 30),
    headers: {'Accept': 'application/json'},
  ));

  dio.interceptors.add(LogInterceptor(requestBody: true, responseBody: true));

  return dio;
});
