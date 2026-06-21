import 'dart:io';
import 'package:dio/dio.dart';
import 'package:file_selector/file_selector.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import '../../core/services/results_api.dart';
import '../../core/network/dio_client.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';

class EnrollmentExcelScreen extends ConsumerStatefulWidget {
  const EnrollmentExcelScreen({super.key});
  @override
  ConsumerState<EnrollmentExcelScreen> createState() => _EnrollmentExcelScreenState();
}

class _EnrollmentExcelScreenState extends ConsumerState<EnrollmentExcelScreen> {
  String? _streamId;
  bool _busy = false;
  String? _error, _result;

  Future<void> _download() async {
    if (_streamId == null) {
      setState(() => _error = 'Select a class first.'); return;
    }
    setState(() { _busy = true; _error = null; _result = null; });
    try {
      final dio = ref.read(dioProvider);
      final res = await dio.get('/enrollment/template',
        queryParameters: {'stream_id': _streamId},
        options: Options(responseType: ResponseType.bytes));
      final dir = await getTemporaryDirectory();
      final f = File('${dir.path}/subject-enrollment.xlsx');
      await f.writeAsBytes(List<int>.from(res.data));
      await OpenFilex.open(f.path);
    } on DioException catch (e) {
      setState(() => _error = e.response?.statusCode == 403
        ? 'You can only manage enrollment for the class you master.'
        : 'Download failed. Check your connection.');
    } finally { if (mounted) setState(() => _busy = false); }
  }

  Future<void> _upload() async {
    setState(() { _busy = true; _error = null; _result = null; });
    try {
      const typeGroup = XTypeGroup(label: 'Excel', extensions: ['xlsx']);
      final file = await openFile(acceptedTypeGroups: [typeGroup]);
      if (file == null) { setState(() => _busy = false); return; }
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(file.path, filename: 'enrollment.xlsx'),
      });
      final res = await ref.read(dioProvider).post('/enrollment/upload', data: form);
      final added = res.data['added'] ?? 0;
      final removed = res.data['removed'] ?? 0;
      final errors = (res.data['errors'] ?? []) as List;
      var msg = 'Enrolled $added · removed $removed';
      if (errors.isNotEmpty) {
        final details = errors.take(3).map((e) => 'Row ${e['row']}: ${e['reason']}').join('\n');
        msg = '$msg\nIssues:\n$details';
      }
      setState(() => _result = msg);
    } on DioException catch (e) {
      setState(() => _error = e.response?.statusCode == 403
        ? 'You can only manage enrollment for the class you master.'
        : 'Upload failed.');
    } finally { if (mounted) setState(() => _busy = false); }
  }

  @override
  Widget build(BuildContext context) {
    final assignments = ref.watch(myAssignmentsProvider);

    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white,
        title: const Text('Subject Enrollment')),
      body: SingleChildScrollView(padding: const EdgeInsets.all(16), child: GlassCard(child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Text('Download your class sheet, mark "X" for each subject a student takes, then upload it back.',
            style: TextStyle(color: AppColors.muted, fontSize: 13)),
          const SizedBox(height: 16),
          assignments.when(
            loading: () => const LinearProgressIndicator(),
            error: (_,__) => const Text('Could not load your classes.', style: TextStyle(color: AppColors.danger, fontSize: 13)),
            data: (a) {
              if (a.masteredStreams.isEmpty) {
                return const Padding(padding: EdgeInsets.symmetric(vertical: 8),
                  child: Text('You are not set as class master for any class yet. Ask the admin.',
                    style: TextStyle(color: AppColors.muted, fontSize: 13)));
              }
              return DropdownButtonFormField<String>(initialValue: _streamId,
                decoration: const InputDecoration(labelText: 'Class'),
                items: a.masteredStreams.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
                onChanged: (v) => setState(() => _streamId = v));
            }),
          if (_error != null) ...[const SizedBox(height: 10), Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13))],
          if (_result != null) ...[const SizedBox(height: 10), Text(_result!, style: const TextStyle(color: AppColors.success, fontSize: 13))],
          const SizedBox(height: 18),
          _busy ? const Center(child: CircularProgressIndicator()) : Column(children: [
            GlossyButton(label: 'Download class sheet', icon: Icons.download, onTap: _download),
            const SizedBox(height: 12),
            GlossyButton(label: 'Upload filled sheet', icon: Icons.upload_file, onTap: _upload),
          ]),
        ])),
      ),
    );
  }
}
