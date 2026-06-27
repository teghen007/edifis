import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class SeasonApi {
  final Ref _ref;
  SeasonApi(this._ref);

  Future<Map<String, dynamic>> current() async =>
      (await _ref.read(dioProvider).get('/season')).data as Map<String, dynamic>;

  Future<void> openNextSequence() async =>
      _ref.read(dioProvider).post('/season/sequence/next');

  Future<void> advanceTerm() async => _ref.read(dioProvider).post('/season/advance');

  Future<void> closeYear() async => _ref.read(dioProvider).post('/season/close-year');

  Future<void> reopenTerm(String termId) async =>
      _ref.read(dioProvider).post('/season/terms/$termId/reopen');
}

final seasonApiProvider = Provider<SeasonApi>((ref) => SeasonApi(ref));

final seasonProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.read(seasonApiProvider).current());
