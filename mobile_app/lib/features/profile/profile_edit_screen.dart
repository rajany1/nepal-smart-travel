import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../core/models/profile_fields.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../widgets/section_card.dart';
import 'dynamic_profile_field.dart';
import 'package:image_picker/image_picker.dart';

class ProfileEditScreen extends StatefulWidget {
  const ProfileEditScreen({super.key});

  @override
  State<ProfileEditScreen> createState() => _ProfileEditScreenState();
}

class _ProfileEditScreenState extends State<ProfileEditScreen> {
  final _formKey = GlobalKey<FormState>();
  late Map<String, dynamic> _formValues = {};
  bool _isLoading = false;
  bool _fieldsLoaded = false;
  bool _isPicking = false;
  bool _avatarChanged = false;
  String? _avatarUrl;
  final ImagePicker _picker = ImagePicker();
  final Map<String, String?> _fieldErrors = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _initialize());
  }

  Future<void> _initialize() async {
    final profileProv = context.read<ProfileProvider>();
    final profileData = profileProv.profile;

    // Load field schemas if not loaded
    if (profileProv.fieldDefinitions.isEmpty || profileProv.fieldOptions == null) {
      await profileProv.loadFieldSchemas();
    }

    // Initialize form values from current profile
    if (profileData != null) {
      _avatarUrl = profileData.avatarUrl;
      _formValues = {
        'name': profileData.name,
        'email': profileData.email,
        'phone': profileData.phone ?? '',
        'bio': profileData.bio ?? '',
        'gender': profileData.gender,
        'interest': profileData.interest,
        'expertise_regions': profileData.expertiseRegions,
      };
    } else {
      // Fallback to AuthProvider cached user data (profile may not be loaded yet)
      final authUser = context.read<AuthProvider>().user;
      if (authUser != null) {
        _avatarUrl = authUser.avatarUrl;
        _formValues = {
          'name': authUser.name,
          'email': authUser.email,
          'phone': authUser.phone ?? '',
          'bio': authUser.bio ?? '',
          'gender': null,
          'interest': null,
          'expertise_regions': authUser.expertiseRegions,
        };
      }
    }

    if (mounted) setState(() => _fieldsLoaded = true);
  }

  Future<void> _pickImage() async {
    try {
      setState(() => _isPicking = true);
      final XFile? image = await _picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 512,
        maxHeight: 512,
        imageQuality: 80,
      );
      if (image != null) {
        setState(() {
          _avatarUrl = image.path;
          _avatarChanged = true;
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.t('Failed to pick image'))),
        );
      }
    } finally {
      if (mounted) setState(() => _isPicking = false);
    }
  }

  Future<void> _handleSave() async {
    _fieldErrors.clear();
    final profileProv = context.read<ProfileProvider>();

    for (final field in profileProv.fieldDefinitions) {
      final error = field.validateValue(_formValues[field.name]);
      if (error != null) {
        _fieldErrors[field.name] = error;
      }
    }

    if (_fieldErrors.isNotEmpty) {
      setState(() {});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.t('Please fix the errors in your profile')),
            backgroundColor: AppTheme.errorColor,
          ),
        );
      }
      return;
    }

    if (!mounted) return;
    setState(() => _isLoading = true);

    bool avatarFailed = false;
    try {
      if (_avatarChanged && _avatarUrl != null && !_avatarUrl!.startsWith('http')) {
        final bytes = await File(_avatarUrl!).readAsBytes();
        final base64Image = base64Encode(bytes);
        final ok = await profileProv.updateAvatar('data:image/jpeg;base64,$base64Image');
        if (ok) {
          final newAvatarUrl = profileProv.profile?.avatarUrl;
          if (newAvatarUrl != null && newAvatarUrl.startsWith('http')) {
            setState(() => _avatarUrl = newAvatarUrl);
          }
        } else {
          avatarFailed = true;
        }
      }
    } catch (e) {
      avatarFailed = true;
    }

    try {
      final data = Map<String, dynamic>.from(_formValues);
      data.removeWhere((k, v) => v == null || (v is String && v.isEmpty));
      data.remove('email');
      data.remove('phone');

      await context.read<AuthProvider>().updateProfile(data);
      await context.read<ProfileProvider>().loadProfile(forceRefresh: true);

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                Icon(Icons.check_circle, color: Colors.white, size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    avatarFailed
                        ? '${context.t('Profile saved')}, ${context.t('avatar upload failed')}'
                        : context.t('Profile saved successfully'),
                  ),
                ),
              ],
            ),
            backgroundColor: avatarFailed ? AppTheme.warningColor : AppTheme.successColor,
            duration: const Duration(seconds: 2),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            margin: const EdgeInsets.all(16),
          ),
        );
        await Future.delayed(const Duration(milliseconds: 1500));
        if (mounted) Navigator.of(context).pop(true);
      }
    } catch (e) {
      if (mounted) {
        final msg = e.toString().contains('Exception')
            ? e.toString().replaceFirst('Exception: ', '')
            : e.toString();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.error_outline, color: Colors.white, size: 20),
                const SizedBox(width: 8),
                Expanded(child: Text('${context.t('Error')}: $msg')),
              ],
            ),
            backgroundColor: AppTheme.errorColor,
            duration: const Duration(seconds: 3),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            margin: const EdgeInsets.all(16),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Widget _buildDefaultAvatar() {
    final initials = (_formValues['name'] as String? ?? '').isNotEmpty
        ? (_formValues['name'] as String)[0].toUpperCase()
        : '';
    return Container(
      width: 100,
      height: 100,
      decoration: const BoxDecoration(
        shape: BoxShape.circle,
        gradient: LinearGradient(
          colors: [Color(0xFF00695C), Color(0xFF00897B), Color(0xFF26A69A)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Center(
        child: initials.isNotEmpty
            ? Text(
                initials,
                style: const TextStyle(
                  fontSize: 40,
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                ),
              )
            : const Icon(Icons.person, size: 48, color: Colors.white),
      ),
    );
  }

  List<ProfileFieldDefinition> _sectionFields(ProfileProvider prov, String section) {
    return prov.fieldDefinitions.where((f) {
      switch (section) {
        case 'basic':
          return f.type != ProfileFieldType.textarea && f.type != ProfileFieldType.multiselect;
        case 'about':
          return f.type == ProfileFieldType.textarea;
        case 'prefs':
          return f.type == ProfileFieldType.multiselect;
      }
      return false;
    }).toList();
  }

  Widget _sectionHeader(BuildContext context, String title, IconData icon, Color color) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(6),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, size: 16, color: color),
        ),
        const SizedBox(width: 8),
        Text(
          context.t(title),
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textLg, color: AppTheme.textPrimary),
        ),
      ],
    );
  }

  Widget _buildFieldList(List<ProfileFieldDefinition> fields) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: fields.map((field) {
        return Padding(
          padding: const EdgeInsets.only(bottom: 16),
          child: DynamicProfileField(
            fieldDef: field,
            initialValue: _formValues[field.name],
            fieldOptions: context.read<ProfileProvider>().fieldOptions,
            onChanged: (value) {
              setState(() {
                _formValues[field.name] = value;
                _fieldErrors.remove(field.name);
              });
            },
            errorText: _fieldErrors[field.name],
          ),
        );
      }).toList(),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: GestureDetector(
          onTap: () => Navigator.pop(context),
          child: Container(
            margin: const EdgeInsets.all(8),
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(Icons.arrow_back_ios_new, color: Color(0xFF2D3436), size: 18),
          ),
        ),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: const Color(0xFF00695C).withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.edit, color: Color(0xFF00695C), size: 20),
            ),
            const SizedBox(width: 10),
            Text(
              context.t('Edit Profile'),
              style: const TextStyle(
                color: Color(0xFF2D3436),
                fontSize: 20,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
      body: Consumer<ProfileProvider>(
        builder: (context, profileProv, _) {
          if (!_fieldsLoaded || profileProv.isFieldsLoading) {
            return const Center(
              child: CircularProgressIndicator(color: AppTheme.primaryColor),
            );
          }

          if (profileProv.fieldDefinitions.isEmpty) {
            return Center(
              child: Text(context.t('Failed to load profile fields')),
            );
          }

          final basicFields = _sectionFields(profileProv, 'basic');
          final aboutFields = _sectionFields(profileProv, 'about');
          final prefsFields = _sectionFields(profileProv, 'prefs');

          return SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 24, 16, 24),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Avatar Section
                  Center(
                    child: Column(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(4),
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: const Color(0xFF00695C).withOpacity(0.2),
                              width: 3,
                            ),
                          ),
                          child: Stack(
                            children: [
                              CircleAvatar(
                                radius: 50,
                                backgroundColor: const Color(0xFF00695C).withOpacity(0.1),
                                backgroundImage: _avatarUrl != null && _avatarUrl!.startsWith('http')
                                    ? NetworkImage(_avatarUrl!)
                                    : null,
                                child: _avatarUrl == null || !_avatarUrl!.startsWith('http')
                                    ? (_avatarUrl != null
                                        ? ClipOval(
                                            child: Image.file(
                                              File(_avatarUrl!),
                                              width: 100,
                                              height: 100,
                                              fit: BoxFit.cover,
                                              errorBuilder: (_, __, ___) => _buildDefaultAvatar(),
                                            ),
                                          )
                                        : _buildDefaultAvatar())
                                    : null,
                              ),
                              Positioned(
                                bottom: 2,
                                right: 2,
                                child: GestureDetector(
                                  onTap: _isPicking ? null : _pickImage,
                                  child: Container(
                                    padding: const EdgeInsets.all(8),
                                    decoration: BoxDecoration(
                                      gradient: const LinearGradient(
                                        colors: [Color(0xFF00695C), Color(0xFF00897B)],
                                      ),
                                      shape: BoxShape.circle,
                                      border: Border.all(color: Colors.white, width: 2),
                                      boxShadow: [
                                        BoxShadow(
                                          color: const Color(0xFF00695C).withOpacity(0.3),
                                          blurRadius: 6,
                                          offset: const Offset(0, 2),
                                        ),
                                      ],
                                    ),
                                    child: _isPicking
                                        ? const SizedBox(
                                            width: 16,
                                            height: 16,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                              valueColor: AlwaysStoppedAnimation(Colors.white),
                                            ),
                                          )
                                        : const Icon(Icons.camera_alt, size: 16, color: Colors.white),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 10),
                        GestureDetector(
                          onTap: _isPicking ? null : _pickImage,
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                            decoration: BoxDecoration(
                              color: const Color(0xFF00695C).withOpacity(0.08),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.photo_library_outlined, size: 14, color: Color(0xFF00695C)),
                                const SizedBox(width: 4),
                                Text(
                                  context.t('Change Photo'),
                                  style: const TextStyle(
                                    color: Color(0xFF00695C),
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 28),

                  if (basicFields.isNotEmpty) ...[
                    _sectionHeader(context, 'Basic Info', Icons.person, AppTheme.primaryColor),
                    const SizedBox(height: 10),
                    SectionCard(
                      child: _buildFieldList(basicFields),
                    ),
                    const SizedBox(height: 20),
                  ],

                  if (aboutFields.isNotEmpty) ...[
                    _sectionHeader(context, 'About', Icons.description, AppTheme.infoColor),
                    const SizedBox(height: 10),
                    SectionCard(
                      child: _buildFieldList(aboutFields),
                    ),
                    const SizedBox(height: 20),
                  ],

                  if (prefsFields.isNotEmpty) ...[
                    _sectionHeader(context, 'Preferences', Icons.favorite, AppTheme.secondaryColor),
                    const SizedBox(height: 10),
                    SectionCard(
                      child: _buildFieldList(prefsFields),
                    ),
                    const SizedBox(height: 20),
                  ],

                  // Save Button
                  Container(
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF00695C), Color(0xFF00897B)],
                      ),
                      borderRadius: BorderRadius.circular(14),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF00695C).withOpacity(0.3),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Material(
                      color: Colors.transparent,
                      child: InkWell(
                        onTap: _isLoading ? null : _handleSave,
                        borderRadius: BorderRadius.circular(14),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          child: Center(
                            child: _isLoading
                                ? const SizedBox(
                                    width: 22,
                                    height: 22,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2.5,
                                      valueColor: AlwaysStoppedAnimation(Colors.white),
                                    ),
                                  )
                                : Row(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      const Icon(Icons.save, size: 18, color: Colors.white),
                                      const SizedBox(width: 8),
                                      Text(
                                        context.t('Save Profile'),
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontSize: 16,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ],
                                  ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}