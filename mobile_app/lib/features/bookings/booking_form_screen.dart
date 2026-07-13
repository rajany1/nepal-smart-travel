import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/themes/app_theme.dart';
import '../../core/api/api_client.dart';
import '../../providers/auth_provider.dart';

class BookingFormScreen extends StatefulWidget {
  final int partnerId;
  final String partnerName;
  final String? partnerPhone;
  final String? partnerEmail;
  final String? partnerType;

  const BookingFormScreen({
    super.key,
    required this.partnerId,
    required this.partnerName,
    this.partnerPhone,
    this.partnerEmail,
    this.partnerType,
  });

  @override
  State<BookingFormScreen> createState() => _BookingFormScreenState();
}

class _BookingFormScreenState extends State<BookingFormScreen> {
  final _api = ApiClient.instance;
  final _formKey = GlobalKey<FormState>();
  final _nameCtl = TextEditingController();
  final _phoneCtl = TextEditingController();
  final _emailCtl = TextEditingController();
  final _amountCtl = TextEditingController();
  final _notesCtl = TextEditingController();
  DateTime? _selectedDate;
  bool _submitting = false;

  List<Map<String, dynamic>> _availableCodes = [];
  bool _loadingCodes = true;
  int? _selectedCodeId;
  Map<String, dynamic>? _selectedCodeData;

  double get _discountAmount {
    if (_selectedCodeData == null) return 0;
    final item = _selectedCodeData!['shop_item'] as Map<String, dynamic>?;
    if (item == null) return 0;
    final discountType = item['discount_type'] as String?;
    final discountValue = double.tryParse(item['discount_value']?.toString() ?? '') ?? 0;
    final amount = double.tryParse(_amountCtl.text) ?? 0;
    if (discountType == 'percentage') {
      return amount * discountValue / 100;
    } else if (discountType == 'fixed') {
      return discountValue;
    }
    return 0;
  }

  double get _finalAmount {
    final amount = double.tryParse(_amountCtl.text) ?? 0;
    final discount = _discountAmount;
    return (amount - discount).clamp(0, amount);
  }

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    if (user != null) {
      _nameCtl.text = user.name;
      _phoneCtl.text = user.phone ?? '';
      _emailCtl.text = user.email;
    }
    _loadCodes();
  }

  Future<void> _loadCodes() async {
    try {
      final response = await _api.getAvailableCodes();
      if (!mounted) return;
      setState(() {
        _availableCodes = (response.data['data'] as List? ?? []).cast<Map<String, dynamic>>();
        _loadingCodes = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loadingCodes = false);
    }
  }

  @override
  void dispose() {
    _nameCtl.dispose();
    _phoneCtl.dispose();
    _emailCtl.dispose();
    _amountCtl.dispose();
    _notesCtl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);

    try {
      final data = {
        'travel_partner_id': widget.partnerId,
        'customer_name': _nameCtl.text,
        'customer_phone': _phoneCtl.text,
        'customer_email': _emailCtl.text,
        'amount': double.tryParse(_amountCtl.text) ?? 0,
        'notes': _notesCtl.text,
        'booked_at': _selectedDate?.toIso8601String(),
      };
      if (_selectedCodeId != null) {
        data['shop_code_id'] = _selectedCodeId;
      }

      await _api.createBooking(data);
      if (!mounted) return;

      _showSuccessDialog();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Booking failed: ${_parseError(e)}'),
          backgroundColor: AppTheme.errorColor,
        ),
      );
    }
    if (mounted) setState(() => _submitting = false);
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.check_circle, size: 64, color: Colors.green),
            const SizedBox(height: 16),
            const Text(
              'Booking Submitted!',
              style: TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              'Your booking with ${widget.partnerName} has been submitted successfully. The partner will confirm your booking shortly.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[600], fontSize: AppTheme.textBase),
            ),
            if (_finalAmount > 0) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.green.withOpacity(0.05),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Text('Total: ', style: TextStyle(fontSize: AppTheme.textLg)),
                    Text(
                      'NPR ${_finalAmount.toStringAsFixed(2)}',
                      style: const TextStyle(
                        fontSize: AppTheme.textLg,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.primaryColor,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
        actions: [
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () {
                Navigator.pop(ctx);
                Navigator.pop(context, true);
              },
              child: const Text('Done'),
            ),
          ),
        ],
      ),
    );
  }

  String _parseError(dynamic e) {
    final s = e.toString();
    if (s.contains('422')) return 'Please check your input.';
    if (s.contains('Connection') || s.contains('timeout')) return 'Connection issue. Try again.';
    return 'An error occurred. Please try again.';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Book a Service')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildPartnerCard(),
              const SizedBox(height: 20),
              _buildSectionTitle('Your Information'),
              const SizedBox(height: 12),
              TextFormField(
                controller: _nameCtl,
                decoration: const InputDecoration(
                  labelText: 'Full Name *',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.person),
                ),
                validator: (v) => v == null || v.trim().isEmpty ? 'Please enter your name' : null,
                textCapitalization: TextCapitalization.words,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _phoneCtl,
                decoration: const InputDecoration(
                  labelText: 'Phone Number',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.phone),
                ),
                keyboardType: TextInputType.phone,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _emailCtl,
                decoration: const InputDecoration(
                  labelText: 'Email',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.email),
                ),
                keyboardType: TextInputType.emailAddress,
                validator: (v) {
                  if (v != null && v.isNotEmpty && !v.contains('@')) return 'Invalid email';
                  return null;
                },
              ),
              const SizedBox(height: 20),
              _buildSectionTitle('Booking Details'),
              const SizedBox(height: 12),
              TextFormField(
                controller: _amountCtl,
                onChanged: (_) => setState(() {}),
                decoration: const InputDecoration(
                  labelText: 'Estimated Amount (NPR) *',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.money),
                  prefixText: 'Rs. ',
                ),
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                validator: (v) {
                  if (v == null || v.isEmpty) return 'Please enter an amount';
                  final val = double.tryParse(v);
                  if (val == null || val <= 0) return 'Enter a valid amount';
                  return null;
                },
              ),
              const SizedBox(height: 12),
              InkWell(
                onTap: () async {
                  final date = await showDatePicker(
                    context: context,
                    firstDate: DateTime.now(),
                    lastDate: DateTime.now().add(const Duration(days: 365)),
                    helpText: 'Select Preferred Date',
                  );
                  if (date != null) setState(() => _selectedDate = date);
                },
                borderRadius: BorderRadius.circular(8),
                child: InputDecorator(
                  decoration: const InputDecoration(
                    labelText: 'Preferred Date',
                    border: OutlineInputBorder(),
                    prefixIcon: Icon(Icons.calendar_today),
                  ),
                  child: Text(
                    _selectedDate != null
                        ? '${_selectedDate!.day}/${_selectedDate!.month}/${_selectedDate!.year}'
                        : 'Tap to select a date',
                    style: TextStyle(
                      color: _selectedDate != null ? Colors.black87 : Colors.grey[500],
                      fontSize: AppTheme.textBase,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _notesCtl,
                decoration: const InputDecoration(
                  labelText: 'Notes / Special Requests',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.notes),
                  alignLabelWithHint: true,
                ),
                maxLines: 3,
                textCapitalization: TextCapitalization.sentences,
              ),
              const SizedBox(height: 20),
              _buildRewardsSection(),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  onPressed: _submitting ? null : _submit,
                  child: _submitting
                      ? const SizedBox(
                          width: 24,
                          height: 24,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Submit Booking', style: TextStyle(fontSize: AppTheme.textLg)),
                ),
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPartnerCard() {
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(children: [
          CircleAvatar(
            backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
            child: Text(
              widget.partnerName[0].toUpperCase(),
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                color: AppTheme.primaryColor,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.partnerName,
                  style: const TextStyle(
                    fontSize: AppTheme.textLg,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (widget.partnerType != null)
                  Text(
                    widget.partnerType!.toUpperCase(),
                    style: TextStyle(
                      fontSize: AppTheme.textSm,
                      color: Colors.grey[500],
                    ),
                  ),
              ],
            ),
          ),
          if (widget.partnerPhone != null)
            IconButton(
              icon: Icon(Icons.phone, color: Colors.grey[600]),
              onPressed: () => launchUrl(Uri.parse('tel:${widget.partnerPhone}')),
              tooltip: 'Call ${widget.partnerName}',
            ),
        ]),
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: AppTheme.textLg,
        fontWeight: FontWeight.w600,
      ),
    );
  }

  Widget _buildRewardsSection() {
    if (_loadingCodes) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(8),
          child: SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        ),
      );
    }

    if (_availableCodes.isEmpty) return const SizedBox.shrink();

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.amber.withOpacity(0.04),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.amber.withOpacity(0.25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Icon(Icons.card_giftcard, size: 18, color: Colors.amber[700]),
            const SizedBox(width: 6),
            Text(
              'Available Rewards & Coupons',
              style: TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: AppTheme.textBase,
                color: Colors.amber[900],
              ),
            ),
          ]),
          const SizedBox(height: 8),
          ..._availableCodes.map((code) {
            final item = code['shop_item'] as Map<String, dynamic>?;
            final itemName = item?['name'] ?? 'Reward';
            final discountType = item?['discount_type'] as String?;
            final discountValue = double.tryParse(item?['discount_value']?.toString() ?? '') ?? 0;
            final codeStr = code['code'] as String? ?? '';
            final isSelected = _selectedCodeId == code['id'];
            return Container(
              margin: const EdgeInsets.only(bottom: 6),
              decoration: BoxDecoration(
                color: isSelected ? Colors.amber.withOpacity(0.08) : Colors.white,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: isSelected ? Colors.amber : Colors.grey[200]!,
                ),
              ),
              child: ListTile(
                dense: true,
                contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 0),
                title: Text(itemName, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                subtitle: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      codeStr,
                      style: TextStyle(
                        fontSize: 11,
                        letterSpacing: 1,
                        color: Colors.grey[500],
                      ),
                    ),
                    if (discountType != null && discountValue > 0)
                      Text(
                        discountType == 'percentage'
                            ? '$discountValue% OFF'
                            : 'Rs. ${discountValue.toStringAsFixed(0)} OFF',
                        style: TextStyle(
                          fontSize: 10,
                          color: Colors.green[700],
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                  ],
                ),
                trailing: isSelected
                    ? TextButton(
                        onPressed: () => setState(() {
                          _selectedCodeId = null;
                          _selectedCodeData = null;
                        }),
                        style: TextButton.styleFrom(foregroundColor: Colors.red),
                        child: const Text('Remove', style: TextStyle(fontSize: 11)),
                      )
                    : TextButton(
                        onPressed: () => setState(() {
                          _selectedCodeId = code['id'] as int?;
                          _selectedCodeData = code;
                        }),
                        child: const Text('Apply', style: TextStyle(fontSize: 11)),
                      ),
              ),
            );
          }),
          if (_selectedCodeId != null && _discountAmount > 0) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.green.withOpacity(0.05),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.green.withOpacity(0.3)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Original Amount: Rs. ${double.tryParse(_amountCtl.text)?.toStringAsFixed(2) ?? "0.00"}',
                          style: TextStyle(fontSize: 11, color: Colors.grey[600]),
                        ),
                        Text(
                          'Discount: - Rs. ${_discountAmount.toStringAsFixed(2)}',
                          style: TextStyle(
                            fontSize: 11,
                            color: Colors.green[700],
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        Text(
                          'Final Amount: Rs. ${_finalAmount.toStringAsFixed(2)}',
                          style: TextStyle(
                            fontSize: 13,
                            color: Colors.green[800],
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Icon(Icons.check_circle, color: Colors.green[600], size: 24),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
