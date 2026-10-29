import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../models/schedule_model.dart';
import '../providers/schedule_provider.dart';
import 'class_attendance_screen.dart';

class ScheduleScreen extends StatefulWidget {
  const ScheduleScreen({super.key});

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  final List<({int day, String name})> _days = const [
    (day: 1, name: 'Senin'),
    (day: 2, name: 'Selasa'),
    (day: 3, name: 'Rabu'),
    (day: 4, name: 'Kamis'),
    (day: 5, name: 'Jumat'),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ScheduleProvider>().fetchSchedules();
    });
  }

  Color _parseHexColor(
    String? hexString, {
    Color fallback = AppConstants.colorPrimaryBase,
  }) {
    if (hexString == null || hexString.isEmpty) return fallback;
    try {
      final hex = hexString.replaceAll('#', '');
      if (hex.length == 6) {
        return Color(int.parse('FF$hex', radix: 16));
      }
    } catch (_) {}
    return fallback;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppConstants.colorBackground,
      appBar: AppBar(
        backgroundColor: AppConstants.colorSurface,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        title: const Text(
          'Jadwal Pelajaran',
          style: TextStyle(
            color: AppConstants.colorTextPrimary,
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(height: 1, color: AppConstants.colorBorder),
        ),
      ),
      body: Consumer<ScheduleProvider>(
        builder: (context, provider, child) {
          final isTeacher = provider.userRole == 'teacher';

          return Column(
            children: [
              // ── Day Selector Pills ──────────────────────────────────────────
              Container(
                color: AppConstants.colorSurface,
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: SizedBox(
                  height: 38,
                  child: ListView.separated(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    scrollDirection: Axis.horizontal,
                    itemCount: _days.length,
                    separatorBuilder: (_, _) => const SizedBox(width: 8),
                    itemBuilder: (context, index) {
                      final item = _days[index];
                      final isSelected = provider.selectedDay == item.day;

                      return InkWell(
                        onTap: () => provider.selectDay(item.day),
                        borderRadius: BorderRadius.circular(20),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color:
                                isSelected
                                    ? AppConstants.colorPrimaryBase
                                    : AppConstants.colorBackground,
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(
                              color:
                                  isSelected
                                      ? AppConstants.colorPrimaryBase
                                      : AppConstants.colorBorder,
                            ),
                          ),
                          child: Text(
                            item.name,
                            style: TextStyle(
                              color:
                                  isSelected
                                      ? Colors.white
                                      : AppConstants.colorTextSecondary,
                              fontSize: 13,
                              fontWeight:
                                  isSelected
                                      ? FontWeight.bold
                                      : FontWeight.w500,
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ),
              const Divider(height: 1, color: AppConstants.colorBorder),

              // ── Content ────────────────────────────────────────────────────
              Expanded(
                child: RefreshIndicator(
                  color: AppConstants.colorPrimaryBase,
                  backgroundColor: AppConstants.colorSurface,
                  onRefresh: () => provider.fetchSchedules(showLoading: false),
                  child: _buildBody(provider, isTeacher),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildBody(ScheduleProvider provider, bool isTeacher) {
    if (provider.isLoading) {
      return const Center(
        child: CircularProgressIndicator(color: AppConstants.colorPrimaryBase),
      );
    }

    if (provider.errorMessage != null && provider.schedules.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.calendar_today_outlined,
                size: 48,
                color: AppConstants.colorTextMuted,
              ),
              const SizedBox(height: 12),
              Text(
                provider.errorMessage!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppConstants.colorTextSecondary),
              ),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: () => provider.fetchSchedules(),
                icon: const Icon(Icons.refresh_rounded, size: 16),
                label: const Text('Coba Lagi'),
              ),
            ],
          ),
        ),
      );
    }

    if (provider.schedules.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: AppConstants.colorPrimaryLight,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.event_busy_rounded,
                  size: 36,
                  color: AppConstants.colorPrimaryBase,
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Tidak Ada Jadwal Pelajaran',
                style: TextStyle(
                  color: AppConstants.colorTextPrimary,
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'Tidak ada agenda belajar atau mengajar yang terdaftar pada hari ini.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: AppConstants.colorTextSecondary,
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      itemCount: provider.schedules.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final schedule = provider.schedules[index];
        return _buildScheduleCard(schedule, isTeacher)
            .animate()
            .fadeIn(duration: 250.ms, delay: (index * 50).ms)
            .slideY(begin: 0.05, end: 0, duration: 250.ms);
      },
    );
  }

  Widget _buildScheduleCard(ScheduleItemModel item, bool isTeacher) {
    final accentColor = _parseHexColor(item.subject?.colorCode);

    return Container(
      decoration: BoxDecoration(
        color: AppConstants.colorSurface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppConstants.colorBorder),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // ── Colored Left Bar ───────────────────────────────────────────
              Container(width: 6, color: accentColor),

              // ── Card Details ───────────────────────────────────────────────
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Top Row: Code Badge & Time
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          if (item.subject?.code.isNotEmpty == true)
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 3,
                              ),
                              decoration: BoxDecoration(
                                color: accentColor.withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                item.subject!.code,
                                style: TextStyle(
                                  color: accentColor,
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          Row(
                            children: [
                              const Icon(
                                Icons.access_time_rounded,
                                size: 14,
                                color: AppConstants.colorTextMuted,
                              ),
                              const SizedBox(width: 4),
                              Text(
                                item.timeFormatted,
                                style: const TextStyle(
                                  color: AppConstants.colorTextSecondary,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),

                      // Subject Name
                      Text(
                        item.subject?.name ?? 'Mata Pelajaran',
                        style: const TextStyle(
                          color: AppConstants.colorTextPrimary,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 8),

                      // Info Rows: Teacher/Classroom & Room
                      Row(
                        children: [
                          // For student: show teacher; for teacher: show classroom
                          Icon(
                            isTeacher
                                ? Icons.groups_rounded
                                : Icons.person_rounded,
                            size: 15,
                            color: AppConstants.colorTextSecondary,
                          ),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              isTeacher
                                  ? 'Kelas: ${item.classroom?.name ?? '-'}'
                                  : (item.teacher?.name ?? 'Guru Pengajar'),
                              style: const TextStyle(
                                color: AppConstants.colorTextSecondary,
                                fontSize: 13,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                      if (item.room != null && item.room!.isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            const Icon(
                              Icons.meeting_room_outlined,
                              size: 15,
                              color: AppConstants.colorTextSecondary,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              'Ruang: ${item.room!}',
                              style: const TextStyle(
                                color: AppConstants.colorTextSecondary,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ],

                      // Teacher Action: Open Attendance Button
                      if (isTeacher) ...[
                        const SizedBox(height: 14),
                        const Divider(
                          height: 1,
                          color: AppConstants.colorBorder,
                        ),
                        const SizedBox(height: 12),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: () {
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder:
                                      (_) =>
                                          ClassAttendanceScreen(schedule: item),
                                ),
                              );
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppConstants.colorPrimaryBase,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 10),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(10),
                              ),
                            ),
                            icon: const Icon(
                              Icons.playlist_add_check_rounded,
                              size: 18,
                            ),
                            label: const Text(
                              'Buka Absensi Kelas',
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
