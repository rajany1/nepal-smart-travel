import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/auth_form_card.dart';

class EmailVerificationScreen extends StatefulWidget {
  final String email;
  const EmailVerificationScreen({required this.email, super.key});

  @override
  State<EmailVerificationScreen> createState() => _EmailVerificationScreenState();
}

class _EmailVerificationScreenState extends State<EmailVerificationScreen> {
  late List<TextEditingController> _otpControllers;
  final int _otpLength = 6;
  late FocusNode _firstFocusNode;

  @override
  void initState() {
    super.initState();
    _otpControllers = List.generate(_otpLength, (i) => TextEditingController());
    _firstFocusNode = FocusNode();
    WidgetsBinding.instance.addPostFrameCallback((_) => FocusScope.of(context).requestFocus(_firstFocusNode));
  }

  @override
  void dispose() {
    for (final c in _otpControllers) c.dispose();
    _firstFocusNode.dispose();
    super.dispose();
  }

  String _getOTP() => _otpControllers.map((c) => c.text).join();

  void _handleOtpInput(int index, String value) {
    if (value.isNotEmpty) {
      if (index < _otpLength - 1) { FocusScope.of(context).nextFocus(); } else { FocusScope.of(context).unfocus(); }
    } else if (index > 0) {
      FocusScope.of(context).previousFocus();
    }
  }

  Future<void> _handleVerification() async {
    final otp = _getOTP();
    if (otp.length != _otpLength) { ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please enter complete OTP'))); return; }
    final provider = context.read<AuthProvider>();
    final success = await provider.verifyEmail(otp);
    if (success && mounted) Navigator.of(context).pushReplacementNamed('/profile-setup');
  }

  Future<void> _handleResendOTP() async {
    final provider = context.read<AuthProvider>();
    await provider.resendVerificationEmail(widget.email);
    if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('OTP sent to your email')));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(title: const Text('Verify Email'), backgroundColor: Colors.transparent, foregroundColor: AppTheme.textPrimary, elevation: 0),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 24),
              Icon(Icons.mark_email_unread_outlined, size: 64, color: AppTheme.primaryColor),
              const SizedBox(height: 16),
              Text('Verify Your Email', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineMedium),
              const SizedBox(height: 8),
              Text('We sent a 6-digit code to\n${widget.email}', textAlign: TextAlign.center, style: const TextStyle(color: AppTheme.textSecondary)),
              const SizedBox(height: 32),
              AuthFormCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: List.generate(_otpLength, (i) => SizedBox(
                      width: 48, height: 56,
                      child: TextField(
                        controller: _otpControllers[i],
                        focusNode: i == 0 ? _firstFocusNode : null,
                        textAlign: TextAlign.center,
                        keyboardType: TextInputType.number,
                        maxLength: 1,
                        decoration: InputDecoration(counterText: '', border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(width: 2))),
                        onChanged: (v) => _handleOtpInput(i, v),
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                    ))),
                    const SizedBox(height: 32),
                    Consumer<AuthProvider>(builder: (ctx, auth, _) => ElevatedButton(
                      onPressed: auth.isLoading ? null : _handleVerification,
                      child: auth.isLoading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Verify Email'),
                    )),
                    Consumer<AuthProvider>(builder: (ctx, auth, _) {
                      if (auth.errorMessage == null) return const SizedBox.shrink();
                      return Padding(padding: const EdgeInsets.only(top: 12), child: Text(auth.errorMessage!, style: const TextStyle(color: AppTheme.errorColor), textAlign: TextAlign.center));
                    }),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                const Text("Didn't receive code?"),
                TextButton(onPressed: _handleResendOTP, child: const Text('Resend OTP')),
              ]),
            ],
          ),
        ),
      ),
    );
  }
}
