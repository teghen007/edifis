import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/services/parent_api.dart';

class ParentDashboardScreen extends ConsumerWidget {
  const ParentDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.read(parentApiProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('EDIFIS Parent')),
      body: FutureBuilder<List<dynamic>>(
        future: api.getChildren(),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError) {
            return Center(child: Text('Error: ${snapshot.error}'));
          }

          final children = snapshot.data ?? [];

          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: children.length + 2,
            itemBuilder: (context, index) {
              if (index == 0) {
                return const Padding(
                  padding: EdgeInsets.only(bottom: 12),
                  child: Text('My Children', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                );
              }

              if (index == children.length + 1) {
                return Padding(
                  padding: const EdgeInsets.only(top: 16),
                  child: OutlinedButton.icon(
                    onPressed: () {},
                    icon: const Icon(Icons.calendar_today),
                    label: const Text('School Calendar'),
                  ),
                );
              }

              final child = children[index - 1];
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.person, color: Colors.green),
                  title: Text('${child['given_name'] ?? ''} ${child['family_name'] ?? ''}'),
                  subtitle: Text('PEA ID: ${child['master_pea_id'] ?? '—'}'),
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => _ChildDetailScreen(
                          studentId: child['id'],
                          studentName: '${child['given_name']} ${child['family_name']}',
                        ),
                      ),
                    );
                  },
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _ChildDetailScreen extends ConsumerWidget {
  final String studentId;
  final String studentName;

  const _ChildDetailScreen({required this.studentId, required this.studentName});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.read(parentApiProvider);

    return Scaffold(
      appBar: AppBar(title: Text(studentName)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _DetailCard(
            title: 'Balance',
            future: api.getBalance(studentId),
            builder: (data) => Text('${data['balance']} XAF', style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
          ),
          const SizedBox(height: 12),
          _DetailCard(
            title: 'Results',
            future: api.getResults(studentId),
            builder: (data) => Text('Average: ${data['average']}/20', style: const TextStyle(fontSize: 18)),
          ),
          const SizedBox(height: 12),
          _DetailCard(
            title: 'Attendance',
            future: api.getAttendance(studentId),
            builder: (data) => Text('${data['attendance_events']} sessions attended'),
          ),
        ],
      ),
    );
  }
}

class _DetailCard extends StatelessWidget {
  final String title;
  final Future<Map<String, dynamic>> future;
  final Widget Function(Map<String, dynamic>) builder;

  const _DetailCard({required this.title, required this.future, required this.builder});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(color: Colors.green)),
            const SizedBox(height: 8),
            FutureBuilder<Map<String, dynamic>>(
              future: future,
              builder: (context, snapshot) {
                if (snapshot.hasData) return builder(snapshot.data!);
                if (snapshot.hasError) return Text('Error: ${snapshot.error}');
                return const CircularProgressIndicator();
              },
            ),
          ],
        ),
      ),
    );
  }
}
