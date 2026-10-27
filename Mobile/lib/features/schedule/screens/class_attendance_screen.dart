import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/widgets/app_notice.dart';
import '../models/class_attendance_model.dart';
import '../models/schedule_model.dart';
import '../providers/schedule_provider.dart';

class ClassAttendanceScreen extends StatefulWidget {
  final ScheduleItemModel schedule;

  const ClassAttendanceScreen({
    super.key,
    required this.schedule,
  });

  @override
  State<ClassAttendanceScreen> createState() => _ClassAttendanceScreenState();
}

class _ClassAttendanceScreenState extends State<ClassAttendanceScreen> {
  DateTime _selectedDate = DateTime.now();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadRoster();
    });
  }

  void _loadRoster() {
    final dateStr = DateFormat('yyyy-MM-dd').format(_selectedDate);
    context.read<ScheduleProvider>().fetchClassRoster(widget.schedule.id, date: dateStr);
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime.now().subtract(const Duration(days: 30)),
      lastDate: DateTime.now().add(const Duration(days: 1)),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: AppConstants.colorPrimaryBase,
              onPrimary: Colors.white,
              surface: AppConstants.colorSurface,
              onSurface: AppConstants.colorTextPrimary,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null && picked != _selectedDate) {
      setState(() {
        _selectedDate = picked;
      });
      _loadRoster();
    }
  }

  Color _getStatusColor(String status) {
    return switch (status) {
      'present' => AppConstants.colorPresent,
      'late' => AppConstants.colorLate,
      'sick' => AppConstants.colorSick,
      'permission' => AppConstants.colorPermission,
      'absent' => AppConstants.colorAbsent,
      _ => AppConstants.colorTextSecondary,
    };
  }

  Color _getStatusBgColor(String status) {
    return switch (status) {
      'present' => AppConstants.colorPresentBg,
      'late' => AppConstants.colorLateBg,
      'sick' => AppConstants.colorSickBg,
      'permission' => AppConstants.colorPermissionBg,
      'absent' => AppConstants.colorAbsentBg,
      _ => const Color(0xFFF1F5F9),
    };
  }

  void _showNotesDialog(ClassStudentAttendanceModel student) {
    final controller = TextEditingController(text: student.notes ?? '');

    showDialog(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          backgroundColor: AppConstants.colorSurface,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Text(
            'Catatan — ${student.name}',
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          content: TextField(
            controller: controller,
            maxLines: 3,
            decoration: InputDecoration(
              hintText: 'Misal: Izin ke UKS, terlambat 10 menit...',
              hintStyle: const TextStyle(fontSize: 13, color: AppConstants.colorTextMuted),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10),
                borderSide: const BorderSide(color: AppConstants.colorPrimaryBase),
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(),
              child: const Text('Batal', style: TextStyle(color: AppConstants.colorTextSecondary)),
            ),
            ElevatedButton(
              onPressed: () {
                context.read<ScheduleProvider>().updateStudentNotes(student.id, controller.text.trim());
                Navigator.of(ctx).pop();
              },
              child: const Text('Simpan'),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final formattedDate = DateFormat('EEEE, dd MMM yyyy', 'id_ID').format(_selectedDate);

    return Scaffold(
      backgroundColor: AppConstants.colorBackground,
      appBar: AppBar(
        backgroundColor: AppConstants.colorSurface,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.schedule.subject?.name ?? 'Absensi Mapel',
              style: const TextStyle(
                color: AppConstants.colorTextPrimary,
                fontSize: 17,
                fontWeight: FontWeight.bold,
              ),
            ),
            Text(
              'Kelas ${widget.schedule.classroom?.name ?? '-'} • ${widget.schedule.timeFormatted}',
              style: const TextStyle(
                color: AppConstants.colorTextSecondary,
                fontSize: 12,
              ),
            ),
          ],
        ),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(height: 1, color: AppConstants.colorBorder),
        ),
      ),
      body: Consumer<ScheduleProvider>(
        builder: (context, provider, child) {
          if (provider.isRosterLoading) {
            return const Center(
              child: CircularProgressIndicator(color: AppConstants.colorPrimaryBase),
            );
          }

          if (provider.rosterErrorMessage != null) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.error_outline_rounded, size: 48, color: AppConstants.colorAbsent),
                    const SizedBox(height: 12),
                    Text(
                      provider.rosterErrorMessage!,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: AppConstants.colorTextSecondary),
                    ),
                    const SizedBox(height: 16),
                    ElevatedButton.icon(
                      onPressed: _loadRoster,
                      icon: const Icon(Icons.refresh_rounded, size: 16),
                      label: const Text('Coba Lagi'),
                    ),
                  ],
                ),
              ),
            );
          }

          final roster = provider.rosterData;
          final students = roster?.students ?? [];

          // Counts
          final hadirCount = students.where((s) => s.status == 'present').length;
          final lateCount = students.where((s) => s.status == 'late').length;
          final izinCount = students.where((s) => s.status == 'permission').length;
          final sakitCount = students.where((s) => s.status == 'sick').length;
          final alfaCount = students.where((s) => s.status == 'absent').length;

          return Column(
            children: [
              // ── Top Header Controls (Date Picker & Quick Stats) ───────────
              Container(
                color: AppConstants.colorSurface,
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
                child: Column(
                  children: [
                    // Date picker bar & Set Semua Hadir
                    Row(
                      children: [
                        InkWell(
                          onTap: _pickDate,
                          borderRadius: BorderRadius.circular(10),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                            decoration: BoxDecoration(
                              color: AppConstants.colorPrimaryLight,
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: AppConstants.colorBorder),
                            ),
                            child: Row(
                              children: [
                                const Icon(
                                  Icons.calendar_today_rounded,
                                  size: 15,
                                  color: AppConstants.colorPrimaryBase,
                                ),
                                const SizedBox(width: 8),
                                Text(
                                  formattedDate,
                                  style: const TextStyle(
                                    color: AppConstants.colorPrimaryBase,
                                    fontSize: 12,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const Spacer(),
                        TextButton.icon(
                          onPressed: () => provider.setAllPresent(),
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                            backgroundColor: AppConstants.colorPresentBg,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          ),
                          icon: const Icon(Icons.check_circle_outline_rounded, size: 15, color: AppConstants.colorPresent),
                          label: const Text(
                            'Set Hadir Semua',
                            style: TextStyle(
                              color: AppConstants.colorPresent,
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // Quick Counters Row
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        _buildCounterChip('Hadir', hadirCount, AppConstants.colorPresent, AppConstants.colorPresentBg),
                        _buildCounterChip('Telat', lateCount, AppConstants.colorLate, AppConstants.colorLateBg),
                        _buildCounterChip('Izin', izinCount, AppConstants.colorPermission, AppConstants.colorPermissionBg),
                        _buildCounterChip('Sakit', sakitCount, AppConstants.colorSick, AppConstants.colorSickBg),
                        _buildCounterChip('Alfa', alfaCount, AppConstants.colorAbsent, AppConstants.colorAbsentBg),
                      ],
                    ),
                  ],
                ),
              ),
              const Divider(height: 1, color: AppConstants.colorBorder),

              // ── Students List ──────────────────────────────────────────────
              Expanded(
                child: students.isEmpty
                    ? const Center(
                        child: Text(
                          'Tidak ada siswa di kelas ini.',
                          style: TextStyle(color: AppConstants.colorTextSecondary),
                        ),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 14, 16, 100),
                        itemCount: students.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 10),
                        itemBuilder: (context, index) {
                          final student = students[index];
                          return _buildStudentCard(student, provider);
                        },
                      ),
              ),
            ],
          );
        },
      ),

      // ── Sticky Save Button ────────────────────────────────────────────────
      bottomSheet: Consumer<ScheduleProvider>(
        builder: (context, provider, _) {
          return Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppConstants.colorSurface,
              border: const Border(top: BorderSide(color: AppConstants.colorBorder)),
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFF0F172A).withValues(alpha: 0.06),
                  blurRadius: 12,
                  offset: const Offset(0, -4),
                ),
              ],
            ),
            child: SafeArea(
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: provider.isSavingAttendance
                      ? null
                      : () async {
                          final dateStr = DateFormat('yyyy-MM-dd').format(_selectedDate);
                          final result = await provider.saveClassAttendance(
                            widget.schedule.id,
                            dateStr,
                          );

                          if (!context.mounted) return;
                          AppNotice.show(
                            context,
                            result.message,
                            type: result.success ? AppNoticeType.success : AppNoticeType.error,
                          );

                          if (result.success) {
                            _loadRoster();
                          }
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppConstants.colorPrimaryBase,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: provider.isSavingAttendance
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                        )
                      : const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.save_rounded, size: 18),
                            SizedBox(width: 8),
                            Text(
                              'Simpan Presensi Mapel',
                              style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildCounterChip(String label, int count, Color color, Color bgColor) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            '$label: ',
            style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w500),
          ),
          Text(
            count.toString(),
            style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.bold),
          ),
        ],
      ),
    );
  }

  Widget _buildStudentCard(ClassStudentAttendanceModel student, ScheduleProvider provider) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppConstants.colorSurface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: student.isSaved ? AppConstants.colorPrimaryBase.withValues(alpha: 0.3) : AppConstants.colorBorder,
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.03),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Student Info Row
          Row(
            children: [
              // Avatar Initial
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: AppConstants.colorPrimaryLight,
                  borderRadius: BorderRadius.circular(8),
                ),
                alignment: Alignment.center,
                child: Text(
                  student.name.isNotEmpty ? student.name[0].toUpperCase() : 'S',
                  style: const TextStyle(
                    color: AppConstants.colorPrimaryBase,
                    fontWeight: FontWeight.bold,
                    fontSize: 15,
                  ),
                ),
              ),
              const SizedBox(width: 10),

              // Name & NIS
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      student.name,
                      style: const TextStyle(
                        color: AppConstants.colorTextPrimary,
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    Row(
                      children: [
                        if (student.nis != null)
                          Text(
                            'NIS: ${student.nis}',
                            style: const TextStyle(
                              color: AppConstants.colorTextMuted,
                              fontSize: 11,
                            ),
                          ),
                        if (student.isPrefilledFromDailyAttendance) ...[
                          const SizedBox(width: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                            decoration: BoxDecoration(
                              color: AppConstants.colorPermissionBg,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: const Text(
                              'Auto Izin Harian',
                              style: TextStyle(
                                color: AppConstants.colorPermission,
                                fontSize: 9,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),

              // Note Button
              IconButton(
                icon: Icon(
                  student.notes != null && student.notes!.isNotEmpty
                      ? Icons.note_alt_rounded
                      : Icons.note_add_outlined,
                  size: 18,
                  color: student.notes != null && student.notes!.isNotEmpty
                      ? AppConstants.colorPrimaryBase
                      : AppConstants.colorTextMuted,
                ),
                tooltip: 'Catatan Siswa',
                onPressed: () => _showNotesDialog(student),
              ),
            ],
          ),

          if (student.notes != null && student.notes!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: AppConstants.colorBorder),
              ),
              child: Text(
                'Catatan: ${student.notes}',
                style: const TextStyle(
                  color: AppConstants.colorTextSecondary,
                  fontSize: 11,
                  fontStyle: FontStyle.italic,
                ),
              ),
            ),
          ],

          const SizedBox(height: 10),

          // Status Selector Buttons (Hadir, Telat, Izin, Sakit, Alfa)
          Row(
            children: [
              _buildStatusOption(student, 'present', 'Hadir', provider),
              const SizedBox(width: 6),
              _buildStatusOption(student, 'late', 'Telat', provider),
              const SizedBox(width: 6),
              _buildStatusOption(student, 'permission', 'Izin', provider),
              const SizedBox(width: 6),
              _buildStatusOption(student, 'sick', 'Sakit', provider),
              const SizedBox(width: 6),
              _buildStatusOption(student, 'absent', 'Alfa', provider),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStatusOption(
    ClassStudentAttendanceModel student,
    String statusKey,
    String label,
    ScheduleProvider provider,
  ) {
    final isSelected = student.status == statusKey;
    final color = _getStatusColor(statusKey);
    final bgColor = _getStatusBgColor(statusKey);

    return Expanded(
      child: InkWell(
        onTap: () => provider.updateStudentStatus(student.id, statusKey),
        borderRadius: BorderRadius.circular(8),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 6),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: isSelected ? color : bgColor,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: isSelected ? color : color.withValues(alpha: 0.2),
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: isSelected ? Colors.white : color,
              fontSize: 11,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }
}
