import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../features/auth/role_placeholder_screen.dart';
import '../../features/parent/parent_dashboard_screen.dart';
import '../../features/staff/staff_home_screen.dart';

final _rootNavigatorKey = GlobalKey<NavigatorState>();

final appRouter = GoRouter(
  navigatorKey: _rootNavigatorKey,
  initialLocation: '/parent',
  routes: [
    GoRoute(
      path: '/parent',
      builder: (context, state) => const ParentDashboardScreen(),
    ),
    GoRoute(
      path: '/principal',
      builder: (context, state) => const StaffHomeScreen(roleName: 'Principal'),
    ),
    GoRoute(
      path: '/vice_principal',
      builder: (context, state) => const StaffHomeScreen(roleName: 'Vice Principal'),
    ),
    GoRoute(
      path: '/bursar',
      builder: (context, state) => const StaffHomeScreen(roleName: 'Bursar'),
    ),
    GoRoute(
      path: '/class_master',
      builder: (context, state) => const StaffHomeScreen(roleName: 'Class Master'),
    ),
    GoRoute(
      path: '/subject_teacher',
      builder: (context, state) => const StaffHomeScreen(roleName: 'Subject Teacher'),
    ),
    GoRoute(
      path: '/discipline_master',
      builder: (context, state) => const StaffHomeScreen(roleName: 'Discipline Master'),
    ),
    GoRoute(
      path: '/secretary',
      builder: (context, state) => const StaffHomeScreen(roleName: 'Secretary'),
    ),
    GoRoute(
      path: '/login',
      builder: (context, state) => const RolePlaceholderScreen(roleName: 'Login'),
    ),
  ],
);
