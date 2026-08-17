import 'dart:io';
import "../../core/services/localization_service.dart";
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import '../../config/themes/app_theme.dart';
import '../../core/api/api_client.dart';
import '../../core/services/offline_db_service.dart';
import '../../providers/place_provider.dart';
import '../../providers/auth_provider.dart';
import '../auth/login_screen.dart';

class AddPlaceScreen extends StatefulWidget {
  final double? initialLat;
  final double? initialLng;
  final String? initialName;
  final String? initialDescription;
  final String? initialAddress;
  final String? initialPhone;
  final String? initialCategory;
  final String? osmId;

  const AddPlaceScreen({
    super.key,
    this.initialLat,
    this.initialLng,
    this.initialName,
    this.initialDescription,
    this.initialAddress,
    this.initialPhone,
    this.initialCategory,
    this.osmId,
  });

  @override
  State<AddPlaceScreen> createState() => _AddPlaceScreenState();
}

class _AddPlaceScreenState extends State<AddPlaceScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _addressController = TextEditingController();
  final _phoneController = TextEditingController();
  final _websiteController = TextEditingController();
  final _offlineDb = OfflineDbService.instance;

  double? _lat;
  double? _lng;
  String? _selectedCategory;
  List<XFile> _selectedImages = [];
  bool _isSubmitting = false;
  bool _isOnline = true;

  final List<String> _categories = [
    'Restaurant', 'Cafe', 'Hotel', 'Attraction', 'Viewpoint',
    'Hospital', 'Pharmacy', 'ATM', 'Bank', 'Market',
    'Transport', 'Parking', 'Shopping', 'Nature', 'Historic Site',
    'Entertainment', 'Services', 'Other',
  ];

  @override
  void initState() {
    super.initState();
    _lat = widget.initialLat;
    _lng = widget.initialLng;
    _nameController.text = widget.initialName ?? '';
    _descriptionController.text = widget.initialDescription ?? '';
    _addressController.text = widget.initialAddress ?? '';
    _phoneController.text = widget.initialPhone ?? '';
    if (widget.initialCategory != null && _categories.contains(widget.initialCategory)) {
      _selectedCategory = widget.initialCategory;
    }
    _checkConnectivity();
  }

  Future<void> _checkConnectivity() async {
    try {
      final result = await Connectivity().checkConnectivity();
      if (!mounted) return;
      setState(() {
        _isOnline = result.isNotEmpty;
      });
    } catch (_) {}
  }

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    _addressController.dispose();
    _phoneController.dispose();
    _websiteController.dispose();
    super.dispose();
  }

  Future<void> _pickImages() async {
    final picker = ImagePicker();
    final images = await picker.pickMultiImage(limit: 5);
    if (images.isNotEmpty) {
      setState(() => _selectedImages.addAll(images));
    }
  }

  Future<void> _pickCameraImage() async {
    final picker = ImagePicker();
    final image = await picker.pickImage(source: ImageSource.camera);
    if (image != null) {
      setState(() => _selectedImages.add(image));
    }
  }

  Future<void> _submitPlace() async {
    if (!_formKey.currentState!.validate()) return;
    if (_lat == null || _lng == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Location not set. Please select on map.')),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    final name = _nameController.text.trim();
    final description = _descriptionController.text.trim();
    final address = _addressController.text.trim();
    final phone = _phoneController.text.trim();
    final website = _websiteController.text.trim();

    final placeData = {
      'name': name,
      'description': description,
      'address': address,
      // FL-31: omit empty fields so backend validation never sees blank values
      if (phone.isNotEmpty) 'phone': phone,
      if (website.isNotEmpty) 'website': website,
      'latitude': _lat,
      'longitude': _lng,
      'category': _selectedCategory ?? 'Other',
      if (widget.osmId != null) 'osm_id': widget.osmId,
    };

    try {
      if (_isOnline) {
        final formData = FormData.fromMap({
          ...placeData,
          if (_selectedImages.isNotEmpty)
            for (final f in _selectedImages)
              'images[]': await MultipartFile.fromFile(f.path),
        });
        await ApiClient.instance.dio.post('/places', data: formData);
      } else {
        await OfflineDbService.instance.addToSyncQueue(
          operation: 'create',
          entityType: 'place',
          payload: placeData,
          mediaPaths: _selectedImages.isNotEmpty
              ? _selectedImages.map((f) => f.path).toList()
              : null,
        );
      }

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(_isOnline ? 'Place submitted for review!' : 'Place saved offline. Will sync when online.'),
            backgroundColor: Colors.green,
          ),
        );
        Navigator.pop(context, true);
      }
    } on DioException catch (e) {
      if (!mounted) return;

      // Lost connection mid-request -> queue offline instead of erroring out
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout ||
          e.type == DioExceptionType.sendTimeout) {
        await OfflineDbService.instance.addToSyncQueue(
          operation: 'create',
          entityType: 'place',
          payload: placeData,
          mediaPaths: _selectedImages.isNotEmpty
              ? _selectedImages.map((f) => f.path).toList()
              : null,
        );
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No connection - place saved offline. Will sync when online.'),
            backgroundColor: Colors.orange,
          ),
        );
        Navigator.pop(context, true);
        return;
      }

      final status = e.response?.statusCode;
      final bodyMessage = (e.response?.data is Map && e.response?.data['message'] != null)
          ? e.response?.data['message'].toString()
          : null;

      if (status == 401) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Please log in to submit places.'),
            backgroundColor: Colors.red,
          ),
        );
        Navigator.pop(context);
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const LoginScreen()),
        );
      } else if (status == 409) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(bodyMessage ?? 'This place already exists in our database.'),
            backgroundColor: Colors.orange,
          ),
        );
        Navigator.pop(context, false);
      } else if (status == 422) {
        final errors = e.response?.data is Map ? (e.response?.data['errors'] as Map?)?.values : null;
        final firstError = errors != null && errors.isNotEmpty
            ? (errors.first as List).first.toString()
            : 'Please check the form and try again.';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(firstError), backgroundColor: Colors.red),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(bodyMessage ?? 'Error: ${e.message ?? 'Could not submit place.'}'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
        );
      }
    }

    setState(() => _isSubmitting = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Add New Place'),
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Location indicator
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.location_on, color: AppTheme.primaryColor, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _lat != null && _lng != null
                            ? 'Location: ${_lat!.toStringAsFixed(4)}, ${_lng!.toStringAsFixed(4)}'
                            : 'Location not set',
                        style: const TextStyle(fontSize: 13),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // Name field
              TextFormField(
                controller: _nameController,
                decoration: _inputDecoration('Place Name', Icons.business),
                validator: (v) => v?.trim().isEmpty == true ? 'Name is required' : null,
              ),
              const SizedBox(height: 16),

              // Category dropdown
              DropdownButtonFormField<String>(
                value: _selectedCategory,
                decoration: _inputDecoration('Category', Icons.category),
                items: _categories.map((cat) => DropdownMenuItem(
                  value: cat,
                  child: Text(cat, style: const TextStyle(fontSize: AppTheme.textBase)),
                )).toList(),
                onChanged: (v) => setState(() => _selectedCategory = v),
                validator: (v) => v == null ? 'Select a category' : null,
              ),
              const SizedBox(height: 16),

              // Description
              TextFormField(
                controller: _descriptionController,
                decoration: _inputDecoration('Description', Icons.description),
                maxLines: 3,
                maxLength: 500,
              ),
              const SizedBox(height: 16),

              // Address
              TextFormField(
                controller: _addressController,
                decoration: _inputDecoration('Address', Icons.location_city),
              ),
              const SizedBox(height: 16),

              // Phone
              TextFormField(
                controller: _phoneController,
                decoration: _inputDecoration('Phone', Icons.phone),
                keyboardType: TextInputType.phone,
              ),
              const SizedBox(height: 16),

              // Website
              TextFormField(
                controller: _websiteController,
                decoration: _inputDecoration('Website (optional)', Icons.language),
                keyboardType: TextInputType.url,
              ),
              const SizedBox(height: 24),

              // Image picker
              const Text('Photos & Video', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
              const SizedBox(height: 8),
              Row(
                children: [
                  _imagePickerButton(
                    icon: Icons.camera_alt,
                    label: 'Camera',
                    onTap: _pickCameraImage,
                  ),
                  const SizedBox(width: 12),
                  _imagePickerButton(
                    icon: Icons.photo_library,
                    label: 'Gallery',
                    onTap: _pickImages,
                  ),
                ],
              ),
              if (_selectedImages.isNotEmpty) ...[
                const SizedBox(height: 12),
                SizedBox(
                  height: 80,
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    itemCount: _selectedImages.length,
                    itemBuilder: (context, index) {
                      return Stack(
                        children: [
                          Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: Image.file(
                                File(_selectedImages[index].path),
                                width: 80, height: 80,
                                fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) => Container(
                                  width: 80, height: 80,
                                  color: Colors.grey.shade200,
                                  child: const Icon(Icons.broken_image),
                                ),
                              ),
                            ),
                          ),
                          Positioned(
                            top: 0, right: 8,
                            child: GestureDetector(
                              onTap: () => setState(() => _selectedImages.removeAt(index)),
                              child: Container(
                                padding: const EdgeInsets.all(2),
                                decoration: const BoxDecoration(
                                  color: Colors.red,
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(Icons.close, size: 14, color: Colors.white),
                              ),
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                ),
              ],
              const SizedBox(height: 32),

              // Submit button
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton.icon(
                  onPressed: _isSubmitting ? null : _submitPlace,
                  icon: _isSubmitting
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.add_location),
                  label: Text(_isSubmitting ? 'Submitting...' : 'Submit Place for Review'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    elevation: 2,
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Center(
                child: Text(
                  'Places are reviewed before being published',
                  style: TextStyle(fontSize: AppTheme.textSm, color: Colors.grey.shade500),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  InputDecoration _inputDecoration(String label, IconData icon) {
    return InputDecoration(
      labelText: label,
      prefixIcon: Icon(icon, size: 20),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: Colors.grey.shade300),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppTheme.primaryColor, width: 2),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      filled: true,
      fillColor: Colors.grey.shade50,
    );
  }

  Widget _imagePickerButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 16),
          decoration: BoxDecoration(
            border: Border.all(color: Colors.grey.shade300, width: 1.5),
            borderRadius: BorderRadius.circular(12),
            color: Colors.grey.shade50,
          ),
          child: Column(
            children: [
              Icon(icon, size: 28, color: AppTheme.primaryColor),
              const SizedBox(height: 4),
              Text(label, style: TextStyle(fontSize: AppTheme.textSm, color: Colors.grey.shade600)),
            ],
          ),
        ),
      ),
    );
  }
}
