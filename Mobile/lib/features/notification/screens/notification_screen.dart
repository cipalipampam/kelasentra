import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/widgets/app_notice.dart';
import '../models/notification_model.dart';
import '../providers/notification_provider.dart';

class NotificationScreen extends StatefulWidget {
  const NotificationScreen({super.key});

  @override
  State<NotificationScreen> createState() => _NotificationScreenState();
}

class _NotificationScreenState extends State<NotificationScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<NotificationProvider>().fetchNotifications();
    });
  }

  IconData _getTypeIcon(String type) {
    return switch (type) {
      'attendance' => Icons.fingerprint_rounded,
      'announcement' => Icons.campaign_rounded,
      'schedule' => Icons.calendar_month_rounded,
      _ => Icons.notifications_rounded,
    };
  }

  Color _getTypeColor(String type) {
    return switch (type) {
      'attendance' => AppConstants.colorAbsent,
      'announcement' => AppConstants.colorPrimaryBase,
      'schedule' => AppConstants.colorPermission,
      _ => AppConstants.colorTextSecondary,
    };
  }

  Color _getTypeBgColor(String type) {
    return switch (type) {
      'attendance' => AppConstants.colorAbsentBg,
      'announcement' => AppConstants.colorPrimaryLight,
      'schedule' => AppConstants.colorPermissionBg,
      _ => const Color(0xFFF1F5F9),
    };
  }

  String _formatDateTime(DateTime? dateTime) {
    if (dateTime == null) return '';
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inMinutes < 60) {
      final mins = difference.inMinutes;
      return mins <= 1 ? 'Baru saja' : '$mins menit lalu';
    } else if (difference.inHours < 24 && dateTime.day == now.day) {
      return 'Hari ini, ${DateFormat('HH:mm').format(dateTime)} WIB';
    } else {
      return '${DateFormat('dd MMM yyyy, HH:mm', 'id_ID').format(dateTime)} WIB';
    }
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
          'Pusat Notifikasi',
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
        actions: [
          Consumer<NotificationProvider>(
            builder: (context, provider, _) {
              if (provider.notifications.isEmpty) return const SizedBox.shrink();
              return TextButton.icon(
                onPressed: () async {
                  final success = await provider.markAllAsRead();
                  if (!context.mounted) return;
                  if (success) {
                    AppNotice.show(
                      context,
                      'Semua notifikasi telah ditandai dibaca.',
                      type: AppNoticeType.success,
                    );
                  }
                },
                icon: const Icon(Icons.done_all_rounded, size: 16, color: AppConstants.colorPrimaryBase),
                label: const Text(
                  'Tandai Semua',
                  style: TextStyle(
                    color: AppConstants.colorPrimaryBase,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              );
            },
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: Consumer<NotificationProvider>(
        builder: (context, provider, child) {
          return Column(
            children: [
              // ── Filter Segment ─────────────────────────────────────────────
              Container(
                color: AppConstants.colorSurface,
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                child: Row(
                  children: [
                    _buildFilterChip(
                      label: 'Semua',
                      isActive: provider.activeFilter == 'all',
                      onTap: () => provider.fetchNotifications(filter: 'all'),
                    ),
                    const SizedBox(width: 10),
                    _buildFilterChip(
                      label: 'Belum Dibaca',
                      badgeCount: provider.unreadCount,
                      isActive: provider.activeFilter == 'unread',
                      onTap: () => provider.fetchNotifications(filter: 'unread'),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1, color: AppConstants.colorBorder),

              // ── List of Notifications ──────────────────────────────────────
              Expanded(
                child: RefreshIndicator(
                  color: AppConstants.colorPrimaryBase,
                  backgroundColor: AppConstants.colorSurface,
                  onRefresh: () => provider.fetchNotifications(showLoading: false),
                  child: _buildContent(provider),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildFilterChip({
    required String label,
    required bool isActive,
    int? badgeCount,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(
          color: isActive ? AppConstants.colorPrimaryBase : AppConstants.colorSurface,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isActive ? AppConstants.colorPrimaryBase : AppConstants.colorBorder,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              label,
              style: TextStyle(
                color: isActive ? Colors.white : AppConstants.colorTextSecondary,
                fontSize: 13,
                fontWeight: isActive ? FontWeight.bold : FontWeight.w500,
              ),
            ),
            if (badgeCount != null && badgeCount > 0) ...[
              const SizedBox(width: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: isActive ? Colors.white : AppConstants.colorAbsent,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  badgeCount.toString(),
                  style: TextStyle(
                    color: isActive ? AppConstants.colorPrimaryBase : Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildContent(NotificationProvider provider) {
    if (provider.isLoading) {
      return const Center(
        child: CircularProgressIndicator(color: AppConstants.colorPrimaryBase),
      );
    }

    if (provider.errorMessage != null && provider.notifications.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.wifi_off_rounded, size: 48, color: AppConstants.colorTextMuted),
              const SizedBox(height: 12),
              Text(
                provider.errorMessage!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppConstants.colorTextSecondary),
              ),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: () => provider.fetchNotifications(),
                icon: const Icon(Icons.refresh_rounded, size: 16),
                label: const Text('Coba Lagi'),
              ),
            ],
          ),
        ),
      );
    }

    if (provider.notifications.isEmpty) {
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
                  Icons.notifications_none_rounded,
                  size: 36,
                  color: AppConstants.colorPrimaryBase,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                provider.activeFilter == 'unread'
                    ? 'Tidak Ada Notifikasi Baru'
                    : 'Belum Ada Notifikasi',
                style: const TextStyle(
                  color: AppConstants.colorTextPrimary,
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                provider.activeFilter == 'unread'
                    ? 'Semua notifikasi penting telah Anda baca.'
                    : 'Pemberitahuan akademik dan sistem akan muncul di sini.',
                textAlign: TextAlign.center,
                style: const TextStyle(
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
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      itemCount: provider.notifications.length,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final item = provider.notifications[index];
        return _buildNotificationCard(item, provider)
            .animate()
            .fadeIn(duration: 250.ms, delay: (index * 40).ms)
            .slideY(begin: 0.05, end: 0, duration: 250.ms);
      },
    );
  }

  Widget _buildNotificationCard(AppNotificationModel item, NotificationProvider provider) {
    final icon = _getTypeIcon(item.type);
    final color = _getTypeColor(item.type);
    final bgColor = _getTypeBgColor(item.type);

    return InkWell(
      onTap: () {
        if (!item.isRead) {
          provider.markAsRead(item.id);
        }
      },
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: item.isRead ? AppConstants.colorSurface : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: item.isRead ? AppConstants.colorBorder : AppConstants.colorPrimaryBase.withValues(alpha: 0.3),
            width: item.isRead ? 1 : 1.5,
          ),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0F172A).withValues(alpha: 0.03),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Icon Pill
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: bgColor,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: 12),

            // Content
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          item.title,
                          style: TextStyle(
                            color: AppConstants.colorTextPrimary,
                            fontSize: 14,
                            fontWeight: item.isRead ? FontWeight.w600 : FontWeight.bold,
                          ),
                        ),
                      ),
                      if (!item.isRead)
                        Container(
                          width: 8,
                          height: 8,
                          margin: const EdgeInsets.only(left: 6),
                          decoration: const BoxDecoration(
                            color: AppConstants.colorPrimaryBase,
                            shape: BoxShape.circle,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    item.body,
                    style: TextStyle(
                      color: item.isRead ? AppConstants.colorTextSecondary : AppConstants.colorTextPrimary,
                      fontSize: 13,
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _formatDateTime(item.createdAt),
                    style: const TextStyle(
                      color: AppConstants.colorTextMuted,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
