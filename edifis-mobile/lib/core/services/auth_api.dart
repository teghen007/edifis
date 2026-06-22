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

  /// Parent phone login.
  Future<Map<String, dynamic>> parentLogin(String phone, String credential,
      {String? deviceToken, String deviceName = 'EDIFIS App'}) async {
    final body = <String, dynamic>{
      'phone': phone, 'credential': credential, 'device_name': deviceName,
    };
    if (deviceToken != null) body['device_token'] = deviceToken;
    final res = await _dio.post('/parent/login', data: body);
    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> verifyOtp(String phone, String code,
      {String deviceName = 'EDIFIS App'}) async {
    final res = await _dio.post('/parent/verify-otp',
      data: {'phone': phone, 'code': code, 'device_name': deviceName});
    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> setPin(String pin) async {
    final res = await _dio.post('/parent/set-pin', data: {'pin': pin});
    return res.data;
  }

  /// Exchange a verified Firebase ID token (Phone Auth) for an EDIFIS session.
  Future<Map<String, dynamic>> parentFirebaseLogin(String idToken,
      {String deviceName = 'EDIFIS App'}) async {
    final res = await _dio.post('/parent/firebase-login',
      data: {'id_token': idToken, 'device_name': deviceName});
    return res.data as Map<String, dynamic>;
  }
}
