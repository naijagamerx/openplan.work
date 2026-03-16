<?php
$currentUser = Auth::user();
$currentUserEmail = $currentUser['email'] ?? 'user@example.com';
$apiUrl = APP_URL . '/api';
$mcpScriptPath = str_replace('\\', '/', ROOT_PATH) . '/mcp-server/index.js';
?>
<div class="p-6">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">MCP (Model Context Protocol) Guide</h1>
        <p class="text-gray-600 mt-1">Complete guide to connecting Claude Code and other coding agents with your Task Manager</p>
    </div>

    <div class="mb-8 bg-yellow-50 border border-yellow-200 rounded-xl p-5">
        <h2 class="text-base font-semibold text-yellow-900 mb-2">Required Credentials</h2>
        <p class="text-sm text-yellow-800">MCP requires your email and master password. The MCP server sends <code class="bg-yellow-100 px-1 rounded">X-MCP-User-Email</code> and <code class="bg-yellow-100 px-1 rounded">X-Master-Password</code> on every API request.</p>
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
                    <p class="text-xs text-gray-500">Node.js Process</p>
                </div>

                <!-- Arrow -->
                <div class="text-gray-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </div>

                <!-- Task Manager -->
                <div class="text-center">
                    <div class="w-20 h-20 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-2">
                        <span class="text-3xl">📁</span>
                    </div>
                    <p class="font-semibold text-gray-900">Task Manager</p>
                    <p class="text-xs text-gray-500">PHP + Encrypted JSON</p>
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
                        <h3 class="font-semibold text-gray-900 mb-1">Auto-Started by Your Coding Agent</h3>
                        <p class="text-sm text-gray-600">You don't need to run the MCP server manually. Claude Code and other MCP-compatible coding agents can automatically spawn the Node.js process when they detect the configuration.</p>
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
                        <p class="text-sm text-gray-600">MCP uses standard input/output (stdio) - not HTTP. The server reads JSON commands from stdin and writes responses to stdout.</p>
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
                        <h3 class="font-semibold text-gray-900 mb-1">Secure Authentication</h3>
                        <p class="text-sm text-gray-600">The MCP server authenticates using your email and master password via <code class="bg-gray-100 px-1 rounded">X-MCP-User-Email</code> and <code class="bg-gray-100 px-1 rounded">X-Master-Password</code>. No session cookies needed.</p>
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
                        <p class="text-sm text-gray-600">All data passes through your existing API with AES-256-GCM encryption. Your data stays encrypted at rest.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Setup Instructions -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Setup Instructions</h2>

        <div class="space-y-4">
            <!-- Step 1 -->
            <div class="flex gap-4">
                <div class="w-8 h-8 bg-black text-white rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">1</div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 mb-1">Copy the MCP Configuration</h3>
                    <p class="text-sm text-gray-600 mb-3">Click the button below to copy the JSON configuration for Claude Code and other MCP-compatible coding agents.</p>
                    <button onclick="copyConfig()" class="flex items-center gap-2 px-4 py-2 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        <span id="copyBtnText">Copy Configuration</span>
                    </button>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="flex gap-4">
                <div class="w-8 h-8 bg-black text-white rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">2</div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 mb-1">Create MCP Config File</h3>
                    <p class="text-sm text-gray-600 mb-3">Create a file named <code class="bg-gray-100 px-2 py-0.5 rounded text-sm">mcp.json</code> in your coding agent settings folder or project root:</p>
                    <div class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm font-mono">
                        <pre id="mcpConfigCode">{
  "mcpServers": {
    "lazyman-taskmanager": {
      "command": "node",
      "args": ["<?php echo $mcpScriptPath; ?>"],
      "env": {
        "API_URL": "<?php echo $apiUrl; ?>",
        "USER_EMAIL": "<?php echo $currentUserEmail; ?>",
        "MASTER_PASSWORD": "YOUR_SECRET_KEY"
      }
    }
  }
}</pre>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">💡 Tip: Store both <code class="bg-gray-100 px-1 rounded">USER_EMAIL</code> and <code class="bg-gray-100 px-1 rounded">MASTER_PASSWORD</code> in secure environment variables.</p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="flex gap-4">
                <div class="w-8 h-8 bg-black text-white rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">3</div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 mb-1">Restart Your Coding Agent</h3>
                    <p class="text-sm text-gray-600">Restart Claude Code (or your MCP-compatible coding agent) so it detects the MCP configuration and starts the server when needed.</p>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="flex gap-4">
                <div class="w-8 h-8 bg-black text-white rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">4</div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 mb-1">Test the Connection</h3>
                    <p class="text-sm text-gray-600 mb-3">Ask your coding agent to test the connection:</p>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <p class="text-sm text-gray-700"><strong>You:</strong> "Test the LazyMan Task Manager connection"</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">MCP Configuration</h2>
        <div class="bg-gray-900 text-green-400 p-4 rounded-xl overflow-x-auto text-sm font-mono">
            <pre id="mcpConfig">{
  "mcpServers": {
    "lazyman-taskmanager": {
      "command": "node",
      "args": ["<?php echo $mcpScriptPath; ?>"],
      "env": {
        "API_URL": "<?php echo $apiUrl; ?>",
        "USER_EMAIL": "<?php echo $currentUserEmail; ?>",
        "MASTER_PASSWORD": "YOUR_SECRET_KEY"
      }
    }
  }
}</pre>
        </div>
        <div class="mt-3 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <h4 class="font-semibold text-yellow-800 mb-2">⚠️ Configuration Notes</h4>
            <ul class="text-sm text-yellow-700 space-y-1">
                <li>• <strong>Windows:</strong> Use forward slashes: <code class="bg-yellow-100 px-1">C:/MAMP/htdocs/taskmanager/...</code></li>
                <li>• <strong>Linux/Mac:</strong> Use absolute paths: <code class="bg-yellow-100 px-1">/var/www/html/taskmanager/...</code></li>
                <li>• <strong>Email:</strong> Replace <code class="bg-yellow-100 px-1">user@example.com</code> with your login email</li>
                <li>• <strong>Secret Key:</strong> Replace <code class="bg-yellow-100 px-1">YOUR_SECRET_KEY</code> with your master key</li>
                <li>• <strong>API URL:</strong> Update if your Task Manager runs on a different port or domain</li>
            </ul>
        </div>
    </div>

    <!-- Available Tools -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Available MCP Tools</h2>
        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
            <!-- Todo Tools -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">✅</span>
                    <h3 class="font-semibold text-gray-800">Todo</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_todos</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_todo</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">update_todo</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">complete_todo</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">delete_todo</code></li>
                </ul>
            </div>

            <!-- Project & Task Tools -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">📋</span>
                    <h3 class="font-semibold text-gray-800">Projects</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_projects</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_project</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">get_project</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_task</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_subtask</code></li>
                </ul>
            </div>

            <!-- Client Tools -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">👥</span>
                    <h3 class="font-semibold text-gray-800">Clients</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_clients</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">get_client</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">add_client</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">update_client</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">delete_client</code></li>
                </ul>
            </div>

            <!-- Invoice Tools -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">📄</span>
                    <h3 class="font-semibold text-gray-800">Invoices</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">list_invoices</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">create_invoice</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">update_invoice_status</code></li>
                </ul>
            </div>

            <!-- Finance Tools -->
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

            <!-- Inventory Tools -->
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

            <!-- Health Tools -->
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">💧</span>
                    <h3 class="font-semibold text-gray-800">Health</h3>
                </div>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li><code class="bg-gray-100 px-1 rounded">get_water_status</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">log_water</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">set_water_goal</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">list_habits</code></li>
                    <li><code class="bg-gray-100 px-1 rounded">complete_habit</code></li>
                </ul>
            </div>

            <!-- System Tools -->
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
                <div class="bg-white rounded-lg p-4 border border-blue-200">
                    <p class="text-sm text-gray-700 mb-2"><strong>You:</strong> "Create a new project 'Website Redesign' for TechStart Inc"</p>
                    <p class="text-sm text-green-600 mb-2"><strong>Claude:</strong> "I've created the project 'Website Redesign' linked to TechStart Inc."</p>
                    <p class="text-sm text-gray-700 mb-2"><strong>You:</strong> "Add a task 'Design homepage mockup' with high priority and due date next Friday"</p>
                    <p class="text-sm text-green-600"><strong>Claude:</strong> "Added the task with high priority, due on January 17th."</p>
                </div>
            </div>

            <div class="bg-green-50 border border-green-200 rounded-xl p-5">
                <h3 class="font-semibold text-green-900 mb-3">💵 Invoice Creation</h3>
                <div class="bg-white rounded-lg p-4 border border-green-200">
                    <p class="text-sm text-gray-700 mb-2"><strong>You:</strong> "Create an invoice for Global Corp with consulting services - 20 hours at $150/hour"</p>
                    <p class="text-sm text-green-600"><strong>Claude:</strong> "Invoice 2026-0012 created for $3,000 (plus tax). Due on February 15th."</p>
                </div>
            </div>

            <div class="bg-purple-50 border border-purple-200 rounded-xl p-5">
                <h3 class="font-semibold text-purple-900 mb-3">💰 Finance Tracking</h3>
                <div class="bg-white rounded-lg p-4 border border-purple-200">
                    <p class="text-sm text-gray-700 mb-2"><strong>You:</strong> "Add an expense of $250 for office supplies from Amazon"</p>
                    <p class="text-sm text-green-600"><strong>Claude:</strong> "Added expense of $250 for office supplies."</p>
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
                    <li>• Verify Node.js is installed: <code class="bg-gray-200 px-1 rounded">node --version</code></li>
                    <li>• Check the path to <code class="bg-gray-200 px-1 rounded">mcp-server/index.js</code> is correct</li>
                    <li>• Ensure the API URL is accessible in your browser</li>
                </ul>
            </div>

            <div class="bg-gray-50 rounded-xl p-4">
                <h3 class="font-semibold text-gray-900 mb-2">🔴 Authentication Failed</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Verify your admin email (<code class="bg-gray-200 px-1 rounded">USER_EMAIL</code>) is correct</li>
                    <li>• Verify your secret key (<code class="bg-gray-200 px-1 rounded">MASTER_PASSWORD</code>) is correct</li>
                    <li>• Ensure the Task Manager is running (MAMP/XAMPP)</li>
                    <li>• Check the API URL is correct and accessible</li>
                </ul>
            </div>

            <div class="bg-gray-50 rounded-xl p-4">
                <h3 class="font-semibold text-gray-900 mb-2">🔴 Tools Not Available</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Restart your coding agent after adding/changing MCP config</li>
                    <li>• Verify <code class="bg-gray-200 px-1 rounded">mcp.json</code> is in the correct location</li>
                    <li>• Check your coding agent logs/console for MCP errors</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Server Location -->
    <div class="bg-gray-900 text-white rounded-xl p-6">
        <h3 class="font-semibold mb-3">📂 MCP Server Location</h3>
        <p class="text-gray-400 text-sm mb-3">The MCP server is located at:</p>
        <code class="bg-gray-800 px-4 py-2 rounded-lg block text-green-400 font-mono text-sm">
            C:\MAMP\htdocs\taskmanager\mcp-server\index.js
        </code>
    </div>
</div>

<script>
function copyConfig() {
    const config = document.getElementById('mcpConfig').textContent;
    navigator.clipboard.writeText(config).then(() => {
        const btnText = document.getElementById('copyBtnText');
        btnText.textContent = 'Copied!';
        setTimeout(() => {
            btnText.textContent = 'Copy Configuration';
        }, 2000);
        showToast('Configuration copied to clipboard!', 'success');
    }).catch(err => {
        showToast('Failed to copy configuration', 'error');
    });
}
</script>
