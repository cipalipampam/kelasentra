import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:latlong2/latlong.dart' as latlong;
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/widgets/app_notice.dart';
import '../providers/attendance_provider.dart';

enum PresensiType { hadir, izin, sakit }

class AttendanceScreen extends StatefulWidget {
  final VoidCallback? onNavigateToHistory;
  const AttendanceScreen({super.key, this.onNavigateToHistory});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen>
    with WidgetsBindingObserver {
  PresensiType _selectedPresensiType = PresensiType.hadir;
  
  Position? _currentPosition;
  String? _errorGps;
  
  final TextEditingController _keteranganController = TextEditingController();
  File? _buktiFile;
  final ImagePicker _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = Provider.of<AttendanceProvider>(context, listen: false);
      provider.fetchHistory();
      provider.fetchLocationSettings();
    });
    _fetchLocation();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      final provider = Provider.of<AttendanceProvider>(context, listen: false);
      provider.fetchLocationSettings();
      provider.fetchHistory();
      _fetchLocation();
    }
  }

  Future<void> _fetchLocation() async {
    if (_selectedPresensiType != PresensiType.hadir) return;
    
    if (mounted) setState(() => _errorGps = null);

    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) throw 'GPS tidak aktif. Mohon aktifkan lokasi pada perangkat.';

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) throw 'Izin lokasi ditolak.';
      }

      if (permission == LocationPermission.deniedForever) {
        throw 'Izin lokasi ditolak permanen. Buka pengaturan aplikasi.';
      }

      Position position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
        ),
      );

      if (!mounted) return;
      setState(() {
        _currentPosition = position;
      });
    } catch (e) {
      if (mounted) setState(() => _errorGps = e.toString());
    }
  }

  Future<void> _pickBukti() async {
    final XFile? pickedFile = await _picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1280,
      maxHeight: 1280,
      imageQuality: 75,
    );
    if (pickedFile != null) {
      setState(() {
        _buktiFile = File(pickedFile.path);
      });
    }
  }

  Future<void> _submitAttendance() async {
    final attendanceProvider = Provider.of<AttendanceProvider>(context, listen: false);

    bool success = false;

    if (_selectedPresensiType == PresensiType.hadir) {
      if (_currentPosition == null) {
        AppNotice.show(context, 'Lokasi GPS belum terkunci, harap tunggu atau nyalakan GPS.', type: AppNoticeType.error);
        return;
      }

      success = await attendanceProvider.checkIn(
        latitude: _currentPosition!.latitude,
        longitude: _currentPosition!.longitude,
        notes: _keteranganController.text,
        proofImage: _buktiFile,
      );
    } else {
      if (_keteranganController.text.trim().isEmpty) {
        AppNotice.show(context, 'Harap masukkan alasan izin atau sakit secara lengkap.', type: AppNoticeType.info);
        return;
      }

      if (_buktiFile == null) {
        AppNotice.show(context, 'Harap lampirkan foto surat dokter atau bukti pendukung.', type: AppNoticeType.info);
        return;
      }

      success = await attendanceProvider.submitPermission(
        status: _selectedPresensiType == PresensiType.izin ? 'permission' : 'sick',
        notes: _keteranganController.text,
        proofImage: _buktiFile,
      );
    }

    if (success && mounted) {
      AppNotice.show(context, 'Data presensi berhasil dikirim.', type: AppNoticeType.success);
      _keteranganController.clear();
      setState(() => _buktiFile = null);
    } else if (mounted) {
      AppNotice.show(
        context,
        attendanceProvider.errorMessage ?? 'Terjadi kesalahan sistem.',
        type: AppNoticeType.error,
      );
    }
  }

  Widget _buildBannerBlock({required IconData icon, required Color color, required Color bgColor, required String text}) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 24),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              text,
              style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }

  String _getTabName(PresensiType type) {
    if (type == PresensiType.hadir) return 'Hadir';
    if (type == PresensiType.izin) return 'Izin';
    return 'Sakit';
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _keteranganController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppConstants.colorBackground,
      appBar: AppBar(
        title: const Text(
          'Form Presensi',
          style: TextStyle(
            fontWeight: FontWeight.bold,
            color: AppConstants.colorTextPrimary,
            fontSize: 18,
          ),
        ),
        backgroundColor: AppConstants.colorSurface,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        centerTitle: true,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(height: 1, color: AppConstants.colorBorder),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 120),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Segmented Control (Pill Tabs)
            Container(
              padding: const EdgeInsets.all(5),
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
                children: PresensiType.values.map((type) {
                  final isSelected = _selectedPresensiType == type;
                  return Expanded(
                    child: GestureDetector(
                      onTap: () {
                        setState(() {
                          _selectedPresensiType = type;
                        });
                        if (type == PresensiType.hadir && _currentPosition == null) {
                          _fetchLocation();
                        }
                      },
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        curve: Curves.easeInOut,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        decoration: BoxDecoration(
                          color: isSelected ? AppConstants.colorPrimaryBase : Colors.transparent,
                          borderRadius: BorderRadius.circular(12),
                          boxShadow: isSelected
                              ? [
                                  BoxShadow(
                                    color: AppConstants.colorPrimaryBase.withValues(alpha: 0.25),
                                    blurRadius: 8,
                                    offset: const Offset(0, 3),
                                  )
                                ]
                              : [],
                        ),
                        child: Text(
                          _getTabName(type),
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: isSelected ? Colors.white : AppConstants.colorTextSecondary,
                            fontWeight: FontWeight.bold,
                            fontSize: 14,
                          ),
                        ),
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
            const SizedBox(height: 20),

            // MAP SECTION for Hadir
            if (_selectedPresensiType == PresensiType.hadir) ...[
              if (_currentPosition != null) ...[
                Consumer<AttendanceProvider>(
                  builder: (context, provider, child) {
                    return Container(
                      decoration: BoxDecoration(
                        color: AppConstants.colorSurface,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: AppConstants.colorBorder),
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFF0F172A).withValues(alpha: 0.04),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      padding: const EdgeInsets.all(8),
                      child: Column(
                        children: [
                          Container(
                            height: 220,
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(16),
                              child: FlutterMap(
                                options: MapOptions(
                                  initialCenter: provider.officeLat != null
                                    ? latlong.LatLng(provider.officeLat!, provider.officeLng!) 
                                    : latlong.LatLng(_currentPosition!.latitude, _currentPosition!.longitude),
                                  initialZoom: 16.0,
                                ),
                                children: [
                                  TileLayer(
                                    urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                                    userAgentPackageName: 'com.example.epresensi',
                                  ),
                                  if (provider.officeLat != null)
                                    CircleLayer(
                                      circles: [
                                        CircleMarker(
                                          point: latlong.LatLng(provider.officeLat!, provider.officeLng!),
                                          color: AppConstants.colorPrimaryBase.withValues(alpha: 0.15),
                                          borderStrokeWidth: 2,
                                          borderColor: AppConstants.colorPrimaryBase,
                                          useRadiusInMeter: true,
                                          radius: provider.officeRadius?.toDouble() ?? 50.0,
                                        ),
                                      ],
                                    ),
                                  MarkerLayer(
                                    markers: [
                                      Marker(
                                        point: latlong.LatLng(
                                          _currentPosition!.latitude,
                                          _currentPosition!.longitude,
                                        ),
                                        width: 44,
                                        height: 44,
                                        child: Container(
                                          decoration: BoxDecoration(
                                            color: AppConstants.colorAbsent,
                                            shape: BoxShape.circle,
                                            border: Border.all(color: Colors.white, width: 3),
                                            boxShadow: [
                                              BoxShadow(
                                                color: Colors.black.withValues(alpha: 0.2),
                                                blurRadius: 6,
                                              )
                                            ],
                                          ),
                                          child: const Icon(
                                            Icons.person,
                                            color: Colors.white,
                                            size: 22,
                                          ),
                                        ),
                                      ),
                                      if (provider.officeLat != null)
                                        Marker(
                                          point: latlong.LatLng(provider.officeLat!, provider.officeLng!),
                                          width: 44,
                                          height: 44,
                                          child: Container(
                                            decoration: BoxDecoration(
                                              color: AppConstants.colorPrimaryBase,
                                              shape: BoxShape.circle,
                                              border: Border.all(color: Colors.white, width: 3),
                                              boxShadow: [
                                                BoxShadow(
                                                  color: Colors.black.withValues(alpha: 0.2),
                                                  blurRadius: 6,
                                                )
                                              ],
                                            ),
                                            child: const Icon(
                                              Icons.school_rounded,
                                              color: Colors.white,
                                              size: 22,
                                            ),
                                          ),
                                        ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: 10),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                            decoration: BoxDecoration(
                              color: AppConstants.colorPresentBg,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: AppConstants.colorPresent.withValues(alpha: 0.3)),
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: const [
                                Icon(Icons.gps_fixed_rounded, color: AppConstants.colorPresent, size: 16),
                                SizedBox(width: 8),
                                Text(
                                  'Koordinat GPS Terkunci',
                                  style: TextStyle(
                                    color: AppConstants.colorPresent,
                                    fontWeight: FontWeight.bold,
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    );
                  }
                ),
              ] else if (_errorGps != null) ...[
                Container(
                  height: 180,
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: AppConstants.colorAbsentBg,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppConstants.colorAbsent.withValues(alpha: 0.3)),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.location_off_rounded, color: AppConstants.colorAbsent, size: 36),
                      const SizedBox(height: 10),
                      Text(
                        _errorGps!,
                        style: const TextStyle(color: AppConstants.colorAbsent, fontWeight: FontWeight.bold, fontSize: 13),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppConstants.colorAbsent,
                          side: const BorderSide(color: AppConstants.colorAbsent),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        onPressed: _fetchLocation,
                        icon: const Icon(Icons.refresh_rounded, size: 16),
                        label: const Text('Coba Lagi', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                      )
                    ],
                  ),
                )
              ] else ...[
                Container(
                  height: 180,
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: AppConstants.colorSurface,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppConstants.colorBorder),
                  ),
                  child: const Center(
                    child: CircularProgressIndicator(color: AppConstants.colorPrimaryBase),
                  ),
                )
              ],
              const SizedBox(height: 20),
            ],

            // Keterangan / Alasan
            Text(
              _selectedPresensiType == PresensiType.hadir ? 'Catatan Tambahan (Opsional)' : 'Keterangan Alasan (Wajib)',
              style: const TextStyle(
                color: AppConstants.colorTextPrimary,
                fontSize: 13,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _keteranganController,
              style: const TextStyle(color: AppConstants.colorTextPrimary, fontSize: 14),
              decoration: InputDecoration(
                hintText: _selectedPresensiType == PresensiType.hadir
                    ? 'Contoh: Masuk tepat waktu, tugas piket...'
                    : 'Contoh: Mengalami flu, izin keperluan keluarga...',
                hintStyle: const TextStyle(color: AppConstants.colorTextMuted, fontSize: 13),
                prefixIcon: const Icon(Icons.edit_note_rounded, color: AppConstants.colorTextSecondary),
                filled: true,
                fillColor: AppConstants.colorSurface,
                contentPadding: const EdgeInsets.all(16),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: const BorderSide(color: AppConstants.colorBorder),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: const BorderSide(color: AppConstants.colorBorder),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: const BorderSide(color: AppConstants.colorPrimaryBase, width: 2),
                ),
              ),
              maxLines: 3,
            ),
            const SizedBox(height: 16),

            // Upload Bukti
            Text(
              _selectedPresensiType == PresensiType.hadir ? 'Foto Bukti Kehadiran (Opsional)' : 'Foto Surat / Bukti (Wajib)',
              style: const TextStyle(
                color: AppConstants.colorTextPrimary,
                fontSize: 13,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: InkWell(
                    onTap: _pickBukti,
                    borderRadius: BorderRadius.circular(16),
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      decoration: BoxDecoration(
                        color: _buktiFile != null ? AppConstants.colorPresentBg : AppConstants.colorSurface,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: _buktiFile != null ? AppConstants.colorPresent : AppConstants.colorBorder,
                          width: 1.5,
                        ),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            _buktiFile != null ? Icons.check_circle_rounded : Icons.camera_alt_rounded,
                            color: _buktiFile != null ? AppConstants.colorPresent : AppConstants.colorTextSecondary,
                            size: 20,
                          ),
                          const SizedBox(width: 8),
                          Text(
                            _buktiFile != null ? 'Bukti Foto Terlampir' : 'Ambil / Unggah Foto',
                            style: TextStyle(
                              color: _buktiFile != null ? AppConstants.colorPresent : AppConstants.colorTextSecondary,
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                if (_buktiFile != null) ...[
                  const SizedBox(width: 12),
                  InkWell(
                    onTap: () => setState(() => _buktiFile = null),
                    borderRadius: BorderRadius.circular(16),
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppConstants.colorAbsentBg,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppConstants.colorAbsent.withValues(alpha: 0.4)),
                      ),
                      child: const Icon(Icons.delete_outline_rounded, color: AppConstants.colorAbsent, size: 20),
                    ),
                  )
                ]
              ],
            ),
            
            if (_buktiFile != null)
              Container(
                margin: const EdgeInsets.only(top: 14),
                height: 160,
                width: double.infinity,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppConstants.colorBorder),
                  image: DecorationImage(
                    image: FileImage(_buktiFile!),
                    fit: BoxFit.cover,
                  ),
                ),
              ),

            const SizedBox(height: 28),

            // BUTTONS & STATUS BANNERS
            Consumer<AttendanceProvider>(
              builder: (context, provider, child) {
                if (provider.isRejectedToday) {
                  return _buildBannerBlock(
                    icon: Icons.cancel_outlined,
                    color: AppConstants.colorAbsent,
                    bgColor: AppConstants.colorAbsentBg,
                    text: 'Permohonan Anda ditolak. Anda tercatat Alfa hari ini.',
                  );
                }
                if (provider.isPendingIzinSakitToday) {
                  return _buildBannerBlock(
                    icon: Icons.hourglass_top_rounded,
                    color: AppConstants.colorLate,
                    bgColor: AppConstants.colorLateBg,
                    text: 'Permohonan Izin/Sakit Anda sedang menunggu verifikasi Admin.',
                  );
                }
                if (provider.isIzinSakitApprovedToday) {
                  return _buildBannerBlock(
                    icon: Icons.check_circle_outline_rounded,
                    color: AppConstants.colorPresent,
                    bgColor: AppConstants.colorPresentBg,
                    text: 'Permohonan disetujui. Selamat beristirahat hari ini.',
                  );
                }
                if (provider.hasCheckedOutToday) {
                  return _buildBannerBlock(
                    icon: Icons.task_alt_rounded,
                    color: AppConstants.colorPrimaryBase,
                    bgColor: AppConstants.colorPrimaryLight,
                    text: 'Anda telah menyelesaikan absensi hari ini (Check-out tuntas).',
                  );
                }

                if (provider.hasCheckedInToday) {
                  return SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppConstants.colorAbsent,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      ),
                      onPressed: provider.isLoading ? null : () async {
                        final success = await provider.checkOut();
                        if (!context.mounted) return;

                        if (success) {
                          AppNotice.show(context, 'Berhasil melakukan absensi pulang', type: AppNoticeType.success);
                          widget.onNavigateToHistory?.call();
                        } else if (provider.errorMessage != null) {
                          AppNotice.show(context, provider.errorMessage!, type: AppNoticeType.error);
                        }
                      },
                      icon: const Icon(Icons.logout_rounded, size: 20),
                      label: provider.isLoading
                          ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Text(
                              'Absen Pulang (Check-Out)',
                              style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                            ),
                    ),
                  );
                }

                return SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppConstants.colorPrimaryBase,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    ),
                    onPressed: provider.isLoading ? null : _submitAttendance,
                    icon: const Icon(Icons.send_rounded, size: 20),
                    label: provider.isLoading
                        ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                        : Text(
                            'Kirim Presensi ${_getTabName(_selectedPresensiType)}',
                            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                          ),
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
