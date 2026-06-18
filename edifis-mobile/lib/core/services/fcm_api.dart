import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

final fcmApiProvider = Provider<FcmApi>((ref) => FcmApi(ref.read(dioProvider)));

class FcmApi {
  final Dio _dio;
  FcmApi(this._dio);

  Future<void> registerToken(String token) async {
    await _dio.post('/api/fcm/register', data: {
      'token': token,
      'device_name': 'flutter-android',
    });
  }
}
