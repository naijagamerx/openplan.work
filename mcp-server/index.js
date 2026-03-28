#!/usr/bin/env node

/**
 * OpenPlan.work - MCP Server
 *
 * This MCP server provides complete control over OpenPlan.work
 * through Claude Code. It exposes all features as tools including:
 * - Todo management
 * - Project and task management
 * - Client CRM operations
 * - Water tracking
 * - Habit tracking
 * - Finance tracking
 * - Inventory management
 * - Invoice management
 * - Advanced Invoice generator (custom invoices with flexible fields)
 *
 * Usage:
 *   node index.js
 *
 * Environment Variables:
 *   API_URL         - Base URL of the OpenPlan.work API (default: http://localhost/taskmanager/api)
 *   MASTER_PASSWORD - Master password for authentication (default: Money2026@Demo#)
 */

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

// Import knowledge base text tools (easy file upload without base64 hassles)
import { toolDefinitions as kbTextToolDefs, toolHandlers as kbTextToolHandlers } from "./kb-text-tools.js";

// ============================================================================
// CONFIGURATION
// ============================================================================

const API_URL = process.env.API_URL || "http://localhost/taskmanager/api";

// New token-based authentication (recommended)
const MCP_API_TOKEN = process.env.MCP_API_TOKEN || "";

// Master password - support both LAZYMAN_MASTER_PASSWORD and MASTER_PASSWORD for compatibility
const MASTER_PASSWORD = process.env.LAZYMAN_MASTER_PASSWORD || process.env.MASTER_PASSWORD || "";

// Legacy authentication (for backward compatibility)
const USER_EMAIL = process.env.USER_EMAIL || "";

// Detect which authentication mode to use
const useTokenAuth = MCP_API_TOKEN.length > 0;

// ============================================================================
// API HELPER FUNCTIONS
// ============================================================================

/**
 * Make an API call to the OpenPlan.work backend
 * @param {string} endpoint - API endpoint path
 * @param {string} method - HTTP method (GET, POST, PUT, DELETE, PATCH)
 * @param {object|null} body - Request body
 * @param {object} queryParams - Query string parameters
 * @returns {Promise<object>} - API response
 */
async function apiCall(endpoint, method = "GET", body = null, queryParams = {}) {
  const headers = {
    "X-MCP-Tool": "true",  // Bypass CSRF validation
    "Content-Type": "application/json",
  };

  // Use token-based authentication if available (recommended for multi-user setups)
  if (useTokenAuth) {
    headers["X-MCP-Api-Token"] = MCP_API_TOKEN;
    // Also send master password so PHP can decrypt the database
    if (MASTER_PASSWORD) {
      headers["X-Master-Password"] = MASTER_PASSWORD;
    }
  } else {
    // Legacy mode: use email + master password
    headers["X-Master-Password"] = MASTER_PASSWORD;
    if (USER_EMAIL) {
      headers["X-MCP-User-Email"] = USER_EMAIL;
    }
  }

  // Build URL with query parameters
  const url = new URL(`${API_URL}/${endpoint}`);
  for (const [key, value] of Object.entries(queryParams)) {
    url.searchParams.append(key, value);
  }

  const options = {
    method,
    headers,
  };

  if (body && (method === "POST" || method === "PUT" || method === "PATCH" || method === "DELETE")) {
    options.body = JSON.stringify(body);
  }

  try {
    const response = await fetch(url.toString(), options);
    const data = await response.json();

    // Handle both old and new API response formats
    if (!data.success && !data.error) {
      return { success: false, error: data.message || "Unknown error", data };
    }

    return data;
  } catch (error) {
    return { success: false, error: error.message };
  }
}

/**
 * Generate a unique ID (UUID v4 compatible)
 */
function generateId() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

// ============================================================================
// MCP SERVER SETUP
// ============================================================================

const server = new Server(
  {
    name: "openplan-work",
    version: "1.0.0",
  },
  {
    capabilities: {
      tools: {},
    }
  }
);

// ============================================================================
// TOOL DEFINITIONS
// ============================================================================

server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      // ======================================================================
      // TODO TOOLS
      // ======================================================================
      {
        name: "list_todos",
        description: "Get a list of all todos. Returns all todo items sorted by creation date (newest first).",
        inputSchema: {
          type: "object",
          properties: {
            status: {
              type: "string",
              enum: ["pending", "completed", "all"],
              description: "Filter by status (default: all)"
            }
          },
        },
      },
      {
        name: "add_todo",
        description: "Create a new todo item with title, optional description, and priority.",
        inputSchema: {
          type: "object",
          properties: {
            title: { type: "string", description: "The todo title (required)" },
            description: { type: "string", description: "Optional detailed description" },
            priority: {
              type: "string",
              enum: ["low", "medium", "high", "urgent"],
              description: "Priority level (default: medium)"
            },
            category: { type: "string", description: "Optional category/tag" },
            dueDate: { type: "string", description: "Due date in ISO format (YYYY-MM-DD)" },
          },
          required: ["title"],
        },
      },
      {
        name: "update_todo",
        description: "Update an existing todo. Can update title, description, priority, status, or dueDate.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The todo ID (UUID)" },
            title: { type: "string", description: "New title" },
            description: { type: "string", description: "New description" },
            priority: {
              type: "string",
              enum: ["low", "medium", "high", "urgent"],
              description: "New priority level"
            },
            status: {
              type: "string",
              enum: ["pending", "completed"],
              description: "New status"
            },
            dueDate: { type: "string", description: "New due date (YYYY-MM-DD)" },
          },
          required: ["id"],
        },
      },
      {
        name: "delete_todo",
        description: "Delete a todo by its ID.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The todo ID (UUID)" },
          },
          required: ["id"],
        },
      },
      {
        name: "complete_todo",
        description: "Mark a todo as completed.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The todo ID (UUID)" },
          },
          required: ["id"],
        },
      },

      // ======================================================================
      // PROJECT TOOLS
      // ======================================================================
      {
        name: "list_projects",
        description: "Get all projects with their embedded tasks. Returns project details including all tasks.",
        inputSchema: {
          type: "object",
          properties: {
            status: {
              type: "string",
              enum: ["active", "completed", "on-hold", "cancelled", "all"],
              description: "Filter by status (default: all)"
            },
            clientId: { type: "string", description: "Filter by client ID" },
          },
        },
      },
      {
        name: "get_project",
        description: "Get detailed information about a specific project including all its tasks.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The project ID (UUID)" },
          },
          required: ["id"],
        },
      },
      {
        name: "add_project",
        description: "Create a new project. Optionally link to an existing client.",
        inputSchema: {
          type: "object",
          properties: {
            name: { type: "string", description: "Project name (required)" },
            description: { type: "string", description: "Project description" },
            clientId: { type: "string", description: "Linked client ID" },
            status: {
              type: "string",
              enum: ["active", "completed", "on-hold", "cancelled"],
              description: "Project status (default: active)"
            },
            color: { type: "string", description: "Hex color code (e.g., #3B82F6)" },
          },
          required: ["name"],
        },
      },
      {
        name: "update_project",
        description: "Update project details including name, description, status, or color.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The project ID (UUID)" },
            name: { type: "string", description: "New project name" },
            description: { type: "string", description: "New description" },
            clientId: { type: "string", description: "New client ID" },
            status: {
              type: "string",
              enum: ["active", "completed", "on-hold", "cancelled"],
              description: "New status"
            },
            color: { type: "string", description: "New hex color" },
          },
          required: ["id"],
        },
      },
      {
        name: "delete_project",
        description: "Delete a project and all its embedded tasks.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The project ID (UUID)" },
          },
          required: ["id"],
        },
      },

      // ======================================================================
      // TASK TOOLS (within projects)
      // ======================================================================
      {
        name: "list_tasks",
        description: "Get all tasks across all projects, or filter by projectId, status, or priority.",
        inputSchema: {
          type: "object",
          properties: {
            projectId: { type: "string", description: "Filter by project ID" },
            status: {
              type: "string",
              enum: ["backlog", "todo", "in_progress", "review", "done"],
              description: "Filter by status"
            },
            priority: {
              type: "string",
              enum: ["low", "medium", "high", "urgent"],
              description: "Filter by priority"
            },
          },
        },
      },
      {
        name: "add_task",
        description: "Add a new task to a project. Requires projectId and title.",
        inputSchema: {
          type: "object",
          properties: {
            projectId: { type: "string", description: "The project ID to add task to (required)" },
            title: { type: "string", description: "Task title (required)" },
            description: { type: "string", description: "Task description" },
            status: {
              type: "string",
              enum: ["backlog", "todo", "in_progress", "review", "done"],
              description: "Initial status (default: todo)"
            },
            priority: {
              type: "string",
              enum: ["low", "medium", "high", "urgent"],
              description: "Priority level (default: medium)"
            },
            dueDate: { type: "string", description: "Due date (YYYY-MM-DD)" },
            estimatedMinutes: { type: "number", description: "Estimated time in minutes" },
          },
          required: ["projectId", "title"],
        },
      },
      {
        name: "update_task",
        description: "Update a task within a project. Requires projectId and taskId.",
        inputSchema: {
          type: "object",
          properties: {
            projectId: { type: "string", description: "The project ID (required)" },
            taskId: { type: "string", description: "The task ID (UUID, required)" },
            title: { type: "string", description: "New title" },
            description: { type: "string", description: "New description" },
            status: {
              type: "string",
              enum: ["backlog", "todo", "in_progress", "review", "done"],
              description: "New status"
            },
            priority: {
              type: "string",
              enum: ["low", "medium", "high", "urgent"],
              description: "New priority"
            },
            dueDate: { type: "string", description: "New due date" },
            estimatedMinutes: { type: "number", description: "New estimated time" },
          },
          required: ["projectId", "taskId"],
        },
      },
      {
        name: "delete_task",
        description: "Delete a task from a project.",
        inputSchema: {
          type: "object",
          properties: {
            projectId: { type: "string", description: "The project ID (required)" },
            taskId: { type: "string", description: "The task ID (UUID, required)" },
          },
          required: ["projectId", "taskId"],
        },
      },
      {
        name: "complete_task",
        description: "Mark a task as completed.",
        inputSchema: {
          type: "object",
          properties: {
            projectId: { type: "string", description: "The project ID (required)" },
            taskId: { type: "string", description: "The task ID (UUID, required)" },
          },
          required: ["projectId", "taskId"],
        },
      },
      {
        name: "add_subtask",
        description: "Add a subtask to an existing task.",
        inputSchema: {
          type: "object",
          properties: {
            projectId: { type: "string", description: "The project ID (required)" },
            taskId: { type: "string", description: "The task ID (UUID, required)" },
            title: { type: "string", description: "Subtask title (required)" },
            estimatedMinutes: { type: "number", description: "Estimated time" },
          },
          required: ["projectId", "taskId", "title"],
        },
      },

      // ======================================================================
      // CLIENT TOOLS
      // ======================================================================
      {
        name: "list_clients",
        description: "Get all clients/contacts from the CRM.",
        inputSchema: {
          type: "object",
          properties: {},
        },
      },
      {
        name: "get_client",
        description: "Get detailed information about a specific client.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The client ID (UUID)" },
          },
          required: ["id"],
        },
      },
      {
        name: "add_client",
        description: "Add a new client/contact to the CRM.",
        inputSchema: {
          type: "object",
          properties: {
            name: { type: "string", description: "Client name (required)" },
            email: { type: "string", description: "Email address" },
            phone: { type: "string", description: "Phone number" },
            company: { type: "string", description: "Company name" },
            street: { type: "string", description: "Street address" },
            city: { type: "string", description: "City" },
            state: { type: "string", description: "State/Province" },
            zip: { type: "string", description: "ZIP/Postal code" },
            country: { type: "string", description: "Country" },
            notes: { type: "string", description: "Additional notes" },
          },
          required: ["name"],
        },
      },
      {
        name: "update_client",
        description: "Update client information.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The client ID (UUID, required)" },
            name: { type: "string", description: "New name" },
            email: { type: "string", description: "New email" },
            phone: { type: "string", description: "New phone" },
            company: { type: "string", description: "New company" },
            street: { type: "string", description: "New street" },
            city: { type: "string", description: "New city" },
            state: { type: "string", description: "New state" },
            zip: { type: "string", description: "New ZIP" },
            country: { type: "string", description: "New country" },
            notes: { type: "string", description: "New notes" },
          },
          required: ["id"],
        },
      },
      {
        name: "delete_client",
        description: "Delete a client from the CRM.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The client ID (UUID)" },
          },
          required: ["id"],
        },
      },

      // ======================================================================
      // WATER TRACKING TOOLS
      // ======================================================================
      {
        name: "get_water_status",
        description: "Get current water tracking status including today's intake and goal.",
        inputSchema: {
          type: "object",
          properties: {
            date: { type: "string", description: "Specific date (YYYY-MM-DD, default: today)" },
          },
        },
      },
      {
        name: "log_water",
        description: "Log water intake by adding glasses. Default is 1 glass.",
        inputSchema: {
          type: "object",
          properties: {
            glasses: { type: "number", description: "Number of glasses to add (default: 1)" },
            date: { type: "string", description: "Date for this entry (YYYY-MM-DD, default: today)" },
          },
        },
      },
      {
        name: "set_water_goal",
        description: "Set or update the daily water goal (number of glasses).",
        inputSchema: {
          type: "object",
          properties: {
            goal: { type: "number", description: "Daily goal in glasses (required)" },
          },
          required: ["goal"],
        },
      },
      {
        name: "get_water_history",
        description: "Get water tracking history for a date range.",
        inputSchema: {
          type: "object",
          properties: {
            startDate: { type: "string", description: "Start date (YYYY-MM-DD)" },
            endDate: { type: "string", description: "End date (YYYY-MM-DD)" },
          },
        },
      },
      {
        name: "generate_water_plan",
        description: "Generate an AI-powered personalized hydration plan based on weight, activity level, climate, and schedule.",
        inputSchema: {
          type: "object",
          properties: {
            weight: { type: "number", description: "Weight in kg (default: 70)" },
            activity: { type: "string", enum: ["sedentary", "moderate", "active", "athlete"], description: "Activity level (default: moderate)" },
            climate: { type: "string", enum: ["temperate", "hot", "humid", "cold"], description: "Climate (default: temperate)" },
            goal: { type: "number", description: "Daily water goal in ml (default: 2500)" },
            wakeTime: { type: "string", description: "Wake time HH:MM (default: 07:00)" },
            sleepTime: { type: "string", description: "Sleep time HH:MM (default: 23:00)" },
            glassSize: { type: "number", description: "Glass size in ml (default: 250)" },
          },
        },
      },
      {
        name: "list_water_plan_history",
        description: "List all saved water plan history.",
        inputSchema: {
          type: "object",
          properties: {},
        },
      },
      {
        name: "save_water_plan_history",
        description: "Save the current water plan to history for later use.",
        inputSchema: {
          type: "object",
          properties: {
            planId: { type: "string", description: "The plan ID to save (required)" },
          },
          required: ["planId"],
        },
      },

      // ======================================================================
      // HABIT TRACKING TOOLS
      // ======================================================================
      {
        name: "list_habits",
        description: "Get all habits with their current status.",
        inputSchema: {
          type: "object",
          properties: {},
        },
      },
      {
        name: "add_habit",
        description: "Create a new habit to track.",
        inputSchema: {
          type: "object",
          properties: {
            name: { type: "string", description: "Habit name (required)" },
            category: { type: "string", description: "Category (default: general)" },
            frequency: {
              type: "string",
              enum: ["daily", "weekly", "monthly"],
              description: "Frequency (default: daily)"
            },
            reminderTime: { type: "string", description: "Daily reminder time (HH:MM)" },
            targetDuration: { type: "number", description: "Target duration in minutes" },
          },
          required: ["name"],
        },
      },
      {
        name: "complete_habit",
        description: "Mark a habit as complete for today or a specific date.",
        inputSchema: {
          type: "object",
          properties: {
            habitId: { type: "string", description: "The habit ID (UUID, required)" },
            date: { type: "string", description: "Date to complete (YYYY-MM-DD, default: today)" },
          },
          required: ["habitId"],
        },
      },
      {
        name: "get_habit_stats",
        description: "Get habit tracking statistics and completion history.",
        inputSchema: {
          type: "object",
          properties: {
            habitId: { type: "string", description: "Specific habit ID" },
            days: { type: "number", description: "Number of days to look back (default: 30)" },
          },
        },
      },
      {
        name: "start_habit_timer",
        description: "Start a timer session for a habit.",
        inputSchema: {
          type: "object",
          properties: {
            habitId: { type: "string", description: "The habit ID (UUID, required)" },
          },
          required: ["habitId"],
        },
      },
      {
        name: "stop_habit_timer",
        description: "Stop the current timer session for a habit.",
        inputSchema: {
          type: "object",
          properties: {
            habitId: { type: "string", description: "The habit ID (UUID, required)" },
          },
          required: ["habitId"],
        },
      },
      {
        name: "delete_habit",
        description: "Delete a habit and all its completion records.",
        inputSchema: {
          type: "object",
          properties: {
            habitId: { type: "string", description: "The habit ID (UUID, required)" },
          },
          required: ["habitId"],
        },
      },

      // ======================================================================
      // NOTES TOOLS
      // ======================================================================
      {
        name: "list_notes",
        description: "Get all notes with optional filters.",
        inputSchema: {
          type: "object",
          properties: {
            tag: { type: "string", description: "Filter by tag" },
            search: { type: "string", description: "Search query" },
            isPinned: { type: "boolean", description: "Filter to pinned notes only" },
            isFavorite: { type: "boolean", description: "Filter to favorite notes only" },
          },
        },
      },
      {
        name: "get_note",
        description: "Get a specific note by ID.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The note ID (UUID, required)" },
          },
          required: ["id"],
        },
      },
      {
        name: "create_note",
        description: "Create a new note.",
        inputSchema: {
          type: "object",
          properties: {
            title: { type: "string", description: "Note title (required)" },
            content: { type: "string", description: "Note content" },
            tags: { type: "array", items: { type: "string" }, description: "Array of tags" },
            color: { type: "string", description: "Background color (hex)" },
          },
          required: ["title"],
        },
      },
      {
        name: "update_note",
        description: "Update an existing note.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The note ID (UUID, required)" },
            title: { type: "string", description: "New title" },
            content: { type: "string", description: "New content" },
            tags: { type: "array", items: { type: "string" }, description: "New tags array" },
            color: { type: "string", description: "New background color" },
            isPinned: { type: "boolean", description: "Pin status" },
            isFavorite: { type: "boolean", description: "Favorite status" },
          },
          required: ["id"],
        },
      },
      {
        name: "delete_note",
        description: "Delete a note.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The note ID (UUID, required)" },
          },
          required: ["id"],
        },
      },
      {
        name: "search_notes",
        description: "Search notes by query (title or content).",
        inputSchema: {
          type: "object",
          properties: {
            query: { type: "string", description: "Search query (required)" },
          },
          required: ["query"],
        },
      },
      {
        name: "list_note_tags",
        description: "Get all unique tags from all notes.",
        inputSchema: {
          type: "object",
          properties: {},
        },
      },
      {
        name: "toggle_note_pin",
        description: "Pin or unpin a note.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The note ID (UUID, required)" },
          },
          required: ["id"],
        },
      },
      {
        name: "toggle_note_favorite",
        description: "Add or remove a note from favorites.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The note ID (UUID, required)" },
          },
          required: ["id"],
        },
      },
      {
        name: "convert_note_to_kb",
        description: "Convert a note to a file in the knowledge base. Exports as Markdown or XML with auto-renaming for duplicates.",
        inputSchema: {
          type: "object",
          properties: {
            noteId: { type: "string", description: "The note ID to convert (UUID, required)" },
            folderId: { type: "string", description: "Target folder ID in knowledge base (UUID, required)" },
            filename: { type: "string", description: "Filename (without extension, auto-generated from note title if not provided)" },
            format: {
              type: "string",
              enum: ["markdown", "xml"],
              description: "Export format: markdown (.md) or xml (.xml). Default: markdown"
            },
          },
          required: ["noteId", "folderId"],
        },
      },
      {
        name: "ai_edit_note",
        description: "Use AI to edit a note with operations: rewrite, improve, expand, or summarize.",
        inputSchema: {
          type: "object",
          properties: {
            noteId: { type: "string", description: "The note ID to edit (UUID, required)" },
            operation: {
              type: "string",
              enum: ["rewrite", "improve", "expand", "summarize"],
              description: "AI operation: rewrite (clarity/tone), improve (grammar/readability), expand (add details), summarize (bullet points)"
            },
            provider: { type: "string", enum: ["groq", "openrouter"], description: "AI provider: groq or openrouter (optional, defaults to first configured)" },
            model: { type: "string", description: "AI model ID (optional, uses default for provider)" },
          },
          required: ["noteId", "operation"],
        },
      },

      // ======================================================================
      // FINANCE TOOLS
      // ======================================================================
      {
        name: "list_transactions",
        description: "Get all financial transactions (income and expenses).",
        inputSchema: {
          type: "object",
          properties: {
            type: {
              type: "string",
              enum: ["expense", "revenue"],
              description: "Filter by type"
            },
            startDate: { type: "string", description: "Start date (YYYY-MM-DD)" },
            endDate: { type: "string", description: "End date (YYYY-MM-DD)" },
          },
        },
      },
      {
        name: "add_transaction",
        description: "Add a new financial transaction (income or expense).",
        inputSchema: {
          type: "object",
          properties: {
            type: {
              type: "string",
              enum: ["expense", "revenue"],
              description: "Transaction type (required)"
            },
            amount: { type: "number", description: "Amount (required)" },
            description: { type: "string", description: "Description (required)" },
            category: { type: "string", description: "Category" },
            date: { type: "string", description: "Date (YYYY-MM-DD, default: today)" },
            projectId: { type: "string", description: "Related project ID" },
            clientId: { type: "string", description: "Related client ID" },
            notes: { type: "string", description: "Additional notes" },
          },
          required: ["type", "amount", "description"],
        },
      },
      {
        name: "get_finance_summary",
        description: "Get a financial summary including total income, expenses, and balance.",
        inputSchema: {
          type: "object",
          properties: {
            startDate: { type: "string", description: "Start date (YYYY-MM-DD)" },
            endDate: { type: "string", description: "End date (YYYY-MM-DD)" },
          },
        },
      },

      // ======================================================================
      // INVENTORY TOOLS
      // ======================================================================
      {
        name: "list_inventory",
        description: "Get all inventory items with stock levels.",
        inputSchema: {
          type: "object",
          properties: {
            category: { type: "string", description: "Filter by category" },
            lowStock: { type: "boolean", description: "Show only low stock items" },
          },
        },
      },
      {
        name: "add_inventory_item",
        description: "Add a new item to inventory.",
        inputSchema: {
          type: "object",
          properties: {
            name: { type: "string", description: "Item name (required)" },
            sku: { type: "string", description: "Stock keeping unit" },
            description: { type: "string", description: "Description" },
            category: { type: "string", description: "Category" },
            quantity: { type: "number", description: "Initial quantity (default: 0)" },
            unitPrice: { type: "number", description: "Selling price" },
            costPrice: { type: "number", description: "Cost price" },
            reorderPoint: { type: "number", description: "Reorder alert level" },
            supplier: { type: "string", description: "Supplier name" },
            notes: { type: "string", description: "Additional notes" },
          },
          required: ["name"],
        },
      },
      {
        name: "update_inventory_stock",
        description: "Update inventory item quantity.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "Item ID (UUID, required)" },
            quantity: { type: "number", description: "New quantity (required)" },
          },
          required: ["id", "quantity"],
        },
      },
      {
        name: "adjust_inventory_stock",
        description: "Adjust inventory quantity by adding or subtracting.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "Item ID (UUID, required)" },
            adjustment: { type: "number", description: "Change amount (+/-)" },
            notes: { type: "string", description: "Reason for adjustment" },
          },
          required: ["id", "adjustment"],
        },
      },

      // ======================================================================
      // INVOICE TOOLS
      // ======================================================================
      {
        name: "list_invoices",
        description: "Get all invoices.",
        inputSchema: {
          type: "object",
          properties: {
            status: {
              type: "string",
              enum: ["draft", "sent", "paid", "overdue", "cancelled"],
              description: "Filter by status"
            },
            clientId: { type: "string", description: "Filter by client" },
          },
        },
      },
      {
        name: "create_invoice",
        description: "Create a new invoice for a client. Pass lineItems with description, quantity, and unitPrice for each item. The API will calculate totals automatically.",
        inputSchema: {
          type: "object",
          properties: {
            clientId: { type: "string", description: "Client ID (UUID, required)" },
            projectId: { type: "string", description: "Related project ID" },
            lineItems: {
              type: "array",
              description: "Invoice line items with description, quantity, unitPrice",
              items: {
                type: "object",
                properties: {
                  description: { type: "string", description: "Item description" },
                  quantity: { type: "number", description: "Quantity" },
                  unitPrice: { type: "number", description: "Unit price" },
                },
                required: ["description", "quantity", "unitPrice"],
              },
            },
            dueDate: { type: "string", description: "Due date (YYYY-MM-DD, default: 30 days from now)" },
            notes: { type: "string", description: "Invoice notes" },
            currency: { type: "string", description: "Currency code (USD, EUR, etc.)" },
          },
          required: ["clientId", "lineItems"],
        },
      },
      {
        name: "update_invoice_status",
        description: "Update invoice status (e.g., mark as paid).",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "Invoice ID (UUID, required)" },
            status: {
              type: "string",
              enum: ["draft", "sent", "paid", "overdue", "cancelled"],
              description: "New status (required)"
            },
          },
          required: ["id", "status"],
        },
      },

      // ======================================================================
      // ADVANCED INVOICE TOOLS
      // ======================================================================
      {
        name: "list_advanced_invoices",
        description: "Get all advanced invoices with company header, customer, line items, and payment details.",
        inputSchema: {
          type: "object",
          properties: {},
        },
      },
      {
        name: "get_advanced_invoice",
        description: "Get a specific advanced invoice by ID with all details.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The advanced invoice ID (UUID)" },
          },
          required: ["id"],
        },
      },
      {
        name: "create_advanced_invoice",
        description: "Create a new advanced invoice with custom company header, customer details, line items, and payment information. All fields are manually entered.",
        inputSchema: {
          type: "object",
          properties: {
            invoiceDate: { type: "string", description: "Invoice date (YYYY-MM-DD, default: today)" },
            companyHeader: {
              type: "object",
              description: "Company information header",
              properties: {
                logoUrl: { type: "string", description: "Company logo URL" },
                companyName: { type: "string", description: "Company name" },
                companyEmail: { type: "string", description: "Company email" },
                companyPhone: { type: "string", description: "Company phone" },
                companyAddress: { type: "string", description: "Company address" },
              },
            },
            customer: {
              type: "object",
              description: "Customer details",
              properties: {
                name: { type: "string", description: "Customer name" },
                email: { type: "string", description: "Customer email" },
                phone: { type: "string", description: "Customer phone" },
                propertyAddress: { type: "string", description: "Property/billing address" },
                customerId: { type: "string", description: "Customer ID/reference" },
                contractFrom: { type: "string", description: "Contract start date (YYYY-MM-DD)" },
                contractTo: { type: "string", description: "Contract end date (YYYY-MM-DD)" },
              },
              required: ["name"],
            },
            lineItems: {
              type: "array",
              description: "Line items with date, reference, description, and amount",
              items: {
                type: "object",
                properties: {
                  date: { type: "string", description: "Item date (YYYY-MM-DD)" },
                  reference: { type: "string", description: "Reference code" },
                  description: { type: "string", description: "Item description" },
                  amount: { type: "number", description: "Item amount" },
                },
                required: ["description", "amount"],
              },
            },
            paymentDetails: {
              type: "object",
              description: "Payment information",
              properties: {
                bankName: { type: "string", description: "Bank name" },
                accountNumber: { type: "string", description: "Account number" },
                branchCode: { type: "string", description: "Branch code" },
                paymentReference: { type: "string", description: "Payment reference" },
                dueDate: { type: "string", description: "Payment due date (YYYY-MM-DD)" },
              },
            },
            currency: { type: "string", description: "Currency code (ZAR, USD, EUR, GBP - default: ZAR)" },
            notes: { type: "string", description: "Invoice notes" },
            footerText: { type: "string", description: "Footer text (e.g., 'No refunds')" },
            customFields: {
              type: "array",
              description: "Custom fields for additional information",
              items: {
                type: "object",
                properties: {
                  label: { type: "string", description: "Field label" },
                  value: { type: "string", description: "Field value" },
                  section: { type: "string", description: "Section (companyHeader, customer, paymentDetails, footer)" },
                  type: { type: "string", enum: ["text", "number", "date", "textarea"], description: "Field type" },
                  showOnPrint: { type: "boolean", description: "Show on print" },
                },
              },
            },
          },
          required: ["customer", "lineItems", "paymentDetails"],
        },
      },
      {
        name: "update_advanced_invoice",
        description: "Update an existing advanced invoice.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The invoice ID (UUID, required)" },
            invoiceDate: { type: "string", description: "New invoice date" },
            companyHeader: {
              type: "object",
              description: "Updated company header",
              properties: {
                logoUrl: { type: "string" },
                companyName: { type: "string" },
                companyEmail: { type: "string" },
                companyPhone: { type: "string" },
                companyAddress: { type: "string" },
              },
            },
            customer: {
              type: "object",
              description: "Updated customer details",
              properties: {
                name: { type: "string" },
                email: { type: "string" },
                phone: { type: "string" },
                propertyAddress: { type: "string" },
                customerId: { type: "string" },
                contractFrom: { type: "string" },
                contractTo: { type: "string" },
              },
            },
            lineItems: {
              type: "array",
              description: "Updated line items",
              items: {
                type: "object",
                properties: {
                  date: { type: "string" },
                  reference: { type: "string" },
                  description: { type: "string" },
                  amount: { type: "number" },
                },
              },
            },
            paymentDetails: {
              type: "object",
              description: "Updated payment details",
              properties: {
                bankName: { type: "string" },
                accountNumber: { type: "string" },
                branchCode: { type: "string" },
                paymentReference: { type: "string" },
                dueDate: { type: "string" },
              },
            },
            currency: { type: "string", description: "Currency" },
            notes: { type: "string", description: "Notes" },
            footerText: { type: "string", description: "Footer text" },
            customFields: {
              type: "array",
              description: "Custom fields",
              items: {
                type: "object",
                properties: {
                  label: { type: "string" },
                  value: { type: "string" },
                  section: { type: "string" },
                  type: { type: "string" },
                  showOnPrint: { type: "boolean" },
                },
              },
            },
          },
          required: ["id"],
        },
      },
      {
        name: "delete_advanced_invoice",
        description: "Delete an advanced invoice.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "The invoice ID (UUID, required)" },
          },
          required: ["id"],
        },
      },

      // ======================================================================
      // KNOWLEDGE BASE TOOLS
      // ======================================================================
      {
        name: "list_kb_folders",
        description: "Get all folders in the knowledge base.",
        inputSchema: {
          type: "object",
          properties: {
            parentId: { type: "string", description: "Filter by parent folder ID" }
          },
        },
      },
      {
        name: "create_kb_folder",
        description: "Create a new folder in the knowledge base.",
        inputSchema: {
          type: "object",
          properties: {
            name: { type: "string", description: "Folder name (required, URL-safe characters only)" },
            parentId: { type: "string", description: "Parent folder ID (optional)" },
          },
          required: ["name"],
        },
      },
      {
        name: "update_kb_folder",
        description: "Rename a knowledge base folder.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "Folder ID (UUID, required)" },
            name: { type: "string", description: "New folder name" },
          },
          required: ["id", "name"],
        },
      },
      {
        name: "delete_kb_folder",
        description: "Delete a knowledge base folder (must be empty).",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "Folder ID (UUID, required)" },
          },
          required: ["id"],
        },
      },
      {
        name: "list_kb_files",
        description: "Get all files in a knowledge base folder.",
        inputSchema: {
          type: "object",
          properties: {
            folderId: { type: "string", description: "Folder ID (required)" },
          },
          required: ["folderId"],
        },
      },
      {
        name: "upload_kb_file",
        description: "Upload a new file to the knowledge base. Requires file content as base64 string.",
        inputSchema: {
          type: "object",
          properties: {
            folderId: { type: "string", description: "Target folder ID (required)" },
            filename: { type: "string", description: "File name with extension (required)" },
            content: { type: "string", description: "File content as base64 string (required)" },
          },
          required: ["folderId", "filename", "content"],
        },
      },
      {
        name: "get_kb_file",
        description: "Get a knowledge base file with content.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "File ID (UUID, required)" },
          },
          required: ["id"],
        },
      },
      {
        name: "update_kb_file",
        description: "Update a knowledge base file (rename or update content).",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "File ID (UUID, required)" },
            name: { type: "string", description: "New file name" },
            content: { type: "string", description: "New file content as base64" },
          },
          required: ["id"],
        },
      },
      {
        name: "delete_kb_file",
        description: "Delete a knowledge base file.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "File ID (UUID, required)" },
          },
          required: ["id"],
        },
      },
      {
        name: "move_kb_file",
        description: "Move a knowledge base file to another folder.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "File ID (UUID, required)" },
            targetFolderId: { type: "string", description: "Target folder ID (required)" },
          },
          required: ["id", "targetFolderId"],
        },
      },
      {
        name: "search_knowledge_base",
        description: "Search for files and folders in the knowledge base.",
        inputSchema: {
          type: "object",
          properties: {
            query: { type: "string", description: "Search query (required)" },
          },
          required: ["query"],
        },
      },

      // ======================================================================
      // KNOWLEDGE BASE TEXT TOOLS (Easy upload/download without base64)
      // ======================================================================
      ...kbTextToolDefs,

      // ======================================================================
      // SYSTEM TOOLS
      // ======================================================================
      {
        name: "test_connection",
        description: "Test the connection to the OpenPlan.work API server.",
        inputSchema: {
          type: "object",
          properties: {},
        },
      },
      {
        name: "get_system_status",
        description: "Get system status including storage usage and configuration.",
        inputSchema: {
          type: "object",
          properties: {},
        },
      },
      {
        name: "search_all",
        description: "Search across all data (todos, tasks, projects, clients, notes).",
        inputSchema: {
          type: "object",
          properties: {
            query: { type: "string", description: "Search query (required)" },
          },
          required: ["query"],
        },
      },
    ],
  };
});

// ============================================================================
// TOOL HANDLERS
// ============================================================================

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    switch (name) {
      // ======================================================================
      // TODO HANDLERS
      // ======================================================================
      case "list_todos": {
        const result = await apiCall("todos.php");
        // Filter by status if specified
        if (args.status && args.status !== "all" && result.data) {
          result.data = result.data.filter(t => t.status === args.status);
        }
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "add_todo": {
        const body = {
          title: args.title,
          description: args.description || "",
          priority: args.priority || "medium",
          category: args.category || "",
          dueDate: args.dueDate ? new Date(args.dueDate).toISOString() : null,
        };
        const result = await apiCall("todos.php", "POST", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "update_todo": {
        const body = {};
        if (args.title) body.title = args.title;
        if (args.description) body.description = args.description;
        if (args.priority) body.priority = args.priority;
        if (args.status) body.status = args.status;
        if (args.dueDate) body.dueDate = new Date(args.dueDate).toISOString();

        const result = await apiCall(`todos.php?id=${args.id}`, "PUT", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "delete_todo": {
        const result = await apiCall(`todos.php?id=${args.id}`, "DELETE");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "complete_todo": {
        const result = await apiCall(`todos.php?id=${args.id}`, "PUT", { status: "completed" });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      // ======================================================================
      // PROJECT HANDLERS
      // ======================================================================
      case "list_projects": {
        const result = await apiCall("projects.php");
        if (args.status && args.status !== "all" && result.data) {
          result.data = result.data.filter(p => p.status === args.status);
        }
        if (args.clientId && result.data) {
          result.data = result.data.filter(p => p.clientId === args.clientId);
        }
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "get_project": {
        const result = await apiCall(`projects.php?id=${args.id}`);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "add_project": {
        const body = {
          name: args.name,
          description: args.description || "",
          clientId: args.clientId || null,
          status: args.status || "active",
          color: args.color || "#3B82F6",
        };
        const result = await apiCall("projects.php", "POST", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "update_project": {
        const body = {};
        if (args.name) body.name = args.name;
        if (args.description) body.description = args.description;
        if (args.clientId) body.clientId = args.clientId;
        if (args.status) body.status = args.status;
        if (args.color) body.color = args.color;

        const result = await apiCall(`projects.php?id=${args.id}`, "PUT", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "delete_project": {
        const result = await apiCall(`projects.php?id=${args.id}`, "DELETE");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      // ======================================================================
      // TASK HANDLERS
      // ======================================================================
      case "list_tasks": {
        const projectsResult = await apiCall("projects.php");
        let tasks = [];

        if (projectsResult.data) {
          for (const project of projectsResult.data) {
            if (project.tasks && Array.isArray(project.tasks)) {
              const projectTasks = project.tasks.map(t => ({
                ...t,
                projectId: project.id,
                projectName: project.name,
              }));
              tasks = tasks.concat(projectTasks);
            }
          }
        }

        // Apply filters
        if (args.projectId) {
          tasks = tasks.filter(t => t.projectId === args.projectId);
        }
        if (args.status) {
          tasks = tasks.filter(t => t.status === args.status);
        }
        if (args.priority) {
          tasks = tasks.filter(t => t.priority === args.priority);
        }

        return {
          content: [{ type: "text", text: JSON.stringify({ success: true, data: tasks }, null, 2) }],
        };
      }

      case "add_task": {
        const body = {
          projectId: args.projectId,
          title: args.title,
          description: args.description || "",
          status: args.status || "todo",
          priority: args.priority || "medium",
          dueDate: args.dueDate ? new Date(args.dueDate).toISOString() : null,
          estimatedMinutes: args.estimatedMinutes || 0,
        };
        const result = await apiCall("tasks.php", "POST", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "update_task": {
        const body = {};
        if (args.title) body.title = args.title;
        if (args.description) body.description = args.description;
        if (args.status) body.status = args.status;
        if (args.priority) body.priority = args.priority;
        if (args.dueDate) body.dueDate = new Date(args.dueDate).toISOString();
        if (args.estimatedMinutes) body.estimatedMinutes = args.estimatedMinutes;

        const result = await apiCall(`tasks.php?projectId=${args.projectId}&id=${args.taskId}`, "PUT", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "delete_task": {
        const result = await apiCall(`tasks.php?projectId=${args.projectId}&id=${args.taskId}`, "DELETE");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "complete_task": {
        const result = await apiCall(`tasks.php?projectId=${args.projectId}&id=${args.taskId}`, "PUT", { status: "done" });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "add_subtask": {
        const result = await apiCall(`tasks.php?projectId=${args.projectId}&id=${args.taskId}&action=subtask`, "POST", {
          title: args.title,
          estimatedMinutes: args.estimatedMinutes || 0,
        });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      // ======================================================================
      // CLIENT HANDLERS
      // ======================================================================
      case "list_clients": {
        const result = await apiCall("clients.php");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "get_client": {
        const result = await apiCall(`clients.php?id=${args.id}`);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "add_client": {
        const body = {
          name: args.name,
          email: args.email || "",
          phone: args.phone || "",
          company: args.company || "",
          address: {
            street: args.street || "",
            city: args.city || "",
            state: args.state || "",
            zip: args.zip || "",
            country: args.country || "",
          },
          notes: args.notes || "",
        };
        const result = await apiCall("clients.php", "POST", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "update_client": {
        const body = {};
        if (args.name) body.name = args.name;
        if (args.email) body.email = args.email;
        if (args.phone) body.phone = args.phone;
        if (args.company) body.company = args.company;
        if (args.notes) body.notes = args.notes;
        if (args.street || args.city || args.state || args.zip || args.country) {
          body.address = {
            street: args.street || "",
            city: args.city || "",
            state: args.state || "",
            zip: args.zip || "",
            country: args.country || "",
          };
        }

        const result = await apiCall(`clients.php?id=${args.id}`, "PUT", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "delete_client": {
        const result = await apiCall(`clients.php?id=${args.id}`, "DELETE");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      // ======================================================================
      // WATER HANDLERS
      // ======================================================================
      case "get_water_status": {
        const date = args.date || new Date().toISOString().split("T")[0];
        const result = await apiCall(`habits.php?action=get_water_tracker&date=${date}`);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "log_water": {
        const glasses = args.glasses || 1;
        const date = args.date || new Date().toISOString().split("T")[0];
        const result = await apiCall(`habits.php?action=add_water_glass`, "POST", {
          count: glasses,
          goal: 8,
          reminderInterval: 60,
          date: date
        });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "set_water_goal": {
        const result = await apiCall("habits.php?action=set_water_goal", "POST", { goal: args.goal });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "get_water_history": {
        const startDate = args.startDate || new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split("T")[0];
        const endDate = args.endDate || new Date().toISOString().split("T")[0];

        // Fetch history by iterating through dates
        let history = [];
        const start = new Date(startDate);
        const end = new Date(endDate);

        for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
          const dateStr = d.toISOString().split("T")[0];
          const result = await apiCall(`habits.php?action=get_water_tracker&date=${dateStr}`);
          if (result.data) {
            history.push({ date: dateStr, ...result.data });
          }
        }

        return {
          content: [{ type: "text", text: JSON.stringify({ success: true, data: history }, null, 2) }],
        };
      }

      case "generate_water_plan": {
        const result = await apiCall("habits.php?action=generate_water_plan", "POST", {
          weight: args.weight || 70,
          activity: args.activity || "moderate",
          climate: args.climate || "temperate",
          goal: args.goal || 2500,
          wakeTime: args.wakeTime || "07:00",
          sleepTime: args.sleepTime || "23:00",
          glassSize: args.glassSize || 250
        });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "list_water_plan_history": {
        const result = await apiCall("habits.php?action=list_water_plan_history");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "save_water_plan_history": {
        const result = await apiCall("habits.php?action=save_water_plan_history", "POST", {
          planId: args.planId
        });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      // ======================================================================
      // HABIT HANDLERS
      // ======================================================================
      case "list_habits": {
        const result = await apiCall("habits.php");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "add_habit": {
        const body = {
          name: args.name,
          category: args.category || "general",
          frequency: args.frequency || "daily",
          reminderTime: args.reminderTime || null,
          targetDuration: args.targetDuration || 0,
        };
        const result = await apiCall("habits.php", "POST", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "complete_habit": {
        const date = args.date || new Date().toISOString().split("T")[0];
        const result = await apiCall("habits.php?action=complete", "POST", {
          habitId: args.habitId,
          date: date
        });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "get_habit_stats": {
        const result = await apiCall(`habits.php?action=stats&habitId=${args.habitId || ""}&days=${args.days || 30}`);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "start_habit_timer": {
        const result = await apiCall(`habits.php?action=start_timer`, "POST", { habitId: args.habitId });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "stop_habit_timer": {
        const result = await apiCall(`habits.php?action=stop_timer`, "POST", { habitId: args.habitId });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "delete_habit": {
        const result = await apiCall(`habits.php?id=${args.habitId}`, "DELETE");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      // ======================================================================
      // NOTES HANDLERS
      // ======================================================================
      case "list_notes": {
        const params = {};
        if (args.tag) params.tag = args.tag;
        if (args.search) params.search = args.search;
        if (args.isPinned !== undefined) params.isPinned = args.isPinned;
        if (args.isFavorite !== undefined) params.isFavorite = args.isFavorite;

        const result = await apiCall("notes.php", "GET", null, params);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "get_note": {
        const result = await apiCall(`notes.php?id=${args.id}`);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "create_note": {
        const body = {
          title: args.title,
          content: args.content || "",
          tags: args.tags || [],
          color: args.color || "",
        };
        const result = await apiCall("notes.php", "POST", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "update_note": {
        const body = {};
        if (args.title !== undefined) body.title = args.title;
        if (args.content !== undefined) body.content = args.content;
        if (args.tags !== undefined) body.tags = args.tags;
        if (args.color !== undefined) body.color = args.color;
        if (args.isPinned !== undefined) body.isPinned = args.isPinned;
        if (args.isFavorite !== undefined) body.isFavorite = args.isFavorite;

        const result = await apiCall(`notes.php?id=${args.id}`, "PUT", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "delete_note": {
        const result = await apiCall(`notes.php?id=${args.id}`, "DELETE");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "search_notes": {
        const result = await apiCall(`notes.php?action=search&query=${encodeURIComponent(args.query)}`);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "list_note_tags": {
        const result = await apiCall("notes.php?action=tags");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "toggle_note_pin": {
        const result = await apiCall(`notes.php?action=pin&id=${args.id}`, "POST", {});
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "toggle_note_favorite": {
        const result = await apiCall(`notes.php?action=favorite&id=${args.id}`, "POST", {});
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "convert_note_to_kb": {
        // First, get the note
        const noteResult = await apiCall(`notes.php?id=${args.noteId}`);
        if (!noteResult.success) {
          return {
            content: [{ type: "text", text: JSON.stringify(noteResult, null, 2) }],
            isError: true,
          };
        }

        const note = noteResult.data;
        const format = args.format || "markdown";
        const extension = format === "xml" ? ".xml" : ".md";
        const filename = (args.filename || note.title || "note").toLowerCase().replace(/[^a-z0-9]+/g, "-") + extension;

        // Generate content based on format
        let content;
        if (format === "xml") {
          const escapeXml = (str) => {
            if (!str) return "";
            return String(str)
              .replace(/&/g, "&amp;")
              .replace(/</g, "&lt;")
              .replace(/>/g, "&gt;")
              .replace(/"/g, "&quot;")
              .replace(/'/g, "&apos;");
          };
          const tags = (note.tags || []).map(t => `    <tag>${escapeXml(t)}</tag>`).join("\n");
          content = `<?xml version="1.0" encoding="UTF-8"?>
<note>
  <title>${escapeXml(note.title || "Untitled")}</title>
  <content>${escapeXml(note.content || "")}</content>
  <tags>
${tags || "    "}
  </tags>
  <metadata>
    <createdAt>${note.createdAt || new Date().toISOString()}</createdAt>
    <updatedAt>${note.updatedAt || new Date().toISOString()}</updatedAt>
  </metadata>
</note>`;
        } else {
          content = `# ${note.title || "Note"}\n\n${note.content || ""}`;
        }

        // Convert content to base64
        const encodedContent = Buffer.from(content).toString("base64");

        // Upload to knowledge base
        const uploadResult = await apiCall("knowledge-base.php", "POST", {
          folderId: args.folderId,
          name: filename,
          type: format === "xml" ? "xml" : "markdown",
          content: encodedContent,
          size: content.length,
        }, { action: "upload_file" });

        return {
          content: [{ type: "text", text: JSON.stringify(uploadResult, null, 2) }],
        };
      }

      case "ai_edit_note": {
        // Get the note first
        const noteResult = await apiCall(`notes.php?id=${args.noteId}`);
        if (!noteResult.success) {
          return {
            content: [{ type: "text", text: JSON.stringify(noteResult, null, 2) }],
            isError: true,
          };
        }

        // Call AI edit endpoint
        const aiBody = {
          noteId: args.noteId,
          operation: args.operation,
          provider: args.provider || undefined,
          model: args.model || undefined,
        };

        const aiResult = await apiCall("ai.php?action=edit_note", "POST", aiBody);

        if (!aiResult.success) {
          return {
            content: [{ type: "text", text: JSON.stringify(aiResult, null, 2) }],
            isError: true,
          };
        }

        // Update the note with edited content
        const updateResult = await apiCall(`notes.php?id=${args.noteId}`, "PUT", {
          content: aiResult.data.content,
        });

        return {
          content: [{ type: "text", text: JSON.stringify({
            success: true,
            message: `Note ${args.operation} completed`,
            edited: aiResult.data,
            update: updateResult,
          }, null, 2) }],
        };
      }

      // ======================================================================
      // FINANCE HANDLERS
      // ======================================================================
      case "list_transactions": {
        const params = {};
        if (args.type) params.type = args.type;
        if (args.startDate) params.startDate = args.startDate;
        if (args.endDate) params.endDate = args.endDate;

        const result = await apiCall("finance.php", "GET", null, params);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "add_transaction": {
        // Ensure date is in ISO format
        let date = args.date;
        if (!date) {
          date = new Date().toISOString();
        } else if (!date.includes("T")) {
          // If just YYYY-MM-DD, add time
          date = new Date(date).toISOString();
        }

        const body = {
          type: args.type,
          amount: parseFloat(args.amount),
          description: args.description,
          category: args.category || "",
          date,
          projectId: args.projectId || null,
          clientId: args.clientId || null,
          notes: args.notes || "",
        };
        const result = await apiCall("finance.php", "POST", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "get_finance_summary": {
        const params = {};
        if (args.startDate) params.startDate = args.startDate;
        if (args.endDate) params.endDate = args.endDate;

        const result = await apiCall("finance.php?action=summary", "GET", null, params);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      // ======================================================================
      // INVENTORY HANDLERS
      // ======================================================================
      case "list_inventory": {
        const params = {};
        if (args.category) params.category = args.category;
        if (args.lowStock) params.lowStock = "true";

        const result = await apiCall("inventory.php", "GET", null, params);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "add_inventory_item": {
        const body = {
          name: args.name,
          sku: args.sku || "",
          description: args.description || "",
          category: args.category || "",
          quantity: args.quantity || 0,
          unitPrice: args.unitPrice || 0,
          costPrice: args.costPrice || 0,
          reorderPoint: args.reorderPoint || 5,
          supplier: args.supplier || "",
          notes: args.notes || "",
        };
        const result = await apiCall("inventory.php", "POST", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "update_inventory_stock": {
        const body = { quantity: args.quantity };
        const result = await apiCall(`inventory.php?id=${args.id}`, "PUT", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "adjust_inventory_stock": {
        const result = await apiCall(`inventory.php?id=${args.id}&action=adjust`, "POST", {
          adjustment: args.adjustment,
          notes: args.notes || "",
        });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      // ======================================================================
      // INVOICE HANDLERS
      // ======================================================================
      case "list_invoices": {
        const params = {};
        if (args.status) params.status = args.status;
        if (args.clientId) params.clientId = args.clientId;

        const result = await apiCall("invoices.php", "GET", null, params);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "create_invoice": {
        // Ensure dueDate is in YYYY-MM-DD format
        let dueDate = args.dueDate;
        if (!dueDate) {
          dueDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split("T")[0];
        } else if (dueDate.includes("T")) {
          dueDate = dueDate.split("T")[0];
        }

        // Send raw data, let API calculate totals
        const body = {
          clientId: args.clientId,
          projectId: args.projectId || null,
          dueDate,
          notes: args.notes || "",
          // Pass line items directly
          items: args.lineItems || [],
        };
        const result = await apiCall("invoices.php", "POST", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "update_invoice_status": {
        const result = await apiCall(`invoices.php?id=${args.id}`, "PUT", { status: args.status });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      // ======================================================================
      // ADVANCED INVOICE HANDLERS
      // ======================================================================
      case "list_advanced_invoices": {
        const result = await apiCall("advanced-invoices.php");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "get_advanced_invoice": {
        const result = await apiCall(`advanced-invoices.php?id=${args.id}`);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "create_advanced_invoice": {
        const body = {
          invoiceDate: args.invoiceDate || new Date().toISOString().split("T")[0],
          companyHeader: args.companyHeader || {},
          customer: args.customer,
          lineItems: args.lineItems || [],
          paymentDetails: args.paymentDetails,
          currency: args.currency || "ZAR",
          notes: args.notes || "",
          footerText: args.footerText || "",
          customFields: args.customFields || [],
        };
        const result = await apiCall("advanced-invoices.php", "POST", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "update_advanced_invoice": {
        const body = {};
        if (args.invoiceDate !== undefined) body.invoiceDate = args.invoiceDate;
        if (args.companyHeader !== undefined) body.companyHeader = args.companyHeader;
        if (args.customer !== undefined) body.customer = args.customer;
        if (args.lineItems !== undefined) body.lineItems = args.lineItems;
        if (args.paymentDetails !== undefined) body.paymentDetails = args.paymentDetails;
        if (args.currency !== undefined) body.currency = args.currency;
        if (args.notes !== undefined) body.notes = args.notes;
        if (args.footerText !== undefined) body.footerText = args.footerText;
        if (args.customFields !== undefined) body.customFields = args.customFields;

        const result = await apiCall(`advanced-invoices.php?id=${args.id}`, "PUT", body);
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "delete_advanced_invoice": {
        const result = await apiCall(`advanced-invoices.php?id=${args.id}`, "DELETE");
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      // ======================================================================
      // KNOWLEDGE BASE HANDLERS
      // ======================================================================
      case "list_kb_folders": {
        const params = {};
        if (args.parentId) params.parentId = args.parentId;
        const result = await apiCall("knowledge-base.php", "GET", null, { action: "list_folders", ...params });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "create_kb_folder": {
        const body = {
          name: args.name,
          parentId: args.parentId || null,
        };
        const result = await apiCall("knowledge-base.php", "POST", body, { action: "create_folder" });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "update_kb_folder": {
        const body = { name: args.name };
        const result = await apiCall("knowledge-base.php", "PUT", body, { action: "update_folder" });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "delete_kb_folder": {
        const result = await apiCall("knowledge-base.php", "DELETE", { id: args.id }, { action: "delete_folder" });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "list_kb_files": {
        const result = await apiCall("knowledge-base.php", "GET", null, { action: "list_files", folderId: args.folderId });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "upload_kb_file": {
        console.log("[upload_kb_file] Received args:", JSON.stringify(args));
        const body = {
          content: args.content,
          folderId: args.folderId,
          name: args.filename,
        };
        console.log("[upload_kb_file] Calling API with body:", JSON.stringify(body));
        const result = await apiCall("knowledge-base.php", "POST", body, { action: "upload_file" });
        console.log("[upload_kb_file] API result:", JSON.stringify(result));
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "get_kb_file": {
        const result = await apiCall("knowledge-base.php", "GET", null, { action: "get_file", id: args.id });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "update_kb_file": {
        const body = {};
        if (args.name) body.name = args.name;
        if (args.content) body.content = args.content;

        const result = await apiCall("knowledge-base.php", "PUT", body, { action: "update_file", id: args.id });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "delete_kb_file": {
        const result = await apiCall("knowledge-base.php", "DELETE", { id: args.id }, { action: "delete_file" });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "move_kb_file": {
        const body = { targetFolderId: args.targetFolderId };
        const result = await apiCall("knowledge-base.php", "POST", body, { action: "move_file", id: args.id });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "search_knowledge_base": {
        const result = await apiCall("knowledge-base.php", "GET", null, { action: "search", q: args.query });
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      // ======================================================================
      // KNOWLEDGE BASE TEXT HANDLERS (Easy upload/download without base64)
      // ======================================================================
      case "upload_kb_file_text":
      case "get_kb_file_text":
      case "update_kb_file_text":
      case "create_kb_md_file":
      case "create_kb_xml_file":
      case "batch_upload_kb_text": {
        // These handlers are imported from kb-text-tools.js
        if (kbTextToolHandlers[name]) {
          return await kbTextToolHandlers[name](args, apiCall);
        }
        throw new Error(`Handler not found for tool: ${name}`);
      }

      // ======================================================================
      // SYSTEM HANDLERS
      // ======================================================================
      case "test_connection": {
        const result = await apiCall("auth.php?action=status");
        if (result.success) {
          return {
            content: [{ type: "text", text: JSON.stringify({
              success: true,
              message: "Connection to OpenPlan.work API successful",
              server: "openplan-work",
              apiUrl: API_URL,
            }, null, 2) }],
          };
        }
        throw new Error("Failed to connect to OpenPlan.work API");
      }

      case "get_system_status": {
        const [authResult, configResult] = await Promise.all([
          apiCall("auth.php?action=status"),
          apiCall("settings.php"),
        ]);

        return {
          content: [{ type: "text", text: JSON.stringify({
            success: true,
            authenticated: authResult.success,
            config: configResult.data,
            apiUrl: API_URL,
          }, null, 2) }],
        };
      }

      case "search_all": {
        const [todos, projects, clients] = await Promise.all([
          apiCall("todos.php"),
          apiCall("projects.php"),
          apiCall("clients.php"),
        ]);

        const query = args.query.toLowerCase();
        const results = {
          todos: (todos.data || []).filter(t =>
            t.title?.toLowerCase().includes(query) ||
            t.description?.toLowerCase().includes(query)
          ),
          projects: (projects.data || []).filter(p =>
            p.name?.toLowerCase().includes(query) ||
            p.description?.toLowerCase().includes(query)
          ),
          clients: (clients.data || []).filter(c =>
            c.name?.toLowerCase().includes(query) ||
            c.company?.toLowerCase().includes(query) ||
            c.email?.toLowerCase().includes(query)
          ),
        };

        return {
          content: [{ type: "text", text: JSON.stringify({
            success: true,
            query: args.query,
            results,
          }, null, 2) }],
        };
      }

      default:
        throw new Error(`Unknown tool: ${name}`);
    }
  } catch (error) {
    return {
      content: [{ type: "text", text: `Error: ${error.message}` }],
      isError: true,
    };
  }
});

// ============================================================================
// START SERVER
// ============================================================================

const transport = new StdioServerTransport();
await server.connect(transport);
