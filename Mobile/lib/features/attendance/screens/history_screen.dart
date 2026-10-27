import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_constants.dart';
import '../models/attendance_model.dart';
import '../providers/attendance_provider.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen>
    with WidgetsBindingObserver {
  // Filter state
  int _selectedMonth = DateTime.now().month;
  int _selectedYear = DateTime.now().year;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _fetchHistory();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _fetchHistory();
    }
  }

  void _fetchHistory() {
    Provider.of<AttendanceProvider>(context, listen: false)
        .fetchHistory(month: _selectedMonth, year: _selectedYear);
  }

  /// Returns badge semantic color, bg color, and label based on attendance status logic
  ({Color color, Color bgColor, String label}) _resolveBadge(
    AttendanceModel attendance,
  ) {
    if (attendance.status == 'present') {
      if (attendance.isLate) {
        return (
          color: AppConstants.colorLate,
          bgColor: AppConstants.colorLateBg,
          label: 'TERLAMBAT',
        );
      }
      return (
        color: AppConstants.colorPresent,
        bgColor: AppConstants.colorPresentBg,
        label: 'HADIR TEPAT WAKTU',
      );
    }
    if (attendance.status == 'permission' || attendance.status == 'sick') {
      final label = attendance.status == 'sick' ? 'SAKIT' : 'IZIN';
      if (attendance.isApproved == null) {
        return (
          color: AppConstants.colorLate,
          bgColor: AppConstants.colorLateBg,
          label: 'MENUNGGU ($label)',
        );
      }
      if (attendance.isApproved == true) {
        return (
          color: AppConstants.colorPermission,
          bgColor: AppConstants.colorPermissionBg,
          label: 'DISETUJUI ($label)',
        );
      }
      return (
        color: AppConstants.colorAbsent,
        bgColor: AppConstants.colorAbsentBg,
        label: 'DITOLAK ($label)',
      );
    }
    return (
      color: AppConstants.colorAbsent,
      bgColor: AppConstants.colorAbsentBg,
      label: 'ALFA',
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppConstants.colorBackground,
      appBar: AppBar(
        title: const Text(
          'Riwayat Presensi',
          style: TextStyle(
            fontWeight: FontWeight.bold,
            color: AppConstants.colorTextPrimary,
            fontSize: 18,
          ),
        ),
        centerTitle: true,
        backgroundColor: AppConstants.colorSurface,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(height: 1, color: AppConstants.colorBorder),
        ),
      ),
      body: Column(
        children: [
          // ── Month/Year Filter bar ─────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              decoration: BoxDecoration(
                color: AppConstants.colorSurface,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppConstants.colorBorder),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF0F172A).withValues(alpha: 0.03),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                children: [
                  const Icon(Icons.calendar_month_rounded, color: AppConstants.colorPrimaryBase, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<int>(
                        value: _selectedMonth,
                        dropdownColor: AppConstants.colorSurface,
                        menuMaxHeight: 280,
                        isDense: true,
                        icon: const Icon(Icons.keyboard_arrow_down_rounded, color: AppConstants.colorTextSecondary),
                        style: const TextStyle(color: AppConstants.colorTextPrimary, fontWeight: FontWeight.bold, fontSize: 14),
                        items: List.generate(12, (i) {
                          final month = i + 1;
                          return DropdownMenuItem(
                            value: month,
                            child: Text(
                              DateFormat('MMMM', 'id_ID').format(DateTime(0, month)),
                            ),
                          );
                        }),
                        onChanged: (val) {
                          if (val != null) {
                            setState(() => _selectedMonth = val);
                            _fetchHistory();
                          }
                        },
                      ),
                    ),
                  ),
                  Container(width: 1, height: 28, color: AppConstants.colorBorder, margin: const EdgeInsets.symmetric(horizontal: 12)),
                  Expanded(
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<int>(
                        value: _selectedYear,
                        dropdownColor: AppConstants.colorSurface,
                        menuMaxHeight: 280,
                        isDense: true,
                        icon: const Icon(Icons.keyboard_arrow_down_rounded, color: AppConstants.colorTextSecondary),
                        style: const TextStyle(color: AppConstants.colorTextPrimary, fontWeight: FontWeight.bold, fontSize: 14),
                        items: List.generate(5, (i) {
                          final year = DateTime.now().year - i;
                          return DropdownMenuItem(
                            value: year,
                            child: Text('$year'),
                          );
                        }),
                        onChanged: (val) {
                          if (val != null) {
                            setState(() => _selectedYear = val);
                            _fetchHistory();
                          }
                        },
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // ── List ─────────────────────────────────────────────────────────
          Expanded(
            child: Consumer<AttendanceProvider>(
              builder: (context, provider, child) {
                if (provider.isLoading && provider.historyList.isEmpty) {
                  return const Center(
                    child: CircularProgressIndicator(color: AppConstants.colorPrimaryBase),
                  );
                }
                if (provider.errorMessage != null && provider.historyList.isEmpty) {
                  return Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.cloud_off_rounded, color: AppConstants.colorAbsent, size: 40),
                        const SizedBox(height: 8),
                        Text(
                          provider.errorMessage!,
                          style: const TextStyle(color: AppConstants.colorAbsent, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 12),
                        OutlinedButton(
                          onPressed: _fetchHistory,
                          child: const Text('Muat Ulang'),
                        ),
                      ],
                    ),
                  );
                }
                if (provider.historyList.isEmpty) {
                  return Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.event_busy_rounded, color: AppConstants.colorTextMuted, size: 48),
                        const SizedBox(height: 12),
                        const Text(
                          'Belum ada data riwayat bulan ini.',
                          style: TextStyle(color: AppConstants.colorTextSecondary, fontSize: 14, fontWeight: FontWeight.w500),
                        ),
                      ],
                    ),
                  );
                }

                return Stack(
                  children: [
                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 240),
                      child: ListView.builder(
                        key: ValueKey(
                          provider.historyList
                              .map((item) => '${item.id}:${item.status}:${item.isApproved}:${item.checkOutTime}')
                              .join('|'),
                        ),
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 120),
                        itemCount: provider.historyList.length,
                        itemBuilder: (context, index) {
                          final data = provider.historyList[index];
                          final badge = _resolveBadge(data);
                          final dateStr = DateFormat('dd MMMM yyyy', 'id_ID').format(data.recordedAt);
                          final dayStr = DateFormat('EEEE', 'id_ID').format(data.recordedAt);
                          final timeIn = DateFormat('HH:mm').format(data.recordedAt);
                          final timeOut = data.checkOutTime != null
                              ? DateFormat('HH:mm').format(data.checkOutTime!)
                              : null;

                          return Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Container(
                              padding: const EdgeInsets.all(16.0),
                              decoration: BoxDecoration(
                                color: AppConstants.colorSurface,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(color: AppConstants.colorBorder),
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(0xFF0F172A).withValues(alpha: 0.04),
                                    blurRadius: 6,
                                    offset: const Offset(0, 2),
                                  ),
                                ],
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  // Top row: Date and Status Badge
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            dateStr,
                                            style: const TextStyle(
                                              color: AppConstants.colorTextPrimary,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 15,
                                            ),
                                          ),
                                          Text(
                                            dayStr,
                                            style: const TextStyle(
                                              color: AppConstants.colorTextSecondary,
                                              fontSize: 12,
                                            ),
                                          ),
                                        ],
                                      ),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                                        decoration: BoxDecoration(
                                          color: badge.bgColor,
                                          borderRadius: BorderRadius.circular(20),
                                          border: Border.all(color: badge.color.withValues(alpha: 0.3)),
                                        ),
                                        child: Text(
                                          badge.label,
                                          style: TextStyle(
                                            color: badge.color,
                                            fontWeight: FontWeight.bold,
                                            fontSize: 11,
                                            letterSpacing: 0.4,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 14),
                                  const Divider(height: 1, color: AppConstants.colorBorderSubtle),
                                  const SizedBox(height: 14),

                                  // Bottom section: Time In, Time Out, and Proof Image
                                  Row(
                                    crossAxisAlignment: CrossAxisAlignment.center,
                                    children: [
                                      Expanded(
                                        child: Row(
                                          children: [
                                            // Masuk
                                            Expanded(
                                              child: Row(
                                                children: [
                                                  Container(
                                                    padding: const EdgeInsets.all(6),
                                                    decoration: BoxDecoration(
                                                      color: AppConstants.colorPresentBg,
                                                      borderRadius: BorderRadius.circular(8),
                                                    ),
                                                    child: const Icon(Icons.login_rounded, size: 16, color: AppConstants.colorPresent),
                                                  ),
                                                  const SizedBox(width: 8),
                                                  Column(
                                                    crossAxisAlignment: CrossAxisAlignment.start,
                                                    children: [
                                                      const Text('Masuk', style: TextStyle(color: AppConstants.colorTextSecondary, fontSize: 11)),
                                                      Text(timeIn, style: const TextStyle(color: AppConstants.colorTextPrimary, fontSize: 14, fontWeight: FontWeight.bold)),
                                                    ],
                                                  ),
                                                ],
                                              ),
                                            ),
                                            // Pulang
                                            Expanded(
                                              child: Row(
                                                children: [
                                                  Container(
                                                    padding: const EdgeInsets.all(6),
                                                    decoration: BoxDecoration(
                                                      color: AppConstants.colorAbsentBg,
                                                      borderRadius: BorderRadius.circular(8),
                                                    ),
                                                    child: const Icon(Icons.logout_rounded, size: 16, color: AppConstants.colorAbsent),
                                                  ),
                                                  const SizedBox(width: 8),
                                                  Column(
                                                    crossAxisAlignment: CrossAxisAlignment.start,
                                                    children: [
                                                      const Text('Pulang', style: TextStyle(color: AppConstants.colorTextSecondary, fontSize: 11)),
                                                      Text(
                                                        timeOut ?? '--:--',
                                                        style: TextStyle(
                                                          color: timeOut != null ? AppConstants.colorTextPrimary : AppConstants.colorTextMuted,
                                                          fontSize: 14,
                                                          fontWeight: FontWeight.bold,
                                                        ),
                                                      ),
                                                    ],
                                                  ),
                                                ],
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                      if (data.proofImage != null)
                                        GestureDetector(
                                          onTap: () => _showProofImage(context, _proofUrl(data)),
                                          child: Container(
                                            width: 48,
                                            height: 48,
                                            margin: const EdgeInsets.only(left: 8),
                                            decoration: BoxDecoration(
                                              color: AppConstants.colorBackground,
                                              borderRadius: BorderRadius.circular(10),
                                              border: Border.all(color: AppConstants.colorBorder),
                                              image: DecorationImage(
                                                image: CachedNetworkImageProvider(
                                                  _proofUrl(data),
                                                ),
                                                fit: BoxFit.cover,
                                              ),
                                            ),
                                          ),
                                        ),
                                    ],
                                  ),
                                  if (data.notes != null && data.notes!.isNotEmpty) ...[
                                    const SizedBox(height: 10),
                                    Container(
                                      width: double.infinity,
                                      padding: const EdgeInsets.all(10),
                                      decoration: BoxDecoration(
                                        color: AppConstants.colorBackground,
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: Text(
                                        'Catatan: ${data.notes!}',
                                        style: const TextStyle(
                                          color: AppConstants.colorTextSecondary,
                                          fontSize: 12,
                                          fontStyle: FontStyle.italic,
                                        ),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    if (provider.isLoading)
                      const Positioned(
                        top: 0,
                        left: 16,
                        right: 16,
                        child: LinearProgressIndicator(
                          minHeight: 2,
                          color: AppConstants.colorPrimaryBase,
                          backgroundColor: Colors.transparent,
                        ),
                      ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  String _proofUrl(AttendanceModel attendance) {
    return attendance.proofUrl ??
        '${AppConstants.storageBaseUrl}/${attendance.proofImage!}';
  }

  void _showProofImage(BuildContext context, String url) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.all(16),
        child: Stack(
          alignment: Alignment.center,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: CachedNetworkImage(
                imageUrl: url,
                fit: BoxFit.contain,
                errorWidget: (context, error, stackTrace) => Container(
                  color: AppConstants.colorSurface,
                  padding: const EdgeInsets.all(32),
                  child: const Text(
                    'Gagal memuat gambar bukti',
                    style: TextStyle(color: AppConstants.colorTextSecondary),
                    textAlign: TextAlign.center,
                  ),
                ),
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: CircleAvatar(
                backgroundColor: Colors.black54,
                child: IconButton(
                  icon: const Icon(Icons.close, color: Colors.white),
                  onPressed: () => Navigator.pop(context),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
