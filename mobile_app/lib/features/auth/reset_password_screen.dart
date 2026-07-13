import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/auth_form_card.dart';

class ResetPasswordScreen extends StatefulWidget {
  final String email;
  final String resetToken;
  const ResetPasswordScreen({super.key, required this.email, required this.resetToken});

  @override
  State<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  bool _obscurePassword = true;
  bool _resetSuccess = false;

  @override
  void dispose() {
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _handleReset() async {
    final password = _passwordController.text;
    final confirm = _confirmController.text;
    if (password.isEmpty) { ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please enter a new password'))); return; }
    if (password.length < 8) { ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Password must be at least 8 characters'))); return; }
    if (password != confirm) { ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Passwords do not match'))); return; }
    final provider = context.read<AuthProvider>();
    final success = await provider.resetPassword(widget.email, widget.resetToken, password);
    if (success && mounted) setState(() => _resetSuccess = true);
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
              Text('Set New Password', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineMedium),
              const SizedBox(height: 8),
              Text('Enter your new password for ${widget.email}', textAlign: TextAlign.center, style: const TextStyle(color: AppTheme.textSecondary)),
              const SizedBox(height: 32),
              if (!_resetSuccess) ...[
                AuthFormCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextFormField(controller: _passwordController, obscureText: _obscurePassword, decoration: InputDecoration(labelText: 'New Password', prefixIcon: const Icon(Icons.lock_outlined), suffixIcon: IconButton(icon: Icon(_obscurePassword ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _obscurePassword = !_obscurePassword)))),
                      const SizedBox(height: 16),
                      TextFormField(controller: _confirmController, obscureText: true, decoration: const InputDecoration(labelText: 'Confirm Password', prefixIcon: Icon(Icons.lock_outlined))),
                      const SizedBox(height: 24),
                      Consumer<AuthProvider>(builder: (ctx, auth, _) => ElevatedButton(
                        onPressed: auth.isLoading ? null : _handleReset,
                        child: auth.isLoading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Reset Password'),
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
                    Text('Password Reset Successful', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold, color: AppTheme.successColor)),
                    const SizedBox(height: 8),
                    Text('Your password has been changed. You can now log in with your new password.', textAlign: TextAlign.center, style: TextStyle(color: AppTheme.successColor)),
                  ]),
                ),
                const SizedBox(height: 24),
                ElevatedButton(onPressed: () => Navigator.of(context).pushNamedAndRemoveUntil('/login', (route) => false), child: const Text('Back to Login')),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
