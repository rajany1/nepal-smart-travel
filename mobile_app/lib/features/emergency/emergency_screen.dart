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

  Future<void> _refresh() async {
    final provider = context.read<SosProvider>();
    await Future.wait([
      provider.checkActiveSos(),
      provider.fetchContacts(),
      provider.fetchSosForMe(),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: AppTheme.backgroundColor,
        statusBarIconBrightness: Brightness.dark,
        statusBarBrightness: Brightness.light,
      ),
      child: Scaffold(
        backgroundColor: AppTheme.backgroundColor,
        body: SafeArea(
          child: Consumer<SosProvider>(
            builder: (context, provider, _) {
              return RefreshIndicator(
                color: AppTheme.errorColor,
                onRefresh: _refresh,
                child: SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 100),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildHeader(context, provider),
                      const SizedBox(height: 4),

                      // Active SOS banner or the SOS hero card
                      if (provider.hasActiveSos) ...[
                        _ActiveSosBanner(sos: provider.activeSos!),
                        const SizedBox(height: 22),
                      ] else ...[
                        _SosHeroCard(
                          contactCount: provider.contacts.length,
                          onTap: () => _showSOSCountdown(context),
                        ),
                        const SizedBox(height: 24),
                      ],

                      // Quick dial numbers
                      _SectionHeader(
                        icon: Icons.phone_in_talk_rounded,
                        title: context.t('Quick Emergency Numbers'),
                        color: AppTheme.errorColor,
                        trailing: _Pill(
                          label: context.t('Free'),
                          color: AppTheme.errorColor,
                        ),
                      ),
                      const SizedBox(height: 10),
                      _buildQuickNumbers(context),
                      const SizedBox(height: 24),

                      // Emergency contacts
                      _SectionHeader(
                        icon: Icons.contacts_rounded,
                        title: context.t('Emergency Contacts'),
                        color: AppTheme.primaryColor,
                        trailing: provider.contacts.isEmpty
                            ? null
                            : _Pill(
                                label: '${provider.contacts.length}',
                                color: AppTheme.primaryColor,
                              ),
                      ),
                      const SizedBox(height: 10),
                      _EmergencyContactsPreview(
                        onViewAll: () => _openContacts(context),
                      ),
                      const SizedBox(height: 24),

                      // SOS alerts from contacts
                      if (provider.inboxSos.isNotEmpty) ...[
                        _SectionHeader(
                          icon: Icons.notification_important_rounded,
                          title: context.t('SOS Alerts from Contacts'),
                          color: AppTheme.warningColor,
                          trailing: _Pill(
                            label: '${provider.inboxSos.length}',
                            color: AppTheme.warningColor,
                          ),
                        ),
                        const SizedBox(height: 10),
                        _InboxSosList(alerts: provider.inboxSos),
                        const SizedBox(height: 24),
                      ],

                      // Medical & rescue services
                      _SectionHeader(
                        icon: Icons.medical_services_rounded,
                        title: context.t('Medical & Rescue Services'),
                        color: AppTheme.infoColor,
                      ),
                      const SizedBox(height: 10),
                      _buildMedicalServices(context),
                      const SizedBox(height: 24),

                      const _TipsBox(),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(BuildContext context, SosProvider provider) {
    final canPop = Navigator.of(context).canPop();
    return Padding(
      padding: const EdgeInsets.fromLTRB(2, 10, 2, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (canPop) ...[
                _RoundIconButton(
                  icon: Icons.arrow_back_rounded,
                  tooltip: context.t('Back'),
                  onTap: () => Navigator.pop(context),
                ),
                const SizedBox(width: 8),
              ],
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [AppTheme.errorColor, AppTheme.secondaryColor],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [
                    BoxShadow(
                      color: AppTheme.errorColor.withOpacity(0.24),
                      blurRadius: 12,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: const Icon(Icons.health_and_safety_rounded,
                    color: Colors.white, size: 25),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.t('ALWAYS ON'),
                      style: const TextStyle(
                        fontSize: 9.5,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.3,
                        color: AppTheme.errorColor,
                      ),
                    ),
                    const SizedBox(height: 1),
                    Text(
                      context.t('Emergency'),
                      style: const TextStyle(
                        fontSize: 25,
                        fontWeight: FontWeight.w800,
                        color: AppTheme.textPrimary,
                        letterSpacing: -0.6,
                        height: 1.1,
                      ),
                    ),
                  ],
                ),
              ),
              _ContactActionButton(
                count: provider.contacts.length,
                onTap: () => _openContacts(context),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: AppTheme.errorColor.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(20),
                  border:
                      Border.all(color: AppTheme.errorColor.withOpacity(0.16)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.shield_rounded,
                        size: 13, color: AppTheme.errorColor),
                    const SizedBox(width: 5),
                    Text(
                      context.t('24/7 · Free'),
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: AppTheme.errorColor,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  context.t('Help is one tap away'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppTheme.textSecondary,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildQuickNumbers(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: 4,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        mainAxisExtent: 126,
      ),
      itemBuilder: (context, index) {
        switch (index) {
          case 0:
            return _QuickDialCard(
              icon: Icons.local_hospital_rounded,
              label: context.t('Ambulance'),
              number: AppConstants.ambulanceNumber,
              caption: context.t('Tap to call'),
              color: AppTheme.ambulanceColor,
              onTap: () => _makeCall(AppConstants.ambulanceNumber),
            );
          case 1:
            return _QuickDialCard(
              icon: Icons.local_police_rounded,
              label: context.t('Police'),
              number: AppConstants.policeNumber,
              caption: context.t('Tap to call'),
              color: AppTheme.policeColor,
              onTap: () => _makeCall(AppConstants.policeNumber),
            );
          case 2:
            return _QuickDialCard(
              icon: Icons.local_fire_department_rounded,
              label: context.t('Fire'),
              number: AppConstants.fireNumber,
              caption: context.t('Tap to call'),
              color: AppTheme.warningColor,
              onTap: () => _makeCall(AppConstants.fireNumber),
            );
          default:
            return _QuickDialCard(
              icon: Icons.medical_services_rounded,
              label: context.t('Hospital'),
              number: context.t('Search'),
              caption: context.t('Nearby hospitals'),
              color: AppTheme.hospitalColor,
              onTap: () => _openPlacesSearch(context),
            );
        }
      },
    );
  }

  Widget _buildMedicalServices(BuildContext context) {
    final services = [
      (
        Icons.bloodtype_rounded,
        context.t('Blood Bank'),
        context.t('Find nearest'),
        AppTheme.errorColor,
        () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) =>
                    const ExploreSearchScreen(category: 'Blood Bank'),
              ),
            ),
      ),
      (
        Icons.medication_rounded,
        context.t('24/7 Pharmacy'),
        context.t('Open now nearby'),
        AppTheme.infoColor,
        () => _openPlacesSearch(context),
      ),
      (
        Icons.terrain_rounded,
        context.t('Mountain Rescue'),
        context.t('Rescue services'),
        AppTheme.severityCritical,
        () => _openPlacesSearch(context),
      ),
      (
        Icons.tour_rounded,
        context.t('Tourist Police'),
        context.t('Helpline 1144'),
        AppTheme.policeColor,
        () => _makeCall('1144'),
      ),
    ];

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: services.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        mainAxisExtent: 116,
      ),
      itemBuilder: (context, index) {
        final service = services[index];
        return _ServiceTile(
          icon: service.$1,
          title: service.$2,
          subtitle: service.$3,
          color: service.$4,
          onTap: service.$5,
        );
      },
    );
  }

  void _openContacts(BuildContext context) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const EmergencyContactsScreen()),
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

// ============ HEADER ACTION BUTTONS ============
class _RoundIconButton extends StatelessWidget {
  final IconData icon;
  final String tooltip;
  final VoidCallback onTap;

  const _RoundIconButton({
    required this.icon,
    required this.tooltip,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppTheme.dividerColor),
            ),
            child: Icon(icon, size: 20, color: AppTheme.textPrimary),
          ),
        ),
      ),
    );
  }
}

class _ContactActionButton extends StatelessWidget {
  final int count;
  final VoidCallback onTap;

  const _ContactActionButton({required this.count, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: context.t('Emergency Contacts'),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppTheme.errorColor.withOpacity(0.18)),
              boxShadow: [
                BoxShadow(
                  color: AppTheme.primaryColor.withOpacity(0.05),
                  blurRadius: 10,
                  offset: const Offset(0, 3),
                ),
              ],
            ),
            child: Stack(
              clipBehavior: Clip.none,
              alignment: Alignment.center,
              children: [
                Icon(
                  count > 0
                      ? Icons.contacts_rounded
                      : Icons.person_add_alt_1_rounded,
                  size: 21,
                  color: AppTheme.errorColor,
                ),
                if (count > 0)
                  Positioned(
                    top: -4,
                    right: -4,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 5, vertical: 1.5),
                      decoration: BoxDecoration(
                        color: AppTheme.errorColor,
                        borderRadius: BorderRadius.circular(9),
                        border: Border.all(color: Colors.white, width: 1.5),
                      ),
                      child: Text(
                        '$count',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ============ SOS HERO CARD ============
class _SosHeroCard extends StatefulWidget {
  final int contactCount;
  final VoidCallback onTap;

  const _SosHeroCard({required this.contactCount, required this.onTap});

  @override
  State<_SosHeroCard> createState() => _SosHeroCardState();
}

class _SosHeroCardState extends State<_SosHeroCard>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1600),
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
        return Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            boxShadow: [
              BoxShadow(
                color: AppTheme.errorColor.withOpacity(0.16 + 0.14 * t),
                blurRadius: 16 + 8 * t,
                offset: const Offset(0, 7),
              ),
            ],
          ),
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              borderRadius: BorderRadius.circular(22),
              onTap: widget.onTap,
              child: Ink(
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 14),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [AppTheme.errorColor, AppTheme.secondaryColor],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(22),
                ),
                child: Column(
                  children: [
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final showArrow = constraints.maxWidth >= 280;
                        return Row(
                          children: [
                            Container(
                              width: 54,
                              height: 54,
                              decoration: BoxDecoration(
                                color: Colors.white.withOpacity(0.16),
                                shape: BoxShape.circle,
                                border: Border.all(
                                    color: Colors.white.withOpacity(0.28),
                                    width: 1.5),
                              ),
                              child: const Icon(Icons.sos_rounded,
                                  color: Colors.white, size: 30),
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    context.t('SOS EMERGENCY'),
                                    style: const TextStyle(
                                      fontSize: 20,
                                      fontWeight: FontWeight.w800,
                                      color: Colors.white,
                                      letterSpacing: 0.3,
                                      height: 1.1,
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    context.t(
                                        'Alert contacts and share your live location'),
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: Colors.white.withOpacity(0.85),
                                      height: 1.3,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (showArrow)
                              Container(
                                width: 32,
                                height: 32,
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.18),
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(Icons.arrow_forward_rounded,
                                    color: Colors.white, size: 18),
                              ),
                          ],
                        );
                      },
                    ),
                    const SizedBox(height: 14),
                    Container(
                      height: 1,
                      color: Colors.white.withOpacity(0.22),
                    ),
                    const SizedBox(height: 11),
                    Row(
                      children: [
                        Icon(Icons.notifications_active_rounded,
                            size: 14, color: Colors.white.withOpacity(0.85)),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            widget.contactCount > 0
                                ? widget.contactCount == 1
                                    ? '1 ${context.t('contact ready')}'
                                    : '${widget.contactCount} ${context.t('contacts ready')}'
                                : context.t('Add contacts to alert them'),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w600,
                              color: Colors.white.withOpacity(0.9),
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            context.t('ACTIVATE'),
                            style: const TextStyle(
                              fontSize: 9.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.8,
                              color: AppTheme.errorColor,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

// ============ ACTIVE SOS BANNER ============
class _ActiveSosBanner extends StatelessWidget {
  final SosAlert sos;
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
          gradient: const LinearGradient(
            colors: [AppTheme.severityCritical, AppTheme.errorColor],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: AppTheme.errorColor.withOpacity(0.3),
              blurRadius: 14,
              offset: const Offset(0, 5),
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
              child: const Icon(Icons.sos_rounded,
                  color: Colors.white, size: 28),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 8,
                        height: 8,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: Colors.white.withOpacity(0.7),
                              blurRadius: 5,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        context.t('SOS ACTIVE'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.8,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(
                    context.t('Your emergency alert is live — tap to view'),
                    style: TextStyle(
                        color: Colors.white.withOpacity(0.85),
                        fontSize: 12.5),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded,
                color: Colors.white, size: 24),
          ],
        ),
      ),
    );
  }
}

// ============ SECTION HEADER ============
class _SectionHeader extends StatelessWidget {
  final IconData icon;
  final String title;
  final Color color;
  final Widget? trailing;

  const _SectionHeader({
    required this.icon,
    required this.title,
    required this.color,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: color.withOpacity(0.1),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, size: 18, color: color),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: AppTheme.textPrimary,
              letterSpacing: -0.2,
            ),
          ),
        ),
        if (trailing != null) trailing!,
      ],
    );
  }
}

// ============ SMALL PILL ============
class _Pill extends StatelessWidget {
  final String label;
  final Color color;

  const _Pill({required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.09),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withOpacity(0.16)),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w800,
          color: color,
        ),
      ),
    );
  }
}

// ============ QUICK DIAL CARD ============
class _QuickDialCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final String number;
  final String caption;
  final Color color;
  final VoidCallback onTap;

  const _QuickDialCard({
    required this.icon,
    required this.label,
    required this.number,
    required this.caption,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: color.withOpacity(0.16)),
            boxShadow: [
              BoxShadow(
                color: color.withOpacity(0.06),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          color.withOpacity(0.18),
                          color.withOpacity(0.06),
                        ],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(icon, color: color, size: 19),
                  ),
                  const Spacer(),
                  Container(
                    width: 26,
                    height: 26,
                    decoration: BoxDecoration(
                      color: color.withOpacity(0.08),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(Icons.call_rounded, color: color, size: 13),
                  ),
                ],
              ),
              const SizedBox(height: 7),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: AppTheme.textPrimary,
                  height: 1.2,
                ),
              ),
              const SizedBox(height: 1),
              Text(
                number,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 19,
                  fontWeight: FontWeight.w800,
                  color: color,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 1),
              Text(
                caption,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 10,
                  color: AppTheme.textSecondary,
                  height: 1.2,
                ),
              ),
            ],
          ),
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
        final hasContacts = contacts.isNotEmpty;

        return Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          child: InkWell(
            onTap: onViewAll,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: hasContacts
                      ? AppTheme.primaryColor.withOpacity(0.14)
                      : AppTheme.errorColor.withOpacity(0.16),
                ),
                boxShadow: [
                  BoxShadow(
                    color: AppTheme.primaryColor.withOpacity(0.05),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Row(
                children: [
                  if (hasContacts)
                    _AvatarStack(contacts: contacts)
                  else
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            AppTheme.errorColor.withOpacity(0.16),
                            AppTheme.errorColor.withOpacity(0.05),
                          ],
                        ),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(Icons.person_add_alt_1_rounded,
                          color: AppTheme.errorColor, size: 21),
                    ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          hasContacts
                              ? '${contacts.length} ${contacts.length == 1 ? context.t('contact') : context.t('contacts')}'
                              : context.t('No emergency contacts'),
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppTheme.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          hasContacts
                              ? context.t('Live location on SOS')
                              : context.t(
                                  'Add trusted people for SOS alerts'),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 11,
                            color: AppTheme.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(
                      color: (hasContacts
                              ? AppTheme.primaryColor
                              : AppTheme.errorColor)
                          .withOpacity(0.08),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          hasContacts
                              ? context.t('Manage')
                              : context.t('Add'),
                          style: TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.w800,
                            color: hasContacts
                                ? AppTheme.primaryColor
                                : AppTheme.errorColor,
                          ),
                        ),
                        const SizedBox(width: 2),
                        Icon(
                          Icons.chevron_right_rounded,
                          size: 14,
                          color: hasContacts
                              ? AppTheme.primaryColor
                              : AppTheme.errorColor,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

class _AvatarStack extends StatelessWidget {
  final List<EmergencyContact> contacts;

  const _AvatarStack({required this.contacts});

  static const _colors = [
    Color(0xFFE74C3C),
    Color(0xFF2980B9),
    Color(0xFF27AE60),
    Color(0xFFF39C12),
  ];

  @override
  Widget build(BuildContext context) {
    final shown = contacts.take(3).toList();
    return SizedBox(
      width: 38 + (shown.length - 1) * 16,
      height: 38,
      child: Stack(
        children: [
          for (var i = 0; i < shown.length; i++)
            Positioned(
              left: i * 16,
              child: Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [
                      _colors[i % _colors.length],
                      _colors[i % _colors.length].withOpacity(0.75),
                    ],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 2),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.08),
                      blurRadius: 4,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Center(
                  child: Text(
                    shown[i].name.isNotEmpty
                        ? shown[i].name[0].toUpperCase()
                        : '?',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

// ============ SERVICE TILE ============
class _ServiceTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  const _ServiceTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: color.withOpacity(0.14)),
            boxShadow: [
              BoxShadow(
                color: color.withOpacity(0.05),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          color.withOpacity(0.18),
                          color.withOpacity(0.06),
                        ],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(icon, color: color, size: 18),
                  ),
                  const Spacer(),
                  Icon(Icons.arrow_outward_rounded,
                      color: color.withOpacity(0.6), size: 15),
                ],
              ),
              const SizedBox(height: 7),
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: AppTheme.textPrimary,
                  height: 1.2,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 10.5,
                  color: AppTheme.textSecondary,
                  height: 1.3,
                ),
              ),
            ],
          ),
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
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: sos.isActive
                    ? AppTheme.errorColor.withOpacity(0.4)
                    : AppTheme.dividerColor,
              ),
              boxShadow: [
                BoxShadow(
                  color: (sos.isActive
                          ? AppTheme.errorColor
                          : AppTheme.textSecondary)
                      .withOpacity(0.05),
                  blurRadius: 10,
                  offset: const Offset(0, 3),
                ),
              ],
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: () => _openLocation(sos, context),
                borderRadius: BorderRadius.circular(16),
                child: Padding(
                  padding:
                      const EdgeInsets.symmetric(vertical: 12, horizontal: 12),
                  child: Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color: (sos.isActive
                                  ? AppTheme.errorColor
                                  : AppTheme.warningColor)
                              .withOpacity(0.12),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          sos.isActive ? Icons.sos_rounded : Icons.sos_outlined,
                          color: sos.isActive
                              ? AppTheme.errorColor
                              : AppTheme.warningColor,
                          size: 20,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              sos.userName ??
                                  context.t('Emergency contact'),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 13.5,
                                color: AppTheme.textPrimary,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '${_typeLabel(sos.emergencyType, context)} • ${_relative(sos.startedAt)}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 11,
                                color: AppTheme.textSecondary,
                              ),
                            ),
                            if (sos.message != null &&
                                sos.message!.isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.only(top: 3),
                                child: Text(
                                  sos.message!,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                      fontSize: 11.5, height: 1.3),
                                ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: (sos.isActive
                                  ? AppTheme.errorColor
                                  : AppTheme.successColor)
                              .withOpacity(0.12),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          sos.isActive
                              ? context.t('ACTIVE')
                              : (sos.status == 'cancelled'
                                  ? context.t('CANCELLED')
                                  : context.t('RESOLVED')),
                          style: TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.3,
                            color: sos.isActive
                                ? AppTheme.errorColor
                                : AppTheme.successColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        if (active.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Row(
              children: [
                const Icon(Icons.info_outline_rounded,
                    size: 14, color: AppTheme.warningColor),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    context.t(
                        'Tap an alert to open its live location on the map.'),
                    style: const TextStyle(
                        fontSize: AppTheme.textSm,
                        color: AppTheme.textSecondary),
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
      (Icons.self_improvement_rounded,
          context.t('Stay calm and assess the situation')),
      (Icons.call_rounded,
          context.t('Call the appropriate emergency number')),
      (Icons.location_on_rounded, context.t('Share your exact location')),
      (Icons.hearing_rounded,
          context.t('Follow instructions from emergency services')),
      (Icons.bookmark_rounded,
          context.t('Keep emergency contacts saved offline')),
    ];
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppTheme.warningColor.withOpacity(0.10),
            AppTheme.warningColor.withOpacity(0.03),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppTheme.warningColor.withOpacity(0.25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [
                      AppTheme.warningColor.withOpacity(0.22),
                      AppTheme.warningColor.withOpacity(0.08),
                    ],
                  ),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: const Icon(Icons.lightbulb_rounded,
                    color: AppTheme.warningColor, size: 20),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.t('Emergency Tips'),
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    Text(
                      context.t('Before help arrives'),
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppTheme.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (var i = 0; i < tips.length; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 20,
                    height: 20,
                    decoration: BoxDecoration(
                      color: AppTheme.warningColor.withOpacity(0.16),
                      shape: BoxShape.circle,
                    ),
                    child: Center(
                      child: Text(
                        '${i + 1}',
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          color: AppTheme.warningColor,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: Text(
                      tips[i].$2,
                      style: const TextStyle(
                        fontSize: AppTheme.textSm + 1,
                        height: 1.4,
                        color: AppTheme.textPrimary,
                      ),
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
