import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../services/push_notification_service.dart';
import '../../core/services/offline_db_service.dart';
import '../../core/services/app_settings_service.dart';
import '../offline/offline_maps_screen.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  bool _notificationsEnabled = true;
  bool _emailNotifications = true;
  bool _pushNotifications = true;
  bool _showOnMap = true;
  String _selectedLanguage = 'English';
  bool _dataSaverMode = false;
  bool _autoDownload = true;
  bool _saving = false;

  final List<String> _languages = ['English', 'नेपाली'];

  @override
  void initState() {
    super.initState();
    _loadSettings();
  }

  Future<void> _loadSettings() async {
    final provider = context.read<ProfileProvider>();
    await provider.loadSettings();
    if (!mounted) return;

    final settings = provider.settings;

    setState(() {
      _notificationsEnabled = settings['notifications_enabled'] ?? true;
      _emailNotifications = settings['email_notifications'] ?? true;
      _pushNotifications = settings['push_notifications'] ?? true;
      _selectedLanguage = settings['language'] == 'ne' ? 'नेपाली' : 'English';
      _showOnMap = settings['show_on_map'] ?? true;
    });

    _dataSaverMode = await AppSettingsService.dataSaverMode;
    _autoDownload = await AppSettingsService.autoDownloadMaps;
    if (mounted) setState(() {});
  }

  Future<void> _saveSettings() async {
    final languageCode = _selectedLanguage == 'नेपाली' ? 'ne' : 'en';

    setState(() => _saving = true);

    final ok = await context.read<ProfileProvider>().updateSettings({
      'notifications_enabled': _notificationsEnabled,
      'email_notifications': _emailNotifications,
      'push_notifications': _pushNotifications,
      'language': languageCode,
      'show_on_map': _showOnMap,
    });

    await AppSettingsService.setDataSaverMode(_dataSaverMode);
    await AppSettingsService.setAutoDownloadMaps(_autoDownload);

    if (!mounted) return;
    setState(() => _saving = false);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(ok ? Icons.check_circle : Icons.error, color: Colors.white, size: 20),
            const SizedBox(width: 10),
            Text(ok ? context.t('Settings saved') : context.t('Could not save settings. Please try again.')),
          ],
        ),
        backgroundColor: ok ? AppTheme.successColor : AppTheme.errorColor,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        margin: const EdgeInsets.all(16),
        duration: const Duration(seconds: 2),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: GestureDetector(
          onTap: () => Navigator.pop(context),
          child: Container(
            margin: const EdgeInsets.all(8),
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(Icons.arrow_back_ios_new, color: Color(0xFF2D3436), size: 18),
          ),
        ),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: const Color(0xFF00695C).withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.settings, color: Color(0xFF00695C), size: 20),
            ),
            const SizedBox(width: 10),
            Text(
              context.t('Settings'),
              style: const TextStyle(
                color: Color(0xFF2D3436),
                fontSize: 20,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        actions: [
          _saving
              ? const Padding(
                  padding: EdgeInsets.all(14),
                  child: SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF00695C)),
                  ),
                )
              : GestureDetector(
                  onTap: _saveSettings,
                  child: Container(
                    margin: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    decoration: BoxDecoration(
                      color: const Color(0xFF00695C),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.save_rounded, color: Colors.white, size: 16),
                        SizedBox(width: 4),
                        Text('Save', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600)),
                      ],
                    ),
                  ),
                ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
        children: [
          // Header text
          Padding(
            padding: const EdgeInsets.only(bottom: 20),
            child: Text(
              context.t('Customize your experience'),
              style: TextStyle(
                fontSize: 14,
                color: AppTheme.textSecondary.withOpacity(0.8),
              ),
            ),
          ),

          // Section: Notifications
          _buildModernSection(
            title: context.t('Notifications'),
            icon: Icons.notifications_outlined,
            gradient: [const Color(0xFF667EEA), const Color(0xFF764BA2)],
            children: [
              _buildModernSwitch(
                title: context.t('Push Notifications'),
                subtitle: context.t('Alerts and updates'),
                icon: Icons.notifications_active_rounded,
                value: _pushNotifications,
                onChanged: (v) {
                  setState(() => _pushNotifications = v);
                  PushNotificationService().setSubscription(v);
                },
              ),
              _buildModernSwitch(
                title: context.t('Email Notifications'),
                subtitle: context.t('Report updates via email'),
                icon: Icons.mail_rounded,
                value: _emailNotifications,
                onChanged: (v) => setState(() => _emailNotifications = v),
              ),
              _buildModernSwitch(
                title: context.t('Critical Alerts'),
                subtitle: context.t('Emergency notifications near you'),
                icon: Icons.warning_amber_rounded,
                value: _notificationsEnabled,
                onChanged: (v) => setState(() => _notificationsEnabled = v),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Section: Appearance
          _buildModernSection(
            title: context.t('Appearance'),
            icon: Icons.palette_outlined,
            gradient: [const Color(0xFFF093FB), const Color(0xFFF5576C)],
            children: [
              _buildModernDropdown(
                title: context.t('Language'),
                subtitle: context.t('Choose your preferred language'),
                icon: Icons.language_rounded,
                value: _selectedLanguage,
                items: _languages,
                onChanged: (v) {
                  setState(() => _selectedLanguage = v!);
                  context.read<LocalizationService>().setLanguage(
                        v == 'नेपाली' ? 'ne' : 'en',
                      );
                },
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Section: Privacy
          _buildModernSection(
            title: context.t('Privacy & Security'),
            icon: Icons.shield_outlined,
            gradient: [const Color(0xFF4FACFE), const Color(0xFF00F2FE)],
            children: [
              _buildModernSwitch(
                title: context.t('Show on Map'),
                subtitle: context.t('Visible contributions on map'),
                icon: Icons.map_rounded,
                value: _showOnMap,
                onChanged: (v) => setState(() => _showOnMap = v),
              ),
              _buildModernAction(
                title: context.t('Delete Account'),
                subtitle: context.t('Permanently remove your data'),
                icon: Icons.delete_forever_rounded,
                iconColor: AppTheme.errorColor,
                titleColor: AppTheme.errorColor,
                onTap: () => _showDeleteConfirmation(context),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Section: Data & Storage
          _buildModernSection(
            title: context.t('Data & Storage'),
            icon: Icons.storage_outlined,
            gradient: [const Color(0xFF43E97B), const Color(0xFF38F9D7)],
            children: [
              _buildModernSwitch(
                title: context.t('Data Saver Mode'),
                subtitle: context.t('Reduce data usage'),
                icon: Icons.data_saver_on_rounded,
                value: _dataSaverMode,
                onChanged: (v) => setState(() => _dataSaverMode = v),
              ),
              _buildModernSwitch(
                title: context.t('Auto-download Maps'),
                subtitle: context.t('Cache maps for offline use'),
                icon: Icons.download_rounded,
                value: _autoDownload,
                onChanged: (v) => setState(() => _autoDownload = v),
              ),
              _buildModernAction(
                title: context.t('Offline Maps'),
                subtitle: context.t('Download Nepal map for offline'),
                icon: Icons.map_rounded,
                iconColor: AppTheme.primaryColor,
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (context) => const OfflineMapsScreen()),
                  );
                },
              ),
              _buildModernAction(
                title: context.t('Clear Cache'),
                subtitle: context.t('Free up storage space'),
                icon: Icons.cleaning_services_rounded,
                iconColor: AppTheme.warningColor,
                onTap: _clearCache,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _clearCache() async {
    final messenger = ScaffoldMessenger.of(context);
    final db = OfflineDbService.instance;
    final dbBytes = await db.cacheSizeBytes();

    messenger.showSnackBar(
      SnackBar(
        content: const Row(
          children: [
            SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
            SizedBox(width: 12),
            Text('Clearing cache...'),
          ],
        ),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        margin: const EdgeInsets.all(16),
      ),
    );

    await db.clearAll();
    PaintingBinding.instance.imageCache.clear();
    PaintingBinding.instance.imageCache.clearLiveImages();
    try {
      await DefaultCacheManager().emptyCache();
    } catch (_) {}

    final freedMb = (dbBytes / (1024 * 1024)).toStringAsFixed(1);
    messenger.showSnackBar(
      SnackBar(
        content: Row(
          children: [
            const Icon(Icons.check_circle, color: Colors.white, size: 20),
            const SizedBox(width: 10),
            Text('${context.t('Cache cleared')} ($freedMb MB freed)'),
          ],
        ),
        backgroundColor: AppTheme.successColor,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        margin: const EdgeInsets.all(16),
      ),
    );
  }

  Widget _buildModernSection({
    required String title,
    required IconData icon,
    required List<Color> gradient,
    required List<Widget> children,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Section Header with gradient
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: gradient,
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,
              ),
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.25),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(icon, size: 18, color: Colors.white),
                ),
                const SizedBox(width: 10),
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: Colors.white,
                  ),
                ),
              ],
            ),
          ),
          // Children
          ...children,
          const SizedBox(height: 4),
        ],
      ),
    );
  }

  Widget _buildModernSwitch({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool value,
    required ValueChanged<bool> onChanged,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: value
                  ? AppTheme.primaryColor.withOpacity(0.1)
                  : Colors.grey.withOpacity(0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              icon,
              size: 20,
              color: value ? AppTheme.primaryColor : Colors.grey,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w500,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: TextStyle(
                    fontSize: 12,
                    color: AppTheme.textSecondary.withOpacity(0.7),
                  ),
                ),
              ],
            ),
          ),
          Transform.scale(
            scale: 0.9,
            child: Switch(
              value: value,
              onChanged: onChanged,
              activeColor: AppTheme.primaryColor,
              activeTrackColor: AppTheme.primaryColor.withOpacity(0.3),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildModernDropdown({
    required String title,
    required String subtitle,
    required IconData icon,
    required String value,
    required List<String> items,
    required ValueChanged<String?> onChanged,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: AppTheme.primaryColor.withOpacity(0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, size: 20, color: AppTheme.primaryColor),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w500,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: TextStyle(
                    fontSize: 12,
                    color: AppTheme.textSecondary.withOpacity(0.7),
                  ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: AppTheme.primaryColor.withOpacity(0.08),
              borderRadius: BorderRadius.circular(8),
            ),
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: value,
                isDense: true,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                  color: AppTheme.primaryColor,
                ),
                items: items.map((item) {
                  return DropdownMenuItem(value: item, child: Text(item));
                }).toList(),
                onChanged: onChanged,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildModernAction({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color iconColor,
    Color? titleColor,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: iconColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 20, color: iconColor),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w500,
                      color: titleColor ?? AppTheme.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: TextStyle(
                      fontSize: 12,
                      color: AppTheme.textSecondary.withOpacity(0.7),
                    ),
                  ),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: Colors.grey.withOpacity(0.5), size: 22),
          ],
        ),
      ),
    );
  }

  void _showDeleteConfirmation(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppTheme.errorColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.delete_forever, color: AppTheme.errorColor, size: 24),
            ),
            const SizedBox(width: 12),
            Text(ctx.t('Delete Account')),
          ],
        ),
        content: Text(ctx.t(
          'Are you sure you want to delete your account? This action cannot be undone. All your data, reports, and contributions will be permanently removed.',
        )),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: Text(ctx.t('Cancel')),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              _deleteAccount(context);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.errorColor,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            child: Text(ctx.t('Delete')),
          ),
        ],
      ),
    );
  }

  Future<void> _deleteAccount(BuildContext context) async {
    final authProvider = context.read<AuthProvider>();
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);

    final deleted = await authProvider.deleteAccount();
    if (deleted) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(context.t('Your account has been deleted.')),
          backgroundColor: AppTheme.successColor,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          margin: const EdgeInsets.all(16),
        ),
      );
      navigator.pushReplacementNamed('/login');
    } else {
      messenger.showSnackBar(
        SnackBar(
          content: Text(context.t('Could not delete your account. Please try again.')),
          backgroundColor: AppTheme.errorColor,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          margin: const EdgeInsets.all(16),
        ),
      );
    }
  }
}
