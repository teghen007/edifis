import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

const _kSchoolKey = 'edifis_school_code';

final sharedPrefsProvider = Provider<SharedPreferences>(
  (ref) => throw UnimplementedError('sharedPrefsProvider must be overridden in main()'),
);

class SchoolNotifier extends Notifier<String?> {
  @override
  String? build() => ref.read(sharedPrefsProvider).getString(_kSchoolKey);

  Future<void> setSchool(String code) async {
    final c = code.trim().toLowerCase();
    await ref.read(sharedPrefsProvider).setString(_kSchoolKey, c);
    state = c;
  }

  Future<void> clear() async {
    await ref.read(sharedPrefsProvider).remove(_kSchoolKey);
    state = null;
  }
}

final schoolProvider = NotifierProvider<SchoolNotifier, String?>(SchoolNotifier.new);

String schoolBaseUrl(String code) => 'https://${code.trim().toLowerCase()}.myedifis.com';
