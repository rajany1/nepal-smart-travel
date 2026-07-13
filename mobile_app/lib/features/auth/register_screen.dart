import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/auth_form_card.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  bool _agreedToTerms = false;
  String _passwordStrength = '';

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  String _evaluatePasswordStrength(String password) {
    if (password.isEmpty) return '';
    if (password.length < 6) return 'weak';
    if (password.length < 8) return 'medium';
    if (RegExp(r'[A-Z]').hasMatch(password) &&
        RegExp(r'[0-9]').hasMatch(password) &&
        RegExp(r'[!@#$%^&*(),.?":{}|<>]').hasMatch(password)) return 'strong';
    return 'medium';
  }

  bool _isValidNepaliPhone(String phone) {
    final nepaliPhoneRegex = RegExp(r'^(?:\+977|977)?[9][7-8]\d{8}$');
    return nepaliPhoneRegex.hasMatch(phone.replaceAll('-', '').replaceAll(' ', ''));
  }

  Future<void> _handleRegister() async {
    if (!_formKey.currentState!.validate()) return;
    if (!_agreedToTerms) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please agree to Terms & Conditions')));
      return;
    }
    final provider = context.read<AuthProvider>();
    final success = await provider.register(
      name: _nameController.text.trim(),
      email: _emailController.text.trim(),
      phone: _phoneController.text.trim(),
      password: _passwordController.text,
      passwordConfirmation: _confirmPasswordController.text,
    );
    if (success && mounted) {
      Navigator.of(context).pushReplacementNamed('/profile-completion');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(title: const Text('Create Account'), backgroundColor: Colors.transparent, foregroundColor: AppTheme.textPrimary, elevation: 0),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 16),
                Text('Join the Community', style: Theme.of(context).textTheme.headlineMedium),
                const SizedBox(height: 4),
                const Text('Create an account to start contributing and exploring Nepal', style: TextStyle(color: AppTheme.textSecondary)),
                const SizedBox(height: 32),
                AuthFormCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextFormField(controller: _nameController, decoration: const InputDecoration(labelText: 'Full Name', prefixIcon: Icon(Icons.person_outline)), validator: (v) => v == null || v.isEmpty ? 'Please enter your name' : null),
                      const SizedBox(height: 16),
                      TextFormField(controller: _emailController, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email', prefixIcon: Icon(Icons.email_outlined)), validator: (v) => v == null || v.isEmpty ? 'Please enter your email' : !v.contains('@') ? 'Enter a valid email' : null),
                      const SizedBox(height: 16),
                      TextFormField(controller: _phoneController, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Phone Number', prefixIcon: Icon(Icons.phone_outlined), hintText: '+977-98xxxxxxxx'), validator: (v) => v == null || v.isEmpty ? 'Please enter your phone' : !_isValidNepaliPhone(v) ? 'Enter a valid Nepali phone number' : null),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _passwordController,
                        obscureText: _obscurePassword,
                        onChanged: (v) => setState(() => _passwordStrength = _evaluatePasswordStrength(v)),
                        decoration: InputDecoration(labelText: 'Password', prefixIcon: const Icon(Icons.lock_outlined), suffixIcon: IconButton(icon: Icon(_obscurePassword ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _obscurePassword = !_obscurePassword))),
                        validator: (v) => v == null || v.isEmpty ? 'Enter a password' : v.length < 6 ? 'Password must be at least 6 characters' : null,
                      ),
                      if (_passwordStrength.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: Row(children: [
                            Text('Strength: ', style: Theme.of(context).textTheme.bodySmall),
                            const SizedBox(width: 8),
                            Expanded(child: LinearProgressIndicator(value: _passwordStrength == 'weak' ? 0.33 : _passwordStrength == 'medium' ? 0.66 : 1.0, color: _passwordStrength == 'weak' ? Colors.red : _passwordStrength == 'medium' ? Colors.orange : Colors.green)),
                            const SizedBox(width: 8),
                            Text(_passwordStrength.toUpperCase(), style: Theme.of(context).textTheme.bodySmall?.copyWith(color: _passwordStrength == 'weak' ? Colors.red : _passwordStrength == 'medium' ? Colors.orange : Colors.green)),
                          ]),
                        ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _confirmPasswordController,
                        obscureText: _obscureConfirm,
                        decoration: InputDecoration(labelText: 'Confirm Password', prefixIcon: const Icon(Icons.lock_outlined), suffixIcon: IconButton(icon: Icon(_obscureConfirm ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _obscureConfirm = !_obscureConfirm))),
                        validator: (v) => v != _passwordController.text ? 'Passwords do not match' : null,
                      ),
                      const SizedBox(height: 12),
                      Row(children: [
                        Checkbox(value: _agreedToTerms, onChanged: (v) => setState(() => _agreedToTerms = v ?? false), activeColor: AppTheme.primaryColor),
                        GestureDetector(onTap: () => setState(() => _agreedToTerms = !_agreedToTerms), child: Text.rich(TextSpan(children: [const TextSpan(text: 'I agree to the '), TextSpan(text: 'Terms & Conditions', style: const TextStyle(color: AppTheme.primaryColor, fontWeight: FontWeight.bold))]))),
                      ]),
                      const SizedBox(height: 16),
                      Consumer<AuthProvider>(builder: (ctx, auth, _) => ElevatedButton(
                        onPressed: auth.isLoading ? null : _handleRegister,
                        child: auth.isLoading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Create Account'),
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
                  const Text('Already have an account?'),
                  TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Login')),
                ]),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
