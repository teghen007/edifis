import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../config/school_config.dart';
import '../services/auth_api.dart';
import '../services/dashboard_api.dart';
import 'app_role.dart';

class AuthData {
  final String token;
  final AppRole role;
  final String userId;
  final DateTime expiresAt;
  const AuthData({required this.token, required this.role,
      required this.userId, required this.expiresAt});
}

class AuthNotifier extends Notifier<AuthData?> {
  static const _kToken = 'auth_token', _kRole = 'auth_role',
      _kUser = 'auth_user', _kExp = 'auth_exp';
  SharedPreferences get _p => ref.read(sharedPrefsProvider);

  @override
  AuthData? build() {
    final t = _p.getString(_kToken), r = _p.getString(_kRole),
        u = _p.getString(_kUser), e = _p.getString(_kExp);
    if (t == null || r == null || u == null || e == null) return null;
    final exp = DateTime.tryParse(e);
    if (exp == null || exp.isBefore(DateTime.now())) { _clearPrefs(); return null; }
    return AuthData(token: t, role: AppRole.fromJson(r), userId: u, expiresAt: exp);
  }

  Future<void> login(String identifier, String password) async {
    final d = await ref.read(authApiProvider).login(identifier, password);
    final auth = AuthData(
      token: d['token'] as String,
      role: AppRole.fromJson(d['role'] as String),
      userId: d['user_id'] as String,
      expiresAt: DateTime.parse(d['expires_at'] as String),
    );
    await _p.setString(_kToken, auth.token);
    await _p.setString(_kRole, auth.role.jsonValue);
    await _p.setString(_kUser, auth.userId);
    await _p.setString(_kExp, auth.expiresAt.toIso8601String());
    state = auth;
  }

  Future<void> logout() async { await _clearPrefs(); state = null; }

  Future<void> setParentSession(String token, String? deviceToken, String phone) async {
    await _p.setString(_kToken, token);
    final me = await ref.read(dashboardApiProvider).me();
    final auth = AuthData(token: token, role: AppRole.fromJson(me.role),
        userId: me.userId, expiresAt: DateTime.now().add(const Duration(days: 30)));
    await _p.setString(_kRole, auth.role.jsonValue);
    await _p.setString(_kUser, auth.userId);
    await _p.setString(_kExp, auth.expiresAt.toIso8601String());
    if (deviceToken != null) await _p.setString('parent_device_$phone', deviceToken);
    state = auth;
  }

  String? parentDeviceToken(String phone) => _p.getString('parent_device_$phone');

  Future<void> _clearPrefs() async {
    await _p.remove(_kToken); await _p.remove(_kRole);
    await _p.remove(_kUser);  await _p.remove(_kExp);
  }
}

final authProvider = NotifierProvider<AuthNotifier, AuthData?>(AuthNotifier.new);
