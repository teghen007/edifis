import 'dart:io';
import 'package:dio/dio.dart';
import 'package:file_selector/file_selector.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import '../../core/network/dio_client.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';

class FeesExcelScreen extends ConsumerStatefulWidget {
  const FeesExcelScreen({super.key});
  @override
  ConsumerState<FeesExcelScreen> createState() => _FeesExcelScreenState();
}

class _FeesExcelScreenState extends ConsumerState<FeesExcelScreen> {
  bool _busy = false;
  String? _error, _result;

  Future<void> _download() async {
    setState(() { _busy = true; _error = null; _result = null; });
    try {
      final dio = ref.read(dioProvider);
      final res = await dio.get('/fees/template',
        options: Options(responseType: ResponseType.bytes));
      final dir = await getTemporaryDirectory();
      final f = File('${dir.path}/fees-sheet.xlsx');
      await f.writeAsBytes(List<int>.from(res.data));
      await OpenFilex.open(f.path);
    } on DioException {
      setState(() => _error = 'Download failed. Check your connection.');
    } finally { if (mounted) setState(() => _busy = false); }
  }

  Future<void> _upload() async {
    setState(() { _busy = true; _error = null; _result = null; });
    try {
      const typeGroup = XTypeGroup(label: 'Excel', extensions: ['xlsx']);
      final file = await openFile(acceptedTypeGroups: [typeGroup]);
      if (file == null) { setState(() => _busy = false); return; }
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(file.path, filename: 'fees.xlsx'),
      });
      final res = await ref.read(dioProvider).post('/fees/upload', data: form);
      final charged = res.data['charged_count'] ?? 0;
      final chargedTotal = res.data['charged_total'] ?? 0;
      final collected = res.data['collected_count'] ?? 0;
      final collectedTotal = res.data['collected_total'] ?? 0;
      final errors = (res.data['errors'] ?? []) as List;
      var msg = 'Charged $charged students ($chargedTotal XAF)\n'
        'Collected from $collected students ($collectedTotal XAF)';
      if (errors.isNotEmpty) {
        final details = errors.take(3).map((e) => 'Row ${e['row']}: ${e['reason']}').join('\n');
        msg = '$msg\nIssues:\n$details';
      }
      setState(() => _result = msg);
    } on DioException {
      setState(() => _error = 'Upload failed.');
    } finally { if (mounted) setState(() => _busy = false); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white,
        title: const Text('Fees (Excel)')),
      body: SingleChildScrollView(padding: const EdgeInsets.all(16), child: GlassCard(child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Text('Download the fees sheet, fill "Charge" to bill a student and/or "Payment" to record money collected, then upload it back.',
            style: TextStyle(color: AppColors.muted, fontSize: 13)),
          if (_error != null) ...[const SizedBox(height: 12), Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13))],
          if (_result != null) ...[const SizedBox(height: 12), Text(_result!, style: const TextStyle(color: AppColors.success, fontSize: 13))],
          const SizedBox(height: 18),
          _busy ? const Center(child: CircularProgressIndicator()) : Column(children: [
            GlossyButton(label: 'Download fees sheet', icon: Icons.download, onTap: _download),
            const SizedBox(height: 12),
            GlossyButton(label: 'Upload filled sheet', icon: Icons.upload_file, onTap: _upload),
          ]),
        ])),
      ),
    );
  }
}
