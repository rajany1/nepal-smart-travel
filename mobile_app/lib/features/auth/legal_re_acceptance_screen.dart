import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';
import '../profile/legal_document_screen.dart';

/// Screen shown when the backend requires (re-)acceptance of updated legal
/// documents.  Typically presented modally or as a full-screen route when the
/// API interceptor catches a LEGAL_RE_ACCEPTANCE_REQUIRED response.
class LegalReAcceptanceScreen extends StatefulWidget {
  const LegalReAcceptanceScreen({super.key});

  @override
  State<LegalReAcceptanceScreen> createState() => _LegalReAcceptanceScreenState();
}

class _LegalReAcceptanceScreenState extends State<LegalReAcceptanceScreen> {
  final ApiClient _api = ApiClient.instance;
  List<Map<String, dynamic>> _pendingDocuments = [];
  bool _isLoading = true;
  bool _isSubmitting = false;
  String? _errorMessage;

  /// Local toggle state keyed by document_type.
  final Map<String, bool> _accepted = {};

  @override
  void initState() {
    super.initState();
    _loadPendingDocuments();
  }

  Future<void> _loadPendingDocuments() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final response = await _api.getLegalAcceptanceStatus();
      final data = response.data;
      if (data['success'] == true) {
        final pending = List<Map<String, dynamic>>.from(
          data['pending_documents'] ?? [],
        );
        setState(() {
          _pendingDocuments = pending;
          for (final doc in pending) {
            _accepted[doc['document_type'] as String] = false;
          }
          _isLoading = false;
        });
      } else {
        setState(() {
          _errorMessage = 'Failed to load legal documents.';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Network error. Please try again.';
        _isLoading = false;
      });
    }
  }

  bool get _allAccepted =>
      _accepted.isNotEmpty && _accepted.values.every((v) => v);

  Future<void> _submitAcceptance() async {
    if (!_allAccepted || _isSubmitting) return;

    setState(() => _isSubmitting = true);

    try {
      final documents = _accepted.entries
          .where((e) => e.value)
          .map((e) => e.key)
          .toList();

      final response = await _api.acceptLegal(documents: documents);

      if (response.data['success'] == true && mounted) {
        Navigator.of(context).pop(true); // signal success
      } else {
        setState(() {
          _errorMessage = response.data['message'] ?? 'Submission failed.';
          _isSubmitting = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Network error. Please try again.';
        _isSubmitting = false;
      });
    }
  }

  void _openDocument(String type) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => LegalDocumentScreen(type: type)),
    );
  }

  String _documentTitle(String type) {
    switch (type) {
      case 'terms_conditions':
        return 'Terms of Use';
      case 'privacy_policy':
        return 'Privacy Policy';
      default:
        return type;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Updated Terms'),
        backgroundColor: Colors.transparent,
        foregroundColor: const Color(0xFF2D3436),
        elevation: 0,
        automaticallyImplyLeading: false,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _errorMessage != null && _pendingDocuments.isEmpty
              ? _buildErrorView()
              : SafeArea(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _buildHeader(),
                        const SizedBox(height: 32),
                        ..._pendingDocuments.map(_buildDocumentRow),
                        if (_errorMessage != null) ...[
                          const SizedBox(height: 16),
                          _buildErrorMessage(),
                        ],
                        const SizedBox(height: 32),
                        _buildSubmitButton(),
                        const SizedBox(height: 12),
                        TextButton(
                          onPressed: _isSubmitting
                              ? null
                              : () => Navigator.of(context).pop(false),
                          child: Text(
                            'Cancel',
                            style: TextStyle(color: Colors.grey.shade600),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            const Color(0xFF00695C).withOpacity(0.08),
            const Color(0xFF00695C).withOpacity(0.04),
          ],
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: const Color(0xFF00695C).withOpacity(0.15),
        ),
      ),
      child: Column(
        children: [
          const Icon(
            Icons.gavel_rounded,
            size: 48,
            color: Color(0xFF00695C),
          ),
          const SizedBox(height: 12),
          const Text(
            'Legal Documents Updated',
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: Color(0xFF2D3436),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'We\'ve updated our terms. Please review and accept to continue using Oripori.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 14,
              color: Colors.grey.shade600,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDocumentRow(Map<String, dynamic> doc) {
    final type = doc['document_type'] as String;
    final title = _documentTitle(type);
    final isAccepted = _accepted[type] ?? false;

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          GestureDetector(
            onTap: () => setState(() => _accepted[type] = !isAccepted),
            child: Row(
              children: [
                Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: isAccepted ? const Color(0xFF00695C) : Colors.transparent,
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(
                      color: isAccepted ? const Color(0xFF00695C) : Colors.grey.shade300,
                      width: 1.5,
                    ),
                  ),
                  child: isAccepted
                      ? const Icon(Icons.check, color: Colors.white, size: 14)
                      : null,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text.rich(
                    TextSpan(
                      text: 'I agree to the ',
                      style: TextStyle(
                        color: Colors.grey.shade600,
                        fontSize: 13,
                      ),
                      children: [
                        TextSpan(
                          text: title,
                          style: const TextStyle(
                            color: Color(0xFF00695C),
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(left: 32, top: 4),
            child: GestureDetector(
              onTap: () => _openDocument(type),
              child: Text(
                'Tap to read $title',
                style: TextStyle(
                  color: Theme.of(context).primaryColor,
                  fontSize: 12,
                  decoration: TextDecoration.underline,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorMessage() {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFDEDEC),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFF5B7B1)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline, color: Color(0xFFE74C3C), size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              _errorMessage!,
              style: const TextStyle(color: Color(0xFFE74C3C), fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSubmitButton() {
    return ElevatedButton(
      onPressed: _allAccepted && !_isSubmitting ? _submitAcceptance : null,
      style: ElevatedButton.styleFrom(
        backgroundColor: const Color(0xFF00695C),
        foregroundColor: Colors.white,
        disabledBackgroundColor: Colors.grey.shade300,
        disabledForegroundColor: Colors.grey.shade500,
        padding: const EdgeInsets.symmetric(vertical: 16),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
        elevation: 0,
      ),
      child: _isSubmitting
          ? const SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(
                color: Colors.white,
                strokeWidth: 2.5,
              ),
            )
          : const Text(
              'Accept & Continue',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
    );
  }

  Widget _buildErrorView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline_rounded, size: 56, color: Color(0xFFE74C3C)),
            const SizedBox(height: 24),
            Text(
              _errorMessage ?? 'Something went wrong',
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 16, color: Color(0xFF2D3436)),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: _loadPendingDocuments,
              icon: const Icon(Icons.refresh_rounded, size: 20),
              label: const Text('Retry'),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF00695C),
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
