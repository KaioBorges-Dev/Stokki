// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'Stockki';

  @override
  String get dashboard => 'Dashboard';

  @override
  String get inventory => 'Inventory';

  @override
  String get expiryAlerts => 'Expiry Alerts';

  @override
  String get scanProduct => 'Scan Product Label';

  @override
  String get settings => 'Settings';

  @override
  String get logout => 'Log Out';

  @override
  String get productName => 'Product Name';

  @override
  String get sku => 'SKU';

  @override
  String get batchNumber => 'Batch Number';

  @override
  String get quantity => 'Quantity';

  @override
  String get expiryDate => 'Expiry Date';

  @override
  String get safetyStock => 'Safety Stock';

  @override
  String get statusExpired => 'Expired';

  @override
  String get statusExpiringSoon => 'Expiring Soon';

  @override
  String get statusSafe => 'Safe';

  @override
  String get addProduct => 'Add Product';

  @override
  String get save => 'Save';

  @override
  String get cancel => 'Cancel';

  @override
  String get delete => 'Delete';

  @override
  String get edit => 'Edit';

  @override
  String get analyzingLabel => 'Extracting label details...';

  @override
  String get ocrSuccess => 'Details extracted successfully';

  @override
  String get ocrFailed => 'Failed to extract product details';
}
