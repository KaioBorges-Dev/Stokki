// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Portuguese (`pt`).
class AppLocalizationsPt extends AppLocalizations {
  AppLocalizationsPt([String locale = 'pt']) : super(locale);

  @override
  String get appName => 'Stockki';

  @override
  String get dashboard => 'Painel Principal';

  @override
  String get inventory => 'Estoque';

  @override
  String get expiryAlerts => 'Alertas de Validade';

  @override
  String get scanProduct => 'Escanear Rótulo';

  @override
  String get settings => 'Configurações';

  @override
  String get logout => 'Sair';

  @override
  String get productName => 'Nome do Produto';

  @override
  String get sku => 'SKU';

  @override
  String get batchNumber => 'Número do Lote';

  @override
  String get quantity => 'Quantidade';

  @override
  String get expiryDate => 'Data de Validade';

  @override
  String get safetyStock => 'Estoque Mínimo';

  @override
  String get statusExpired => 'Vencido';

  @override
  String get statusExpiringSoon => 'Vencendo em Breve';

  @override
  String get statusSafe => 'Seguro';

  @override
  String get addProduct => 'Novo Produto';

  @override
  String get save => 'Salvar';

  @override
  String get cancel => 'Cancelar';

  @override
  String get delete => 'Excluir';

  @override
  String get edit => 'Editar';

  @override
  String get analyzingLabel => 'Extraindo informações do rótulo...';

  @override
  String get ocrSuccess => 'Informações extraídas com sucesso';

  @override
  String get ocrFailed => 'Falha ao extrair informações do produto';
}
