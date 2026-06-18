/// Eight staff roles + parent. NO student — minors do not have accounts (ADR-013).
/// Must match the contract's role enum in edifis-contracts/openapi exactly.
enum AppRole {
  principal,
  vicePrincipal,
  bursar,
  classMaster,
  subjectTeacher,
  disciplineMaster,
  secretary,
  parent;

  String get jsonValue {
    switch (this) {
      case AppRole.principal: return 'principal';
      case AppRole.vicePrincipal: return 'vice_principal';
      case AppRole.bursar: return 'bursar';
      case AppRole.classMaster: return 'class_master';
      case AppRole.subjectTeacher: return 'subject_teacher';
      case AppRole.disciplineMaster: return 'discipline_master';
      case AppRole.secretary: return 'secretary';
      case AppRole.parent: return 'parent';
    }
  }

  static AppRole fromJson(String value) {
    return AppRole.values.firstWhere(
      (r) => r.jsonValue == value,
      orElse: () => throw ArgumentError('Unknown role: $value'),
    );
  }

  String get displayName {
    switch (this) {
      case AppRole.principal: return 'Principal';
      case AppRole.vicePrincipal: return 'Vice Principal';
      case AppRole.bursar: return 'Bursar';
      case AppRole.classMaster: return 'Class Master';
      case AppRole.subjectTeacher: return 'Subject Teacher';
      case AppRole.disciplineMaster: return 'Discipline Master';
      case AppRole.secretary: return 'Secretary';
      case AppRole.parent: return 'Parent';
    }
  }
}
