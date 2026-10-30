import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

class AppConstants {
  // ponytail: dynamic remote-config discovery skipped; add when runtime multi-tenant URL switching required.

  /// Explicit base URL override via `--dart-define=API_BASE_URL=https://.../api/v1`.
  static const String _customBaseUrl = String.fromEnvironment('API_BASE_URL');

  /// Hostname for backend API and Reverb server.
  /// Debug defaults to local LAN IP for physical device testing.
  /// Release defaults to production domain or explicit `--dart-define=BACKEND_HOST=...`.
  static const String backendHost = String.fromEnvironment(
    'BACKEND_HOST',
    defaultValue: kReleaseMode ? 'api.kelasentra.sch.id' : '192.168.0.104',
  );

  /// Whether transport uses HTTPS/WSS.
  /// Automatically true in release mode; false in debug.
  /// Override with `--dart-define=USE_HTTPS=true/false`.
  static const bool useHttps = bool.fromEnvironment(
    'USE_HTTPS',
    defaultValue: kReleaseMode,
  );

  /// HTTP API port. Defaults to 443 in HTTPS/release mode, 8000 in debug.
  static const int apiPort = int.fromEnvironment(
    'API_PORT',
    defaultValue: kReleaseMode ? (useHttps ? 443 : 80) : 8000,
  );

  /// Resolves the API base URL with fallback and environment parameter overrides.
  static String resolveBaseUrl({
    String? customBaseUrl,
    bool? isHttps,
    String? host,
    int? port,
    bool isRelease = kReleaseMode,
  }) {
    final override = customBaseUrl ?? _customBaseUrl;
    if (override.isNotEmpty) {
      return override;
    }
    final https = isHttps ?? (bool.hasEnvironment('USE_HTTPS') ? useHttps : isRelease);
    final h = host ?? (bool.hasEnvironment('BACKEND_HOST') ? backendHost : (isRelease ? 'api.kelasentra.sch.id' : '192.168.0.104'));
    final p = port ?? (bool.hasEnvironment('API_PORT') ? apiPort : (isRelease ? (https ? 443 : 80) : 8000));
    final scheme = https ? 'https' : 'http';
    final portSuffix = (https && p == 443) || (!https && p == 80) ? '' : ':$p';
    return '$scheme://$h$portSuffix/api/v1';
  }

  /// Full base URL for API requests. Guaranteed HTTPS in release builds.
  static String get baseUrl => resolveBaseUrl();

  /// Root URL (without /api/v1) — used for storage URLs and broadcasting auth.
  static String get rootUrl =>
      baseUrl.replaceFirst(RegExp(r'/api/v1$'), '');

  /// Base URL for public storage assets: e.g. $storageBaseUrl/attendances/proof.jpg
  static String get storageBaseUrl => '$rootUrl/storage';

  // Broadcasting (Reverb) config
  static const String reverbKey = String.fromEnvironment(
    'REVERB_APP_KEY',
    defaultValue: 'local-key',
  );

  static const String reverbHost = String.fromEnvironment(
    'REVERB_HOST',
    defaultValue: backendHost,
  );

  /// Resolves WebSocket scheme. Defaults to `wss` in release / HTTPS mode, `ws` in debug.
  static String resolveReverbScheme({
    String? customScheme,
    bool? isHttps,
    bool isRelease = kReleaseMode,
  }) {
    if (customScheme != null && customScheme.isNotEmpty) {
      return customScheme;
    }
    const envScheme = String.fromEnvironment('REVERB_SCHEME');
    if (envScheme.isNotEmpty) {
      return envScheme;
    }
    final https = isHttps ?? (bool.hasEnvironment('USE_HTTPS') ? useHttps : isRelease);
    return https ? 'wss' : 'ws';
  }

  /// Active WebSocket scheme (`wss` in release, `ws` in debug).
  static String get reverbScheme => resolveReverbScheme();

  /// Resolves WebSocket port. Defaults to 443 in release / HTTPS mode, 8080 in debug.
  static int resolveReverbPort({
    int? customPort,
    bool? isHttps,
    bool isRelease = kReleaseMode,
  }) {
    if (customPort != null) {
      return customPort;
    }
    if (bool.hasEnvironment('REVERB_PORT')) {
      return const int.fromEnvironment('REVERB_PORT');
    }
    final https = isHttps ?? (bool.hasEnvironment('USE_HTTPS') ? useHttps : isRelease);
    return https ? 443 : 8080;
  }

  /// Active WebSocket port (443 in release, 8080 in debug).
  static int get reverbPort => resolveReverbPort();

  // SharedPreferences keys
  static const String tokenKey = 'AUTH_TOKEN_KEY';
  static const String userKey = 'AUTH_USER_KEY';

  // --- UI Theme: Modern Enterprise EdTech (Clean Slate & Royal Blue) ---
  static const Color colorPrimaryBase = Color(0xFF2563EB);   // Royal Blue 600
  static const Color colorPrimaryDark = Color(0xFF1E40AF);   // Royal Blue 800
  static const Color colorPrimaryLight = Color(0xFFEFF6FF);  // Blue 50
  static const Color colorSecondaryBase = Color(0xFF0F172A); // Slate 900

  // Canvas & Surfaces
  static const Color colorBackground = Color(0xFFF8FAFC);    // Slate 50
  static const Color colorSurface = Color(0xFFFFFFFF);       // Crisp White Card
  static const Color colorBorder = Color(0xFFE2E8F0);        // Slate 200
  static const Color colorBorderSubtle = Color(0xFFF1F5F9);  // Slate 100

  // Compatibility aliases for existing screens during transition
  static const Color colorBackgroundDark = Color(0xFFF8FAFC);
  static const Color colorCardDark = Color(0xFFFFFFFF);

  // Typography
  static const Color colorTextPrimary = Color(0xFF0F172A);   // Slate 900
  static const Color colorTextSecondary = Color(0xFF64748B); // Slate 500
  static const Color colorTextMuted = Color(0xFF94A3B8);     // Slate 400

  // Semantic Status Colors
  static const Color colorPresent = Color(0xFF059669);       // Emerald 600
  static const Color colorPresentBg = Color(0xFFECFDF5);     // Emerald 50
  static const Color colorLate = Color(0xFFD97706);          // Amber 600
  static const Color colorLateBg = Color(0xFFFFFBEB);        // Amber 50
  static const Color colorSick = Color(0xFF0284C7);          // Sky 600
  static const Color colorSickBg = Color(0xFFF0F9FF);        // Sky 50
  static const Color colorPermission = Color(0xFF4F46E5);    // Indigo 600
  static const Color colorPermissionBg = Color(0xFFEEF2FF);  // Indigo 50
  static const Color colorAbsent = Color(0xFFE11D48);        // Rose 600
  static const Color colorAbsentBg = Color(0xFFFFF1F2);      // Rose 50
}
