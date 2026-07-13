import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/themes/app_theme.dart';
import '../../core/models/booking_model.dart';
import '../../providers/booking_provider.dart';
import '../../core/api/api_client.dart';
import 'booking_form_screen.dart';

class MyBookingsScreen extends StatefulWidget {
  const MyBookingsScreen({super.key});

  @override
  State<MyBookingsScreen> createState() => _MyBookingsScreenState();
}

class _MyBookingsScreenState extends State<MyBookingsScreen> {
  final _api = ApiClient.instance;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<BookingProvider>().loadMyBookings();
    });
  }

  void _newBooking() async {
    try {
      final res = await _api.getPartners();
      final partners = (res.data['data'] as List? ?? []).cast<Map<String, dynamic>>();
      if (!mounted) return;
      if (partners.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No partners available yet')),
        );
        return;
      }
      final selected = await showModalBottomSheet<Map<String, dynamic>>(
        context: context,
        isScrollControlled: true,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        builder: (_) => _PartnerPickerSheet(partners: partners),
      );
      if (selected != null && mounted) {
        final result = await Navigator.push<bool>(
          context,
          MaterialPageRoute(
            builder: (_) => BookingFormScreen(
              partnerId: selected['id'] as int,
              partnerName: selected['name'] as String? ?? '',
              partnerPhone: selected['phone'] as String?,
              partnerEmail: selected['email'] as String?,
              partnerType: selected['type'] as String?,
            ),
          ),
        );
        if (result == true && mounted) {
          context.read<BookingProvider>().loadMyBookings();
        }
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to load partners')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Bookings')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _newBooking,
        icon: const Icon(Icons.add),
        label: const Text('New Booking'),
      ),
      body: Consumer<BookingProvider>(
        builder: (context, bp, _) {
          if (bp.isLoading && bp.bookings.isEmpty) {
            return const _BookingShimmer();
          }
          if (bp.error != null && bp.bookings.isEmpty) {
            return _ErrorState(
              message: bp.error!,
              onRetry: () => bp.loadMyBookings(),
            );
          }
          return Column(
            children: [
              _StatusFilterBar(provider: bp),
              Expanded(
                child: bp.bookings.isEmpty
                    ? _EmptyState(onBook: _newBooking)
                    : RefreshIndicator(
                        onRefresh: () => bp.loadMyBookings(),
                        child: ListView.builder(
                          padding: const EdgeInsets.fromLTRB(12, 4, 12, 80),
                          itemCount: bp.filteredBookings.length,
                          itemBuilder: (_, i) => _BookingCard(
                            booking: bp.filteredBookings[i],
                            provider: bp,
                          ),
                        ),
                      ),
              ),
            ],
          );
        },
      ),
    );
  }
}

// ── Status Filter Bar ──

class _StatusFilterBar extends StatelessWidget {
  final BookingProvider provider;
  const _StatusFilterBar({required this.provider});

  @override
  Widget build(BuildContext context) {
    final filters = [
      ('', 'All', provider.countAll),
      ('pending', 'Pending', provider.countPending),
      ('confirmed', 'Confirmed', provider.countConfirmed),
      ('completed', 'Completed', provider.countCompleted),
      ('cancelled', 'Cancelled', provider.countCancelled),
    ];
    return Container(
      color: Colors.white,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        child: Row(
          children: filters.map((f) {
            final selected = provider.statusFilter == f.$1;
            return Padding(
              padding: const EdgeInsets.only(right: 6),
              child: FilterChip(
                label: Text('${f.$2} (${f.$3})'),
                selected: selected,
                onSelected: (_) => provider.setStatusFilter(f.$1),
                selectedColor: AppTheme.primaryColor.withOpacity(0.12),
                checkmarkColor: AppTheme.primaryColor,
                labelStyle: TextStyle(
                  fontSize: 12,
                  fontWeight: selected ? FontWeight.w600 : FontWeight.normal,
                  color: selected ? AppTheme.primaryColor : AppTheme.textSecondary,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                  side: BorderSide(
                    color: selected ? AppTheme.primaryColor : AppTheme.dividerColor,
                  ),
                ),
              ),
            );
          }).toList(),
        ),
      ),
    );
  }
}

// ── Booking Card ──

class _BookingCard extends StatelessWidget {
  final BookingModel booking;
  final BookingProvider provider;
  const _BookingCard({required this.booking, required this.provider});

  void _showDetail(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (_) => _BookingDetailSheet(
        booking: booking,
        provider: provider,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final partner = booking.travelPartner;
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => _showDetail(context),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(children: [
                CircleAvatar(
                  radius: 16,
                  backgroundColor: booking.statusColor.withOpacity(0.12),
                  child: Text(
                    (partner?.name ?? '?')[0].toUpperCase(),
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: booking.statusColor,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    partner?.name ?? 'Unknown Partner',
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: AppTheme.textBase,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: booking.statusColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    booking.statusLabel.toUpperCase(),
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      color: booking.statusColor,
                    ),
                  ),
                ),
              ]),
              const SizedBox(height: 10),
              Row(children: [
                Icon(Icons.person_outline, size: 14, color: Colors.grey[500]),
                const SizedBox(width: 4),
                Text(
                  booking.customerName,
                  style: const TextStyle(
                    fontWeight: FontWeight.w500,
                    fontSize: AppTheme.textBase,
                  ),
                ),
              ]),
              const SizedBox(height: 4),
              Row(children: [
                Icon(Icons.calendar_today, size: 13, color: Colors.grey[500]),
                const SizedBox(width: 4),
                Text(
                  _formatDate(booking.bookedAt ?? booking.createdAt),
                  style: TextStyle(fontSize: AppTheme.textSm, color: Colors.grey[600]),
                ),
                const Spacer(),
                Text(
                  'NPR ${_fmt(booking.finalAmount)}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: AppTheme.textBase,
                    color: AppTheme.primaryColor,
                  ),
                ),
              ]),
              if (booking.discountAmount > 0) ...[
                const SizedBox(height: 2),
                Row(children: [
                  const Spacer(),
                  Text(
                    'Discount: -NPR ${_fmt(booking.discountAmount)}',
                    style: TextStyle(
                      fontSize: AppTheme.textSm,
                      color: Colors.green[700],
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ]),
              ],
              if (booking.shopCode != null) ...[
                const SizedBox(height: 4),
                Row(children: [
                  Icon(Icons.card_giftcard, size: 13, color: Colors.amber[700]),
                  const SizedBox(width: 4),
                  Text(
                    'Coupon Applied',
                    style: TextStyle(
                      color: Colors.amber[700],
                      fontSize: AppTheme.textSm,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ]),
              ],
              if (booking.notes != null && booking.notes!.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(
                  booking.notes!,
                  style: TextStyle(
                    color: Colors.grey[500],
                    fontSize: AppTheme.textSm,
                    fontStyle: FontStyle.italic,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
              if (booking.isPending) ...[
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: () => _confirmCancel(context),
                    icon: const Icon(Icons.cancel_outlined, size: 16),
                    label: const Text('Cancel Booking', style: TextStyle(fontSize: 12)),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppTheme.errorColor,
                      side: const BorderSide(color: AppTheme.errorColor),
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  void _confirmCancel(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel Booking'),
        content: Text('Are you sure you want to cancel the booking for ${booking.customerName}?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Keep Booking'),
          ),
          TextButton(
            onPressed: () {
              Navigator.pop(ctx);
              provider.cancelBooking(booking.id);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Booking cancelled')),
              );
            },
            style: TextButton.styleFrom(foregroundColor: AppTheme.errorColor),
            child: const Text('Yes, Cancel'),
          ),
        ],
      ),
    );
  }
}

// ── Detail Bottom Sheet ──

class _BookingDetailSheet extends StatelessWidget {
  final BookingModel booking;
  final BookingProvider provider;
  const _BookingDetailSheet({required this.booking, required this.provider});

  @override
  Widget build(BuildContext context) {
    final partner = booking.travelPartner;
    return DraggableScrollableSheet(
      initialChildSize: 0.55,
      minChildSize: 0.35,
      maxChildSize: 0.85,
      expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: const EdgeInsets.all(20),
          child: ListView(
            controller: scrollController,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey[300],
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Row(children: [
                CircleAvatar(
                  radius: 22,
                  backgroundColor: booking.statusColor.withOpacity(0.12),
                  child: Text(
                    (partner?.name ?? '?')[0].toUpperCase(),
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: booking.statusColor,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        partner?.name ?? 'Unknown Partner',
                        style: const TextStyle(
                          fontSize: AppTheme.textXl,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      if (partner?.type != null)
                        Text(
                          partner!.type!.toUpperCase(),
                          style: TextStyle(
                            fontSize: AppTheme.textSm,
                            color: Colors.grey[500],
                          ),
                        ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: booking.statusColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    booking.statusLabel.toUpperCase(),
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: booking.statusColor,
                    ),
                  ),
                ),
              ]),
              const SizedBox(height: 20),

              // Status Timeline
              _StatusTimeline(booking: booking),
              const SizedBox(height: 20),

              // Booking Details
              const Text(
                'Booking Details',
                style: TextStyle(
                  fontSize: AppTheme.textLg,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 8),
              _detailField('Customer', booking.customerName),
              if (booking.customerPhone != null && booking.customerPhone!.isNotEmpty)
                _detailField('Phone', booking.customerPhone!),
              if (booking.customerEmail != null && booking.customerEmail!.isNotEmpty)
                _detailField('Email', booking.customerEmail!),
              _detailField('Amount', 'NPR ${_fmt(booking.amount)}'),
              if (booking.discountAmount > 0)
                _detailField('Discount', '-NPR ${_fmt(booking.discountAmount)}',
                    valueColor: Colors.green),
              _detailField('Final Amount', 'NPR ${_fmt(booking.finalAmount)}',
                  valueColor: AppTheme.primaryColor, bold: true),
              if (booking.notes != null && booking.notes!.isNotEmpty)
                _detailField('Notes', booking.notes!),
              if (booking.shopCode != null) ...[
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Colors.amber.withOpacity(0.08),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.amber.withOpacity(0.3)),
                  ),
                  child: Row(children: [
                    Icon(Icons.card_giftcard, size: 16, color: Colors.amber[700]),
                    const SizedBox(width: 6),
                    Text(
                      'Coupon: ${booking.shopCode!.code ?? '—'}',
                      style: TextStyle(
                        color: Colors.amber[700],
                        fontWeight: FontWeight.w500,
                        fontSize: AppTheme.textBase,
                      ),
                    ),
                  ]),
                ),
              ],
              const SizedBox(height: 20),

              // Contact Partner
              if (partner != null && (partner.phone != null || partner.email != null)) ...[
                const Text(
                  'Contact Partner',
                  style: TextStyle(
                    fontSize: AppTheme.textLg,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 8),
                if (partner.phone != null)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.phone, color: AppTheme.primaryColor),
                    title: Text(partner.phone!),
                    trailing: const Icon(Icons.open_in_new, size: 16),
                    onTap: () => launchUrl(Uri.parse('tel:${partner.phone}')),
                  ),
                if (partner.email != null)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.email, color: AppTheme.primaryColor),
                    title: Text(partner.email!),
                    trailing: const Icon(Icons.open_in_new, size: 16),
                    onTap: () => launchUrl(Uri.parse('mailto:${partner.email}')),
                  ),
                const SizedBox(height: 8),
              ],

              // Cancel Button
              if (booking.isPending) ...[
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      Navigator.pop(context);
                      provider.cancelBooking(booking.id);
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Booking cancelled')),
                      );
                    },
                    icon: const Icon(Icons.cancel_outlined),
                    label: const Text('Cancel This Booking'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.errorColor,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
              ],
            ],
          ),
        );
      },
    );
  }

  Widget _detailField(String label, String value, {Color? valueColor, bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 90,
            child: Text(
              label,
              style: TextStyle(color: Colors.grey[600], fontSize: AppTheme.textBase),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                fontSize: AppTheme.textBase,
                fontWeight: bold ? FontWeight.w600 : FontWeight.w500,
                color: valueColor,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Status Timeline ──

class _StatusTimeline extends StatelessWidget {
  final BookingModel booking;
  const _StatusTimeline({required this.booking});

  @override
  Widget build(BuildContext context) {
    final steps = [
      _TimelineStep(
        label: 'Booked',
        time: booking.bookedAt ?? booking.createdAt,
        active: true,
        completed: true,
      ),
      _TimelineStep(
        label: 'Confirmed',
        time: booking.confirmedAt,
        active: booking.isConfirmed || booking.isCompleted,
        completed: booking.isConfirmed || booking.isCompleted,
      ),
      _TimelineStep(
        label: 'Completed',
        time: booking.completedAt,
        active: booking.isCompleted,
        completed: booking.isCompleted,
      ),
    ];

    if (booking.isCancelled) {
      steps.add(_TimelineStep(
        label: 'Cancelled',
        time: booking.cancelledAt,
        active: true,
        completed: true,
        isCancelled: true,
      ));
    }

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.grey[50],
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Status Timeline',
            style: TextStyle(
              fontSize: AppTheme.textLg,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          ...steps.asMap().entries.map((entry) {
            final idx = entry.key;
            final step = entry.value;
            final isLast = idx == steps.length - 1;
            return IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 24,
                    child: Column(children: [
                      Container(
                        width: step.completed ? 20 : 14,
                        height: step.completed ? 20 : 14,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: step.isCancelled
                              ? AppTheme.errorColor
                              : step.completed
                                  ? Colors.green
                                  : Colors.grey[300],
                        ),
                        child: step.completed
                            ? const Icon(Icons.check, size: 12, color: Colors.white)
                            : null,
                      ),
                      if (!isLast)
                        Expanded(
                          child: Container(
                            width: 2,
                            color: step.completed ? Colors.green : Colors.grey[300],
                          ),
                        ),
                    ]),
                  ),
                  const SizedBox(width: 12),
                  Padding(
                    padding: EdgeInsets.only(bottom: isLast ? 0 : 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          step.label,
                          style: TextStyle(
                            fontWeight: FontWeight.w500,
                            fontSize: AppTheme.textBase,
                            color: step.completed
                                ? (step.isCancelled ? AppTheme.errorColor : Colors.green[800])
                                : Colors.grey[500],
                          ),
                        ),
                        if (step.time != null)
                          Text(
                            _formatDate(step.time!),
                            style: TextStyle(
                              fontSize: AppTheme.textSm,
                              color: Colors.grey[500],
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }
}

class _TimelineStep {
  final String label;
  final DateTime? time;
  final bool active;
  final bool completed;
  final bool isCancelled;

  _TimelineStep({
    required this.label,
    this.time,
    this.active = false,
    this.completed = false,
    this.isCancelled = false,
  });
}

// ── Partner Picker Sheet ──

class _PartnerPickerSheet extends StatelessWidget {
  final List<Map<String, dynamic>> partners;
  const _PartnerPickerSheet({required this.partners});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Select a Travel Partner',
            style: TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          Text(
            'Choose who you want to book with',
            style: TextStyle(color: Colors.grey[500], fontSize: AppTheme.textSm),
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: partners.length > 6 ? 320 : null,
            child: ListView.separated(
              shrinkWrap: true,
              itemCount: partners.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (_, i) {
                final p = partners[i];
                final name = p['name'] as String? ?? '';
                final type = p['type'] as String?;
                final phone = p['phone'] as String?;
                final district = p['district'] as String?;
                return ListTile(
                  contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                  leading: CircleAvatar(
                    backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                    child: Text(
                      name.isNotEmpty ? name[0].toUpperCase() : '?',
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        color: AppTheme.primaryColor,
                      ),
                    ),
                  ),
                  title: Text(name, style: const TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text(
                    [if (type != null) type, if (district != null) district]
                        .join(' · '),
                    style: const TextStyle(fontSize: AppTheme.textSm),
                  ),
                  trailing: phone != null
                      ? Icon(Icons.phone, size: 18, color: Colors.grey[400])
                      : null,
                  onTap: () => Navigator.pop(context, p),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

// ── Empty State ──

class _EmptyState extends StatelessWidget {
  final VoidCallback onBook;
  const _EmptyState({required this.onBook});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.book_online, size: 64, color: Colors.grey[300]),
            const SizedBox(height: 16),
            const Text(
              'No bookings yet',
              style: TextStyle(
                fontSize: AppTheme.textXl,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Book a service with a travel partner\nto get started',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[500], fontSize: AppTheme.textBase),
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: onBook,
              icon: const Icon(Icons.add),
              label: const Text('New Booking'),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Error State ──

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 48, color: AppTheme.errorColor),
            const SizedBox(height: 12),
            const Text(
              'Something went wrong',
              style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[500], fontSize: AppTheme.textSm),
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Try Again'),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Shimmer Loading ──

class _BookingShimmer extends StatelessWidget {
  const _BookingShimmer();
  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: 5,
      itemBuilder: (_, __) => Card(
        margin: const EdgeInsets.only(bottom: 8),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(children: [
                _shimmerBox(32, 32, borderRadius: 16),
                const SizedBox(width: 8),
                _shimmerBox(140, 14),
                const Spacer(),
                _shimmerBox(70, 22, borderRadius: 6),
              ]),
              const SizedBox(height: 10),
              _shimmerBox(160, 13),
              const SizedBox(height: 6),
              Row(children: [
                _shimmerBox(120, 11),
                const Spacer(),
                _shimmerBox(80, 14),
              ]),
            ],
          ),
        ),
      ),
    );
  }

  Widget _shimmerBox(double w, double h, {double borderRadius = 4}) {
    return Container(
      width: w,
      height: h,
      decoration: BoxDecoration(
        color: Colors.grey[200],
        borderRadius: BorderRadius.circular(borderRadius),
      ),
    );
  }
}

// ── Helpers ──

String _formatDate(DateTime? dt) {
  if (dt == null) return '—';
  return '${dt.day}/${dt.month}/${dt.year}';
}

String _fmt(double val) {
  return val.toStringAsFixed(2);
}
