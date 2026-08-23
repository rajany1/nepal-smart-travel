import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../providers/theme_provider.dart';
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
  String _selectedTheme = 'Light';
  bool _dataSaverMode = false;
  bool _autoDownload = true;
  bool _saving = false;

  final List<String> _languages = ['English', 'नेपाली'];
  final List<String> _themes = ['Light', 'Dark', 'System'];

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
      _selectedTheme = settings['theme'] == 'dark' ? 'Dark' :
                      settings['theme'] == 'system' ? 'System' : 'Light';
      _showOnMap = settings['show_on_map'] ?? true;
    });

    _dataSaverMode = await AppSettingsService.dataSaverMode;
    _autoDownload = await AppSettingsService.autoDownloadMaps;
    if (mounted) setState(() {});
  }

  Future<void> _saveSettings() async {
    final languageCode = _selectedLanguage == 'नेपाली' ? 'ne' : 'en';
    final themeMode = _selectedTheme.toLowerCase();

    setState(() => _saving = true);

    final ok = await context.read<ProfileProvider>().updateSettings({
      'notifications_enabled': _notificationsEnabled,
      'email_notifications': _emailNotifications,
      'push_notifications': _pushNotifications,
      'language': languageCode,
      'theme': themeMode,
      'show_on_map': _showOnMap,
    });

    await AppSettingsService.setDataSaverMode(_dataSaverMode);
    await AppSettingsService.setAutoDownloadMaps(_autoDownload);

    if (!mounted) return;
    setState(() => _saving = false);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
            ok ? context.t('Settings saved') : context.t('Could not save settings. Please try again.')),
        backgroundColor: ok ? AppTheme.successColor : AppTheme.errorColor,
        duration: const Duration(seconds: 2),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        title: Text(context.t('Settings')),
        backgroundColor: AppTheme.primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
        actions: [
          _saving
              ? const Padding(
                  padding: EdgeInsets.all(14),
                  child: SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  ),
                )
              : IconButton(
                  icon: const Icon(Icons.save),
                  onPressed: _saveSettings,
                ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Section: Notifications
          _buildSectionCard(
            title: context.t('Notifications'),
            icon: Icons.notifications_outlined,
            color: AppTheme.infoColor,
            children: [
              _buildSwitchTile(
                context.t('Push Notifications'),
                context.t('Receive push notifications for alerts and updates'),
                Icons.notifications_active,
                _pushNotifications,
                (v) {
                  setState(() => _pushNotifications = v);
                  PushNotificationService().setSubscription(v);
                },
              ),
              const Divider(height: 1),
              _buildSwitchTile(
                context.t('Email Notifications'),
                context.t('Receive email updates about your reports'),
                Icons.email_outlined,
                _emailNotifications,
                (v) => setState(() => _emailNotifications = v),
              ),
              const Divider(height: 1),
              _buildSwitchTile(
                context.t('Alert Alerts'),
                context.t('Get notified about critical alerts near you'),
                Icons.warning_amber,
                _notificationsEnabled,
                (v) => setState(() => _notificationsEnabled = v),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Section: Appearance
          _buildSectionCard(
            title: context.t('Appearance'),
            icon: Icons.palette_outlined,
            color: AppTheme.accentColor,
            children: [
              _buildDropdownTile(
                context.t('Language'),
                Icons.language,
                _selectedLanguage,
                _languages,
                (v) {
                  setState(() => _selectedLanguage = v!);
                  context.read<LocalizationService>().setLanguage(
                        v == 'नेपाली' ? 'ne' : 'en',
                      );
                },
              ),
              const Divider(height: 1),
              _buildDropdownTile(
                context.t('Theme'),
                Icons.dark_mode,
                _selectedTheme,
                _themes,
                (v) {
                  setState(() => _selectedTheme = v!);
                  context.read<ThemeProvider>().setMode(
                    v == 'Dark'
                        ? ThemeMode.dark
                        : v == 'System'
                            ? ThemeMode.system
                            : ThemeMode.light,
                  );
                },
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Section: Privacy
          _buildSectionCard(
            title: context.t('Privacy & Security'),
            icon: Icons.security,
            color: AppTheme.warningColor,
            children: [
              _buildSwitchTile(
                context.t('Show on Map'),
                context.t('Allow others to see your contributions on the map'),
                Icons.map,
                _showOnMap,
                (v) => setState(() => _showOnMap = v),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.delete_outline, color: AppTheme.errorColor),
                title: Text(context.t('Delete Account'), style: const TextStyle(color: AppTheme.errorColor)),
                subtitle: Text(context.t('Permanently delete your account and data')),
                onTap: () => _showDeleteConfirmation(context),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Section: Data & Storage
          _buildSectionCard(
            title: context.t('Data & Storage'),
            icon: Icons.storage,
            color: AppTheme.infoColor,
            children: [
              _buildSwitchTile(
                context.t('Data Saver Mode'),
                context.t('Skip background map downloads and use less memory for images'),
                Icons.data_saver_on,
                _dataSaverMode,
                (v) => setState(() => _dataSaverMode = v),
              ),
              const Divider(height: 1),
              _buildSwitchTile(
                context.t('Auto-download Maps'),
                context.t('Cache map tiles so the map works without internet'),
                Icons.download,
                _autoDownload,
                (v) => setState(() => _autoDownload = v),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.map_outlined, color: AppTheme.primaryColor),
                title: Text(context.t('Offline Maps')),
                subtitle: Text(context.t('Download the full Nepal map (30/60 days), update or delete it'),
                    style: TextStyle(color: AppTheme.textSecondary.withOpacity(0.7))),
                trailing: const Icon(Icons.chevron_right, color: AppTheme.textSecondary),
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => const OfflineMapsScreen(),
                    ),
                  );
                },
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.delete_sweep_outlined, color: AppTheme.textSecondary),
                title: Text(context.t('Clear Cache')),
                subtitle: Text(context.t('Free up storage space (maps, places, images)'),
                    style: TextStyle(color: AppTheme.textSecondary.withOpacity(0.7))),
                onTap: _clearCache,
              ),
            ],
          ),
          const SizedBox(height: 32),
        ],
      ),
    );
  }

  Future<void> _clearCache() async {
    final messenger = ScaffoldMessenger.of(context);
    final db = OfflineDbService.instance;

    final dbBytes = await db.cacheSizeBytes();

    messenger.showSnackBar(SnackBar(content: Text(context.t('Clearing cache...'))));

    await db.clearAll();
    PaintingBinding.instance.imageCache.clear();
    PaintingBinding.instance.imageCache.clearLiveImages();
    try {
      await DefaultCacheManager().emptyCache();
    } catch (_) {}

    final freedMb = (dbBytes / (1024 * 1024)).toStringAsFixed(1);
    messenger.showSnackBar(
      SnackBar(
        content: Text('${context.t('Cache cleared')} ($freedMb MB freed)'),
        backgroundColor: AppTheme.successColor,
      ),
    );
  }

  Widget _buildSectionCard({
    required String title,
    required IconData icon,
    required Color color,
    required List<Widget> children,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: color.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(icon, size: 18, color: color),
                ),
                const SizedBox(width: 10),
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textPrimary,
                  ),
                ),
              ],
            ),
          ),
          ...children,
          const SizedBox(height: 4),
        ],
      ),
    );
  }

  Widget _buildSwitchTile(
    String title,
    String subtitle,
    IconData icon,
    bool value,
    ValueChanged<bool> onChanged,
  ) {
    return SwitchListTile(
      title: Text(title, style: const TextStyle(fontSize: AppTheme.textBase, fontWeight: FontWeight.w500)),
      subtitle: Text(subtitle, style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
      secondary: Icon(icon, size: 20, color: value ? AppTheme.primaryColor : AppTheme.textSecondary),
      value: value,
      activeColor: AppTheme.primaryColor,
      dense: true,
      onChanged: onChanged,
    );
  }

  Widget _buildDropdownTile(
    String title,
    IconData icon,
    String value,
    List<String> items,
    ValueChanged<String?> onChanged,
  ) {
    return ListTile(
      leading: Icon(icon, size: 20, color: AppTheme.textSecondary),
      title: Text(title, style: const TextStyle(fontSize: AppTheme.textBase, fontWeight: FontWeight.w500)),
      trailing: DropdownButton<String>(
        value: value,
        underline: const SizedBox(),
        style: TextStyle(fontSize: AppTheme.textBase, color: AppTheme.primaryColor),
        items: items.map((item) {
          return DropdownMenuItem(value: item, child: Text(item));
        }).toList(),
        onChanged: onChanged,
      ),
    );
  }

  void _showDeleteConfirmation(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(ctx.t('Delete Account')),
        content: Text(ctx.t(
          'Are you sure you want to delete your account? This action cannot be undone. All your data, reports, and contributions will be permanently removed.',
        )),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: Text(ctx.t('Cancel')),
          ),
          TextButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              _deleteAccount(context);
            },
            style: TextButton.styleFrom(foregroundColor: AppTheme.errorColor),
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
          SnackBar(content: Text(context.t('Your account has been deleted.'))));
      navigator.pushReplacementNamed('/login');
    } else {
      messenger.showSnackBar(
        SnackBar(
          content: Text(context.t('Could not delete your account. Please try again.')),
          backgroundColor: AppTheme.errorColor,
        ),
      );
    }
  }
}
