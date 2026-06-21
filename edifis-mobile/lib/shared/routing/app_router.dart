import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/auth/auth_state.dart';
import '../../core/config/school_config.dart';
import '../../features/auth/login_screen.dart';
import '../../features/auth/parent_login_screen.dart';
import '../../features/auth/parent_otp_screen.dart';
import '../../features/onboarding/school_picker_screen.dart';
import '../../features/parent/parent_dashboard_screen.dart';
import '../../features/staff/staff_home_screen.dart';

import '../../features/staff/students_screen.dart';
import '../../features/staff/submit_mark_screen.dart';
import '../../features/staff/fees_screen.dart';
import '../../features/staff/take_attendance_screen.dart';
import '../../features/staff/timetable_screen.dart';
import '../../features/results/report_card_screen.dart';
import '../../features/results/results_screen.dart';
import '../../features/staff/marks_excel_screen.dart';
import '../../features/staff/enrollment_excel_screen.dart';

final _rootNavigatorKey = GlobalKey<NavigatorState>();

final routerProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    navigatorKey: _rootNavigatorKey,
    initialLocation: '/parent',
    redirect: (context, state) {
      final hasSchool = ref.read(schoolProvider) != null;
      final auth = ref.read(authProvider);
      final loc = state.matchedLocation;
      if (!hasSchool) return loc == '/onboarding' ? null : '/onboarding';
      const authFree = {'/login', '/parent-login', '/parent-otp'};
      if (auth == null) return authFree.contains(loc) ? null : '/login';
      if (loc == '/onboarding' || loc == '/login' || loc == '/parent-login' || loc == '/parent-otp') return '/${auth.role.jsonValue}';
      return null;
    },
    routes: [
      GoRoute(path: '/onboarding', builder: (c, s) => const SchoolPickerScreen()),
      GoRoute(path: '/login', builder: (c, s) => const LoginScreen()),
      GoRoute(path: '/parent-login', builder: (c, s) => const ParentLoginScreen()),
      GoRoute(path: '/parent-otp', builder: (c, s) => ParentOtpScreen(phone: s.extra as String)),
      GoRoute(path: '/parent', builder: (c, s) => const ParentDashboardScreen()),
      GoRoute(path: '/principal', builder: (c, s) => const StaffHomeScreen(roleName: 'Principal')),
      GoRoute(path: '/vice_principal', builder: (c, s) => const StaffHomeScreen(roleName: 'Vice Principal')),
      GoRoute(path: '/bursar', builder: (c, s) => const StaffHomeScreen(roleName: 'Bursar')),
      GoRoute(path: '/class_master', builder: (c, s) => const StaffHomeScreen(roleName: 'Class Master')),
      GoRoute(path: '/subject_teacher', builder: (c, s) => const StaffHomeScreen(roleName: 'Subject Teacher')),
      GoRoute(path: '/discipline_master', builder: (c, s) => const StaffHomeScreen(roleName: 'Discipline Master')),
      GoRoute(path: '/secretary', builder: (c, s) => const StaffHomeScreen(roleName: 'Secretary')),
      GoRoute(path: '/students', builder: (c, s) => const StudentsScreen()),
      GoRoute(path: '/submit-mark', builder: (c, s) => const SubmitMarkScreen()),
      GoRoute(path: '/fees', builder: (c, s) => const FeesScreen()),
      GoRoute(path: '/take-attendance', builder: (c, s) => const TakeAttendanceScreen()),
      GoRoute(path: '/timetable', builder: (c, s) => const TimetableScreen()),
      GoRoute(path: '/report-card', builder: (c, s) {
        final e = s.extra as Map<String, dynamic>? ?? {};
        return ReportCardScreen(studentId: e['id'] ?? '', studentName: e['name'] ?? '');
      }),
      GoRoute(path: '/results', builder: (c, s) => const ResultsScreen()),
      GoRoute(path: '/marks-excel', builder: (c, s) => const MarksExcelScreen()),
      GoRoute(path: '/enrollment-excel', builder: (c, s) => const EnrollmentExcelScreen()),
    ],
  );
});
