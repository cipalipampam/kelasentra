import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import '../constants/app_constants.dart';

class WebSocketService with WidgetsBindingObserver {
  static final WebSocketService _instance = WebSocketService._internal();
  factory WebSocketService() => _instance;
  WebSocketService._internal() {
    WidgetsBinding.instance.addObserver(this);
  }

  PusherChannelsClient? _client;
  final List<Channel> _registeredChannels = [];
  Timer? _reconnectTimer;
  String? _token;
  int? _userId;
  int _reconnectAttempt = 0;
  int _connectionGeneration = 0;
  bool _isConnecting = false;
  bool _isManuallyDisconnected = false;

  // Callback functions for UI/Providers to listen to
  Function(Map<String, dynamic>)? onSessionInvalidated;
  final List<void Function(Map<String, dynamic>)> _announcementListeners = [];
  final List<void Function(Map<String, dynamic>)> _attendanceApprovalListeners = [];
  final List<void Function(Map<String, dynamic>)> _settingsListeners = [];
  final List<void Function(Map<String, dynamic>)> _notificationListeners = [];

  void addNotificationListener(void Function(Map<String, dynamic>) listener) {
    if (!_notificationListeners.contains(listener)) {
      _notificationListeners.add(listener);
    }
  }

  void removeNotificationListener(
      void Function(Map<String, dynamic>) listener) {
    _notificationListeners.remove(listener);
  }

  void addAttendanceApprovalListener(
      void Function(Map<String, dynamic>) listener) {
    if (!_attendanceApprovalListeners.contains(listener)) {
      _attendanceApprovalListeners.add(listener);
    }
  }

  void addAnnouncementListener(void Function(Map<String, dynamic>) listener) {
    if (!_announcementListeners.contains(listener)) {
      _announcementListeners.add(listener);
    }
  }

  void addSettingsListener(void Function(Map<String, dynamic>) listener) {
    if (!_settingsListeners.contains(listener)) {
      _settingsListeners.add(listener);
    }
  }

  Future<void> init({required String token, int? userId}) async {
    debugPrint("WebSocket: Initializing for user $userId...");

    _token = token;
    _userId = userId;
    _isManuallyDisconnected = false;
    _reconnectTimer?.cancel();
    _reconnectTimer = null;

    await _connect(replaceExisting: true);
  }

  Future<void> _connect({bool replaceExisting = false}) async {
    if (_token == null || _isConnecting || _isManuallyDisconnected) return;

    _isConnecting = true;
    final generation = ++_connectionGeneration;

    try {
      if (replaceExisting) {
        await _client?.disconnect();
        _client = null;
        _registeredChannels.clear();
      }

      final hostOptions = PusherChannelsOptions.fromHost(
        scheme: AppConstants.reverbScheme,
        host: AppConstants.reverbHost,
        port: AppConstants.reverbPort,
        key: AppConstants.reverbKey,
      );

      _client = PusherChannelsClient.websocket(
        options: hostOptions,
        connectionErrorHandler: (exception, trace, client) {
          debugPrint("WebSocket Connection Error: $exception");
          if (generation == _connectionGeneration) _scheduleReconnect();
        },
      );

      _client!.lifecycleStream.listen((state) {
        debugPrint("WebSocket Connection State: $state");
        if (generation != _connectionGeneration) return;

        final stateName = state.toString().toLowerCase();
        if (stateName.contains('connected')) {
          _reconnectAttempt = 0;
          _reconnectTimer?.cancel();
          _reconnectTimer = null;
        } else if (stateName.contains('disconnected') ||
            stateName.contains('failed')) {
          _scheduleReconnect();
        }
      });

      _client!.onConnectionEstablished.listen((_) {
        if (generation != _connectionGeneration) return;

        for (final channel in _registeredChannels) {
          channel.subscribeIfNotUnsubscribed();
        }
        debugPrint('WebSocket: private channels subscribed.');
      });

      // Register listeners before connecting. The package recommends subscribing
      // only after pusher:connection_established, handled above.
      if (_userId != null) {
        _registerPrivateChannel(
          "private-App.Models.User.$_userId",
          token: _token!,
        );
        _registerPrivateChannel("private-announcements", token: _token!);
        _registerPrivateChannel("private-settings", token: _token!);
      }

      await _client!.connect();
      debugPrint("WebSocket: Connect request sent!");
    } catch (e) {
      debugPrint("WebSocket Init Error: $e");
      _scheduleReconnect();
    } finally {
      _isConnecting = false;
    }
  }

  void _scheduleReconnect() {
    if (_isManuallyDisconnected || _token == null || _reconnectTimer != null) {
      return;
    }

    final exponent = _reconnectAttempt > 5 ? 5 : _reconnectAttempt;
    final seconds = 1 << exponent;
    _reconnectAttempt++;
    debugPrint('WebSocket: retrying in $seconds second(s).');
    _reconnectTimer = Timer(Duration(seconds: seconds), () {
      _reconnectTimer = null;
      unawaited(_connect(replaceExisting: true));
    });
  }

  void _registerPrivateChannel(String channelName, {required String token}) {
    if (_client == null) return;

    // Fix #5: Auth endpoint should be /broadcasting/auth (Laravel default),
    // NOT /api/broadcasting/auth (which doesn't exist).
    // The baseUrl includes /api/v1 so we build the auth URL explicitly from root.
    final rootUrl = AppConstants.rootUrl;

    final channel = _client!.privateChannel(
      channelName,
      authorizationDelegate:
          EndpointAuthorizableChannelTokenAuthorizationDelegate
              .forPrivateChannel(
        authorizationEndpoint: Uri.parse("$rootUrl/broadcasting/auth"),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded',
        },
      ),
    );

    _bindEvents(channel);
    _registeredChannels.add(channel);
  }

  void _bindEvents(Channel channel) {
    channel.bindToAll().listen((event) {
      debugPrint("WebSocket Raw Event: ${event.name} on ${event.channelName}");
      _handleEvent(event);
    });
  }

  void _handleEvent(ChannelReadEvent event) {
    if (event.data == null) return;

    try {
      final dataMap = event.tryGetDataAsMap();
      if (dataMap == null) return;

      // Laravel wraps event data in a nested 'data' key when using broadcastWith()
      final raw = dataMap.containsKey('data') ? dataMap['data'] : dataMap;
      if (raw is! Map) return;

      final data = Map<String, dynamic>.from(raw);

      // Normalize event name: strip namespace prefix (e.g. "App\Events\AttendanceLogged" → "AttendanceLogged")
      final eventName = event.name.split('.').last.split('\\').last;

      switch (eventName) {
        case 'AnnouncementChanged':
          debugPrint('WebSocket: announcement changed; refreshing dashboard.');
          for (final listener in _announcementListeners) {
            listener(data);
          }
          break;
        case 'AttendanceApproved':
          debugPrint('WebSocket: attendance approval received.');
          for (final listener in _attendanceApprovalListeners) {
            listener(data);
          }
          break;
        case 'SystemSettingsUpdated':
          debugPrint('WebSocket: settings changed; refreshing dependent data.');
          for (final listener in _settingsListeners) {
            listener(data);
          }
          break;
        case 'SessionInvalidated':
          debugPrint('WebSocket: session invalidated.');
          onSessionInvalidated?.call(data);
          break;
        case 'NotificationCreated':
          debugPrint('WebSocket: notification created event received.');
          for (final listener in _notificationListeners) {
            listener(data);
          }
          break;
        default:
          debugPrint("WebSocket: Unhandled event: ${event.name}");
      }
    } catch (e) {
      debugPrint("Error parsing WebSocket event: $e");
    }
  }

  Future<void> disconnect() async {
    _isManuallyDisconnected = true;
    _connectionGeneration++;
    _reconnectTimer?.cancel();
    _reconnectTimer = null;
    _reconnectAttempt = 0;
    _token = null;
    _userId = null;
    _registeredChannels.clear();
    await _client?.disconnect();
    _client = null;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed &&
        _token != null &&
        !_isManuallyDisconnected) {
      _reconnectTimer?.cancel();
      _reconnectTimer = null;
      unawaited(_connect(replaceExisting: true));
    }
  }
}
