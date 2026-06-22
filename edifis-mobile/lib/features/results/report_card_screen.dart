import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import '../../core/network/dio_client.dart';
import '../../core/services/results_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';

class ReportCardScreen extends ConsumerStatefulWidget {
  final String studentId, studentName;
  const ReportCardScreen({super.key, required this.studentId, required this.studentName});
  @override
  ConsumerState<ReportCardScreen> createState() => _ReportCardScreenState();
}

class _ReportCardScreenState extends ConsumerState<ReportCardScreen> {
  String? _termId;
  bool _downloading = false;

  Future<void> _downloadPdf() async {
    if (_termId == null) return;
    setState(() => _downloading = true);
    try {
      final dio = ref.read(dioProvider);
      final res = await dio.get('/results/report-card/pdf',
        queryParameters: {'student_id': widget.studentId, 'term_id': _termId},
        options: Options(responseType: ResponseType.bytes));
      final dir = await getTemporaryDirectory();
      final f = File('${dir.path}/report-card.pdf');
      await f.writeAsBytes(List<int>.from(res.data));
      await OpenFilex.open(f.path);
    } on DioException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(
          e.response?.statusCode == 404
            ? 'No results computed for this term yet.'
            : 'Could not download report card.')));
      }
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  Color _gradeColor(String g) {
    final c = g.toUpperCase();
    if (c.startsWith('A') || c.startsWith('B')) return AppColors.success;
    if (c.startsWith('C') || c.startsWith('D')) return AppColors.warning;
    return AppColors.danger;
  }

  @override
  Widget build(BuildContext context) {
    final terms = ref.watch(termsProvider);
    if (_termId == null) {
      terms.maybeWhen(data: (t) { if (t.isNotEmpty) _termId = t.first.id; }, orElse: () {});
    }
    final card = _termId != null ? ref.watch(reportCardProvider((widget.studentId, _termId!))) : null;

    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white, title: Text(widget.studentName)),
      body: SingleChildScrollView(padding: const EdgeInsets.all(16), child: Column(children: [
        terms.when(loading: () => const LinearProgressIndicator(), error: (_,__) => const SizedBox.shrink(),
          data: (t) => DropdownButtonFormField<String>(
            value: _termId, decoration: const InputDecoration(labelText: 'Term'),
            items: t.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
            onChanged: (v) => setState(() => _termId = v))),
        const SizedBox(height: 16),
        if (card == null) const CircularProgressIndicator()
        else card.when(loading: () => const CircularProgressIndicator(),
          error: (e,_) => const Text('Error loading report card', style: TextStyle(color: AppColors.danger)),
          data: (r) => Column(children: [
            GlassCard(child: Column(children: [
              Text(r.termName, style: const TextStyle(fontSize: 14, color: AppColors.muted)),
              const SizedBox(height: 4),
              Text(r.streamName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppColors.ink)),
              const SizedBox(height: 12),
              Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: [
                _stat('Average', r.overallAverage),
                _stat('Grade', r.grade, _gradeColor(r.grade)),
                _stat('Position', '${r.position}/${r.outOf}'),
              ]),
              if (r.mention.isNotEmpty) ...[
                const SizedBox(height: 10),
                _stat('Mention', r.mention, AppColors.gold),
              ],
              if (r.classAverage.isNotEmpty) ...[
                const SizedBox(height: 10),
                _stat('Class avg', r.classAverage, AppColors.blue600),
              ],
              if (r.conductGrade.isNotEmpty) ...[
                const SizedBox(height: 10),
                _stat('Conduct', r.conductGrade, AppColors.success),
                if (r.conductComment.isNotEmpty)
                  Padding(padding: const EdgeInsets.only(top: 4),
                    child: Text(r.conductComment, style: const TextStyle(fontSize: 12, color: AppColors.muted), textAlign: TextAlign.center)),
              ],
              const SizedBox(height: 16),
              _downloading
                ? const Padding(padding: EdgeInsets.all(8), child: CircularProgressIndicator())
                : GlossyButton(label: 'Download PDF', icon: Icons.picture_as_pdf, onTap: _downloadPdf),
            ])),
            if (r.aiRemark.isNotEmpty) ...[
              const SizedBox(height: 12),
              GlassCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Remark', style: TextStyle(fontSize: 11, color: AppColors.muted, fontWeight: FontWeight.w600, letterSpacing: .5)),
                const SizedBox(height: 4),
                Text(r.aiRemark, style: const TextStyle(fontSize: 13.5, color: AppColors.ink, height: 1.4)),
              ])),
            ],
            const SizedBox(height: 16),
            ...r.subjects.map((s) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: GlassCard(padding: const EdgeInsets.all(12), child: Row(children: [
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(s.subject, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink)),
                  Text('Coef ${s.coefficient}  ·  Total ${s.weighted}', style: const TextStyle(fontSize: 11.5, color: AppColors.muted)),
                  if (s.classAvg.isNotEmpty) Text('Class avg ${s.classAvg}', style: const TextStyle(fontSize: 11.5, color: AppColors.blue600)),
                  if (s.remark.isNotEmpty) Text(s.remark, style: const TextStyle(fontSize: 12, color: AppColors.muted)),
                ])),
                Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(color: _gradeColor(s.grade).withValues(alpha: .15), borderRadius: BorderRadius.circular(8)),
                  child: Text(s.average, style: TextStyle(color: _gradeColor(s.grade), fontWeight: FontWeight.bold))),
                const SizedBox(width: 12),
                Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: _gradeColor(s.grade).withValues(alpha: .1), borderRadius: BorderRadius.circular(8)),
                  child: Text(s.grade, style: TextStyle(color: _gradeColor(s.grade), fontWeight: FontWeight.w600, fontSize: 12))),
              ])),
            )),
          ]),
        ),
      ])),
    );
  }

  Widget _stat(String label, String value, [Color? color]) => Column(children: [
    Text(value, style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: color ?? AppColors.ink)),
    Text(label, style: const TextStyle(fontSize: 11, color: AppColors.muted)),
  ]);
}
