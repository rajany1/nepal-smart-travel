import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import 'package:url_launcher/url_launcher.dart';
import '../../config/constants/app_constants.dart';
import '../../config/themes/app_theme.dart';
import '../places/explore_search_screen.dart';

class EmergencyScreen extends StatelessWidget {
  const EmergencyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('Emergency Support')),
        backgroundColor: AppTheme.errorColor,
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // SOS Button (pulsing)
            _SOSPulseButton(onTap: () => _showSOSDialog(context)),
            const SizedBox(height: 8),
            Center(
              child: Text(
                context.t('Tap SOS for immediate emergency assistance'),
                style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm),
              ),
            ),
            const SizedBox(height: 24),

            // Quick Dial Grid
            _SectionHeader(icon: Icons.phone_in_talk, title: context.t('Quick Emergency Contacts')),
            const SizedBox(height: 12),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.1,
              children: [
                _EmergencyButton(
                  icon: Icons.local_hospital,
                  label: context.t('Ambulance'),
                  number: AppConstants.ambulanceNumber,
                  color: AppTheme.ambulanceColor,
                  onTap: () => _makeCall(AppConstants.ambulanceNumber),
                ),
                _EmergencyButton(
                  icon: Icons.local_police,
                  label: context.t('Police'),
                  number: AppConstants.policeNumber,
                  color: AppTheme.policeColor,
                  onTap: () => _makeCall(AppConstants.policeNumber),
                ),
                _EmergencyButton(
                  icon: Icons.fire_extinguisher,
                  label: context.t('Fire'),
                  number: AppConstants.fireNumber,
                  color: AppTheme.warningColor,
                  onTap: () => _makeCall(AppConstants.fireNumber),
                ),
                _EmergencyButton(
                  icon: Icons.local_hospital,
                  label: context.t('Hospital'),
                  number: context.t('Search'),
                  color: AppTheme.hospitalColor,
                  onTap: () => _openPlacesSearch(context),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Medical & Rescue
            _SectionHeader(icon: Icons.medical_services_outlined, title: context.t('Medical & Rescue Services')),
            const SizedBox(height: 12),
            _ServiceCard(
              icon: Icons.bloodtype,
              title: context.t('Blood Bank'),
              subtitle: context.t('Find nearest blood bank'),
              color: AppTheme.errorColor,
              onTap: () => _openPlacesSearch(context),
            ),
            _ServiceCard(
              icon: Icons.medication,
              title: context.t('24/7 Pharmacy'),
              subtitle: context.t('Nearby pharmacies open now'),
              color: AppTheme.infoColor,
              onTap: () => _openPlacesSearch(context),
            ),
            _ServiceCard(
              icon: Icons.airline_seat_individual_suite,
              title: context.t('Mountain Rescue'),
              subtitle: context.t('Emergency mountain rescue services'),
              color: AppTheme.severityCritical,
              onTap: () => _openPlacesSearch(context),
            ),
            _ServiceCard(
              icon: Icons.contact_phone,
              title: context.t('Tourist Police'),
              subtitle: context.t('Helpline for tourists: 1144'),
              color: AppTheme.policeColor,
              onTap: () => _makeCall('1144'),
            ),

            const SizedBox(height: 24),
            // Emergency Tips
            const _TipsBox(),
          ],
        ),
      ),
    );
  }

  void _openPlacesSearch(BuildContext context) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const ExploreSearchScreen()),
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
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppTheme.errorColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.sos, color: AppTheme.errorColor, size: 28),
            ),
            const SizedBox(width: 10),
            Text(context.t('SOS Emergency')),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              context.t('Your emergency alert will be sent to your emergency contacts with your current location.'),
              style: const TextStyle(fontSize: AppTheme.textBase),
            ),
            const SizedBox(height: 16),
            Text(
              context.t('Emergency contacts will be notified immediately.'),
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(context.t('Cancel'))),
          ElevatedButton.icon(
            onPressed: () {
              Navigator.pop(ctx);
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(context.t('SOS Alert Sent! Emergency contacts notified with your location.')),
                ),
              );
              _makeCall(AppConstants.policeNumber);
            },
            icon: const Icon(Icons.sos, color: Colors.white),
            label: Text(context.t('Send SOS')),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.errorColor,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
          ),
        ],
      ),
    );
  }
}

// ============ SOS PULSING BUTTON ============
class _SOSPulseButton extends StatefulWidget {
  final VoidCallback onTap;
  const _SOSPulseButton({required this.onTap});

  @override
  State<_SOSPulseButton> createState() => _SOSPulseButtonState();
}

class _SOSPulseButtonState extends State<_SOSPulseButton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final t = Curves.easeInOut.transform(_controller.value);
        return Transform.scale(
          scale: 1.0 + 0.025 * t,
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: AppTheme.errorColor.withOpacity(0.25 + 0.35 * t),
                  blurRadius: 18 + 10 * t,
                  spreadRadius: 1 + 3 * t,
                ),
              ],
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                borderRadius: BorderRadius.circular(20),
                onTap: widget.onTap,
                child: Ink(
                  height: 88,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        AppTheme.errorColor,
                        AppTheme.errorColor.withOpacity(0.75),
                      ],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.sos, size: 40, color: Colors.white),
                      const SizedBox(width: 14),
                      Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            context.t('SOS EMERGENCY'),
                            style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w800,
                              color: Colors.white,
                              letterSpacing: 0.5,
                            ),
                          ),
                          Text(
                            context.t('Tap for immediate help'),
                            style: const TextStyle(fontSize: 12, color: Colors.white70),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

// ============ SECTION HEADER ============
class _SectionHeader extends StatelessWidget {
  final IconData icon;
  final String title;
  const _SectionHeader({required this.icon, required this.title});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(6),
          decoration: BoxDecoration(
            color: AppTheme.errorColor.withOpacity(0.1),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, size: 18, color: AppTheme.errorColor),
        ),
        const SizedBox(width: 10),
        Text(title, style: const TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold)),
      ],
    );
  }
}

// ============ QUICK DIAL BUTTON ============
class _EmergencyButton extends StatelessWidget {
  final IconData icon;
  final String label, number;
  final Color color;
  final VoidCallback onTap;

  const _EmergencyButton({
    required this.icon,
    required this.label,
    required this.number,
    required this.color,
    required this.onTap,
  });

  bool get _isNumber {
    final trimmed = number.replaceAll(' ', '');
    if (trimmed.isEmpty) return false;
    return trimmed.split('').every((c) => int.tryParse(c) != null);
  }

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [color.withOpacity(0.14), color.withOpacity(0.05)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: color.withOpacity(0.25)),
          ),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                    boxShadow: [
                      BoxShadow(
                        color: color.withOpacity(0.3),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Icon(icon, color: color, size: 28),
                ),
                const SizedBox(height: 8),
                Text(
                  label,
                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(_isNumber ? Icons.call : Icons.search, size: 13, color: color),
                    const SizedBox(width: 3),
                    Text(
                      number,
                      style: TextStyle(color: color, fontSize: AppTheme.textLg, fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ============ SERVICE CARD ============
class _ServiceCard extends StatelessWidget {
  final IconData icon;
  final String title, subtitle;
  final Color color;
  final VoidCallback onTap;

  const _ServiceCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: color.withOpacity(0.15)),
      ),
      child: ListTile(
        onTap: onTap,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        leading: Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [color.withOpacity(0.15), color.withOpacity(0.05)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: color, size: 22),
        ),
        title: Text(
          title,
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: AppTheme.textBase),
        ),
        subtitle: Text(subtitle, style: const TextStyle(fontSize: AppTheme.textSm)),
        trailing: Container(
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            color: color.withOpacity(0.12),
            shape: BoxShape.circle,
          ),
          child: Icon(Icons.chevron_right, color: color, size: 18),
        ),
      ),
    );
  }
}

// ============ EMERGENCY TIPS BOX ============
class _TipsBox extends StatelessWidget {
  const _TipsBox();

  @override
  Widget build(BuildContext context) {
    final tips = [
      (Icons.self_improvement, context.t('Stay calm and assess the situation')),
      (Icons.call, context.t('Call the appropriate emergency number')),
      (Icons.location_on, context.t('Share your exact location')),
      (Icons.hearing, context.t('Follow instructions from emergency services')),
      (Icons.bookmark, context.t('Keep emergency contacts saved offline')),
    ];
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppTheme.warningColor.withOpacity(0.12),
            AppTheme.warningColor.withOpacity(0.04),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.warningColor.withOpacity(0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(7),
                decoration: BoxDecoration(
                  color: AppTheme.warningColor.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.lightbulb, color: AppTheme.warningColor, size: 20),
              ),
              const SizedBox(width: 10),
              Text(
                context.t('Emergency Tips'),
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textBase + 2),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (final tip in tips)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(tip.$1, size: 16, color: AppTheme.warningColor),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      tip.$2,
                      style: const TextStyle(fontSize: AppTheme.textSm + 1, height: 1.4),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}