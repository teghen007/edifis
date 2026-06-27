import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import '../../core/network/dio_client.dart';
import '../../core/services/students_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';

/// Photo Day: walk the student list and capture each face with the camera
/// (or pick from the gallery). A ring marks who still needs a photo.
class PhotoDayScreen extends ConsumerStatefulWidget {
  const PhotoDayScreen({super.key});
  @override
  ConsumerState<PhotoDayScreen> createState() => _PhotoDayScreenState();
}

class _PhotoDayScreenState extends ConsumerState<PhotoDayScreen> {
  final _picker = ImagePicker();
  String _q = '';
  String? _busyId;
  bool _missingOnly = false;

  String _initials(String n) {
    final p = n.trim().split(RegExp(r'\s+'));
    return (p.isEmpty ? '?' : (p.first.isNotEmpty ? p.first[0] : '') + (p.length > 1 && p.last.isNotEmpty ? p.last[0] : '')).toUpperCase();
  }

  Future<void> _capture(StudentRow s, ImageSource source) async {
    try {
      final shot = await _picker.pickImage(
        source: source, maxWidth: 1000, maxHeight: 1000, imageQuality: 82,
      );
      if (shot == null) return;
      setState(() => _busyId = s.id);
      await uploadStudentPhoto(ref.read(dioProvider), s.id, shot.path);
      ref.invalidate(studentsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Saved ${s.name}\'s photo'), backgroundColor: AppColors.success));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Upload failed. Check your connection.'), backgroundColor: AppColors.danger));
      }
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  void _pickSource(StudentRow s) {
    showModalBottomSheet(
      context: context,
      builder: (_) => SafeArea(child: Column(mainAxisSize: MainAxisSize.min, children: [
        Padding(padding: const EdgeInsets.fromLTRB(20, 16, 20, 4),
          child: Align(alignment: Alignment.centerLeft,
            child: Text(s.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)))),
        ListTile(
          leading: const Icon(LucideIcons.camera, color: AppColors.blue600),
          title: const Text('Take photo'),
          onTap: () { Navigator.pop(context); _capture(s, ImageSource.camera); }),
        ListTile(
          leading: const Icon(LucideIcons.image, color: AppColors.blue600),
          title: const Text('Choose from gallery'),
          onTap: () { Navigator.pop(context); _capture(s, ImageSource.gallery); }),
        const SizedBox(height: 8),
      ])),
    );
  }

  @override
  Widget build(BuildContext context) {
    final students = ref.watch(studentsProvider);
    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.blue700, foregroundColor: Colors.white, title: const Text('Photo Day')),
      body: students.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(LucideIcons.wifiOff, size: 40, color: AppColors.muted),
          const SizedBox(height: 12),
          const Text("Couldn't load students.", style: TextStyle(color: AppColors.muted)),
          const SizedBox(height: 12),
          FilledButton(onPressed: () => ref.invalidate(studentsProvider), child: const Text('Retry')),
        ])),
        data: (all) {
          final withPhoto = all.where((s) => s.hasPhoto).length;
          var list = _q.isEmpty ? all
            : all.where((s) => s.name.toLowerCase().contains(_q.toLowerCase())).toList();
          if (_missingOnly) list = list.where((s) => !s.hasPhoto).toList();

          return Column(children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
              child: TextField(
                onChanged: (v) => setState(() => _q = v),
                decoration: const InputDecoration(
                  hintText: 'Search students', prefixIcon: Icon(LucideIcons.search)),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 16, 8),
              child: Row(children: [
                Expanded(child: Text('$withPhoto of ${all.length} have photos',
                  style: const TextStyle(color: AppColors.muted, fontSize: 13))),
                FilterChip(
                  label: const Text('Missing only'),
                  selected: _missingOnly,
                  onSelected: (v) => setState(() => _missingOnly = v)),
              ]),
            ),
            Expanded(child: list.isEmpty
              ? const Center(child: Text('No students found.', style: TextStyle(color: AppColors.muted)))
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
                  itemCount: list.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (c, i) {
                    final s = list[i];
                    final busy = _busyId == s.id;
                    return GlassCard(padding: const EdgeInsets.all(12), child: Row(children: [
                      _avatar(s, busy),
                      const SizedBox(width: 14),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(s.name, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink)),
                        Text(s.className.isEmpty ? 'No class set' : s.className,
                          style: const TextStyle(color: AppColors.muted, fontSize: 12.5)),
                      ])),
                      FilledButton.icon(
                        onPressed: busy ? null : () => _pickSource(s),
                        style: FilledButton.styleFrom(
                          backgroundColor: s.hasPhoto ? AppColors.blue400 : AppColors.blue600,
                          padding: const EdgeInsets.symmetric(horizontal: 14)),
                        icon: const Icon(LucideIcons.camera, size: 16),
                        label: Text(s.hasPhoto ? 'Retake' : 'Capture')),
                    ]));
                  }),
            ),
          ]);
        },
      ),
    );
  }

  Widget _avatar(StudentRow s, bool busy) {
    final ring = s.hasPhoto ? AppColors.success : AppColors.gold;
    return Container(
      width: 50, height: 50,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: ring, width: 2),
      ),
      padding: const EdgeInsets.all(2),
      child: busy
        ? const Center(child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)))
        : ClipOval(child: s.hasPhoto
            ? Image.network(s.photoUrl!, fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => _initialsBox(s))
            : _initialsBox(s)),
    );
  }

  Widget _initialsBox(StudentRow s) => Container(
    alignment: Alignment.center,
    decoration: const BoxDecoration(
      gradient: LinearGradient(colors: [AppColors.blue600, AppColors.blue400]),
      shape: BoxShape.circle),
    child: Text(_initials(s.name), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
  );
}
