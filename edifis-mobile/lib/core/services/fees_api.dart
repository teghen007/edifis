import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class BalanceRow {
  final String studentId, name, className, currency;
  final int balance;
  BalanceRow({required this.studentId, required this.name, required this.className,
    required this.balance, required this.currency});
  factory BalanceRow.fromJson(Map<String, dynamic> j) => BalanceRow(
    studentId: j['student_id'] ?? '', name: j['name'] ?? '', className: j['class_name'] ?? '',
    balance: (j['balance'] ?? 0) is int ? (j['balance'] as int) : int.tryParse('${j['balance']}') ?? 0,
    currency: j['currency'] ?? 'XAF');
}

final balancesProvider = FutureProvider<List<BalanceRow>>((ref) async {
  final r = await ref.read(dioProvider).get('/fees/balances');
  return ((r.data ?? []) as List).map((e) => BalanceRow.fromJson(e as Map<String, dynamic>)).toList();
});
