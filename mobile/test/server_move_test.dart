import 'package:flutter_test/flutter_test.dart';
import 'package:nisconnect/core/config/server_move.dart';

void main() {
  Future<String?> Function(String) servers(Map<String, String?> movedTo) =>
      (root) async => movedTo[root];

  test('switches to a confirmed https address', () async {
    final next = await resolveServerMove('https://apps.immigration.gov.ng', servers({
      'https://apps.immigration.gov.ng': 'https://connect.immigration.gov.ng/',
      'https://connect.immigration.gov.ng': 'https://connect.immigration.gov.ng',
    }));
    expect(next, 'https://connect.immigration.gov.ng');
  });

  test('stays when nothing moved', () async {
    expect(await resolveServerMove('https://apps.immigration.gov.ng', servers({})), isNull);
  });

  test('ignores a move the new address does not confirm', () async {
    final next = await resolveServerMove('https://apps.immigration.gov.ng', servers({
      'https://apps.immigration.gov.ng': 'https://evil.example.com',
      'https://evil.example.com': null,
    }));
    expect(next, isNull);
  });

  test('never moves to plain http, and never from a local dev server', () async {
    expect(
        await resolveServerMove('https://apps.immigration.gov.ng', servers({
          'https://apps.immigration.gov.ng': 'http://connect.immigration.gov.ng',
        })),
        isNull);
    expect(
        await resolveServerMove('http://10.0.2.2:8000', servers({
          'http://10.0.2.2:8000': 'https://connect.immigration.gov.ng',
        })),
        isNull);
  });
}
