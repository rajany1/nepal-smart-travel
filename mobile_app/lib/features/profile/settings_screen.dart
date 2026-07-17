import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../services/push_notification_service.dart';

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

  final List<String> _languages = ['English', 'नेपाली', 'हिन्दी', '中文', '日本語'];
  final List<String> _themes = ['Light', 'Dark', 'System'];

  @override
  void initState() {
    super.initState();
    _loadSettings();
  }

  void _loadSettings() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final settings = context.read<ProfileProvider>().settings;
      if (settings.isNotEmpty) {
        setState(() {
          _notificationsEnabled = settings['notifications_enabled'] ?? true;
          _emailNotifications = settings['email_notifications'] ?? true;
          _pushNotifications = settings['push_notifications'] ?? true;
          _selectedLanguage = settings['language'] == 'ne' ? 'नेपाली' :
                              settings['language'] == 'hi' ? 'हिन्दी' : 'English';
          _selectedTheme = settings['theme'] == 'dark' ? 'Dark' : 'Light';
          _showOnMap = settings['show_on_map'] ?? true;
        });
      }
    });
  }

  Future<void> _saveSettings() async {
    final languageCode = _selectedLanguage == 'नेपाली' ? 'ne' :
                         _selectedLanguage == 'हिन्दी' ? 'hi' : 'en';
    final themeMode = _selectedTheme.toLowerCase();
    
    await context.read<ProfileProvider>().updateSettings({
      'notifications_enabled': _notificationsEnabled,
      'email_notifications': _emailNotifications,
      'push_notifications': _pushNotifications,
      'language': languageCode,
      'theme': themeMode,
      'show_on_map': _showOnMap,
    });

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Settings saved'),
          backgroundColor: AppTheme.successColor,
          duration: Duration(seconds: 1),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        title: const Text('Settings'),
        backgroundColor: AppTheme.primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
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
            title: 'Notifications',
            icon: Icons.notifications_outlined,
            color: AppTheme.infoColor,
            children: [
              _buildSwitchTile(
                'Push Notifications',
                'Receive push notifications for alerts and updates',
                Icons.notifications_active,
                _pushNotifications,
                (v) {
                  setState(() => _pushNotifications = v);
                  PushNotificationService().setSubscription(v);
                },
              ),
              const Divider(height: 1),
              _buildSwitchTile(
                'Email Notifications',
                'Receive email updates about your reports',
                Icons.email_outlined,
                _emailNotifications,
                (v) => setState(() => _emailNotifications = v),
              ),
              const Divider(height: 1),
              _buildSwitchTile(
                'Alert Alerts',
                'Get notified about critical alerts near you',
                Icons.warning_amber,
                _notificationsEnabled,
                (v) => setState(() => _notificationsEnabled = v),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Section: Appearance
          _buildSectionCard(
            title: 'Appearance',
            icon: Icons.palette_outlined,
            color: AppTheme.accentColor,
            children: [
              _buildDropdownTile(
                'Language',
                Icons.language,
                _selectedLanguage,
                _languages,
                (v) => setState(() => _selectedLanguage = v!),
              ),
              const Divider(height: 1),
              _buildDropdownTile(
                'Theme',
                Icons.dark_mode,
                _selectedTheme,
                _themes,
                (v) => setState(() => _selectedTheme = v!),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Section: Privacy
          _buildSectionCard(
            title: 'Privacy & Security',
            icon: Icons.security,
            color: AppTheme.warningColor,
            children: [
              _buildSwitchTile(
                'Show on Map',
                'Allow others to see your contributions on the map',
                Icons.map,
                _showOnMap,
                (v) => setState(() => _showOnMap = v),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.delete_outline, color: AppTheme.errorColor),
                title: const Text('Delete Account', style: TextStyle(color: AppTheme.errorColor)),
                subtitle: const Text('Permanently delete your account and data'),
                onTap: () => _showDeleteConfirmation(context),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Section: Data & Storage
          _buildSectionCard(
            title: 'Data & Storage',
            icon: Icons.storage,
            color: AppTheme.infoColor,
            children: [
              _buildSwitchTile(
                'Data Saver Mode',
                'Use less data when loading images',
                Icons.data_saver_on,
                _dataSaverMode,
                (v) => setState(() => _dataSaverMode = v),
              ),
              const Divider(height: 1),
              _buildSwitchTile(
                'Auto-download Maps',
                'Automatically download maps for offline use',
                Icons.download,
                _autoDownload,
                (v) => setState(() => _autoDownload = v),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.delete_sweep_outlined, color: AppTheme.textSecondary),
                title: const Text('Clear Cache'),
                subtitle: Text('Free up storage space', style: TextStyle(color: AppTheme.textSecondary.withOpacity(0.7))),
                trailing: Text('128 MB', style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
                onTap: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Cache cleared')),
                  );
                },
              ),
            ],
          ),
          const SizedBox(height: 32),
        ],
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
        title: const Text('Delete Account'),
        content: const Text(
          'Are you sure you want to delete your account? This action cannot be undone. All your data, reports, and contributions will be permanently removed.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              // Logout and delete
              context.read<AuthProvider>().logout();
              Navigator.of(context).pushReplacementNamed('/login');
            },
            style: TextButton.styleFrom(foregroundColor: AppTheme.errorColor),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
  }
}
