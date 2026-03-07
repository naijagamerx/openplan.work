import { Client } from "../mcp-server/node_modules/@modelcontextprotocol/sdk/dist/client/index.js";
import { StdioClientTransport } from "../mcp-server/node_modules/@modelcontextprotocol/sdk/dist/client/stdio.js";

const API_URL = process.env.MCP_API_URL || "http://localhost:4041/api";
const MASTER_PASSWORD = process.env.MCP_MASTER_PASSWORD || process.env.MASTER_PASSWORD || "";
const USER_EMAIL = process.env.MCP_USER_EMAIL || process.env.USER_EMAIL || "";

if (!MASTER_PASSWORD || !USER_EMAIL) {
  console.error("Missing MCP credentials. Set MCP_MASTER_PASSWORD and MCP_USER_EMAIL.");
  process.exit(1);
}

function parseToolPayload(result) {
  const text = result?.content?.[0]?.text ?? "";
  try {
    return JSON.parse(text);
  } catch {
    return { raw: text };
  }
}

function extractId(payload) {
  if (!payload || typeof payload !== "object") return "";
  if (typeof payload.id === "string") return payload.id;
  if (payload.data && typeof payload.data.id === "string") return payload.data.id;
  if (payload.data && payload.data.item && typeof payload.data.item.id === "string") return payload.data.item.id;
  if (payload.data && payload.data.invoice && typeof payload.data.invoice.id === "string") return payload.data.invoice.id;
  if (payload.data && payload.data.project && typeof payload.data.project.id === "string") return payload.data.project.id;
  if (payload.data && Array.isArray(payload.data) && payload.data.length && typeof payload.data[0]?.id === "string") {
    return payload.data[0].id;
  }
  return "";
}

async function run() {
  const report = {
    toolsCount: 0,
    channels: {},
    created: {},
    failures: [],
  };

  const transport = new StdioClientTransport({
    command: "node",
    args: ["mcp-server/index.js"],
    env: {
      ...process.env,
      API_URL,
      MASTER_PASSWORD,
      USER_EMAIL,
    },
    cwd: process.cwd(),
  });

  const client = new Client(
    {
      name: "mcp-smoke-client",
      version: "1.0.0",
    },
    {
      capabilities: {},
    }
  );

  const call = async (name, args = {}) => {
    const result = await client.callTool({ name, arguments: args });
    const parsed = parseToolPayload(result);
    const ok = parsed?.success !== false && !result?.isError;
    report.channels[name] = ok ? "ok" : "failed";
    if (!ok) {
      report.failures.push({ tool: name, response: parsed });
    }
    return parsed;
  };

  try {
    await client.connect(transport);
    const tools = await client.listTools();
    report.toolsCount = tools?.tools?.length ?? 0;

    // System
    await call("test_connection");
    await call("get_system_status");

    // Read-only checks per channel
    await call("list_todos");
    await call("list_projects");
    await call("list_tasks");
    await call("list_clients");
    await call("list_invoices");
    await call("list_transactions");
    await call("list_inventory");
    await call("get_water_status");
    await call("list_habits");
    await call("list_notes");
    await call("list_kb_folders");
    await call("list_advanced_invoices");
    await call("search_all", { query: "OpenPlan" });

    // Create records requested by user
    const unique = Date.now().toString();
    const clientPayload = await call("add_client", {
      name: `MCP QA Client ${unique}`,
      email: `mcp.qa.${unique}@example.com`,
      company: "OpenPlan QA",
      phone: "+10000000000",
    });
    const clientId = extractId(clientPayload);
    report.created.clientId = clientId;

    const projectPayload = await call("add_project", {
      name: `MCP QA Project ${unique}`,
      description: "Created via MCP channel smoke test",
      status: "active",
      color: "#111827",
    });
    const projectId = extractId(projectPayload);
    report.created.projectId = projectId;

    const taskPayload = await call("add_task", {
      projectId,
      title: `MCP QA Task ${unique}`,
      description: "Created via MCP",
      priority: "high",
      status: "todo",
      estimatedMinutes: 45,
    });
    report.created.taskId = extractId(taskPayload);

    const invoicePayload = await call("create_invoice", {
      clientId,
      projectId,
      lineItems: [
        { description: "MCP QA Service", quantity: 1, unitPrice: 500 },
        { description: "MCP QA Support", quantity: 2, unitPrice: 125 },
      ],
      notes: "Created via MCP smoke test",
      dueDate: "2026-04-01",
    });
    report.created.invoiceId = extractId(invoicePayload);
  } finally {
    await client.close();
  }

  console.log(JSON.stringify(report, null, 2));

  if (report.failures.length > 0) {
    process.exit(2);
  }
}

run().catch((error) => {
  console.error("MCP smoke test crashed:", error);
  process.exit(1);
});
