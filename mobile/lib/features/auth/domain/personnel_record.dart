/// Authorised NIS personnel record returned after Service Number verification.
/// The officer cannot edit these authoritative fields.
class PersonnelRecord {
  const PersonnelRecord({
    required this.serviceNumber,
    required this.surname,
    required this.firstName,
    this.otherName,
    this.rank,
    this.directorate,
    this.department,
    this.zone,
    this.command,
    this.formation,
    this.unit,
    this.posting,
    this.officialEmail,
    this.status,
    this.photoUrl,
  });

  final String serviceNumber;
  final String surname;
  final String firstName;
  final String? otherName;
  final String? rank;
  final String? directorate;
  final String? department;
  final String? zone;
  final String? command;
  final String? formation;
  final String? unit;
  final String? posting;
  final String? officialEmail;
  final String? status;
  final String? photoUrl;

  String get fullName =>
      [firstName, otherName, surname].where((p) => p != null && p!.isNotEmpty).join(' ');

  factory PersonnelRecord.fromJson(Map<String, dynamic> json) => PersonnelRecord(
        serviceNumber: json['service_number'] as String,
        surname: (json['surname'] ?? '') as String,
        firstName: (json['first_name'] ?? '') as String,
        otherName: json['other_name'] as String?,
        rank: json['rank'] as String?,
        directorate: json['directorate'] as String?,
        department: json['department'] as String?,
        zone: json['zone'] as String?,
        command: json['command'] as String?,
        formation: json['formation'] as String?,
        unit: json['unit'] as String?,
        posting: json['posting'] as String?,
        officialEmail: json['official_email'] as String?,
        status: json['status'] as String?,
        photoUrl: json['photo_url'] as String?,
      );
}
