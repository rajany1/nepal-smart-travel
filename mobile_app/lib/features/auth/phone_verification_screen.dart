import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/api/api_client.dart';
import '../../config/themes/app_theme.dart';

class PhoneVerificationScreen extends StatefulWidget {
  const PhoneVerificationScreen({super.key});

  @override
  State<PhoneVerificationScreen> createState() => _PhoneVerificationScreenState();
}

class _PhoneVerificationScreenState extends State<PhoneVerificationScreen> {
  final _phoneController = TextEditingController();
  final _otpController = TextEditingController();
  final _otpFocus = FocusNode();
  bool _otpSent = false;
  bool _isLoading = false;
  String? _error;
  int _resendCountdown = 0;

  @override
  void dispose() {
    _phoneController.dispose();
    _otpController.dispose();
    _otpFocus.dispose();
    super.dispose();
  }

  Future<void> _sendOtp() async {
    if (_phoneController.text.trim().isEmpty) {
      setState(() => _error = 'Please enter your phone number');
      return;
    }
    setState(() { _isLoading = true; _error = null; });
    try {
      final api = context.read<ApiClient>();
      await api.sendPhoneOtp(_phoneController.text.trim());
      setState(() { _otpSent = true; _isLoading = false; _resendCountdown = 60; });
      _otpFocus.requestFocus();
      _startResendCountdown();
    } catch (e) {
      setState(() { _isLoading = false; _error = 'Failed to send code. Please try again.'; });
    }
  }

  Future<void> _verifyOtp() async {
    if (_otpController.text.trim().length != 6) {
      setState(() => _error = 'Please enter the 6-digit code');
      return;
    }
    setState(() { _isLoading = true; _error = null; });
    try {
      final api = context.read<ApiClient>();
      final response = await api.verifyPhone(_otpController.text.trim());
      if (!mounted) return;
      final msg = response.data['message'] ?? 'Phone verified!';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), backgroundColor: AppTheme.successColor),
      );
      Navigator.of(context).pop(true);
    } catch (e) {
      setState(() { _isLoading = false; _error = 'Invalid code. Please try again.'; });
    }
  }

  void _startResendCountdown() {
    Future.doWhile(() async {
      await Future.delayed(const Duration(seconds: 1));
      if (_resendCountdown > 0 && mounted) {
        setState(() => _resendCountdown--);
        return true;
      }
      return false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFF0EA5E9).withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.phone_android, color: Color(0xFF0EA5E9), size: 22),
            ),
            const SizedBox(width: 12),
            Text('Phone Verification', style: GoogleFonts.poppins(color: AppTheme.textPrimary)),
          ],
        ),
        backgroundColor: AppTheme.backgroundColor,
        iconTheme: const IconThemeData(color: AppTheme.textPrimary),
        elevation: 0,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 16),
              Icon(Icons.phone_android, size: 64, color: AppTheme.primaryColor),
              const SizedBox(height: 16),
              Text('Verify your phone number',
                style: GoogleFonts.poppins(fontSize: 20, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text('Get +50 XP and a trust badge when verified!',
                style: GoogleFonts.poppins(color: AppTheme.accentColor, fontSize: 13),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 32),
              if (!_otpSent) ...[
                TextFormField(
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  style: const TextStyle(color: AppTheme.textPrimary),
                  decoration: InputDecoration(
                    labelText: 'Phone Number',
                    hintText: '+977-98xxxxxxxx',
                    prefixIcon: const Icon(Icons.phone_outlined),
                    labelStyle: const TextStyle(color: AppTheme.textSecondary),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: AppTheme.dividerColor),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: AppTheme.primaryColor),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: _isLoading ? null : _sendOtp,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isLoading
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : Text('Send Verification Code', style: GoogleFonts.poppins(fontWeight: FontWeight.w600)),
                ),
              ] else ...[
                Text('A 6-digit code was sent to your device via app notification.',
                  style: TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _otpController,
                  focusNode: _otpFocus,
                  keyboardType: TextInputType.number,
                  maxLength: 6,
                  style: GoogleFonts.poppins(fontSize: 24, letterSpacing: 8, color: AppTheme.textPrimary),
                  textAlign: TextAlign.center,
                  decoration: InputDecoration(
                    hintText: '------',
                    counterText: '',
                    prefixIcon: const Icon(Icons.lock_outline),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: AppTheme.dividerColor),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: AppTheme.primaryColor),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: _isLoading ? null : _verifyOtp,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isLoading
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : Text('Verify', style: GoogleFonts.poppins(fontWeight: FontWeight.w600)),
                ),
                const SizedBox(height: 12),
                TextButton(
                  onPressed: _resendCountdown > 0 ? null : _sendOtp,
                  child: Text(
                    _resendCountdown > 0 ? 'Resend in $_resendCountdown s' : 'Resend Code',
                    style: TextStyle(color: _resendCountdown > 0 ? AppTheme.textSecondary : AppTheme.primaryColor),
                  ),
                ),
              ],
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: const TextStyle(color: AppTheme.errorColor), textAlign: TextAlign.center),
              ],
            ],
          ),
        ),
      ),
    );
  }
}