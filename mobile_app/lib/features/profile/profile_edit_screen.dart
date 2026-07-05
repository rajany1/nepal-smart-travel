import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../core/models/profile_fields.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../core/api/api_client.dart';
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
    }

    setState(() => _fieldsLoaded = true);
  }

  Future<void> _pickImage() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 512,
        maxHeight: 512,
        imageQuality: 80,
      );
      if (image != null) {
        setState(() => _avatarUrl = image.path);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to pick image')),
        );
      }
    }
  }

  Future<void> _handleSave() async {
    // Validate all fields
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
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please fix the errors in your profile'),
          backgroundColor: AppTheme.errorColor,
        ),
      );
      return;
    }

    setState(() => _isLoading = true);

    try {
      final data = Map<String, dynamic>.from(_formValues);
      data.removeWhere((k, v) => v == null || (v is String && v.isEmpty));

      final api = ApiClient.instance;
      await api.updateProfile(data);
      await context.read<AuthProvider>().updateProfile(data);
      await context.read<ProfileProvider>().loadProfile(forceRefresh: true);

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Profile saved successfully'),
            backgroundColor: AppTheme.successColor,
            duration: Duration(seconds: 2),
          ),
        );
        Navigator.of(context).pop();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error saving profile: ${e.toString().substring(0, 100)}'),
            backgroundColor: AppTheme.errorColor,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        title: const Text('Edit Profile'),
        backgroundColor: AppTheme.primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: Consumer<ProfileProvider>(
        builder: (context, profileProv, _) {
          if (!_fieldsLoaded || profileProv.isFieldsLoading) {
            return const Center(child: CircularProgressIndicator());
          }

          if (profileProv.fieldDefinitions.isEmpty) {
            return const Center(
              child: Text('Failed to load profile fields'),
            );
          }

          return SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Avatar Section
                  Center(
                    child: Stack(
                      children: [
                        CircleAvatar(
                          radius: 50,
                          backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                          backgroundImage: _avatarUrl != null && _avatarUrl!.startsWith('http')
                              ? NetworkImage(_avatarUrl!)
                              : null,
                          child: _avatarUrl == null || !_avatarUrl!.startsWith('http')
                              ? Text(
                                  (_formValues['name'] as String? ?? '').isNotEmpty
                                      ? (_formValues['name'] as String)[0].toUpperCase()
                                      : '?',
                                  style: const TextStyle(
                                    fontSize: 40,
                                    color: AppTheme.primaryColor,
                                    fontWeight: FontWeight.bold,
                                  ),
                                )
                              : null,
                        ),
                        Positioned(
                          bottom: 0,
                          right: 0,
                          child: Container(
                            padding: const EdgeInsets.all(4),
                            decoration: const BoxDecoration(
                              color: AppTheme.primaryColor,
                              shape: BoxShape.circle,
                            ),
                            child: IconButton(
                              icon: const Icon(Icons.camera_alt, size: 18, color: Colors.white),
                              onPressed: _pickImage,
                              padding: EdgeInsets.zero,
                              constraints: const BoxConstraints(minWidth: 24, minHeight: 24),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Dynamic form fields
                  ...profileProv.fieldDefinitions.map((field) {
                    return Column(
                      children: [
                        DynamicProfileField(
                          fieldDef: field,
                          initialValue: _formValues[field.name],
                          fieldOptions: profileProv.fieldOptions,
                          onChanged: (value) {
                            setState(() {
                              _formValues[field.name] = value;
                              _fieldErrors.remove(field.name);
                            });
                          },
                          errorText: _fieldErrors[field.name],
                        ),
                        const SizedBox(height: 16),
                      ],
                    );
                  }).toList(),

                  // Save Button
                  SizedBox(
                    height: 48,
                    child: ElevatedButton.icon(
                      onPressed: _isLoading ? null : _handleSave,
                      icon: _isLoading
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation(Colors.white),
                              ),
                            )
                          : const Icon(Icons.save),
                      label: Text(_isLoading ? 'Saving...' : 'Save Profile'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primaryColor,
                        foregroundColor: Colors.white,
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
