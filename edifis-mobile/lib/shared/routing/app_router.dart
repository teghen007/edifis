import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/config/school_config.dart';
import '../../features/auth/role_placeholder_screen.dart';
import '../../features/onboarding/school_picker_screen.dart';
import '../../features/parent/parent_dashboard_screen.dart';
import '../../features/staff/staff_home_screen.dart';

final _rootNavigatorKey = GlobalKey<NavigatorState>();

final routerProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    navigatorKey: _rootNavigatorKey,
    initialLocation: '/parent',
    redirect: (context, state) {
      final hasSchool = ref.read(schoolProvider) != null;
      final atOnboarding = state.matchedLocation == '/onboarding';
      if (!hasSchool) return atOnboarding ? null : '/onboarding';
      if (atOnboarding) return '/login';
      return null;
    },
    routes: [
      GoRoute(path: '/onboarding', builder: (c, s) => const SchoolPickerScreen()),
      GoRoute(path: '/parent', builder: (c, s) => const ParentDashboardScreen()),
      GoRoute(path: '/principal', builder: (c, s) => const StaffHomeScreen(roleName: 'Principal')),
      GoRoute(path: '/vice_principal', builder: (c, s) => const StaffHomeScreen(roleName: 'Vice Principal')),
      GoRoute(path: '/bursar', builder: (c, s) => const StaffHomeScreen(roleName: 'Bursar')),
      GoRoute(path: '/class_master', builder: (c, s) => const StaffHomeScreen(roleName: 'Class Master')),
      GoRoute(path: '/subject_teacher', builder: (c, s) => const StaffHomeScreen(roleName: 'Subject Teacher')),
      GoRoute(path: '/discipline_master', builder: (c, s) => const StaffHomeScreen(roleName: 'Discipline Master')),
      GoRoute(path: '/secretary', builder: (c, s) => const StaffHomeScreen(roleName: 'Secretary')),
      GoRoute(path: '/login', builder: (c, s) => const RolePlaceholderScreen(roleName: 'Login')),
    ],
  );
});
