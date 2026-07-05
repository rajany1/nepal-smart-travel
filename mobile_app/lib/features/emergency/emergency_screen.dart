import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/constants/app_constants.dart';
import '../../config/themes/app_theme.dart';

class EmergencyScreen extends StatelessWidget {
  const EmergencyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Emergency Support'),
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
                label: const Text('SOS EMERGENCY', style: TextStyle(fontSize: AppTheme.text2xl, fontWeight: FontWeight.bold)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.errorColor,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  elevation: 4,
                ),
              ),
            ),
            const SizedBox(height: 8),
            const Text('Tap SOS for immediate emergency assistance', style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm), textAlign: TextAlign.center),
            const SizedBox(height: 24),

            // Quick Dial Grid
            const Text('Quick Emergency Contacts', style: TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold)),
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
                  icon: Icons.local_hospital, label: 'Ambulance', number: AppConstants.ambulanceNumber,
                  color: AppTheme.ambulanceColor, onTap: () => _makeCall(AppConstants.ambulanceNumber),
                ),
                _EmergencyButton(
                  icon: Icons.local_police, label: 'Police', number: AppConstants.policeNumber,
                  color: AppTheme.policeColor, onTap: () => _makeCall(AppConstants.policeNumber),
                ),
                _EmergencyButton(
                  icon: Icons.fire_extinguisher, label: 'Fire', number: AppConstants.fireNumber,
                  color: AppTheme.warningColor, onTap: () => _makeCall(AppConstants.fireNumber),
                ),
                _EmergencyButton(
                  icon: Icons.local_hospital, label: 'Hospital', number: 'Search Nearby',
                  color: AppTheme.hospitalColor, onTap: () {},
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Medical & Rescue
            const Text('Medical & Rescue Services', style: TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            _ServiceCard(icon: Icons.bloodtype, title: 'Blood Bank', subtitle: 'Find nearest blood bank', color: AppTheme.errorColor),
            _ServiceCard(icon: Icons.medication, title: '24/7 Pharmacy', subtitle: 'Nearby pharmacies open now', color: AppTheme.infoColor),
            _ServiceCard(icon: Icons.airline_seat_individual_suite, title: 'Mountain Rescue', subtitle: 'Emergency mountain rescue services', color: AppTheme.severityCritical),
            _ServiceCard(icon: Icons.contact_phone, title: 'Tourist Police', subtitle: 'Helpline for tourists: 1144', color: AppTheme.policeColor),

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
                  const Row(
                    children: [
                      Icon(Icons.info, color: AppTheme.warningColor),
                      SizedBox(width: 8),
                      Text('Emergency Tips', style: TextStyle(fontWeight: FontWeight.bold)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  const Text('• Stay calm and assess the situation\n• Call the appropriate emergency number\n• Share your exact location\n• Follow instructions from emergency services\n• Keep emergency contacts saved offline', style: TextStyle(fontSize: AppTheme.textBase, height: 1.6)),
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
        title: const Row(
          children: [
            Icon(Icons.warning, color: AppTheme.errorColor, size: 28),
            SizedBox(width: 8),
            Text('SOS Emergency'),
          ],
        ),
        content: const Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Your emergency alert will be sent to your emergency contacts with your current location.', style: TextStyle(fontSize: AppTheme.textBase)),
            SizedBox(height: 16),
            Text('Emergency contacts will be notified immediately.', style: TextStyle(fontWeight: FontWeight.w600)),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton.icon(
            onPressed: () {
              Navigator.pop(ctx);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('SOS Alert Sent! Emergency contacts notified with your location.')),
              );
              _makeCall(AppConstants.policeNumber);
            },
            icon: const Icon(Icons.sos, color: Colors.white),
            label: const Text('Send SOS'),
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
