import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'auth/auth_controller.dart';
import 'screens/admin_hub_screen.dart';
import 'screens/admin_payment_methods_screen.dart';
import 'screens/admin_payments_screen.dart';
import 'screens/admin_plans_screen.dart';
import 'screens/admin_shared_keys_screen.dart';
import 'screens/admin_subscriptions_screen.dart';
import 'screens/admin_tickets_screen.dart';
import 'screens/admin_users_screen.dart';
import 'screens/ai_assistant_screen.dart';
import 'screens/audit_logs_screen.dart';
import 'screens/backup_screen.dart';
import 'screens/calendar_screen.dart';
import 'screens/client_detail_screen.dart';
import 'screens/client_form_screen.dart';
import 'screens/clients_screen.dart';
import 'screens/custom_instruction_screen.dart';
import 'screens/dashboard_screen.dart';
import 'screens/finance_screen.dart';
import 'screens/habit_detail_screen.dart';
import 'screens/habit_form_screen.dart';
import 'screens/habits_screen.dart';
import 'screens/inventory_form_screen.dart';
import 'screens/inventory_item_screen.dart';
import 'screens/inventory_screen.dart';
import 'screens/invoice_form_screen.dart';
import 'screens/invoices_screen.dart';
import 'screens/kb_file_screen.dart';
import 'screens/knowledge_base_screen.dart';
import 'screens/login_screen.dart';
import 'screens/meeting_form_screen.dart';
import 'screens/menu_hub_screen.dart';
import 'screens/model_settings_screen.dart';
import 'screens/note_form_screen.dart';
import 'screens/notes_screen.dart';
import 'screens/payment_method_form_screen.dart';
import 'screens/plan_form_screen.dart';
import 'screens/pomodoro_screen.dart';
import 'screens/project_detail_screen.dart';
import 'screens/project_form_screen.dart';
import 'screens/projects_screen.dart';
import 'screens/settings_screen.dart';
import 'screens/task_detail_screen.dart';
import 'screens/task_form_screen.dart';
import 'screens/tasks_screen.dart';
import 'screens/ticket_thread_screen.dart';
import 'screens/transaction_form_screen.dart';
import 'screens/water_plan_form_screen.dart';
import 'screens/water_plan_history_screen.dart';
import 'screens/water_plan_screen.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _AuthRefresh(ref);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: refresh,
    redirect: (context, state) {
      final auth = ref.read(authStateProvider);
      // While auth is resolving at cold start, don't bounce anywhere.
      if (auth.isLoading) return null;
      final loggedIn = auth.asData?.value ?? false;
      final onLogin = state.matchedLocation == '/login';
      if (!loggedIn) return onLogin ? null : '/login';
      if (onLogin) return '/';

      // Admin route guard: only admins may enter /admin/*
      final loc = state.matchedLocation;
      if (loc.startsWith('/admin') && !ref.read(isAdminProvider)) {
        return '/menu';
      }
      return null;
    },
    errorBuilder: (_, state) => _NotFoundScreen(uri: state.uri.toString()),
    routes: [
      // Auth
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),

      // Primary tabs
      GoRoute(path: '/', builder: (_, __) => const DashboardScreen()),
      GoRoute(path: '/tasks', builder: (_, __) => const TasksScreen()),
      GoRoute(path: '/habits', builder: (_, __) => const HabitsScreen()),
      GoRoute(path: '/menu', builder: (_, __) => const MenuHubScreen()),
      GoRoute(path: '/settings', builder: (_, __) => const SettingsScreen()),

      // Tasks (CRUD + detail)
      GoRoute(path: '/tasks/new', builder: (_, __) => const TaskFormScreen()),
      GoRoute(
          path: '/tasks/:id',
          builder: (_, state) =>
              TaskDetailScreen(taskId: state.pathParameters['id']!)),
      GoRoute(
          path: '/tasks/:id/edit',
          builder: (_, state) =>
              TaskFormScreen(taskId: state.pathParameters['id']!)),

      // Habits (CRUD + detail)
      GoRoute(path: '/habits/new', builder: (_, __) => const HabitFormScreen()),
      GoRoute(
          path: '/habits/:id',
          builder: (_, state) =>
              HabitDetailScreen(habitId: state.pathParameters['id']!)),
      GoRoute(
          path: '/habits/:id/edit',
          builder: (_, state) =>
              HabitFormScreen(habitId: state.pathParameters['id']!)),

      // Projects
      GoRoute(path: '/projects', builder: (_, __) => const ProjectsScreen()),
      GoRoute(
          path: '/projects/new', builder: (_, __) => const ProjectFormScreen()),
      GoRoute(
          path: '/projects/:id',
          builder: (_, state) =>
              ProjectDetailScreen(projectId: state.pathParameters['id']!)),
      GoRoute(
          path: '/projects/:id/edit',
          builder: (_, state) =>
              ProjectFormScreen(projectId: state.pathParameters['id']!)),

      // Clients
      GoRoute(path: '/clients', builder: (_, __) => const ClientsScreen()),
      GoRoute(
          path: '/clients/new', builder: (_, __) => const ClientFormScreen()),
      GoRoute(
          path: '/clients/:id',
          builder: (_, state) =>
              ClientDetailScreen(clientId: state.pathParameters['id']!)),
      GoRoute(
          path: '/clients/:id/edit',
          builder: (_, state) =>
              ClientFormScreen(clientId: state.pathParameters['id']!)),

      // Invoices
      GoRoute(path: '/invoices', builder: (_, __) => const InvoicesScreen()),
      GoRoute(
          path: '/invoices/new', builder: (_, __) => const InvoiceFormScreen()),
      GoRoute(
          path: '/invoices/:id',
          builder: (_, state) =>
              InvoiceFormScreen(invoiceId: state.pathParameters['id']!)),

      // Finance
      GoRoute(path: '/finance', builder: (_, __) => const FinanceScreen()),
      GoRoute(
          path: '/finance/new',
          builder: (_, __) => const TransactionFormScreen()),
      GoRoute(
          path: '/finance/:id',
          builder: (_, state) => TransactionFormScreen(
              transactionId: state.pathParameters['id']!)),

      // Inventory
      GoRoute(
          path: '/inventory', builder: (_, __) => const InventoryScreen()),
      GoRoute(
          path: '/inventory/new',
          builder: (_, __) => const InventoryFormScreen()),
      GoRoute(
          path: '/inventory/:id',
          builder: (_, state) =>
              InventoryItemScreen(itemId: state.pathParameters['id']!)),
      GoRoute(
          path: '/inventory/:id/edit',
          builder: (_, state) =>
              InventoryFormScreen(itemId: state.pathParameters['id']!)),

      // Notes
      GoRoute(path: '/notes', builder: (_, __) => const NotesScreen()),
      GoRoute(path: '/notes/new', builder: (_, __) => const NoteFormScreen()),
      GoRoute(
          path: '/notes/:id',
          builder: (_, state) =>
              NoteFormScreen(noteId: state.pathParameters['id']!)),

      // Knowledge Base
      GoRoute(
          path: '/knowledge-base',
          builder: (_, __) => const KnowledgeBaseScreen()),
      GoRoute(
          path: '/knowledge-base/folder/:folderId',
          builder: (_, state) => KbFileScreen(
              folderId: state.pathParameters['folderId']!)),
      GoRoute(
          path: '/knowledge-base/folder/:folderId/file/:fileId',
          builder: (_, state) => KbFileScreen(
              folderId: state.pathParameters['folderId']!,
              fileId: state.pathParameters['fileId']!)),

      // AI Assistant + config
      GoRoute(
          path: '/ai-assistant',
          builder: (_, __) => const AiAssistantScreen()),
      GoRoute(
          path: '/custom-instruction',
          builder: (_, __) => const CustomInstructionScreen()),
      GoRoute(
          path: '/model-settings',
          builder: (_, __) => const ModelSettingsScreen()),

      // Calendar + Meetings
      GoRoute(path: '/calendar', builder: (_, __) => const CalendarScreen()),
      GoRoute(
          path: '/meetings/new', builder: (_, __) => const MeetingFormScreen()),
      GoRoute(
          path: '/meetings/:id/edit',
          builder: (_, state) => MeetingFormScreen(
              meetingId: state.pathParameters['id']!)),

      // Pomodoro
      GoRoute(path: '/pomodoro', builder: (_, __) => const PomodoroScreen()),

      // Water Plan
      GoRoute(path: '/water', builder: (_, __) => const WaterPlanScreen()),
      GoRoute(
          path: '/water/new',
          builder: (_, __) => const WaterPlanFormScreen()),
      GoRoute(
          path: '/water/ai',
          builder: (_, __) => const WaterPlanFormScreen(aiMode: true)),
      GoRoute(
          path: '/water/edit/:id',
          builder: (_, state) => WaterPlanFormScreen(
              planId: state.pathParameters['id']!)),
      GoRoute(
          path: '/water/history',
          builder: (_, __) => const WaterPlanHistoryScreen()),

      // Backup / Data Management
      GoRoute(path: '/backup', builder: (_, __) => const BackupScreen()),

      // Admin (guarded by isAdminProvider in the redirect above)
      GoRoute(path: '/admin', builder: (_, __) => const AdminHubScreen()),
      GoRoute(
          path: '/admin/users', builder: (_, __) => const AdminUsersScreen()),
      GoRoute(
          path: '/admin/plans', builder: (_, __) => const AdminPlansScreen()),
      GoRoute(
          path: '/admin/plans/new', builder: (_, __) => const PlanFormScreen()),
      GoRoute(
          path: '/admin/plans/:id/edit',
          builder: (_, state) =>
              PlanFormScreen(planId: state.pathParameters['id']!)),
      GoRoute(
          path: '/admin/payment-methods',
          builder: (_, __) => const AdminPaymentMethodsScreen()),
      GoRoute(
          path: '/admin/payment-methods/new',
          builder: (_, __) => const PaymentMethodFormScreen()),
      GoRoute(
          path: '/admin/payment-methods/:id/edit',
          builder: (_, state) => PaymentMethodFormScreen(
              methodId: state.pathParameters['id']!)),
      GoRoute(
          path: '/admin/payments',
          builder: (_, __) => const AdminPaymentsScreen()),
      GoRoute(
          path: '/admin/subscriptions',
          builder: (_, __) => const AdminSubscriptionsScreen()),
      GoRoute(
          path: '/admin/shared-keys',
          builder: (_, __) => const AdminSharedKeysScreen()),
      GoRoute(
          path: '/admin/tickets',
          builder: (_, __) => const AdminTicketsScreen()),
      GoRoute(
          path: '/admin/tickets/:id',
          builder: (_, state) =>
              TicketThreadScreen(ticketId: state.pathParameters['id']!)),
      GoRoute(
          path: '/admin/audit',
          builder: (_, __) => const AuditLogsScreen()),
    ],
  );
});

/// Bridges the Riverpod auth state to a Listenable so GoRouter re-runs its
/// redirect whenever the user logs in or out.
class _AuthRefresh extends ChangeNotifier {
  _AuthRefresh(Ref ref) {
    ref.listen(authStateProvider, (_, __) => notifyListeners());
    ref.listen(userRoleProvider, (_, __) => notifyListeners());
  }
}

/// Simple 404 fallback.
class _NotFoundScreen extends StatelessWidget {
  const _NotFoundScreen({required this.uri});
  final String uri;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('404',
                  style: TextStyle(
                      fontSize: 48,
                      fontWeight: FontWeight.w900,
                      color: Theme.of(context).colorScheme.onSurface)),
              const SizedBox(height: 8),
              Text('Page not found',
                  style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: Theme.of(context).colorScheme.onSurface)),
              const SizedBox(height: 4),
              Text(uri,
                  style: TextStyle(
                      fontSize: 13,
                      color: Theme.of(context)
                          .colorScheme
                          .onSurface
                          .withValues(alpha: 0.5))),
            ],
          ),
        ),
      ),
    );
  }
}
