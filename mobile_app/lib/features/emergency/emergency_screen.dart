import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import "../../core/services/localization_service.dart";
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/constants/app_constants.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/sos_provider.dart';
import '../places/explore_search_screen.dart';
import '../places/nearby_map_screen.dart';
import 'sos_activation_screen.dart';
import 'emergency_contacts_screen.dart';

class EmergencyScreen extends StatefulWidget {
  const EmergencyScreen({super.key});

  @override
  State<EmergencyScreen> createState() => _EmergencyScreenState();
}

class _EmergencyScreenState extends State<EmergencyScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<SosProvider>();
      provider.checkActiveSos();
      provider.fetchContacts();
      provider.fetchSosForMe();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('Emergency Support')),
        backgroundColor: AppTheme.errorColor,
        foregroundColor: Colors.white,
        systemOverlayStyle: const SystemUiOverlayStyle(
          statusBarColor: AppTheme.errorColor,
          statusBarIconBrightness: Brightness.light,
          statusBarBrightness: Brightness.dark,
        ),
        actions: [
          IconButton(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const EmergencyContactsScreen()),
            ),
            icon: const Icon(Icons.contact_phone),
            tooltip: context.t('Emergency Contacts'),
          ),
        ],
      ),
      body: Consumer<SosProvider>(
        builder: (context, provider, _) {
          return SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Active SOS banner
                if (provider.hasActiveSos) ...[
                  _ActiveSosBanner(sos: provider.activeSos!),
                  const SizedBox(height: 20),
                ],

                // SOS Button
                if (!provider.hasActiveSos) ...[
                  _SOSPulseButton(
                    onTap: () => _showSOSCountdown(context),
                  ),
                  const SizedBox(height: 8),
                  Center(
                    child: Text(
                      context.t('Tap SOS for immediate emergency assistance'),
                      style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm),
                    ),
                  ),
                  const SizedBox(height: 24),
                ],

                // SOS alerts sent by contacts listing you — so you see any you missed while offline
                if (provider.inboxSos.isNotEmpty) ...[
                  _SectionHeader(
                    icon: Icons.notification_important,
                    title: context.t('SOS Alerts from Contacts'),
                  ),
                  const SizedBox(height: 12),
                  _InboxSosList(alerts: provider.inboxSos),
                  const SizedBox(height: 24),
                ],

                // Emergency Contacts
                _SectionHeader(icon: Icons.contact_phone, title: context.t('Emergency Contacts')),
                const SizedBox(height: 12),
                _EmergencyContactsPreview(
                  onViewAll: () => Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const EmergencyContactsScreen()),
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
                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ExploreSearchScreen(category: 'Blood Bank'))),
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
                const _TipsBox(),
              ],
            ),
          );
        },
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

  void _showSOSCountdown(BuildContext context) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const SosActivationScreen()),
    );
  }
}

// ============ ACTIVE SOS BANNER ============
class _ActiveSosBanner extends StatelessWidget {
  final dynamic sos;
  const _ActiveSosBanner({required this.sos});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const SosActiveScreen()),
      ),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppTheme.errorColor,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: AppTheme.errorColor.withOpacity(0.3),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.2),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(Icons.sos, color: Colors.white, size: 28),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'SOS ACTIVE',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1,
                    ),
                  ),
                  Text(
                    'Your emergency alert is active',
                    style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.normal),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: Colors.white),
          ],
        ),
      ),
    );
  }
}

// ============ EMERGENCY CONTACTS PREVIEW ============
class _EmergencyContactsPreview extends StatelessWidget {
  final VoidCallback onViewAll;
  const _EmergencyContactsPreview({required this.onViewAll});

  @override
  Widget build(BuildContext context) {
    return Consumer<SosProvider>(
      builder: (context, provider, _) {
        final contacts = provider.contacts;
        return Card(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          child: ListTile(
            onTap: onViewAll,
            leading: Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: AppTheme.errorColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(Icons.contact_phone, color: AppTheme.errorColor),
            ),
            title: Text(
              contacts.isEmpty
                  ? context.t('No emergency contacts')
                  : '${contacts.length} ${context.t('emergency contacts configured')}',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            subtitle: Text(
              contacts.isEmpty
                  ? context.t('Add contacts to notify during SOS')
                  : context.t('Tap to manage'),
              style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
            ),
            trailing: const Icon(Icons.chevron_right),
          ),
        );
      },
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
                              fontWeight: FontWeight.w700,
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
                      style: TextStyle(color: color, fontSize: AppTheme.textLg, fontWeight: FontWeight.w700),
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

// ============ SOS INBOX (alerts from your contacts) ============
class _InboxSosList extends StatelessWidget {
  final List<SosAlert> alerts;

  const _InboxSosList({required this.alerts});

  static String _typeLabel(String type, BuildContext context) {
    switch (type) {
      case 'medical':
        return context.t('Medical Emergency');
      case 'accident':
        return context.t('Accident');
      case 'flood':
        return context.t('Flood');
      default:
        return context.t('Emergency');
    }
  }

  static String _relative(DateTime? time) {
    if (time == null) return '';
    final diff = DateTime.now().difference(time);
    if (diff.inSeconds < 60) return 'just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    return '${diff.inDays}d ago';
  }

  void _openLocation(SosAlert sos, BuildContext context) {
    // Show the SOS location in the in-app Nearby Map screen (centered on the
    // alert) instead of opening external Google Maps.
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => NearbyMapScreen(
          focusLat: sos.latitude,
          focusLng: sos.longitude,
          focusLabel: sos.userName ?? context.t('SOS location'),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final active = alerts.where((s) => s.status == 'active').toList();
    return Column(
      children: [
        for (final sos in alerts)
          Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
            decoration: BoxDecoration(
              color: sos.isActive
                  ? AppTheme.errorColor.withOpacity(0.12)
                  : AppTheme.surfaceColor.withOpacity(0.6),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: sos.isActive
                    ? AppTheme.errorColor.withOpacity(0.5)
                    : AppTheme.textSecondary.withOpacity(0.25),
              ),
            ),
            child: InkWell(
              onTap: () => _openLocation(sos, context),
              borderRadius: BorderRadius.circular(14),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(9),
                    decoration: BoxDecoration(
                      color: (sos.isActive ? AppTheme.errorColor : AppTheme.warningColor)
                          .withOpacity(0.15),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      sos.isActive ? Icons.sos : Icons.sos_outlined,
                      color: sos.isActive ? AppTheme.errorColor : AppTheme.warningColor,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          sos.userName ?? context.t('Emergency contact'),
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: AppTheme.textBase),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${_typeLabel(sos.emergencyType, context)} • ${_relative(sos.startedAt)}',
                          style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary),
                        ),
                        if (sos.message != null && sos.message!.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Text(
                              sos.message!,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontSize: AppTheme.textSm, height: 1.3),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: (sos.isActive ? AppTheme.errorColor : AppTheme.successColor)
                          .withOpacity(0.15),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      sos.isActive
                          ? context.t('ACTIVE')
                          : (sos.status == 'cancelled' ? context.t('CANCELLED') : context.t('RESOLVED')),
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        color: sos.isActive ? AppTheme.errorColor : AppTheme.successColor,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        if (active.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Row(
              children: [
                const Icon(Icons.info_outline, size: 14, color: AppTheme.warningColor),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    context.t('Tap an alert to open its live location on the map.'),
                    style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary),
                  ),
                ),
              ],
            ),
          ),
      ],
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
