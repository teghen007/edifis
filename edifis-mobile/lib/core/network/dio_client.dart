import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../config/school_config.dart';

final dioProvider = Provider<Dio>((ref) {
  final code = ref.watch(schoolProvider);
  final base = code != null ? '${schoolBaseUrl(code)}/api' : 'https://myedifis.com/api';
  final dio = Dio(BaseOptions(
    baseUrl: base,
    connectTimeout: const Duration(seconds: 10),
    receiveTimeout: const Duration(seconds: 30),
    headers: {'Accept': 'application/json'},
  ));
  dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
    final token = ref.read(sharedPrefsProvider).getString('auth_token');
    if (token != null) options.headers['Authorization'] = 'Bearer $token';
    handler.next(options);
  }));
  dio.interceptors.add(LogInterceptor(requestBody: true, responseBody: true));
  return dio;
});
