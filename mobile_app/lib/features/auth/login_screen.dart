import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/auth_form_card.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _rememberMe = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleGoogleLogin() async {
    final provider = context.read<AuthProvider>();
    final success = await provider.loginWithGoogle();
    if (success && mounted) {
      final user = context.read<AuthProvider>().user;
      if (user != null && !user.profileCompleted) {
        Navigator.of(context).pushReplacementNamed('/profile-completion');
      } else {
        Navigator.of(context).pushReplacementNamed('/home');
      }
    }
  }

  Future<void> _handleLogin() async {
    if (_formKey.currentState!.validate()) {
      final provider = context.read<AuthProvider>();
      final success = await provider.login(
        _emailController.text.trim(),
        _passwordController.text,
        rememberMe: _rememberMe,
      );
      if (success && mounted) {
        final user = context.read<AuthProvider>().user;
        if (user != null && !user.profileCompleted) {
          Navigator.of(context).pushReplacementNamed('/profile-completion');
        } else {
          Navigator.of(context).pushReplacementNamed('/home');
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 40),
                Icon(Icons.explore, size: 72, color: AppTheme.primaryColor),
                const SizedBox(height: 12),
                Text('Nepal Smart Travel', textAlign: TextAlign.center, style: Theme.of(context).textTheme.displaySmall?.copyWith(color: AppTheme.primaryColor, fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                Text('Your Trusted Travel Intelligence Platform', textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppTheme.textSecondary)),
                const SizedBox(height: 32),
                AuthFormCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextFormField(
                        controller: _emailController,
                        keyboardType: TextInputType.emailAddress,
                        decoration: const InputDecoration(labelText: 'Email', prefixIcon: Icon(Icons.email_outlined)),
                        validator: (v) => v == null || v.isEmpty ? 'Please enter your email' : !v.contains('@') ? 'Enter a valid email' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _passwordController,
                        obscureText: _obscurePassword,
                        decoration: InputDecoration(
                          labelText: 'Password',
                          prefixIcon: const Icon(Icons.lock_outlined),
                          suffixIcon: IconButton(
                            icon: Icon(_obscurePassword ? Icons.visibility_off : Icons.visibility),
                            onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                          ),
                        ),
                        validator: (v) => v == null || v.isEmpty ? 'Please enter your password' : null,
                      ),
                      const SizedBox(height: 12),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              Checkbox(value: _rememberMe, onChanged: (v) => setState(() => _rememberMe = v ?? false), activeColor: AppTheme.primaryColor),
                              const Text('Remember me'),
                            ],
                          ),
                          TextButton(onPressed: () => Navigator.of(context).pushNamed('/forgot-password'), child: const Text('Forgot Password?')),
                        ],
                      ),
                      const SizedBox(height: 16),
                      Consumer<AuthProvider>(builder: (ctx, auth, _) => ElevatedButton(
                        onPressed: auth.isLoading ? null : _handleLogin,
                        child: auth.isLoading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Login'),
                      )),
                      const Row(children: [Expanded(child: Divider()), Padding(padding: EdgeInsets.symmetric(horizontal: 16), child: Text('OR', style: TextStyle(color: AppTheme.textSecondary, fontSize: 13))), Expanded(child: Divider())]),
                      const SizedBox(height: 8),
                      Consumer<AuthProvider>(builder: (ctx, auth, _) => OutlinedButton.icon(
                        onPressed: auth.isLoading ? null : _handleGoogleLogin,
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          side: BorderSide(color: AppTheme.dividerColor.withOpacity(0.5)),
                        ),
                        icon: Image.asset('assets/images/google_logo.png', height: 20, width: 20, errorBuilder: (_, __, ___) => const Icon(Icons.android)),
                        label: const Text('Sign in with Google', style: TextStyle(color: AppTheme.textPrimary)),
                      )),
                      Consumer<AuthProvider>(builder: (ctx, auth, _) {
                        if (auth.errorMessage == null) return const SizedBox.shrink();
                        final msg = auth.errorMessage!;
                        final isBanned = msg.startsWith('');
                        return Padding(
                          padding: const EdgeInsets.only(top: 16),
                          child: Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(color: AppTheme.errorColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8), border: Border.all(color: AppTheme.errorColor.withValues(alpha: 0.3))),
                            child: Row(children: [
                              Icon(isBanned ? Icons.gavel : Icons.error, color: AppTheme.errorColor, size: 20),
                              const SizedBox(width: 8),
                              Expanded(child: Text(msg, style: const TextStyle(color: AppTheme.errorColor, fontSize: AppTheme.textSm))),
                            ]),
                          ),
                        );
                      }),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
                Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                  const Text("Don't have an account?"),
                  TextButton(onPressed: () => Navigator.of(context).pushNamed('/register'), child: const Text('Register')),
                ]),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
