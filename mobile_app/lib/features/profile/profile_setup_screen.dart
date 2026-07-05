import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../core/api/api_client.dart';
import 'package:image_picker/image_picker.dart';
// import 'dart:io';

class ProfileSetupScreen extends StatefulWidget {
  const ProfileSetupScreen({super.key});

  @override
  State<ProfileSetupScreen> createState() => _ProfileSetupScreenState();
}

class _ProfileSetupScreenState extends State<ProfileSetupScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _bioController = TextEditingController();
  final _addressController = TextEditingController();
  
  String? _selectedGender;
  String? _selectedInterest;
  List<String> _selectedExpertiseRegions = [];
  String? _avatarUrl;
  bool _isLoading = false;
  bool _editMode = false;
  final ImagePicker _picker = ImagePicker();

  final List<String> _interests = [
    'Adventure', 'Culture', 'Nature', 'Photography',
    'Food', 'History', 'Hiking', 'Wildlife', 'Trekking',
    'Yoga', 'Meditation', 'Shopping', 'Festivals'
  ];
  
  final List<String> _genders = ['Male', 'Female', 'Other'];
  
  final List<String> _regions = [
    'Kathmandu Valley', 'Pokhara', 'Chitwan', 'Lumbini',
    'Everest Region', 'Annapurna Region', 'Mustang',
    'Janakpur', 'Bardia', 'Ilam', 'Bandipur', 'Nagarkot'
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadExistingData());
  }

  void _loadExistingData() {
    final profileProv = context.read<ProfileProvider>();
    final profile = profileProv.profile;
    final authUser = context.read<AuthProvider>().user;

    // First try ProfileProvider data (full server data)
    if (profile != null) {
      _nameController.text = profile.name;
      _emailController.text = profile.email;
      _phoneController.text = profile.phone ?? '';
      _bioController.text = profile.bio ?? '';
      _selectedGender = profile.gender;
      _selectedInterest = profile.interest;
      _selectedExpertiseRegions = List.from(profile.expertiseRegions);
      _avatarUrl = profile.avatarUrl;
      _editMode = true;
    } 
    // Fallback to AuthProvider cached user data
    else if (authUser != null) {
      _nameController.text = authUser.name;
      _emailController.text = authUser.email;
      _phoneController.text = authUser.phone ?? '';
      _bioController.text = authUser.bio ?? '';
      _selectedExpertiseRegions = List.from(authUser.expertiseRegions);
      _editMode = authUser.phone != null || (authUser.bio != null && authUser.bio!.isNotEmpty);
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _bioController.dispose();
    _addressController.dispose();
    super.dispose();
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
        // In production, upload to server and get URL
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
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);

    try {
      final data = <String, dynamic>{
        'name': _nameController.text.trim(),
        'phone': _phoneController.text.trim(),
        'bio': _bioController.text.trim(),
      };
      if (_selectedGender != null) data['gender'] = _selectedGender;
      if (_selectedInterest != null) data['interest'] = _selectedInterest;
      if (_selectedExpertiseRegions.isNotEmpty) {
        data['expertise_regions'] = _selectedExpertiseRegions;
      }
      if (_avatarUrl != null && _avatarUrl!.startsWith('http')) {
        data['avatar'] = _avatarUrl;
      }

      final api = ApiClient.instance;
      await api.updateProfile(data);
      await context.read<AuthProvider>().updateProfile(data);
      // Refresh profile provider after save
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
            content: Text('Error saving profile: ${e.toString().length > 100 ? e.toString().substring(0, 100) : e.toString()}'),
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
        title: Text(_editMode ? 'Edit Profile' : 'Complete Your Profile'),
        backgroundColor: AppTheme.primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.of(context).pop(),
        ),
        actions: [
          TextButton(
            onPressed: _isLoading ? null : _handleSave,
            child: _isLoading
                ? const SizedBox(
                    width: 20, height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  )
                : const Text('Save', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
      body: SingleChildScrollView(
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
                              _nameController.text.isNotEmpty 
                                  ? _nameController.text[0].toUpperCase() 
                                  : '?',
                              style: const TextStyle(fontSize: 40, color: AppTheme.primaryColor, fontWeight: FontWeight.bold),
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

              // Section: Personal Information
              _buildSectionHeader(Icons.person_outline, 'Personal Information'),
              const SizedBox(height: 12),
              
              _buildTextField(
                controller: _nameController,
                label: 'Full Name',
                icon: Icons.person,
                validator: (v) => v == null || v.trim().isEmpty ? 'Name is required' : null,
              ),
              const SizedBox(height: 12),
              
              _buildTextField(
                controller: _emailController,
                label: 'Email',
                icon: Icons.email,
                enabled: false,
                readOnly: true,
              ),
              const SizedBox(height: 12),
              
              _buildTextField(
                controller: _phoneController,
                label: 'Phone Number',
                icon: Icons.phone,
                keyboardType: TextInputType.phone,
                hint: '98xxxxxxxx',
              ),
              const SizedBox(height: 12),
              
              _buildDropdown(
                value: _selectedGender,
                label: 'Gender',
                icon: Icons.person_outlined,
                items: _genders,
                onChanged: (v) => setState(() => _selectedGender = v),
              ),
              const SizedBox(height: 12),

              _buildDropdown(
                value: _selectedInterest,
                label: 'Travel Interest',
                icon: Icons.favorite_outline,
                items: _interests,
                onChanged: (v) => setState(() => _selectedInterest = v),
              ),
              const SizedBox(height: 24),

              // Section: About & Bio
              _buildSectionHeader(Icons.info_outline, 'About You'),
              const SizedBox(height: 12),
              
              _buildTextField(
                controller: _bioController,
                label: 'Bio',
                icon: Icons.description,
                maxLines: 4,
                maxLength: 500,
                hint: 'Tell us about yourself, your interests, and experiences...',
              ),
              const SizedBox(height: 24),

              // Section: Expertise Regions
              _buildSectionHeader(Icons.explore, 'Expertise Regions'),
              const SizedBox(height: 8),
              Text(
                'Select regions you know well',
                style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _regions.map((region) {
                  final selected = _selectedExpertiseRegions.contains(region);
                  return FilterChip(
                    label: Text(region, style: TextStyle(fontSize: AppTheme.textSm, color: selected ? Colors.white : AppTheme.textPrimary)),
                    selected: selected,
                    selectedColor: AppTheme.primaryColor,
                    checkmarkColor: Colors.white,
                    backgroundColor: AppTheme.dividerColor.withOpacity(0.3),
                    onSelected: (isSelected) {
                      setState(() {
                        if (isSelected) {
                          _selectedExpertiseRegions.add(region);
                        } else {
                          _selectedExpertiseRegions.remove(region);
                        }
                      });
                    },
                  );
                }).toList(),
              ),
              const SizedBox(height: 24),

              // Save Button (bottom)
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton.icon(
                  onPressed: _isLoading ? null : _handleSave,
                  icon: _isLoading
                      ? const SizedBox(
                          width: 20, height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Icon(Icons.save),
                  label: Text(_isLoading ? 'Saving...' : (_editMode ? 'Update Profile' : 'Save Profile')),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ),
              const SizedBox(height: 32),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSectionHeader(IconData icon, String title) {
    return Row(
      children: [
        Icon(icon, size: 20, color: AppTheme.primaryColor),
        const SizedBox(width: 8),
        Text(
          title,
          style: TextStyle(
            fontSize: AppTheme.textLg,
            fontWeight: FontWeight.bold,
            color: AppTheme.textPrimary,
          ),
        ),
        const Divider(thickness: 1),
      ],
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    String? hint,
    TextInputType? keyboardType,
    int? maxLines,
    int? maxLength,
    bool enabled = true,
    bool readOnly = false,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: controller,
      enabled: enabled,
      readOnly: readOnly,
      maxLines: maxLines ?? 1,
      maxLength: maxLength,
      keyboardType: keyboardType,
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        prefixIcon: Icon(icon),
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: AppTheme.dividerColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: AppTheme.dividerColor),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: AppTheme.primaryColor, width: 2),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      ),
      validator: validator,
    );
  }

  Widget _buildDropdown({
    required String? value,
    required String label,
    required IconData icon,
    required List<String> items,
    required ValueChanged<String?> onChanged,
  }) {
    return DropdownButtonFormField<String>(
      value: value,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon),
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: AppTheme.dividerColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: AppTheme.dividerColor),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      ),
      items: items.map((item) {
        return DropdownMenuItem(value: item, child: Text(item));
      }).toList(),
      onChanged: onChanged,
    );
  }
}
