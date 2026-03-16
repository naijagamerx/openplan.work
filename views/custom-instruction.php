<?php
$pageTitle = 'Custom Instructions';
$template = <<<TEMPLATE
# Task Creation Instruction for Claude / Gemini / Trade Agents

When creating tasks in LazyMan Task Manager via MCP, follow these rules:

## 1. Create a Description
Every task must include a clear, concise description explaining the purpose and requirements.

## 2. Convert Checklist Items into Sub-tasks
When you have multiple items to complete, create each as a separate sub-task:
- Use the `add_subtask` tool for each item
- Give each sub-task a clear title

## 3. Add Time Estimates
Each task and sub-task should have an estimated duration:
- Use `estimatedMinutes` parameter (integer, in minutes)
- This helps track productivity and plan future work

## Workflow: Create Project → Task → Sub-tasks

### Step 1: Create a Project
```json
{
  "name": "Website Redesign",
  "description": "Complete redesign of company website with new branding",
  "status": "active",
  "color": "#3B82F6"
}
```

### Step 2: Add a Task (within the project)
```json
{
  "projectId": "uuid-of-project",
  "title": "Design homepage mockup",
  "description": "Create wireframes and high-fidelity mockups for homepage",
  "status": "todo",
  "priority": "high",
  "dueDate": "2025-02-15",
  "estimatedMinutes": 120
}
```

### Step 3: Add Sub-tasks (for checklist items)
```json
{
  "projectId": "uuid-of-project",
  "taskId": "uuid-of-task",
  "title": "Research competitor sites",
  "estimatedMinutes": 30
}
```

## MCP Tools Reference

### Project Management
| Tool | Description |
|------|-------------|
| `add_project` | Create new project (name required) |
| `list_projects` | Get all projects with tasks |
| `get_project` | Get project details by ID |
| `update_project` | Update project details |
| `delete_project` | Delete project and tasks |

### Task Management
| Tool | Description |
|------|-------------|
| `add_task` | Add task to project (projectId, title required) |
| `add_subtask` | Add sub-task to task (projectId, taskId, title required) |
| `update_task` | Update task (status, priority, dueDate, etc.) |
| `complete_task` | Mark task as done |
| `delete_task` | Delete task from project |
| `list_tasks` | List all tasks across projects |

### Time Tracking (via Habits)
| Tool | Description |
|------|-------------|
| `start_habit_timer` | Start timer for a habit |
| `stop_habit_timer` | Stop current timer session |
| `complete_habit` | Mark habit as complete |

## Priority Levels
```
urgent > high > medium > low
```

## Task Status Flow
```
backlog → todo → in_progress → review → done
```

## Important Notes
- Always set `estimatedMinutes` for time tracking
- Use YYYY-MM-DD format for due dates
- All IDs are UUIDs (get from previous tool responses)
- Colors: Hex code (e.g., `#3B82F6`, `#10B981`)

---
*Copy this template to your AI configuration file (claude.md, agents.md, etc.)*
TEMPLATE;
?>

<div class="p-6">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Custom Instructions</h1>
        <p class="text-gray-600 mt-1">Copy this template into your AI configuration file for consistent task creation</p>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3 mb-6">
        <button onclick="copyTemplate()" id="copyBtn" class="flex items-center gap-2 px-5 py-2.5 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
            </svg>
            <span id="copyBtnText">Copy Template</span>
        </button>
        <button onclick="downloadTemplate()" class="flex items-center gap-2 px-5 py-2.5 bg-white border-2 border-gray-200 text-gray-700 rounded-xl font-bold hover:border-black hover:bg-gray-50 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            Download .md
        </button>
    </div>

    <!-- Template Preview -->
    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Template Preview</h2>
            <span class="text-xs text-gray-500">Markdown format</span>
        </div>
        <div class="p-0">
            <pre id="templateContent" class="p-6 bg-gray-900 text-gray-100 text-sm font-mono overflow-x-auto whitespace-pre-wrap break-all"><?php echo e($template); ?></pre>
        </div>
    </div>

    <!-- Instructions -->
    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-6">
        <h3 class="font-semibold text-blue-900 mb-3">How to Use</h3>
        <div class="space-y-3 text-sm text-blue-800">
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-200 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-xs">1</span>
                <p>Click <strong>"Copy Template"</strong> to copy the markdown to your clipboard</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-200 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-xs">2</span>
                <p>Open your AI configuration file (<code class="bg-blue-100 px-1 rounded">claude.md</code>, <code class="bg-blue-100 px-1 rounded">agents.md</code>, etc.)</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-200 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-xs">3</span>
                <p>Paste the template at the end of the file</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-200 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-xs">4</span>
                <p>The AI will now follow these rules when creating tasks</p>
            </div>
        </div>
    </div>

    <!-- Quick Reference -->
    <div class="mt-6 grid md:grid-cols-2 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h3 class="font-semibold text-gray-900 mb-3">MCP Tools Reference</h3>
            <div class="space-y-2 text-sm">
                <div class="flex items-center gap-2">
                    <code class="bg-gray-100 px-2 py-1 rounded text-xs">add_project</code>
                    <span class="text-gray-600">Create new project</span>
                </div>
                <div class="flex items-center gap-2">
                    <code class="bg-gray-100 px-2 py-1 rounded text-xs">add_task</code>
                    <span class="text-gray-600">Add task to project</span>
                </div>
                <div class="flex items-center gap-2">
                    <code class="bg-gray-100 px-2 py-1 rounded text-xs">add_subtask</code>
                    <span class="text-gray-600">Add sub-task to task</span>
                </div>
                <div class="flex items-center gap-2">
                    <code class="bg-gray-100 px-2 py-1 rounded text-xs">complete_task</code>
                    <span class="text-gray-600">Mark task as done</span>
                </div>
                <div class="flex items-center gap-2">
                    <code class="bg-gray-100 px-2 py-1 rounded text-xs">start_habit_timer</code>
                    <span class="text-gray-600">Track habit time</span>
                </div>
            </div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h3 class="font-semibold text-gray-900 mb-3">Priority & Status</h3>
            <div class="space-y-3">
                <div class="flex flex-wrap gap-2">
                    <span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-full">urgent</span>
                    <span class="px-3 py-1 bg-orange-100 text-orange-700 text-xs font-bold rounded-full">high</span>
                    <span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-full">medium</span>
                    <span class="px-3 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded-full">low</span>
                </div>
                <p class="text-xs text-gray-500">Status: backlog → todo → in_progress → review → done</p>
            </div>
        </div>
    </div>
</div>

<script>
const templateContent = `<?php echo str_replace(['`', '${'], ['\\`', '\\${'], e($template)); ?>`;

function copyTemplate() {
    navigator.clipboard.writeText(templateContent).then(() => {
        const btnText = document.getElementById('copyBtnText');
        const originalText = btnText.textContent;
        btnText.textContent = 'Copied!';
        showToast('Template copied to clipboard!', 'success');
        setTimeout(() => {
            btnText.textContent = originalText;
        }, 2000);
    }).catch(err => {
        showToast('Failed to copy template', 'error');
    });
}

function downloadTemplate() {
    const blob = new Blob([templateContent], { type: 'text/markdown' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'lazyman-task-instructions.md';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast('Template downloaded!', 'success');
}
</script>
