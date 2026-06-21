import 'dart:io';
import 'package:dio/dio.dart';
import 'package:file_selector/file_selector.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import '../../core/services/marks_api.dart';
import '../../core/services/results_api.dart';
import '../../core/network/dio_client.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';

class MarksExcelScreen extends ConsumerStatefulWidget {
  const MarksExcelScreen({super.key});
  @override
  ConsumerState<MarksExcelScreen> createState() => _MarksExcelScreenState();
}

class _MarksExcelScreenState extends ConsumerState<MarksExcelScreen> {
  String? _streamId, _subjectId, _termId, _testId;
  bool _busy = false;
  String? _error, _result;

  Future<void> _download() async {
    if (_streamId == null || _subjectId == null || _testId == null) {
      setState(() => _error = 'Select stream, subject and test.'); return;
    }
    setState(() { _busy = true; _error = null; });
    try {
      final dio = ref.read(dioProvider);
      final res = await dio.get('/marks/template',
        queryParameters: {'stream_id': _streamId, 'subject_id': _subjectId, 'test_id': _testId},
        options: Options(responseType: ResponseType.bytes));
      final dir = await getTemporaryDirectory();
      final f = File('${dir.path}/marksheet.xlsx');
      await f.writeAsBytes(List<int>.from(res.data));
      await OpenFilex.open(f.path);
    } on DioException catch (e) {
      setState(() => _error = e.response?.statusCode == 403
        ? 'You can only download sheets for your assigned subject + stream.'
        : 'Download failed. Check your connection.');
    } finally { if (mounted) setState(() => _busy = false); }
  }

  Future<void> _upload() async {
    setState(() { _busy = true; _error = null; _result = null; });
    try {
      const typeGroup = XTypeGroup(label: 'Excel', extensions: ['xlsx']);
      final file = await openFile(acceptedTypeGroups: [typeGroup]);
      if (file == null) { setState(() => _busy = false); return; }
      final path = file.path;
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(path, filename: 'marks.xlsx'),
      });
      final res = await ref.read(dioProvider).post('/marks/upload', data: form);
      final saved = res.data['saved'] ?? 0;
      final errors = (res.data['errors'] ?? []) as List;
      setState(() => _result = _busy ? null : 'Saved $saved marks');
      if (errors.isNotEmpty) {
        final details = errors.take(3).map((e) => 'Row ${e['row']}: ${e['reason']}').join('\n');
        setState(() => _result = 'Saved $saved. Errors:\n$details');
      }
    } on DioException {
      setState(() => _error = 'Upload failed.');
    } finally { if (mounted) setState(() => _busy = false); }
  }

  @override
  Widget build(BuildContext context) {
    final streams = ref.watch(streamsProvider);
    final subjects = ref.watch(subjectsProvider);
    final terms = ref.watch(termsWithTestsProvider);
    final tests = terms.maybeWhen(data: (t) {
      final sel = t.where((e) => e.id == _termId);
      return sel.isNotEmpty ? sel.first.tests : <TestRow>[];
    }, orElse: () => <TestRow>[]);

    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white, title: const Text('Mark Sheet (Excel)')),
      body: SingleChildScrollView(padding: const EdgeInsets.all(16), child: GlassCard(child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          streams.when(loading: () => const LinearProgressIndicator(),
            error: (_,__) => const SizedBox.shrink(),
            data: (s) => DropdownButtonFormField<String>(value: _streamId, decoration: const InputDecoration(labelText: 'Stream'),
              items: s.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
              onChanged: (v) => setState(() => _streamId = v))),
          const SizedBox(height: 12),
          subjects.when(loading: () => const LinearProgressIndicator(),
            error: (_,__) => const SizedBox.shrink(),
            data: (s) => DropdownButtonFormField<String>(value: _subjectId, decoration: const InputDecoration(labelText: 'Subject'),
              items: s.map((e) => DropdownMenuItem(value: e.id, child: Text('${e.name} (${e.code})'))).toList(),
              onChanged: (v) => setState(() => _subjectId = v))),
          const SizedBox(height: 12),
          terms.when(loading: () => const LinearProgressIndicator(),
            error: (_,__) => const SizedBox.shrink(),
            data: (t) => DropdownButtonFormField<String>(value: _termId, decoration: const InputDecoration(labelText: 'Term'),
              items: t.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
              onChanged: (v) => setState(() { _termId = v; _testId = null; }))),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(value: _testId, decoration: const InputDecoration(labelText: 'Test'),
            items: tests.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
            onChanged: (v) => setState(() => _testId = v)),
          if (_error != null) ...[const SizedBox(height: 10), Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13))],
          if (_result != null) ...[const SizedBox(height: 10), Text(_result!, style: const TextStyle(color: AppColors.success, fontSize: 13))],
          const SizedBox(height: 18),
          _busy ? const Center(child: CircularProgressIndicator()) : Column(children: [
            GlossyButton(label: 'Download template', icon: Icons.download, onTap: _download),
            const SizedBox(height: 12),
            GlossyButton(label: 'Upload filled sheet', icon: Icons.upload_file, onTap: _upload),
          ]),
        ])),
      ),
    );
  }
}
