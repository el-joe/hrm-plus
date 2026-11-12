import 'dart:async';
import 'dart:math' as math;

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

enum LocationPermissionState { granted, denied, permanentlyDenied }

/// A GPS fix as sent to the server.
class LocationFix {
  const LocationFix({
    required this.latitude,
    required this.longitude,
    required this.accuracy,
    required this.isMocked,
    required this.timestamp,
  });

  final double latitude;
  final double longitude;

  /// Horizontal accuracy in metres reported by the OS.
  final double accuracy;

  /// Android reports mock providers; iOS reports simulated locations (iOS 15+).
  final bool isMocked;
  final DateTime timestamp;
}

/// GPS did not produce a fix within the time limit.
class LocationTimeoutException implements Exception {
  const LocationTimeoutException();
}

/// Any other location failure (provider error, services turned off mid-way).
class LocationUnavailableException implements Exception {
  const LocationUnavailableException([this.detail]);
  final String? detail;
}

/// Platform location access, behind an interface so the attendance flow can
/// be tested with a fake.
abstract class LocationService {
  Future<bool> isServiceEnabled();
  Future<LocationPermissionState> checkPermission();
  Future<LocationPermissionState> requestPermission();

  /// Throws [LocationTimeoutException] or [LocationUnavailableException].
  Future<LocationFix> currentFix({Duration timeLimit});
  Future<bool> openLocationSettings();
  Future<bool> openAppSettings();
}

class GeolocatorLocationService implements LocationService {
  const GeolocatorLocationService();

  LocationPermissionState _map(LocationPermission permission) =>
      switch (permission) {
        LocationPermission.always ||
        LocationPermission.whileInUse => LocationPermissionState.granted,
        LocationPermission.deniedForever =>
          LocationPermissionState.permanentlyDenied,
        _ => LocationPermissionState.denied,
      };

  @override
  Future<bool> isServiceEnabled() => Geolocator.isLocationServiceEnabled();

  @override
  Future<LocationPermissionState> checkPermission() async =>
      _map(await Geolocator.checkPermission());

  @override
  Future<LocationPermissionState> requestPermission() async =>
      _map(await Geolocator.requestPermission());

  @override
  Future<LocationFix> currentFix({
    Duration timeLimit = const Duration(seconds: 20),
  }) async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: LocationSettings(
          accuracy: LocationAccuracy.best,
          timeLimit: timeLimit,
        ),
      );
      return LocationFix(
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy,
        isMocked: position.isMocked,
        timestamp: position.timestamp,
      );
    } on TimeoutException {
      throw const LocationTimeoutException();
    } on LocationServiceDisabledException {
      throw const LocationUnavailableException('service_disabled');
    } catch (error) {
      throw LocationUnavailableException(error.toString());
    }
  }

  @override
  Future<bool> openLocationSettings() => Geolocator.openLocationSettings();

  @override
  Future<bool> openAppSettings() => Geolocator.openAppSettings();
}

final locationServiceProvider = Provider<LocationService>(
  (ref) => const GeolocatorLocationService(),
);

/// Great-circle distance in metres (haversine).
double distanceMeters(double lat1, double lon1, double lat2, double lon2) {
  const earth = 6371000.0;
  double rad(double deg) => deg * math.pi / 180;
  final dLat = rad(lat2 - lat1);
  final dLon = rad(lon2 - lon1);
  final a =
      math.sin(dLat / 2) * math.sin(dLat / 2) +
      math.cos(rad(lat1)) *
          math.cos(rad(lat2)) *
          math.sin(dLon / 2) *
          math.sin(dLon / 2);
  return earth * 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a));
}
