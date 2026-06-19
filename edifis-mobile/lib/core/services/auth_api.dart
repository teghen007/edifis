import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

final authApiProvider = Provider<AuthApi>((ref) => AuthApi(ref.read(dioProvider)));

class AuthApi {
  final Dio _dio;
  AuthApi(this._dio);

  /// Staff/unified login. Returns { token, expires_at, role, user_id }.
  Future<Map<String, dynamic>> login(String identifier, String password) async {
    final res = await _dio.post('/auth/login',
      data: {'identifier': identifier, 'password': password});
    return res.data as Map<String, dynamic>;
  }

  /// Parent phone login (was `login`).
  Future<Map<String, dynamic>> parentLogin(String phone, String credential) async {
    final res = await _dio.post('/parent/login', data: {
      'phone': phone,
      'credential': credential,
    });
    return res.data;
  }

  Future<Map<String, dynamic>> verifyOtp(String phone, String code) async {
    final res = await _dio.post('/parent/verify-otp', data: {
      'phone': phone,
      'code': code,
    });
    return res.data;
  }

  Future<Map<String, dynamic>> setPin(String pin) async {
    final res = await _dio.post('/parent/set-pin', data: {'pin': pin});
    return res.data;
  }
}
