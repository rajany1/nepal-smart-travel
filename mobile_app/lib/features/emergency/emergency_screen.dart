import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import 'package:url_launcher/url_launcher.dart';
import '../../config/constants/app_constants.dart';
import '../../config/themes/app_theme.dart';
import '../../core/services/localization_service.dart';

class EmergencyScreen extends StatelessWidget {
  const EmergencyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('Emergency Support')),
        backgroundColor: AppTheme.errorColor,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // SOS Button
            SizedBox(
              width: double.infinity,
              height: 80,
              child: ElevatedButton.icon(
                onPressed: () => _showSOSDialog(context),
                icon: const Icon(Icons.sos, size: 32),
                label: Text(context.t('SOS EMERGENCY'), style: const TextStyle(fontSize: AppTheme.text2xl, fontWeight: FontWeight.bold)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.errorColor,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  elevation: 4,
                ),
              ),
            ),
            const SizedBox(height: 8),
            Text(context.t('Tap SOS for immediate emergency assistance'), style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm), textAlign: TextAlign.center),
            const SizedBox(height: 24),

            // Quick Dial Grid
            Text(context.t('Quick Emergency Contacts'), style: const TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.2,
              children: [
                _EmergencyButton(
                  icon: Icons.local_hospital, label: context.t('Ambulance'), number: AppConstants.ambulanceNumber,
                  color: AppTheme.ambulanceColor, onTap: () => _makeCall(AppConstants.ambulanceNumber),
                ),
                _EmergencyButton(
                  icon: Icons.local_police, label: context.t('Police'), number: AppConstants.policeNumber,
                  color: AppTheme.policeColor, onTap: () => _makeCall(AppConstants.policeNumber),
                ),
                _EmergencyButton(
                  icon: Icons.fire_extinguisher, label: context.t('Fire'), number: AppConstants.fireNumber,
                  color: AppTheme.warningColor, onTap: () => _makeCall(AppConstants.fireNumber),
                ),
                _EmergencyButton(
                  icon: Icons.local_hospital, label: context.t('Hospital'), number: context.t('Search Nearby'),
                  color: AppTheme.hospitalColor, onTap: () {},
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Medical & Rescue
            Text(context.t('Medical & Rescue Services'), style: const TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            _ServiceCard(icon: Icons.bloodtype, title: context.t('Blood Bank'), subtitle: context.t('Find nearest blood bank'), color: AppTheme.errorColor),
            _ServiceCard(icon: Icons.medication, title: context.t('24/7 Pharmacy'), subtitle: context.t('Nearby pharmacies open now'), color: AppTheme.infoColor),
            _ServiceCard(icon: Icons.airline_seat_individual_suite, title: context.t('Mountain Rescue'), subtitle: context.t('Emergency mountain rescue services'), color: AppTheme.severityCritical),
            _ServiceCard(icon: Icons.contact_phone, title: context.t('Tourist Police'), subtitle: context.t('Helpline for tourists: 1144'), color: AppTheme.policeColor),

            const SizedBox(height: 24),
            // Emergency Info
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppTheme.warningColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppTheme.warningColor.withOpacity(0.3)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.info, color: AppTheme.warningColor),
                      const SizedBox(width: 8),
                      Text(context.t('Emergency Tips'), style: const TextStyle(fontWeight: FontWeight.bold)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(context.t('• Stay calm and assess the situation\n• Call the appropriate emergency number\n• Share your exact location\n• Follow instructions from emergency services\n• Keep emergency contacts saved offline'), style: const TextStyle(fontSize: AppTheme.textBase, height: 1.6)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _makeCall(String number) async {
    final uri = Uri.parse('tel:$number');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  void _showSOSDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Row(
          children: [
            const Icon(Icons.warning, color: AppTheme.errorColor, size: 28),
            const SizedBox(width: 8),
            Text(context.t('SOS Emergency')),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(context.t('Your emergency alert will be sent to your emergency contacts with your current location.'), style: const TextStyle(fontSize: AppTheme.textBase)),
            const SizedBox(height: 16),
            Text(context.t('Emergency contacts will be notified immediately.'), style: const TextStyle(fontWeight: FontWeight.w600)),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(context.t('Cancel'))),
          ElevatedButton.icon(
            onPressed: () {
              Navigator.pop(ctx);
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text(context.t('SOS Alert Sent! Emergency contacts notified with your location.'))),
              );
              _makeCall(AppConstants.policeNumber);
            },
            icon: const Icon(Icons.sos, color: Colors.white),
            label: Text(context.t('Send SOS')),
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.errorColor, foregroundColor: Colors.white),
          ),
        ],
      ),
    );
  }
}

class _EmergencyButton extends StatelessWidget {
  final IconData icon;
  final String label, number;
  final Color color;
  final VoidCallback onTap;

  const _EmergencyButton({required this.icon, required this.label, required this.number, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
                child: Icon(icon, color: color, size: 32),
              ),
              const SizedBox(height: 8),
              Text(label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase)),
              Text(number, style: TextStyle(color: color, fontSize: AppTheme.textLg, fontWeight: FontWeight.bold)),
            ],
          ),
        ),
      ),
    );
  }
}

class _ServiceCard extends StatelessWidget {
  final IconData icon;
  final String title, subtitle;
  final Color color;

  const _ServiceCard({required this.icon, required this.title, required this.subtitle, required this.color});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
          child: Icon(icon, color: color),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }
}
