import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/partner_payment_provider.dart';
import '../../config/themes/app_theme.dart';
import 'payment_qr_screen.dart';

class PayPartnerScreen extends StatefulWidget {
  const PayPartnerScreen({super.key});

  @override
  State<PayPartnerScreen> createState() => _PayPartnerScreenState();
}

class _PayPartnerScreenState extends State<PayPartnerScreen> {
  final _amountController = TextEditingController();
  final _descController = TextEditingController();
  String _selectedMethod = 'esewa';
  Map<String, dynamic>? _selectedPartner;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<PartnerPaymentProvider>().loadPartners();
    });
  }

  @override
  void dispose() {
    _amountController.dispose();
    _descController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F6F3),
      appBar: AppBar(
        title: const Text('Pay Partner', style: TextStyle(color: Colors.white)),
        backgroundColor: AppTheme.primaryColor,
        iconTheme: const IconThemeData(color: Colors.white),
        elevation: 0,
      ),
      body: Consumer<PartnerPaymentProvider>(
        builder: (context, provider, _) {
          return SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Select Partner',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Choose the business you want to pay',
                  style: TextStyle(color: Colors.grey, fontSize: 13),
                ),
                const SizedBox(height: 12),

                if (provider.isLoadingPartners)
                  const Center(child: CircularProgressIndicator())
                else if (provider.partners.isEmpty)
                  const Center(
                    child: Padding(
                      padding: EdgeInsets.all(24),
                      child: Text('No verified partners available yet', style: TextStyle(color: Colors.grey)),
                    ),
                  )
                else
                  ...provider.partners.map((partner) => _buildPartnerCard(partner)),

                const SizedBox(height: 24),
                const Text(
                  'Payment Details',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 12),

                TextField(
                  controller: _amountController,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Amount (Rs.)',
                    prefixText: 'Rs. ',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    filled: true,
                    fillColor: Colors.white,
                  ),
                ),
                const SizedBox(height: 12),

                TextField(
                  controller: _descController,
                  decoration: InputDecoration(
                    labelText: 'Description (optional)',
                    hintText: 'e.g. Breakfast, Tour guide fee',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    filled: true,
                    fillColor: Colors.white,
                  ),
                ),
                const SizedBox(height: 16),

                const Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w600)),
                const SizedBox(height: 8),
                Row(
                  children: [
                    _buildMethodChip('esewa', 'eSewa', Icons.account_balance_wallet),
                    const SizedBox(width: 8),
                    _buildMethodChip('khalti', 'Khalti', Icons.phone_android),
                  ],
                ),

                if (provider.error != null) ...[
                  const SizedBox(height: 12),
                  Text(provider.error!, style: const TextStyle(color: Colors.red, fontSize: 13)),
                ],

                const SizedBox(height: 24),
                SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: ElevatedButton(
                    onPressed: (_selectedPartner != null && !provider.isLoading) ? _pay : null,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primaryColor,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: provider.isLoading
                        ? const SizedBox(
                            width: 20, height: 20,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                          )
                        : const Text('Pay Now', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildPartnerCard(Map<String, dynamic> partner) {
    final isSelected = _selectedPartner?['id'] == partner['id'];
    return GestureDetector(
      onTap: () => setState(() => _selectedPartner = partner),
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? AppTheme.primaryColor : Colors.grey.shade200,
            width: isSelected ? 2 : 1,
          ),
          boxShadow: isSelected ? [
            BoxShadow(color: AppTheme.primaryColor.withOpacity(0.1), blurRadius: 8, offset: const Offset(0, 2)),
          ] : [],
        ),
        child: Row(
          children: [
            Container(
              width: 48, height: 48,
              decoration: BoxDecoration(
                color: AppTheme.primaryColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(_getPartnerIcon(partner['type']), color: AppTheme.primaryColor),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(partner['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
                  Text(
                    '${_getPartnerTypeLabel(partner['type'])} \u2022 ${partner['district'] ?? ''}',
                    style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                  ),
                ],
              ),
            ),
            if (isSelected)
              Icon(Icons.check_circle, color: AppTheme.primaryColor)
            else
              Icon(Icons.radio_button_unchecked, color: Colors.grey.shade400),
          ],
        ),
      ),
    );
  }

  Widget _buildMethodChip(String value, String label, IconData icon) {
    final isSelected = _selectedMethod == value;
    return Expanded(
      child: GestureDetector(
        onTap: () => setState(() => _selectedMethod = value),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: isSelected ? AppTheme.primaryColor.withOpacity(0.1) : Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: isSelected ? AppTheme.primaryColor : Colors.grey.shade200,
              width: isSelected ? 2 : 1,
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 18, color: isSelected ? AppTheme.primaryColor : Colors.grey),
              const SizedBox(width: 6),
              Text(label, style: TextStyle(
                fontWeight: FontWeight.w600,
                color: isSelected ? AppTheme.primaryColor : Colors.grey,
              )),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pay() async {
    final amount = double.tryParse(_amountController.text);
    if (amount == null || amount < 10) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Minimum amount is Rs. 10')),
      );
      return;
    }

    final provider = context.read<PartnerPaymentProvider>();
    final result = await provider.initiatePayment(
      partnerId: _selectedPartner!['id'],
      amount: amount,
      paymentMethod: _selectedMethod,
      description: _descController.text.isNotEmpty ? _descController.text : null,
    );

    if (result != null && mounted) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => PaymentQRScreen(paymentData: result),
        ),
      );
    }
  }

  IconData _getPartnerIcon(String? type) {
    switch (type) {
      case 'hotel': return Icons.hotel;
      case 'restaurant': return Icons.restaurant;
      case 'guide': return Icons.tour;
      case 'transport': return Icons.directions_car;
      case 'shop': return Icons.store;
      case 'adventure': return Icons.paragliding;
      default: return Icons.business;
    }
  }

  String _getPartnerTypeLabel(String? type) {
    switch (type) {
      case 'hotel': return 'Hotel/Lodge';
      case 'restaurant': return 'Restaurant';
      case 'guide': return 'Tour Guide';
      case 'transport': return 'Transport';
      case 'shop': return 'Shop';
      case 'adventure': return 'Adventure';
      default: return 'Business';
    }
  }
}
