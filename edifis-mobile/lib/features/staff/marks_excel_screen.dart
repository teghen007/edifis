import 'dart:io';
import 'package:dio/dio.dart';
import 'package:file_selector/file_selector.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
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
  bool _downloading = false, _uploading = false;
  String? _error, _result;

  Future<void> _download() async {
    if (_streamId == null || _subjectId == null || _testId == null) {
      setState(() => _error = 'Pick class, subject and sequence first.'); return;
    }
    setState(() { _downloading = true; _error = null; _result = null; });
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
        ? 'You can only download sheets for your assigned subject + class.'
        : 'Download failed. Check your connection.');
    } finally { if (mounted) setState(() => _downloading = false); }
  }

  Future<void> _upload() async {
    setState(() { _uploading = true; _error = null; _result = null; });
    try {
      const typeGroup = XTypeGroup(label: 'Excel', extensions: ['xlsx']);
      final file = await openFile(acceptedTypeGroups: [typeGroup]);
      if (file == null) { setState(() => _uploading = false); return; }
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(file.path, filename: 'marks.xlsx'),
      });
      final res = await ref.read(dioProvider).post('/marks/upload', data: form);
      final saved = res.data['saved'] ?? 0;
      final errors = (res.data['errors'] ?? []) as List;
      var msg = '✓ Saved $saved marks';
      if (errors.isNotEmpty) {
        final details = errors.take(3).map((e) => 'Row ${e['row']}: ${e['reason']}').join('\n');
        msg = '$msg\nSome rows skipped:\n$details';
      }
      setState(() => _result = msg);
    } on DioException {
      setState(() => _error = 'Upload failed. Make sure it\'s the sheet you downloaded.');
    } finally { if (mounted) setState(() => _uploading = false); }
  }

  @override
  Widget build(BuildContext context) {
    final assignments = ref.watch(myAssignmentsProvider);
    final terms = ref.watch(termsWithTestsProvider);

    // Auto-detect: pre-pick the obvious choices so the teacher taps less.
    assignments.whenData((a) {
      if (_streamId == null && a.streams.length == 1) _streamId = a.streams.first.id;
      final subs = a.subjectsFor(_streamId);
      if (_subjectId == null && subs.length == 1) _subjectId = subs.first.id;
    });
    terms.whenData((t) {
      if (_termId == null && t.isNotEmpty) _termId = t.first.id;
      final sel = t.where((e) => e.id == _termId);
      final tests = sel.isNotEmpty ? sel.first.tests : <TestRow>[];
      if (_testId == null && tests.length == 1) _testId = tests.first.id;
    });

    final tests = terms.maybeWhen(data: (t) {
      final sel = t.where((e) => e.id == _termId);
      return sel.isNotEmpty ? sel.first.tests : <TestRow>[];
    }, orElse: () => <TestRow>[]);

    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white, title: const Text('Mark Sheet')),
      body: SingleChildScrollView(padding: const EdgeInsets.all(16), child: Column(children: [
        // ── Already filled a sheet? Upload first (zero setup) ──
        GlassCard(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            const Icon(LucideIcons.upload, color: AppColors.blue600, size: 20),
            const SizedBox(width: 8),
            const Expanded(child: Text('Already filled a sheet?', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.ink))),
          ]),
          const SizedBox(height: 4),
          const Text('Just upload it — we detect the class, subject and sequence from the file automatically.',
            style: TextStyle(color: AppColors.muted, fontSize: 12.5)),
          if (_result != null) ...[const SizedBox(height: 10), Text(_result!, style: const TextStyle(color: AppColors.success, fontSize: 13))],
          const SizedBox(height: 12),
          _uploading
            ? const Center(child: Padding(padding: EdgeInsets.all(6), child: CircularProgressIndicator()))
            : GlossyButton(label: 'Upload filled sheet', icon: Icons.upload_file, onTap: _upload),
        ])),

        const Padding(padding: EdgeInsets.symmetric(vertical: 16),
          child: Row(children: [Expanded(child: Divider()), Padding(padding: EdgeInsets.symmetric(horizontal: 8),
            child: Text('or get a new sheet', style: TextStyle(color: AppColors.muted, fontSize: 12))), Expanded(child: Divider())])),

        // ── Get a fresh template ──
        GlassCard(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Text('1.  Choose your class & sequence', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.ink)),
          const SizedBox(height: 12),
          assignments.when(
            loading: () => const LinearProgressIndicator(),
            error: (_,__) => const Text('Could not load your assignments.', style: TextStyle(color: AppColors.danger, fontSize: 13)),
            data: (a) {
              if (a.scoped && a.streams.isEmpty) {
                return const Text('You have no class/subject assignments yet. Ask the admin to assign you.',
                  style: TextStyle(color: AppColors.muted, fontSize: 13));
              }
              final subjectsForStream = a.subjectsFor(_streamId);
              return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                DropdownButtonFormField<String>(initialValue: _streamId, decoration: const InputDecoration(labelText: 'Class'),
                  items: a.streams.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
                  onChanged: (v) => setState(() { _streamId = v; _subjectId = null; })),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(initialValue: _subjectId, decoration: const InputDecoration(labelText: 'Subject'),
                  items: subjectsForStream.map((e) => DropdownMenuItem(value: e.id, child: Text('${e.name} (${e.code})'))).toList(),
                  onChanged: (v) => setState(() => _subjectId = v)),
              ]);
            }),
          const SizedBox(height: 12),
          terms.when(loading: () => const LinearProgressIndicator(),
            error: (_,__) => const SizedBox.shrink(),
            data: (t) => DropdownButtonFormField<String>(initialValue: _termId, decoration: const InputDecoration(labelText: 'Term'),
              items: t.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
              onChanged: (v) => setState(() { _termId = v; _testId = null; }))),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(initialValue: _testId, decoration: const InputDecoration(labelText: 'Sequence'),
            items: tests.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
            onChanged: (v) => setState(() => _testId = v)),
          if (_error != null) ...[const SizedBox(height: 10), Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13))],
          const SizedBox(height: 16),
          const Text('2.  Download, fill the Marks column, save', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.ink)),
          const SizedBox(height: 10),
          _downloading
            ? const Center(child: Padding(padding: EdgeInsets.all(6), child: CircularProgressIndicator()))
            : GlossyButton(label: 'Download sheet', icon: Icons.download, onTap: _download),
          const SizedBox(height: 6),
          const Text('Then come back up here and tap “Upload filled sheet”.',
            style: TextStyle(color: AppColors.muted, fontSize: 12)),
        ])),
      ])),
    );
  }
}
