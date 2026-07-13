import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../repositories/clients_repository.dart';
import '../theme/app_tokens.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Create or edit a client. Mirrors mobile/views/client-form.php.
class ClientFormScreen extends ConsumerStatefulWidget {
  const ClientFormScreen({super.key, this.clientId});

  final String? clientId;
  bool get isEdit => clientId != null;

  @override
  ConsumerState<ClientFormScreen> createState() => _ClientFormScreenState();
}

class _ClientFormScreenState extends ConsumerState<ClientFormScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _company = TextEditingController();
  final _website = TextEditingController();
  final _address = TextEditingController();
  final _notes = TextEditingController();
  bool _loading = false;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    if (widget.isEdit) _loadForEdit();
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _company.dispose();
    _website.dispose();
    _address.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final c = await ref.read(clientsRepositoryProvider).fetch(widget.clientId!);
      if (c != null && mounted) {
        _name.text = c.name;
        _email.text = c.email;
        _phone.text = c.phone;
        _company.text = c.company;
        _website.text = c.website;
        _address.text = c.address;
        _notes.text = c.notes;
      }
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    final name = _name.text.trim();
    final email = _email.text.trim();
    if (name.isEmpty || email.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Name and email are required'),
          behavior: SnackBarBehavior.floating));
      return;
    }

    setState(() => _saving = true);
    final repo = ref.read(clientsRepositoryProvider);
    try {
      if (widget.isEdit) {
        await repo.update(
          widget.clientId!,
          name: name,
          email: email,
          phone: _phone.text.trim(),
          company: _company.text.trim(),
          website: _website.text.trim(),
          address: _address.text.trim(),
          notes: _notes.text.trim(),
        );
      } else {
        await repo.create(
          name: name,
          email: email,
          phone: _phone.text.trim(),
          company: _company.text.trim(),
          website: _website.text.trim(),
          address: _address.text.trim(),
          notes: _notes.text.trim(),
        );
      }
      ref.invalidate(clientsProvider);
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: Text(widget.isEdit ? 'Edit Client' : 'New Client'),
      ),
      body: SafeArea(
        top: false,
        bottom: false,
        child: Column(
          children: [
            Expanded(
              child: _loading
                  ? _FormSkeleton()
                  : FadeIn(
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(
                            AppSpacing.page, 8, AppSpacing.page, 24),
                        children: [
                          LabeledField(
                            label: 'Client / Company Name',
                            controller: _name,
                            hint: 'Acme Corp',
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 20),
                          LabeledField(
                            label: 'Email Address',
                            controller: _email,
                            hint: 'contact@company.com',
                            keyboardType: TextInputType.emailAddress,
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 20),
                          LabeledField(
                            label: 'Phone Number',
                            controller: _phone,
                            hint: '+1 (555) 000-0000',
                            keyboardType: TextInputType.phone,
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 20),
                          LabeledField(
                            label: 'Company',
                            controller: _company,
                            hint: 'Acme Corporation Ltd.',
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 20),
                          LabeledField(
                            label: 'Website',
                            controller: _website,
                            hint: 'https://example.com',
                            keyboardType: TextInputType.url,
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 20),
                          LabeledField(
                            label: 'Business Address',
                            controller: _address,
                            hint: 'Street, City, Postcode',
                            maxLines: 3,
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 20),
                          LabeledField(
                            label: 'Notes',
                            controller: _notes,
                            hint: 'Client brief, communication notes…',
                            maxLines: 4,
                          ),
                        ],
                      ),
                    ),
            ),
            StickySaveBar(
              onSave: _save,
              onCancel: () => context.pop(),
              saving: _saving,
              saveLabel: widget.isEdit ? 'Save' : 'Create',
            ),
          ],
        ),
      ),
    );
  }
}

/// Skeleton placeholder shaped like the form while an edit record loads
/// (DESIGN.md: use Skeleton, not CircularProgressIndicator).
class _FormSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(AppSpacing.page, 24, AppSpacing.page, 24),
      children: const [
        _FieldSkeleton(),
        SizedBox(height: 20),
        _FieldSkeleton(),
        SizedBox(height: 20),
        _FieldSkeleton(),
        SizedBox(height: 20),
        _FieldSkeleton(),
        SizedBox(height: 20),
        _FieldSkeleton(),
        SizedBox(height: 20),
        _FieldSkeleton(height: 90),
        SizedBox(height: 20),
        _FieldSkeleton(height: 110),
      ],
    );
  }
}

class _FieldSkeleton extends StatelessWidget {
  const _FieldSkeleton({this.height = 48});
  final double height;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Skeleton(height: 12, width: 120),
        const SizedBox(height: 10),
        Skeleton(height: height, radius: AppRadii.button),
      ],
    );
  }
}
