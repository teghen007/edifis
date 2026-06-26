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

class Debtor {
  final String name;
  final int balance;
  Debtor(this.name, this.balance);
}

class FeesOverview {
  final int charged, collected, outstanding, debtorsCount;
  final List<Debtor> topDebtors;
  FeesOverview({required this.charged, required this.collected, required this.outstanding,
    required this.debtorsCount, required this.topDebtors});
  factory FeesOverview.fromJson(Map<String, dynamic> j) {
    int n(dynamic v) => v is int ? v : int.tryParse('${v ?? 0}') ?? 0;
    return FeesOverview(
      charged: n(j['charged_total']), collected: n(j['collected_total']),
      outstanding: n(j['outstanding_total']), debtorsCount: n(j['debtors_count']),
      topDebtors: ((j['top_debtors'] ?? []) as List)
        .map((e) => Debtor('${e['name'] ?? ''}', n(e['balance']))).toList());
  }
}

final feesOverviewProvider = FutureProvider<FeesOverview>((ref) async {
  final r = await ref.read(dioProvider).get('/fees/overview');
  return FeesOverview.fromJson(r.data as Map<String, dynamic>);
});
