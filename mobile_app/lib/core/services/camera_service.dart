import 'dart:io';
import 'package:image_picker/image_picker.dart';

/// Service that ONLY allows in-app camera capture for reports.
/// Gallery/image picking from storage is intentionally blocked
/// to prevent fake photo uploads.
class CameraService {
  final ImagePicker _picker = ImagePicker();

  /// Take a photo using the in-app camera.
  /// Returns the XFile if successful, null if cancelled.
  ///
  /// IMPORTANT: This ONLY uses ImageSource.camera, never ImageSource.gallery.
  /// This is Layer 1 of the anti-fake-report system - all report photos
  /// must be live captures taken within the app at the time of submission.
  Future<XFile?> capturePhoto({
    bool useFrontCamera = false,
    int imageQuality = 85,
    double? maxWidth,
    double? maxHeight,
  }) async {
    try {
      final XFile? photo = await _picker.pickImage(
        source: ImageSource.camera, // NEVER use gallery
        imageQuality: imageQuality,
        maxWidth: maxWidth,
        maxHeight: maxHeight,
        preferredCameraDevice: useFrontCamera
            ? CameraDevice.front
            : CameraDevice.rear,
      );
      return photo;
    } catch (e) {
      print('❌ Camera capture failed: $e');
      return null;
    }
  }

  /// This method intentionally always returns null.
  /// Gallery uploads are forbidden by design to prevent fake reports.
  Future<XFile?> pickFromGallery() async {
    // Intentionally disabled - in-app camera only
    print('⚠️ Gallery upload is disabled. Only in-app camera captures are allowed.');
    return null;
  }

  /// Get the size of the captured image file in bytes
  static Future<int> getFileSize(XFile file) async {
    final File imageFile = File(file.path);
    return await imageFile.length();
  }

  /// Check if the captured photo is within acceptable size limits
  static Future<bool> isWithinSizeLimit(XFile file, {int maxBytes = 5242880}) async {
    final size = await getFileSize(file);
    return size <= maxBytes;
  }

  /// Delete a temporary captured photo file
  static Future<void> cleanUp(XFile file) async {
    try {
      final File imageFile = File(file.path);
      if (await imageFile.exists()) {
        await imageFile.delete();
      }
    } catch (e) {
      print('⚠️ Failed to clean up camera file: $e');
    }
  }
}