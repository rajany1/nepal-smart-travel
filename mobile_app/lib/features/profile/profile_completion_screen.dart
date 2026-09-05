import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_completion_provider.dart';
import '../../core/models/user.dart';
import '../auth/phone_verification_screen.dart';

class ProfileCompletionScreen extends StatefulWidget {
  const ProfileCompletionScreen({super.key});

  @override
  State<ProfileCompletionScreen> createState() => _ProfileCompletionScreenState();
}

class _ProfileCompletionScreenState extends State<ProfileCompletionScreen> {
  final _formKey = GlobalKey<FormState>();
  final _bioController = TextEditingController();
  final _phoneController = TextEditingController();

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    if (user != null) {
      if (user.phone != null && user.phone!.isNotEmpty) {
        _phoneController.text = user.phone!;
      }
      if (user.bio != null && user.bio!.isNotEmpty) {
        _bioController.text = user.bio!;
      }
    }
  }

  @override
  void dispose() {
    _bioController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _handleCompleteProfile() async {
    if (_formKey.currentState!.validate()) {
      final provider = context.read<ProfileCompletionProvider>();

      final success = await provider.completeProfile(
        bio: _bioController.text.trim(),
        phone: _phoneController.text.trim(),
      );

      if (success && mounted) {
        final auth = context.read<AuthProvider>();

        // ✅ Update user's profile_completed locally IMMEDIATELY
        // so on next app open it doesn't redirect here again
        if (auth.user != null) {
          final old = auth.user!;
          final updatedUser = UserModel(
            id: old.id,
            name: old.name,
            email: old.email,
            phone: old.phone ?? _phoneController.text.trim(),
            bio: _bioController.text.trim(),
            role: old.role,
            status: old.status,
            totalXp: old.totalXp,
            approvedReports: old.approvedReports,
            profileCompleted: true, // ✅ Mark as completed locally
            currentLevel: old.currentLevel,
            avatarUrl: old.avatarUrl,
            verificationTick: old.verificationTick,
            badges: old.badges,
            expertiseRegions: old.expertiseRegions,
            totalReports: old.totalReports,
            approvalRate: old.approvalRate,
            rank: old.rank,
            lastContributionAt: old.lastContributionAt,
            createdAt: old.createdAt,
          );
          auth.updateLocalUser(updatedUser);
        }

        // Navigate to home
        if (mounted) {
          Navigator.of(context).pushReplacementNamed('/home');
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        title: const Text('Complete Your Profile'),
        elevation: 0,
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: AppTheme.textPrimary,
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Header Section
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [AppTheme.primaryColor, AppTheme.primaryLight],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        width: 56,
                        height: 56,
                        child: ClipOval(
                          child: Image.asset(
                            'assets/images/oripori_logo_light.png',
                            fit: BoxFit.contain,
                            errorBuilder: (_, __, ___) => const Icon(Icons.person_outline, color: Colors.white, size: 32),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      const Text(
                        'Almost there!',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'Complete your profile to unlock all features of Oripori',
                        style: TextStyle(
                          color: Colors.white70,
                          fontSize: AppTheme.textBase,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 32),

                Consumer<ProfileCompletionProvider>(
                  builder: (context, provider, _) {
                    if (provider.errorMessage != null) {
                      return Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppTheme.errorColor.withOpacity(0.1),
                          border: Border.all(color: AppTheme.errorColor.withOpacity(0.4)),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          children: [
                            Icon(Icons.error_outline, color: AppTheme.errorColor),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                provider.errorMessage!,
                                style: TextStyle(color: AppTheme.errorColor),
                              ),
                            ),
                          ],
                        ),
                      );
                    }
                    return const SizedBox.shrink();
                  },
                ),
                const SizedBox(height: 24),

                // Bio Field (Optional)
                Text(
                  'Bio',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _bioController,
                  maxLines: 4,
                  maxLength: 500,
                  decoration: InputDecoration(
                    labelText: 'Tell us about yourself (optional)',
                    hintText: 'Are you a foodie? A hiking enthusiast? A local guide? Share what makes you tick!',
                    prefixIcon: const Icon(Icons.description_outlined),
                    helperText: 'Helps us personalize your experience',
                    counterText: '',
                  ),
                  validator: (value) {
                    if (value != null && value.trim().isNotEmpty && value.trim().length < 10) {
                      return 'Bio must be at least 10 characters if provided';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 24),

                // Phone Verification Section
                Builder(
                  builder: (context) {
                    final user = context.watch<AuthProvider>().user;
                    final isPartner = user?.role == 'business';
                    return Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: isPartner
                            ? AppTheme.warningColor.withValues(alpha: 0.05)
                            : AppTheme.primaryColor.withValues(alpha: 0.05),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: isPartner
                              ? AppTheme.warningColor.withValues(alpha: 0.3)
                              : AppTheme.primaryColor.withValues(alpha: 0.2),
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Icon(Icons.phone_android,
                                color: isPartner ? AppTheme.warningColor : AppTheme.primaryColor,
                                size: 20,
                              ),
                              const SizedBox(width: 8),
                              Text(
                                'Phone Verification',
                                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: isPartner
                                      ? AppTheme.warningColor.withValues(alpha: 0.15)
                                      : AppTheme.accentColor.withValues(alpha: 0.15),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Text(
                                  isPartner ? 'Required' : '+50 XP',
                                  style: TextStyle(
                                    color: isPartner ? AppTheme.warningColor : AppTheme.accentColor,
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(
                            isPartner
                                ? 'As a business partner, phone verification is mandatory to build trust with customers.'
                                : 'Verify your phone to earn 50 XP and get a trust badge. Other users will trust your reports more!',
                            style: TextStyle(
                              color: AppTheme.textSecondary,
                              fontSize: AppTheme.textBase,
                            ),
                          ),
                          const SizedBox(height: 12),
                          SizedBox(
                            width: double.infinity,
                            child: OutlinedButton.icon(
                              onPressed: () async {
                                final result = await Navigator.push(
                                  context,
                                  MaterialPageRoute(builder: (_) => const PhoneVerificationScreen()),
                                );
                                if (result == true && mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    SnackBar(
                                      content: Text(isPartner
                                          ? 'Phone verified successfully!'
                                          : 'Phone verified! +50 XP earned!'),
                                      backgroundColor: AppTheme.successColor,
                                    ),
                                  );
                                }
                              },
                              icon: const Icon(Icons.verified_user, size: 18),
                              label: const Text('Verify Phone Number'),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: AppTheme.primaryColor,
                                side: const BorderSide(color: AppTheme.primaryColor),
                                padding: const EdgeInsets.symmetric(vertical: 12),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                ),
                const SizedBox(height: 24),

                // Submit Button
                Consumer<ProfileCompletionProvider>(
                  builder: (context, provider, _) {
                    return ElevatedButton.icon(
                      onPressed: provider.isLoading ? null : _handleCompleteProfile,
                      icon: provider.isLoading
                          ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor: AlwaysStoppedAnimation<Color>(
                            Colors.white,
                          ),
                        ),
                      )
                          : const Icon(Icons.check_circle_outline),
                      label: Text(
                        provider.isLoading ? 'Completing...' : 'Complete Profile',
                        style: const TextStyle(fontSize: AppTheme.textLg),
                      ),
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        backgroundColor: AppTheme.primaryColor,
                        disabledBackgroundColor: AppTheme.primaryColor.withOpacity(0.5),
                      ),
                    );
                  },
                ),
                const SizedBox(height: 12),

                // Skip Button
                SizedBox(
                  width: double.infinity,
                  child: TextButton(
                    onPressed: () {
                      Navigator.of(context).pushReplacementNamed('/home');
                    },
                    child: const Text(
                      'Skip for now',
                      style: TextStyle(color: AppTheme.textSecondary),
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Info Message
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.infoColor.withOpacity(0.1),
                    border: Border.all(color: AppTheme.infoColor.withOpacity(0.4)),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.info_outline, color: AppTheme.infoColor, size: 20),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Your profile information helps the community get to know you better and helps us provide personalized recommendations.',
                          style: TextStyle(
                            color: AppTheme.infoColor,
                            fontSize: AppTheme.textBase,
                          ),
                        ),
                      ),
                    ],
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
