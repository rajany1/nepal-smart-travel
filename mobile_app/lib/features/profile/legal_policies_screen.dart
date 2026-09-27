import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../config/constants/app_constants.dart';

/// Profile → Legal & Policies.
///
/// Lists the canonical legal documents and opens them on the website
/// (see AppConstants.websiteBaseUrl → /legal/...) in the external browser.
/// No legal content is duplicated in the app — the website is the
/// single source of truth.
class LegalPoliciesScreen extends StatelessWidget {
  const LegalPoliciesScreen({super.key});

  Future<void> _openUrl(BuildContext context, String url) async {
    final uri = Uri.parse(url);
    try {
      final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!launched && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not open $url')),
        );
      }
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not open $url')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final docs = AppConstants.legalDocuments.entries.toList();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Legal & Policies'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Intro
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Your privacy, safety and rights matter.',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                SizedBox(height: 6),
                Text(
                  'All legal documents live on the Oripori website and open in your browser, so you always see the latest published version.',
                  style: TextStyle(
                    fontSize: 13,
                    height: 1.5,
                    color: Colors.black54,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          // Open full Legal Center
          FilledButton.icon(
            onPressed: () => _openUrl(context, AppConstants.legalCenterUrl),
            icon: const Icon(Icons.open_in_new, size: 18),
            label: const Text('Open Legal Center'),
          ),

          const SizedBox(height: 24),

          Text(
            'Documents',
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: Colors.black54,
                ),
          ),
          const SizedBox(height: 8),

          Card(
            margin: EdgeInsets.zero,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              children: [
                for (var i = 0; i < docs.length; i++) ...[
                  if (i > 0) const Divider(height: 1, indent: 56),
                  ListTile(
                    leading: const Icon(Icons.description_outlined),
                    title: Text(
                      docs[i].key,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                    subtitle: const Text(
                      'Opens in browser',
                      style: TextStyle(fontSize: 12),
                    ),
                    trailing: const Icon(Icons.open_in_new, size: 16),
                    onTap: () => _openUrl(
                      context,
                      AppConstants.legalDocumentUrl(docs[i].value),
                    ),
                  ),
                ],
              ],
            ),
          ),

          const SizedBox(height: 16),

          Text(
            'You can also read every document at ${AppConstants.legalCenterUrl}',
            style: const TextStyle(
              fontSize: 12,
              color: Colors.black45,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}
