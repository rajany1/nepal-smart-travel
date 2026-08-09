import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import '../../config/themes/app_theme.dart';
import '../../core/api/api_client.dart';
import '../../core/services/location_service.dart';
import '../../core/models/place.dart';
import '../../providers/auth_provider.dart';
import '../auth/login_screen.dart';

class PlaceCorrectionScreen extends StatefulWidget {
  final Place place;

  const PlaceCorrectionScreen({super.key, required this.place});

  @override
  State<PlaceCorrectionScreen> createState() => _PlaceCorrectionScreenState();
}

class _PlaceCorrectionScreenState extends State<PlaceCorrectionScreen> {
  final _formKey = GlobalKey<FormState>();
  final _descriptionController = TextEditingController();
  final _suggestedNameController = TextEditingController();
  final _latController = TextEditingController();
  final _lngController = TextEditingController();
  bool _isSubmitting = false;
  bool _pickingLocation = false;

  String? _selectedType;

  static const Map<String, String> _types = {
    'wrong_location': 'Wrong location on map',
    'wrong_name': 'Wrong name',
    'closed': 'Place is closed / gone',
    'duplicate': 'Duplicate place',
    'outdated_info': 'Outdated information',
    'other': 'Something else',
  };

  @override
  void initState() {
    super.initState();
    _latController.text = widget.place.latitude.toStringAsFixed(6);
    _lngController.text = widget.place.longitude.toStringAsFixed(6);
  }

  @override
  void dispose() {
    _descriptionController.dispose();
    _suggestedNameController.dispose();
    _latController.dispose();
    _lngController.dispose();
    super.dispose();
  }

  Future<void> _useCurrentLocation() async {
    setState(() => _pickingLocation = true);
    try {
      final pos = await LocationService().getCurrentLocation();
      if (pos != null && mounted) {
        setState(() {
          _latController.text = pos.latitude.toStringAsFixed(6);
          _lngController.text = pos.longitude.toStringAsFixed(6);
        });
      }
    } catch (_) {}
    if (mounted) setState(() => _pickingLocation = false);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) {
      final goLogin = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Login required'),
          content: Text('Log in to report a problem about "${widget.place.name}".'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Log in'),
            ),
          ],
        ),
      );
      if (goLogin == true && mounted) {
        Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      }
      return;
    }

    setState(() => _isSubmitting = true);

    final isOsm = widget.place.source == 'osm';
    final dbId = isOsm ? null : int.tryParse(widget.place.id.replaceAll(RegExp(r'[^0-9]'), ''));
    final isWrongLocation = _selectedType == 'wrong_location';
    final data = {
      'place_id': dbId,
      'osm_id': isOsm ? widget.place.id : null,
      'place_name': widget.place.name,
      'correction_type': _selectedType,
      'description': _descriptionController.text.trim(),
      if (_selectedType == 'wrong_name' && _suggestedNameController.text.trim().isNotEmpty)
        'suggested_name': _suggestedNameController.text.trim(),
      if (isWrongLocation)
        'suggested_latitude': double.tryParse(_latController.text),
      if (isWrongLocation)
        'suggested_longitude': double.tryParse(_lngController.text),
    };

    try {
      final response = await ApiClient.instance.dio.post('/places/corrections', data: data);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(response.data?['message'] ?? 'Correction request submitted!'),
            backgroundColor: Colors.green,
          ),
        );
        Navigator.pop(context, true);
      }
    } on DioException catch (e) {
      if (!mounted) return;
      if (e.response?.statusCode == 401) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please log in to report a problem.'), backgroundColor: Colors.red),
        );
        Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      } else {
        final raw = e.response?.data is Map ? e.response?.data['message'] : null;
        final msg = raw != null ? raw.toString() : 'Could not submit. Please try again.';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(msg), backgroundColor: Colors.red),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
        );
      }
    }

    if (mounted) setState(() => _isSubmitting = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Report a Problem'),
        elevation: 0,
        backgroundColor: Colors.white,
        foregroundColor: AppTheme.textPrimary,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.place, color: AppTheme.primaryColor, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.place.name,
                            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                          ),
                          Text(
                            'Help us keep this place accurate. Our team will verify your report.',
                            style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              DropdownButtonFormField<String>(
                value: _selectedType,
                decoration: _inputDecoration('What is wrong?', Icons.report_problem),
                items: _types.entries
                    .map((e) => DropdownMenuItem(value: e.key, child: Text(e.value)))
                    .toList(),
                onChanged: (v) => setState(() => _selectedType = v),
                validator: (v) => v == null ? 'Select an issue type' : null,
              ),
              const SizedBox(height: 16),

              if (_selectedType == 'wrong_name') ...[
                TextFormField(
                  controller: _suggestedNameController,
                  decoration: _inputDecoration('Suggested correct name', Icons.edit),
                  validator: (v) => v?.trim().isEmpty == true ? 'Enter the correct name' : null,
                ),
                const SizedBox(height: 16),
              ],

              if (_selectedType == 'wrong_location') ...[
                Text('Correct location', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _latController,
                        decoration: _inputDecoration('Latitude', Icons.my_location),
                        keyboardType: TextInputType.number,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextFormField(
                        controller: _lngController,
                        decoration: _inputDecoration('Longitude', Icons.my_location),
                        keyboardType: TextInputType.number,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: _pickingLocation ? null : _useCurrentLocation,
                  icon: _pickingLocation
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.gps_fixed, size: 18),
                  label: const Text('Use my current location'),
                ),
                const SizedBox(height: 16),
              ],

              TextFormField(
                controller: _descriptionController,
                decoration: _inputDecoration('Describe the problem', Icons.notes),
                maxLines: 4,
                maxLength: 1000,
                validator: (v) => v?.trim().isEmpty == true ? 'Please describe the problem' : null,
              ),
              const SizedBox(height: 24),

              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton.icon(
                  onPressed: _isSubmitting ? null : _submit,
                  icon: _isSubmitting
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.send),
                  label: Text(_isSubmitting ? 'Submitting...' : 'Submit Request'),
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
                  'Your report goes to our admin team for verification.',
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
}
