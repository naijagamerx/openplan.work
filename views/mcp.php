<?php
$currentUser = Auth::user();
$apiUrl = APP_URL . '/api';
$mcpScriptPath = str_replace('\\', '/', ROOT_PATH) . '/mcp-server/index.js';
$csrfToken = Auth::csrfToken();
?>
<div class="p-6">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">OpenPlan.work - MCP Guide</h1>
        <p class="text-gray-600 mt-1">Connect Claude Code and other AI agents to your OpenPlan workspace</p>
    </div>

    <div class="mb-8 bg-blue-50 border border-blue-200 rounded-xl p-5">
        <h2 class="text-base font-semibold text-blue-900 mb-2">🔐 Token-Based Authentication</h2>
        <p class="text-sm text-blue-800">Each user has their own secure MCP API token. Generate your unique token below - no passwords needed!</p>
    </div>

    <!-- How It Works Section -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">How MCP Works</h2>

        <!-- Architecture Diagram -->
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <div class="flex flex-col md:flex-row items-center justify-center gap-8">
                <!-- Claude Code -->
                <div class="text-center">
                    <div class="w-20 h-20 bg-amber-100 rounded-2xl flex items-center justify-center mx-auto mb-2">
                        <span class="text-3xl">🤖</span>
                    </div>
                    <p class="font-semibold text-gray-900">Claude Code</p>
                    <p class="text-xs text-gray-500">Your AI Assistant</p>
                </div>

                <!-- Arrow -->
                <div class="text-gray-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </div>

                <!-- MCP Server -->
                <div class="text-center">
                    <div class="w-20 h-20 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-2">
                        <span class="text-3xl">🔌</span>
                    </div>
                    <p class="font-semibold text-gray-900">MCP Server</p>
                    <p class="text-xs text-gray-500">openplan-work</p>
                </div>

                <!-- Arrow -->
                <div class="text-gray-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </div>

                <!-- OpenPlan -->
                <div class="text-center">
                    <div class="w-20 h-20 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-2">
                        <span class="text-3xl">📋</span>
                    </div>
                    <p class="font-semibold text-gray-900">OpenPlan.work</p>
                    <p class="text-xs text-gray-500">Your Data</p>
                </div>
            </div>
        </div>

        <!-- Key Points -->
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-gray-50 rounded-xl p-5">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-1">Auto-Started by Claude Code</h3>
                        <p class="text-sm text-gray-600">Claude Code automatically starts the MCP server when it detects your mcp.json configuration.</p>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 rounded-xl p-5">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-1">Stdio Communication</h3>
                        <p class="text-sm text-gray-600">MCP uses standard input/output - not HTTP. The server reads JSON commands from stdin.</p>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 rounded-xl p-5">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-1">Secure Token Auth</h3>
                        <p class="text-sm text-gray-600">Uses your unique MCP API token via <code class="bg-gray-100 px-1 rounded">X-MCP-Api-Token</code> header. No passwords exposed.</p>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 rounded-xl p-5">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-1">Encrypted Data Storage</h3>
                        <p class="text-sm text-gray-600">All data encrypted with AES-256-GCM. Your data stays private and secure.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MCP API Token Management -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Your MCP API Token</h2>

        <div id="tokenSection" class="bg-white border border-gray-200 rounded-xl p-6">
            <div class="flex items-center justify-center py-8">
                <svg class="animate-spin h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="ml-3 text-gray-600">Loading token...</span>
            </div>
        </div>

        <p class="text-xs text-gray-500 mt-3">
            💡 <strong>Security:</strong> Your token is unique to your account. Keep it secret - anyone with your token can access your data. Revoke anytime.
        </p>
    </div>

    <!-- Setup Instructions -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Setup Instructions</h2>

        <div class="space-y-4">
            <!-- Step 1 -->
            <div class="flex gap-4">
                <div class="w-8 h-8 bg-black text-white rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">1</div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 mb-1">Generate Your API Token</h3>
                    <p class="text-sm text-gray-600 mb-3">Click the "Generate API Token" button above. Your token will appear and automatically populate the configuration below.</p>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="flex gap-4">
                <div class="w-8 h-8 bg-black text-white rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">2</div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 mb-1">Create MCP Config File</h3>
                    <p class="text-sm text-gray-600 mb-3">Create <code class="bg-gray-100 px-2 py-0.5 rounded text-sm">mcp.json</code> in <code class="bg-gray-100 px-1 rounded">~/.claude/</code> and copy the pre-filled configuration below.</p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="flex gap-4">
                <div class="w-8 h-8 bg-black text-white rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">3</div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 mb-1">Restart Claude Code</h3>
                    <p class="text-sm text-gray-600">Restart Claude Code so it detects the MCP server and loads the tools.</p>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="flex gap-4">
                <div class="w-8 h-8 bg-black text-white rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">4</div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 mb-1">Test Connection</h3>
                    <p class="text-sm text-gray-600 mb-3">Ask Claude: "Test the OpenPlan connection" or "What tools are available?"</p>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <p class="text-sm text-gray-700"><strong>You:</strong> "Test the OpenPlan connection"</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ready-to-Use Configuration -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Your Configuration (Token Pre-Filled)</h2>
        <div class="bg-gray-900 text-green-400 p-4 rounded-xl overflow-x-auto text-sm font-mono">
            <pre id="mcpConfig">{
  "mcpServers": {
    "openplan-work": {
      "command": "node",
      "args": ["<?php echo $mcpScriptPath; ?>"],
      "env": {
        "API_URL": "<?php echo $apiUrl; ?>",
        "MCP_API_TOKEN": "YOUR_TOKEN_HERE",
        "LAZYMAN_MASTER_PASSWORD": "YOUR_MASTER_PASSWORD"
      }
    }
  }
}</pre>
        </div>

        <div class="mt-4 flex gap-3">
            <button onclick="copyConfig()" class="px-5 py-2.5 bg-black text-white rounded-lg font-bold hover:bg-gray-800 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
                <span id="copyBtnText">Copy Configuration</span>
            </button>
        </div>

        <div class="mt-3 p-4 bg-green-50 border border-green-200 rounded-lg">
            <h4 class="font-semibold text-green-800 mb-2">✅ Configuration Notes</h4>
            <ul class="text-sm text-green-700 space-y-1">
                <li>• <strong>Auto-filled:</strong> Generate your token above - this config will automatically include it</li>
                <li>• <strong>Master Password:</strong> Replace <code class="bg-green-100 px-1">YOUR_MASTER_PASSWORD</code> with your actual login password</li>
                <li>• <strong>API URL:</strong> Change only if OpenPlan runs on different domain/port</li>
                <li>• <strong>Windows paths:</strong> Use forward slashes: <code class="bg-green-100 px-1">C:/MAMP/htdocs/...</code></li>
            </ul>
        </div>

        <div class="mt-3 p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <h4 class="font-semibold text-amber-800 mb-2">⚠️ Important: Replace Your Master Password</h4>
            <p class="text-sm text-amber-700">Replace <code class="bg-amber-100 px-1">YOUR_MASTER_PASSWORD</code> above with your actual OpenPlan login password. This is required to decrypt your encrypted data files.</p>
        </div>
    </div>

    <!-- Available Tools -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Available MCP Tools (70+)</h2>
        <p class="text-sm text-gray-600 mb-4">The MCP server exposes all OpenPlan features as tools. Here's what you can do:</p>

        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            <!-- Tasks & Projects -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">📋</span>
                    <h3 class="font-semibold text-gray-800">Tasks & Projects</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_projects</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_project</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">get_project</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">update_project</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">delete_project</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">list_tasks</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_task</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">update_task</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">delete_task</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">complete_task</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_subtask</code></li>
                </ul>
            </div>

            <!-- Todos -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">✅</span>
                    <h3 class="font-semibold text-gray-800">Todos</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_todos</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_todo</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">update_todo</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">complete_todo</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">delete_todo</code></li>
                </ul>
            </div>

            <!-- Clients -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">👥</span>
                    <h3 class="font-semibold text-gray-800">Clients (CRM)</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_clients</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">get_client</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_client</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">update_client</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">delete_client</code></li>
                </ul>
            </div>

            <!-- Invoices -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">📄</span>
                    <h3 class="font-semibold text-gray-800">Invoices</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_invoices</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">create_invoice</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">update_invoice_status</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">create_advanced_invoice</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">get_advanced_invoice</code></li>
                </ul>
            </div>

            <!-- Finance -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">💰</span>
                    <h3 class="font-semibold text-gray-800">Finance</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_transactions</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_transaction</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">get_finance_summary</code></li>
                </ul>
            </div>

            <!-- Inventory -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">📦</span>
                    <h3 class="font-semibold text-gray-800">Inventory</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_inventory</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_inventory_item</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">update_inventory_stock</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">adjust_inventory_stock</code></li>
                </ul>
            </div>

            <!-- Health & Habits -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">💧</span>
                    <h3 class="font-semibold text-gray-800">Health & Habits</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">get_water_status</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">log_water</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">set_water_goal</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">get_water_history</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">generate_water_plan</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">list_habits</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_habit</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">complete_habit</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">delete_habit</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">get_habit_stats</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">start_habit_timer</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">stop_habit_timer</code></li>
                </ul>
            </div>

            <!-- Notes -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">📝</span>
                    <h3 class="font-semibold text-gray-800">Notes</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_notes</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">create_note</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">update_note</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">ai_edit_note</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">search_notes</code></li>
                </ul>
            </div>

            <!-- Knowledge Base -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">📚</span>
                    <h3 class="font-semibold text-gray-800">Knowledge Base</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_kb_folders</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">upload_kb_file</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">search_knowledge_base</code></li>
                </ul>
            </div>

            <!-- System -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">⚙️</span>
                    <h3 class="font-semibold text-gray-800">System</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">test_connection</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">get_system_status</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">search_all</code></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Usage Examples -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Usage Examples</h2>

        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-5">
                <h3 class="font-semibold text-blue-900 mb-3">📝 Task Management</h3>
                <div class="bg-white rounded-lg p-4 border border-blue-200 space-y-2">
                    <p class="text-sm text-gray-700"><strong>You:</strong> "Create a project 'Website Redesign' for client TechStart Inc"</p>
                    <p class="text-sm text-green-600"><strong>Claude:</strong> "I've created the project 'Website Redesign' linked to TechStart Inc."</p>
                    <p class="text-sm text-gray-700"><strong>You:</strong> "Add a task 'Design homepage' with high priority"</p>
                    <p class="text-sm text-green-600"><strong>Claude:</strong> "Added the task with high priority."</p>
                </div>
            </div>

            <div class="bg-green-50 border border-green-200 rounded-xl p-5">
                <h3 class="font-semibold text-green-900 mb-3">💵 Invoicing</h3>
                <div class="bg-white rounded-lg p-4 border border-green-200">
                    <p class="text-sm text-gray-700"><strong>You:</strong> "Create an invoice for Global Corp - 20 hours consulting at $150/hour"</p>
                    <p class="text-sm text-green-600"><strong>Claude:</strong> "Invoice created for $3,000 (plus tax). Due: February 15th."</p>
                </div>
            </div>

            <div class="bg-purple-50 border border-purple-200 rounded-xl p-5">
                <h3 class="font-semibold text-purple-900 mb-3">📊 Finance Tracking</h3>
                <div class="bg-white rounded-lg p-4 border border-purple-200">
                    <p class="text-sm text-gray-700"><strong>You:</strong> "Add expense: $250 for office supplies from Amazon"</p>
                    <p class="text-sm text-green-600"><strong>Claude:</strong> "Expense of $250 for office supplies recorded."</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Troubleshooting -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Troubleshooting</h2>

        <div class="space-y-3">
            <div class="bg-gray-50 rounded-xl p-4">
                <h3 class="font-semibold text-gray-900 mb-2">🔴 MCP Server Not Starting</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Verify Node.js: <code class="bg-gray-200 px-1 rounded">node --version</code></li>
                    <li>• Check the path to <code class="bg-gray-200 px-1 rounded">mcp-server/index.js</code></li>
                    <li>• Ensure OpenPlan is accessible via browser</li>
                </ul>
            </div>

            <div class="bg-gray-50 rounded-xl p-4">
                <h3 class="font-semibold text-gray-900 mb-2">🔴 Authentication Failed</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Verify your MCP API token is current (generate a new one if needed)</li>
                    <li>• Ensure OpenPlan is running (MAMP/XAMPP/etc.)</li>
                    <li>• Check the API URL matches your OpenPlan location</li>
                </ul>
            </div>

            <div class="bg-gray-50 rounded-xl p-4">
                <h3 class="font-semibold text-gray-900 mb-2">🔴 Tools Not Available</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Restart Claude Code after adding mcp.json</li>
                    <li>• Verify mcp.json is in correct location (<code class="bg-gray-200 px-1 rounded">~/.claude/</code>)</li>
                    <li>• Check Claude Code logs for MCP errors</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Server Location -->
    <div class="bg-gray-900 text-white rounded-xl p-6">
        <h3 class="font-semibold mb-3">📂 MCP Server Location</h3>
        <p class="text-gray-400 text-sm mb-3">The MCP server is located at:</p>
        <code class="bg-gray-800 px-4 py-2 rounded-lg block text-green-400 font-mono text-sm">
            <?php echo $mcpScriptPath; ?>
        </code>
    </div>
</div>

<script>
// Load MCP API token on page load
async function loadMcpToken() {
    try {
        const response = await fetch('<?= APP_URL ?>/api/mcp.php?action=get_token');
        const data = await response.json();

        if (data.success) {
            renderTokenSection(data.data);
        } else {
            renderTokenSection({ hasToken: false });
        }
    } catch (error) {
        console.error('Failed to load token:', error);
        renderTokenSection({ hasToken: false, error: true });
    }
}

function renderTokenSection(tokenData) {
    const container = document.getElementById('tokenSection');

    if (tokenData.hasToken) {
        container.innerHTML = `
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-semibold text-gray-900">Active MCP API Token</span>
                    </div>
                    <span class="text-xs text-gray-500">Generated: ${tokenData.generatedAt ? new Date(tokenData.generatedAt).toLocaleDateString() : 'Recently'}</span>
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-center justify-between gap-4">
                        <code class="text-sm font-mono text-gray-800 break-all" id="mcpTokenDisplay">${tokenData.token}</code>
                        <button onclick="copyToken()" class="flex-shrink-0 px-3 py-1.5 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm font-medium transition" title="Copy token">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex gap-3 flex-wrap">
                    <button onclick="copyToken()" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                        </svg>
                        Copy Token
                    </button>
                    <button onclick="regenerateToken()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition">
                        Regenerate
                    </button>
                    <button onclick="revokeToken()" class="px-4 py-2 bg-red-100 text-red-700 rounded-lg font-medium hover:bg-red-200 transition">
                        Revoke
                    </button>
                </div>

                <p class="text-xs text-gray-500">
                    ⚠️ <strong>Important:</strong> Regenerating invalidates your current token. Update your mcp.json after regenerating.
                </p>
            </div>
        `;

        // Update the config display with the actual token
        updateConfigDisplay(tokenData.token);
    } else {
        container.innerHTML = `
            <div class="text-center py-6">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No MCP API Token</h3>
                <p class="text-gray-600 mb-6">Generate your unique API token to connect Claude Code with OpenPlan.</p>
                <button onclick="generateToken()" class="px-6 py-3 bg-black text-white rounded-lg font-bold hover:bg-gray-800 transition">
                    Generate API Token
                </button>
            </div>
        `;
    }
}

async function generateToken() {
    try {
        const response = await fetch('<?= APP_URL ?>/api/mcp.php?action=generate_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: '<?= $csrfToken ?>'
            })
        });
        const data = await response.json();

        if (data.success) {
            showToast('Token generated successfully!', 'success');
            loadMcpToken();
        } else {
            showToast(data.message || 'Failed to generate token', 'error');
        }
    } catch (error) {
        console.error('Token generation error:', error);
        showToast('Failed to generate token', 'error');
    }
}

async function regenerateToken() {
    if (!confirm('Regenerating will invalidate your current token. Continue?')) {
        return;
    }

    try {
        const response = await fetch('<?= APP_URL ?>/api/mcp.php?action=generate_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: '<?= $csrfToken ?>'
            })
        });
        const data = await response.json();

        if (data.success) {
            showToast('Token regenerated successfully!', 'success');
            loadMcpToken();
        } else {
            showToast(data.message || 'Failed to regenerate token', 'error');
        }
    } catch (error) {
        console.error('Token regeneration error:', error);
        showToast('Failed to regenerate token', 'error');
    }
}

async function revokeToken() {
    if (!confirm('This will immediately revoke your token. MCP access will stop working. Continue?')) {
        return;
    }

    try {
        const response = await fetch('<?= APP_URL ?>/api/mcp.php?action=revoke_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: '<?= $csrfToken ?>'
            })
        });
        const data = await response.json();

        if (data.success) {
            showToast('Token revoked successfully', 'success');
            loadMcpToken();
        } else {
            showToast(data.message || 'Failed to revoke token', 'error');
        }
    } catch (error) {
        console.error('Token revocation error:', error);
        showToast('Failed to revoke token', 'error');
    }
}

function copyToken() {
    const token = document.getElementById('mcpTokenDisplay').textContent;
    navigator.clipboard.writeText(token).then(() => {
        showToast('Token copied to clipboard!', 'success');
    }).catch(() => {
        showToast('Failed to copy token', 'error');
    });
}

function updateConfigDisplay(token) {
    const configElement = document.getElementById('mcpConfig');
    if (configElement && token) {
        configElement.textContent = `{
  "mcpServers": {
    "openplan-work": {
      "command": "node",
      "args": ["<?= str_replace('\\', '/', ROOT_PATH) ?>/mcp-server/index.js"],
      "env": {
        "API_URL": "<?= $apiUrl ?>",
        "MCP_API_TOKEN": "${token}",
        "LAZYMAN_MASTER_PASSWORD": "YOUR_MASTER_PASSWORD"
      }
    }
  }
}`;
    }
}

function copyConfig() {
    const config = document.getElementById('mcpConfig').textContent;
    navigator.clipboard.writeText(config).then(() => {
        const btnText = document.getElementById('copyBtnText');
        if (btnText) {
            btnText.textContent = 'Copied!';
            setTimeout(() => {
                btnText.textContent = 'Copy Configuration';
            }, 2000);
        }
        showToast('Configuration copied!', 'success');
    }).catch(err => {
        showToast('Failed to copy configuration', 'error');
    });
}

// Load token on page load
document.addEventListener('DOMContentLoaded', loadMcpToken);
</script>
