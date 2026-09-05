import 'package:flutter/material.dart';
import '../../../config/themes/app_theme.dart';
import '../../../core/models/profile_fields.dart';

/// Dynamic form field widget that renders different field types based on definition
class DynamicProfileField extends StatefulWidget {
  final ProfileFieldDefinition fieldDef;
  final dynamic initialValue;
  final ProfileFieldOptions? fieldOptions;
  final ValueChanged<dynamic> onChanged;
  final String? errorText;

  const DynamicProfileField({
    super.key,
    required this.fieldDef,
    this.initialValue,
    this.fieldOptions,
    required this.onChanged,
    this.errorText,
  });

  @override
  State<DynamicProfileField> createState() => _DynamicProfileFieldState();
}

class _DynamicProfileFieldState extends State<DynamicProfileField> {
  late TextEditingController _textController;
  late List<String> _selectedItems;

  @override
  void initState() {
    super.initState();
    _textController = TextEditingController(
      text: widget.initialValue?.toString() ?? '',
    );
    _selectedItems = widget.initialValue is List
        ? List<String>.from(widget.initialValue)
        : [];
  }

  @override
  void dispose() {
    _textController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Label
        Row(
          children: [
            if (widget.fieldDef.icon != null)
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: Icon(
                  _getIconData(widget.fieldDef.icon!),
                  size: 20,
                  color: AppTheme.primaryColor,
                ),
              ),
            Text(
              widget.fieldDef.label,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppTheme.textPrimary,
              ),
            ),
            if (widget.fieldDef.required)
              const Text(
                ' *',
                style: TextStyle(color: AppTheme.errorColor, fontSize: 14),
              ),
          ],
        ),
        const SizedBox(height: 8),

        // Field widget
        _buildFieldWidget(),

        // Error message
        if (widget.errorText != null) ...[
          const SizedBox(height: 6),
          Text(
            widget.errorText!,
            style: const TextStyle(
              color: AppTheme.errorColor,
              fontSize: 12,
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildFieldWidget() {
    switch (widget.fieldDef.type) {
      case ProfileFieldType.text:
      case ProfileFieldType.email:
      case ProfileFieldType.phone:
        return _buildTextField();

      case ProfileFieldType.textarea:
        return _buildTextAreaField();

      case ProfileFieldType.select:
        return _buildSelectField();

      case ProfileFieldType.multiselect:
        return _buildMultiSelectField();

      case ProfileFieldType.number:
        return _buildNumberField();

      case ProfileFieldType.date:
        return _buildDateField();

      case ProfileFieldType.checkbox:
        return _buildCheckboxField();
    }
  }

  InputDecoration _baseDecoration({String? hint, Widget? suffixIcon}) {
    return InputDecoration(
      hintText: hint ?? widget.fieldDef.placeholder,
      suffixIcon: suffixIcon,
      filled: widget.fieldDef.readonly,
      fillColor: widget.fieldDef.readonly
          ? AppTheme.dividerColor.withValues(alpha: 0.3)
          : Colors.white,
      hintStyle: const TextStyle(color: AppTheme.textSecondary),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppTheme.dividerColor),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppTheme.dividerColor),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(
          color: AppTheme.primaryColor,
          width: 2,
        ),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppTheme.errorColor),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppTheme.errorColor, width: 2),
      ),
      contentPadding: const EdgeInsets.symmetric(
        horizontal: 14,
        vertical: 14,
      ),
    );
  }

  Widget _buildTextField() {
    return TextFormField(
      controller: _textController,
      keyboardType: _getKeyboardType(),
      obscureText: widget.fieldDef.type == ProfileFieldType.email ? false : false,
      maxLength: widget.fieldDef.maxLength,
      readOnly: widget.fieldDef.readonly,
      decoration: _baseDecoration(),
      onChanged: (value) {
        widget.onChanged(value.trim());
      },
    );
  }

  Widget _buildTextAreaField() {
    return TextFormField(
      controller: _textController,
      maxLines: widget.fieldDef.rows ?? 4,
      maxLength: widget.fieldDef.maxLength,
      decoration: _baseDecoration(),
      onChanged: (value) {
        widget.onChanged(value.trim());
      },
    );
  }

  Widget _buildSelectField() {
    final options = widget.fieldOptions?.getOptionsForField(
          widget.fieldDef.optionsKey,
        ) ??
        widget.fieldDef.options;

    final hasValue = widget.initialValue == null ||
        options.any((o) => o.value == widget.initialValue?.toString());

    return DropdownButtonFormField<String>(
      value: hasValue ? widget.initialValue?.toString() : null,
      items: [
        DropdownMenuItem(
          value: null,
          child: Text(
            widget.fieldDef.placeholder ?? 'Select an option',
            style: const TextStyle(color: AppTheme.textSecondary),
          ),
        ),
        ...options.map((opt) =>
            DropdownMenuItem(value: opt.value, child: Text(opt.label))),
      ],
      onChanged: (value) {
        widget.onChanged(value);
      },
      decoration: _baseDecoration(),
    );
  }

  Widget _buildMultiSelectField() {
    final options = widget.fieldOptions?.getOptionsForField(
          widget.fieldDef.optionsKey,
        ) ??
        widget.fieldDef.options;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (_selectedItems.isEmpty)
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFF8F9FA),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.grey.shade200),
            ),
            child: Row(
              children: [
                Icon(Icons.info_outline, size: 16, color: Colors.grey.shade400),
                const SizedBox(width: 8),
                Text(
                  widget.fieldDef.placeholder ?? 'No selections yet',
                  style: TextStyle(
                    color: Colors.grey.shade500,
                    fontSize: 14,
                  ),
                ),
              ],
            ),
          ),
        if (_selectedItems.isNotEmpty) ...[
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _selectedItems.map((item) {
              return Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: const Color(0xFF00695C).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFF00695C).withOpacity(0.3)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      item,
                      style: const TextStyle(
                        color: Color(0xFF004D40),
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(width: 6),
                    GestureDetector(
                      onTap: () {
                        setState(() => _selectedItems.remove(item));
                        widget.onChanged(List<String>.from(_selectedItems));
                      },
                      child: const Icon(Icons.close, size: 14, color: Color(0xFF00695C)),
                    ),
                  ],
                ),
              );
            }).toList(),
          ),
          const SizedBox(height: 10),
        ],
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: options
              .where((opt) => !_selectedItems.contains(opt.value))
              .map((opt) {
            final isSelected = _selectedItems.contains(opt.value);
            return GestureDetector(
              onTap: () {
                setState(() => _selectedItems.add(opt.value));
                widget.onChanged(List<String>.from(_selectedItems));
              },
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(
                  color: isSelected ? const Color(0xFF00695C) : Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: isSelected ? const Color(0xFF00695C) : Colors.grey.shade300,
                  ),
                ),
                child: Text(
                  opt.label,
                  style: TextStyle(
                    color: isSelected ? Colors.white : const Color(0xFF2D3436),
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            );
          }).toList(),
        ),
      ],
    );
  }

  Widget _buildNumberField() {
    return TextFormField(
      controller: _textController,
      keyboardType: TextInputType.number,
      decoration: _baseDecoration(),
      onChanged: (value) {
        widget.onChanged(int.tryParse(value) ?? 0);
      },
    );
  }

  Widget _buildDateField() {
    final currentValue = _textController.text;
    DateTime? initial = DateTime.tryParse(currentValue);
    return TextFormField(
      controller: _textController,
      readOnly: true,
      decoration: _baseDecoration(
        suffixIcon: const Icon(Icons.calendar_today, color: AppTheme.primaryColor),
      ),
      onTap: () async {
        final date = await showDatePicker(
          context: context,
          initialDate: initial ?? DateTime.now(),
          firstDate: DateTime(1900),
          lastDate: DateTime.now(),
          builder: (context, child) => Theme(
            data: Theme.of(context).copyWith(
              colorScheme: ColorScheme.fromSeed(
                seedColor: AppTheme.primaryColor,
                primary: AppTheme.primaryColor,
              ),
            ),
            child: child!,
          ),
        );
        if (date != null) {
          _textController.text = date.toIso8601String().split('T')[0];
          widget.onChanged(date.toIso8601String());
        }
      },
    );
  }

  Widget _buildCheckboxField() {
    return CheckboxListTile(
      value: widget.initialValue ?? false,
      onChanged: (value) {
        widget.onChanged(value ?? false);
      },
      title: Text(widget.fieldDef.label),
      contentPadding: EdgeInsets.zero,
      controlAffinity: ListTileControlAffinity.leading,
    );
  }

  TextInputType _getKeyboardType() {
    switch (widget.fieldDef.type) {
      case ProfileFieldType.email:
        return TextInputType.emailAddress;
      case ProfileFieldType.phone:
        return TextInputType.phone;
      case ProfileFieldType.number:
        return TextInputType.number;
      default:
        return TextInputType.text;
    }
  }

  IconData _getIconData(String iconName) {
    final iconMap = {
      'person': Icons.person,
      'email': Icons.email,
      'phone': Icons.phone,
      'description': Icons.description,
      'wc': Icons.wc,
      'favorite': Icons.favorite,
      'location_on': Icons.location_on,
      'calendar_today': Icons.calendar_today,
      'check_box': Icons.check_box,
    };
    return iconMap[iconName] ?? Icons.info;
  }
}
