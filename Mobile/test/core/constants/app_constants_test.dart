import 'package:flutter_test/flutter_test.dart';
import 'package:kelasentra/core/constants/app_constants.dart';

void main() {
  group('AppConstants Transport Security (P0)', () {
    test('debug defaults use HTTP port 8000 and WS port 8080', () {
      final url = AppConstants.resolveBaseUrl(isRelease: false);
      final wsScheme = AppConstants.resolveReverbScheme(isRelease: false);
      final wsPort = AppConstants.resolveReverbPort(isRelease: false);

      expect(url, equals('http://192.168.0.104:8000/api/v1'));
      expect(wsScheme, equals('ws'));
      expect(wsPort, equals(8080));
    });

    test('release defaults enforce HTTPS and WSS with default port 443', () {
      final url = AppConstants.resolveBaseUrl(isRelease: true);
      final wsScheme = AppConstants.resolveReverbScheme(isRelease: true);
      final wsPort = AppConstants.resolveReverbPort(isRelease: true);

      expect(url, startsWith('https://'));
      expect(url, isNot(contains('http://')));
      expect(url, equals('https://api.kelasentra.sch.id/api/v1'));
      expect(wsScheme, equals('wss'));
      expect(wsPort, equals(443));
    });

    test('explicit customBaseUrl overrides resolved URL directly', () {
      final custom = 'https://custom.sekolah.sch.id/api/v1';
      final resolved = AppConstants.resolveBaseUrl(
        customBaseUrl: custom,
        isRelease: true,
      );

      expect(resolved, equals(custom));
    });

    test('custom host in release mode generates valid HTTPS URL without port 443 in path', () {
      final url = AppConstants.resolveBaseUrl(
        host: 'presensi.sekolah.sch.id',
        isRelease: true,
      );

      expect(url, equals('https://presensi.sekolah.sch.id/api/v1'));
      expect(url, startsWith('https://'));
    });

    test('non-standard HTTPS port includes port suffix', () {
      final url = AppConstants.resolveBaseUrl(
        host: 'api.sekolah.sch.id',
        isHttps: true,
        port: 8443,
        isRelease: true,
      );

      expect(url, equals('https://api.sekolah.sch.id:8443/api/v1'));
    });
  });
}
