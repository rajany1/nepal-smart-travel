import 'package:flutter/material.dart';
import '../../config/themes/app_theme.dart';

class PoliciesScreen extends StatelessWidget {
  const PoliciesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        backgroundColor: AppTheme.backgroundColor,
        appBar: AppBar(
          title: const Text('Policies & Info'),
          backgroundColor: AppTheme.primaryColor,
          foregroundColor: Colors.white,
          elevation: 0,
          bottom: TabBar(
            indicatorColor: Colors.white,
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white60,
            tabs: const [
              Tab(text: 'Terms', icon: Icon(Icons.description, size: 18)),
              Tab(text: 'Privacy', icon: Icon(Icons.security, size: 18)),
              Tab(text: 'About', icon: Icon(Icons.info, size: 18)),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            _TermsTab(),
            _PrivacyTab(),
            _AboutTab(),
          ],
        ),
      ),
    );
  }
}

// Helper widget builders - shared across tabs
Widget _buildSection({
  required IconData icon,
  required String title,
  required String content,
}) {
  return Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: AppTheme.dividerColor),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, size: 20, color: AppTheme.primaryColor),
            const SizedBox(width: 8),
            Text(
              title,
              style: const TextStyle(
                fontSize: AppTheme.textXl,
                fontWeight: FontWeight.bold,
                color: AppTheme.textPrimary,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        Text(
          content,
          style: const TextStyle(fontSize: AppTheme.textBase, color: AppTheme.textPrimary, height: 1.6),
        ),
      ],
    ),
  );
}

Widget _buildInfoBanner(BuildContext context) {
  return Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: AppTheme.infoColor.withOpacity(0.1),
      borderRadius: BorderRadius.circular(8),
      border: Border.all(color: AppTheme.infoColor.withOpacity(0.3)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.info_outline, size: 18, color: AppTheme.infoColor),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            'For questions about these policies, please contact support through the app.',
            style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.infoColor, height: 1.4),
          ),
        ),
      ],
    ),
  );
}

class _TermsTab extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildSection(
            icon: Icons.gavel,
            title: 'Terms of Service',
            content: 'Last updated: May 2026\n\n'
                '1. Acceptance of Terms\n'
                'By accessing and using Nepal Smart Travel, you accept the terms.\n\n'
                '2. User Responsibilities\n'
                '- Provide accurate information\n'
                '- Keep account credentials confidential\n'
                '- Do not submit false content\n'
                '- No illegal activities\n\n'
                '3. Content Guidelines\n'
                '- Reports must be factual\n'
                '- Respect local communities\n'
                '- Do not share others personal info\n'
                '- Follow emergency alert guidelines\n\n'
                '4. Community Standards\n'
                '- Be respectful in interactions\n'
                '- Maintain information accuracy\n'
                '- Report inappropriate content\n'
                '- Contribute positively\n\n'
                '5. Account Termination\n'
                'Violations leading to termination:\n'
                '- Fraudulent reports\n'
                '- Harassing users\n'
                '- Violating laws\n'
                '- Misusing emergency features\n\n'
                '6. Limitation of Liability\n'
                'Information provided as-is basis.\n\n'
                '7. Changes to Terms\n'
                'Users notified of material changes.',
          ),
          const SizedBox(height: 24),
          _buildInfoBanner(context),
        ],
      ),
    );
  }
}

class _PrivacyTab extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildSection(
            icon: Icons.privacy_tip,
            title: 'Privacy Policy',
            content: 'Last updated: May 2026\n\n'
                '1. Information We Collect\n'
                '- Account information: name, email, phone number\n'
                '- Location data: when you submit reports or use map features\n'
                '- Usage data: app interactions, features used\n'
                '- Device information: device type, operating system\n\n'
                '2. How We Use Your Information\n'
                '- Provide and improve our services\n'
                '- Personalize your experience\n'
                '- Send notifications and updates you have opted into\n'
                '- Analyze usage patterns to improve the platform\n'
                '- Ensure security and prevent fraud\n\n'
                '3. Location Data\n'
                'Location data is collected only when:\n'
                '- You submit a report or alert\n'
                '- You search for nearby places\n'
                '- You use map features\n'
                'You can disable location services in your device settings.\n\n'
                '4. Data Sharing\n'
                'We do not sell your personal data. We may share data:\n'
                '- With your explicit consent\n'
                '- To comply with legal obligations\n'
                '- To protect rights and safety\n'
                '- With service providers essential to platform operation\n\n'
                '5. Data Security\n'
                'We implement appropriate security measures:\n'
                '- Encryption of data in transit\n'
                '- Secure token-based authentication\n'
                '- Regular security audits\n'
                '- Access controls on sensitive data\n\n'
                '6. Your Rights\n'
                'You have the right to:\n'
                '- Access your personal data\n'
                '- Correct inaccurate data\n'
                '- Delete your account and data\n'
                '- Export your data\n'
                '- Opt out of marketing communications\n\n'
                '7. Data Retention\n'
                'We retain your data as long as your account is active.\n\n'
                '8. Contact\n'
                'For privacy concerns, contact us through the app feedback feature.',
          ),
          const SizedBox(height: 24),
          _buildInfoBanner(context),
        ],
      ),
    );
  }
}

class _AboutTab extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          const SizedBox(height: 20),
          Container(
            width: 80,
            height: 80,
            decoration: BoxDecoration(
              color: AppTheme.primaryColor,
              borderRadius: BorderRadius.circular(20),
            ),
            child: const Icon(Icons.explore, size: 48, color: Colors.white),
          ),
          const SizedBox(height: 16),
          const Text('Nepal Smart Travel',
              style: TextStyle(fontSize: AppTheme.text3xl, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
          const SizedBox(height: 4),
          const Text('Your Trusted Travel Intelligence Platform',
              style: TextStyle(fontSize: AppTheme.textBase, color: AppTheme.textSecondary)),
          const SizedBox(height: 4),
          const Text('Version 1.0.0',
              style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
          const SizedBox(height: 32),
          _buildAboutCard(
            icon: Icons.assignment,
            title: 'Real-time Reports',
            description: 'Submit and view real-time reports about road conditions, safety issues, and local events.',
            color: AppTheme.infoColor,
          ),
          const SizedBox(height: 12),
          _buildAboutCard(
            icon: Icons.warning_amber,
            title: 'Emergency Alerts',
            description: 'Get critical alerts about natural disasters, security concerns, and emergency situations.',
            color: AppTheme.errorColor,
          ),
          const SizedBox(height: 12),
          _buildAboutCard(
            icon: Icons.people,
            title: 'Community Driven',
            description: 'Powered by a community of travelers and locals sharing accurate information.',
            color: AppTheme.secondaryColor,
          ),
          const SizedBox(height: 12),
          _buildAboutCard(
            icon: Icons.emoji_events,
            title: 'Gamification System',
            description: 'Earn XP, unlock badges, and climb the ranks as you contribute.',
            color: AppTheme.accentColor,
          ),
          const SizedBox(height: 32),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppTheme.dividerColor),
            ),
            child: Column(
              children: [
                const Icon(Icons.mail_outline, color: AppTheme.primaryColor, size: 24),
                const SizedBox(height: 8),
                const Text('Contact & Support', style: TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textBase)),
                const SizedBox(height: 4),
                Text('support@nepalsmarttravel.com', style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
                const SizedBox(height: 4),
                Text('(c) 2026 Nepal Smart Travel', style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
              ],
            ),
          ),
          const SizedBox(height: 32),
        ],
      ),
    );
  }

  Widget _buildAboutCard({
    required IconData icon,
    required String title,
    required String description,
    required Color color,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: color.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color, size: 24),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textBase)),
                const SizedBox(height: 4),
                Text(description, style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
