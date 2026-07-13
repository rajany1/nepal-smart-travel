import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/auth_form_card.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _emailController = TextEditingController();
  bool _emailSent = false;
  String? _resetToken;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _handleSendReset() async {
    if (_emailController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please enter your email')));
      return;
    }
    final provider = context.read<AuthProvider>();
    final resetToken = await provider.sendPasswordReset(_emailController.text.trim());
    if (resetToken != null && mounted) {
      setState(() { _emailSent = true; _resetToken = resetToken; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(title: const Text('Reset Password'), backgroundColor: Colors.transparent, foregroundColor: AppTheme.textPrimary, elevation: 0),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 40),
              Icon(Icons.lock_reset_outlined, size: 72, color: AppTheme.primaryColor),
              const SizedBox(height: 16),
              Text('Forgot Password?', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineMedium),
              const SizedBox(height: 8),
              const Text("Enter your email and we'll send you a reset link", textAlign: TextAlign.center, style: TextStyle(color: AppTheme.textSecondary)),
              const SizedBox(height: 32),
              if (!_emailSent) ...[
                AuthFormCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextFormField(controller: _emailController, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email Address', prefixIcon: Icon(Icons.email_outlined), hintText: 'your@email.com')),
                      const SizedBox(height: 24),
                      Consumer<AuthProvider>(builder: (ctx, auth, _) => ElevatedButton(
                        onPressed: auth.isLoading ? null : _handleSendReset,
                        child: auth.isLoading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Send Reset Link'),
                      )),
                      Consumer<AuthProvider>(builder: (ctx, auth, _) {
                        if (auth.errorMessage == null) return const SizedBox.shrink();
                        return Padding(padding: const EdgeInsets.only(top: 12), child: Text(auth.errorMessage!, style: const TextStyle(color: AppTheme.errorColor), textAlign: TextAlign.center));
                      }),
                    ],
                  ),
                ),
              ] else ...[
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(color: AppTheme.successColor.withOpacity(0.1), border: Border.all(color: AppTheme.successColor), borderRadius: BorderRadius.circular(12)),
                  child: Column(children: [
                    Icon(Icons.check_circle, color: AppTheme.successColor, size: 48),
                    const SizedBox(height: 12),
                    Text('Verification Code Sent', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold, color: AppTheme.successColor)),
                    const SizedBox(height: 8),
                    Text('A verification code has been sent to your email. Please check your inbox and spam folder.', textAlign: TextAlign.center, style: TextStyle(color: AppTheme.successColor)),
                  ]),
                ),
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: () => Navigator.of(context).pushNamed('/reset-password', arguments: {'email': _emailController.text.trim(), 'reset_token': _resetToken}),
                  child: const Text('Enter Reset Code'),
                ),
              ],
              const SizedBox(height: 24),
              Center(child: TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Back to Login'))),
            ],
          ),
        ),
      ),
    );
  }
}
