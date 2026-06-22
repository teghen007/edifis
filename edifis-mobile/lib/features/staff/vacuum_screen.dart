import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import '../../core/network/dio_client.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';

/// Principal VACUUM — ask the school anything in plain language.
class VacuumScreen extends ConsumerStatefulWidget {
  const VacuumScreen({super.key});
  @override
  ConsumerState<VacuumScreen> createState() => _VacuumScreenState();
}

class _VacuumScreenState extends ConsumerState<VacuumScreen> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  final _messages = <({bool fromUser, String text})>[];
  bool _loading = false;

  static const _starters = [
    'Which students owe the most fees?',
    'Who are my struggling students?',
    'Show top performers this term',
  ];

  @override
  void initState() {
    super.initState();
    _messages.add((fromUser: false,
      text: 'Hi Principal 👋  Ask me anything about your school — fees, results, attendance, who needs attention. I read live data and answer in plain language.'));
  }

  @override
  void dispose() { _input.dispose(); _scroll.dispose(); super.dispose(); }

  Future<void> _send(String text) async {
    final t = text.trim();
    if (t.isEmpty) return;
    final q = t.length > 500 ? t.substring(0, 500) : t;
    setState(() { _messages.add((fromUser: true, text: q)); _loading = true; });
    _input.clear();
    _scrollDown();
    try {
      final res = await ref.read(dioProvider).post('/vacuum/query', data: {'question': q});
      final answer = res.data['answer'] as String? ?? 'No answer.';
      setState(() => _messages.add((fromUser: false, text: answer)));
    } on DioException {
      setState(() => _messages.add((fromUser: false,
        text: 'Sorry, I couldn\'t reach the assistant. Please try again.')));
    } finally {
      if (mounted) { setState(() => _loading = false); _scrollDown(); }
    }
  }

  void _scrollDown() => Future.delayed(const Duration(milliseconds: 100), () {
    if (_scroll.hasClients) {
      _scroll.animateTo(_scroll.position.maxScrollExtent,
        duration: const Duration(milliseconds: 200), curve: Curves.easeOut);
    }
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white,
        title: const Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(LucideIcons.sparkles, size: 22), SizedBox(width: 8), Text('VACUUM — Ask your school')])),
      body: Column(children: [
        Expanded(child: ListView.builder(
          controller: _scroll, padding: const EdgeInsets.all(16),
          itemCount: _messages.length + (_loading ? 1 : 0),
          itemBuilder: (c, i) {
            if (i == _messages.length) return _typing();
            final m = _messages[i];
            return m.fromUser ? _userBubble(m.text) : _aiBubble(m.text);
          })),
        Padding(padding: const EdgeInsets.fromLTRB(12, 4, 12, 0), child: Wrap(spacing: 8, children: _starters.map((q) =>
          ActionChip(label: Text(q, style: const TextStyle(fontSize: 12)), onPressed: () => _send(q))).toList())),
        SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: Row(children: [
          Expanded(child: TextField(controller: _input, minLines: 1, maxLines: 3,
            textInputAction: TextInputAction.send, enabled: !_loading,
            onSubmitted: (_) => _send(_input.text),
            decoration: const InputDecoration(hintText: 'e.g. Which classes have low attendance?'))),
          const SizedBox(width: 8),
          SizedBox(height: 48, width: 48, child: _loading
            ? const Center(child: SizedBox(height: 24, width: 24, child: CircularProgressIndicator(strokeWidth: 2)))
            : IconButton(icon: const Icon(LucideIcons.send, color: AppColors.blue600),
                onPressed: () => _send(_input.text))),
        ]))),
      ]),
    );
  }

  Widget _userBubble(String t) => Align(alignment: Alignment.centerRight, child: Container(
    margin: const EdgeInsets.only(bottom: 10, left: 48), padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: AppColors.blue600, borderRadius: BorderRadius.circular(18)),
    child: Text(t, style: const TextStyle(color: Colors.white))));

  Widget _aiBubble(String t) => Align(alignment: Alignment.centerLeft, child: Container(
    margin: const EdgeInsets.only(bottom: 10, right: 48),
    child: GlassCard(padding: const EdgeInsets.all(14), child: Text(t, style: const TextStyle(color: AppColors.ink, height: 1.35)))));

  Widget _typing() => Align(alignment: Alignment.centerLeft, child: Container(
    margin: const EdgeInsets.only(bottom: 10, right: 48), padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: AppColors.bg, borderRadius: BorderRadius.circular(18)),
    child: const Row(mainAxisSize: MainAxisSize.min, children: [
      SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2)),
      SizedBox(width: 10), Text('Thinking...', style: TextStyle(color: AppColors.muted, fontSize: 13))])));
}
