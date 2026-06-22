import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class SchoolProfile {
  final String name, schoolType, motto, logoUrl, currency, principalName, address, phone, email;
  SchoolProfile({
    this.name = '', this.schoolType = '', this.motto = '', this.logoUrl = '',
    this.currency = '', this.principalName = '', this.address = '', this.phone = '', this.email = '',
  });
  factory SchoolProfile.fromJson(Map<String, dynamic> j) => SchoolProfile(
    name: j['name'] ?? '', schoolType: j['school_type'] ?? '', motto: j['motto'] ?? '',
    logoUrl: j['logo_url'] ?? '', currency: j['currency'] ?? '', principalName: j['principal_name'] ?? '',
    address: j['address'] ?? '', phone: j['phone'] ?? '', email: j['email'] ?? '',
  );
  static final empty = SchoolProfile();
}

final schoolProfileProvider = FutureProvider<SchoolProfile>((ref) async {
  try {
    final r = await ref.read(dioProvider).get('/school/profile');
    return SchoolProfile.fromJson(r.data as Map<String, dynamic>);
  } catch (_) {
    return SchoolProfile.empty;
  }
});
