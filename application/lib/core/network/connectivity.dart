import 'package:connectivity_plus/connectivity_plus.dart';

/// "Is there any network at all?" Used to block attendance submission when
/// offline with a clear message (nothing is queued or faked). A connected
/// interface does not guarantee the server is reachable, so request errors
/// are still mapped to OfflineFailure by the API client.
abstract class ConnectivityChecker {
  Future<bool> isOnline();
}

class PlatformConnectivityChecker implements ConnectivityChecker {
  PlatformConnectivityChecker([Connectivity? connectivity])
    : _connectivity = connectivity ?? Connectivity();

  final Connectivity _connectivity;

  @override
  Future<bool> isOnline() async {
    try {
      final results = await _connectivity.checkConnectivity();
      return results.any((result) => result != ConnectivityResult.none);
    } catch (_) {
      // Plugin unavailable (tests, desktop): let the request decide.
      return true;
    }
  }
}
