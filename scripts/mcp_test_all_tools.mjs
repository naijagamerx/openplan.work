import { spawn } from 'node:child_process';

const API_URL = process.env.API_URL || 'http://localhost:4041/api';
const MASTER_PASSWORD = process.env.MASTER_PASSWORD || '';
const USER_EMAIL = process.env.USER_EMAIL || '';

if (!MASTER_PASSWORD || !USER_EMAIL) {
  console.error('Set API_URL, MASTER_PASSWORD, USER_EMAIL first.');
  process.exit(1);
}

function writeRpc(child, payload) {
  const body = Buffer.from(JSON.stringify(payload), 'utf8');
  child.stdin.write(`Content-Length: ${body.length}\r\n\r\n`);
  child.stdin.write(body);
}

function extractId(payload) {
  if (!payload || typeof payload !== 'object') return '';
  if (typeof payload.id === 'string') return payload.id;
  if (payload.data && typeof payload.data.id === 'string') return payload.data.id;
  return '';
}

function parsePayload(result) {
  const text = result?.content?.[0]?.text || '';
  try {
    return JSON.parse(text);
  } catch {
    return { raw: text };
  }
}

function createMcpClient() {
  const child = spawn('node', ['mcp-server/index.js'], {
    cwd: process.cwd(),
    env: { ...process.env, API_URL, MASTER_PASSWORD, USER_EMAIL },
    stdio: ['pipe', 'pipe', 'pipe'],
  });

  let readBuffer = Buffer.alloc(0);
  const pending = new Map();
  let seq = 1;

  child.stdout.on('data', (chunk) => {
    readBuffer = Buffer.concat([readBuffer, Buffer.from(chunk)]);
    while (true) {
      const headerEnd = readBuffer.indexOf('\r\n\r\n');
      if (headerEnd === -1) return;

      const header = readBuffer.slice(0, headerEnd).toString('utf8');
      const match = header.match(/content-length:\s*(\d+)/i);
      if (!match) {
        readBuffer = Buffer.alloc(0);
        return;
      }
      const len = Number(match[1]);
      const bodyStart = headerEnd + 4;
      const total = bodyStart + len;
      if (readBuffer.length < total) return;

      const body = readBuffer.slice(bodyStart, total).toString('utf8');
      readBuffer = readBuffer.slice(total);

      let msg;
      try {
        msg = JSON.parse(body);
      } catch {
        continue;
      }

      if (msg && Object.prototype.hasOwnProperty.call(msg, 'id') && pending.has(msg.id)) {
        const { resolve, reject } = pending.get(msg.id);
        pending.delete(msg.id);
        if (msg.error) reject(msg.error);
        else resolve(msg.result);
      }
    }
  });

  child.stderr.on('data', () => {});

  async function call(method, params = undefined) {
    const id = seq++;
    const req = { jsonrpc: '2.0', id, method };
    if (params !== undefined) req.params = params;
    const promise = new Promise((resolve, reject) => {
      pending.set(id, { resolve, reject });
      setTimeout(() => {
        if (pending.has(id)) {
          pending.delete(id);
          reject(new Error(`RPC timeout: ${method}`));
        }
      }, 30000);
    });
    writeRpc(child, req);
    return promise;
  }

  return {
    async init() {
      await call('initialize', {
        protocolVersion: '2024-11-05',
        capabilities: {},
        clientInfo: { name: 'mcp-all-tools-check', version: '1.0.0' },
      });
      writeRpc(child, { jsonrpc: '2.0', method: 'notifications/initialized' });
    },
    call,
    close() {
      child.stdin.end();
      child.kill();
    },
  };
}

async function run() {
  const client = createMcpClient();
  const results = [];
  const state = {};
  const stamp = Date.now();

  async function callTool(name, args = {}) {
    try {
      const result = await client.call('tools/call', { name, arguments: args });
      const payload = parsePayload(result);
      const ok = result?.isError !== true && payload?.success !== false;
      results.push({ tool: name, ok, args, error: ok ? null : payload });
      return { ok, payload };
    } catch (error) {
      results.push({ tool: name, ok: false, args, error: { message: String(error?.message || error) } });
      return { ok: false, payload: null };
    }
  }

  try {
    await client.init();
    const listed = await client.call('tools/list');
    const toolNames = (listed?.tools || []).map((t) => t.name);
    state.toolsCount = toolNames.length;

    await callTool('test_connection');
    await callTool('get_system_status');
    await callTool('search_all', { query: 'OpenPlan', limit: 3 });

    await callTool('list_todos');
    let r = await callTool('add_todo', { title: `MCP Toolcheck Todo ${stamp}`, status: 'todo' });
    state.todoId = extractId(r.payload);
    if (state.todoId) {
      await callTool('update_todo', { id: state.todoId, description: 'updated via MCP toolcheck' });
      await callTool('complete_todo', { id: state.todoId });
    }

    await callTool('list_projects');
    r = await callTool('add_project', { name: `MCP Toolcheck Project ${stamp}`, description: 'Created for full MCP check', status: 'active', color: '#1f2937' });
    state.projectId = extractId(r.payload);
    if (state.projectId) {
      await callTool('get_project', { id: state.projectId });
      await callTool('update_project', { id: state.projectId, description: 'Project updated via MCP toolcheck' });
    }

    await callTool('list_tasks');
    r = await callTool('add_task', { projectId: state.projectId, title: `MCP Toolcheck Task ${stamp}`, description: 'Task created for full MCP check', priority: 'high', status: 'todo' });
    state.taskId = extractId(r.payload);
    if (state.taskId && state.projectId) {
      await callTool('update_task', { id: state.taskId, description: 'Task updated via MCP toolcheck', priority: 'medium' });
      await callTool('add_subtask', { taskId: state.taskId, projectId: state.projectId, title: 'Subtask from MCP toolcheck' });
      await callTool('complete_task', { id: state.taskId });
      await callTool('delete_task', { id: state.taskId });
    }

    await callTool('list_clients');
    r = await callTool('add_client', { name: `MCP Toolcheck Client ${stamp}`, email: `mcp.toolcheck.${stamp}@example.com`, company: 'Toolcheck Co' });
    state.clientId = extractId(r.payload);
    if (state.clientId) {
      await callTool('get_client', { id: state.clientId });
      await callTool('update_client', { id: state.clientId, phone: '+10123456789' });
    }

    await callTool('list_invoices');
    r = await callTool('create_invoice', {
      clientId: state.clientId,
      projectId: state.projectId,
      dueDate: '2026-04-01',
      notes: 'Created by MCP all-tools checker',
      lineItems: [{ description: 'MCP toolcheck service', quantity: 1, unitPrice: 100 }],
    });
    state.invoiceId = extractId(r.payload);
    if (state.invoiceId) {
      await callTool('update_invoice_status', { id: state.invoiceId, status: 'sent' });
    }

    await callTool('list_advanced_invoices');
    await callTool('list_transactions');
    await callTool('add_transaction', { type: 'expense', description: `MCP Toolcheck Transaction ${stamp}`, amount: 1.11, category: 'Testing' });
    await callTool('get_finance_summary');

    await callTool('list_inventory');
    r = await callTool('add_inventory_item', { name: `MCP Toolcheck Item ${stamp}`, quantity: 1, unitPrice: 1.5, costPrice: 1.0 });
    state.inventoryId = extractId(r.payload);
    if (state.inventoryId) {
      await callTool('update_inventory_stock', { id: state.inventoryId, quantity: 2 });
      await callTool('adjust_inventory_stock', { id: state.inventoryId, adjustment: 1, note: 'MCP toolcheck adjust' });
    }

    await callTool('get_water_status');
    await callTool('log_water', { glasses: 1 });
    await callTool('set_water_goal', { goal: 8, reminderInterval: 60 });

    r = await callTool('list_habits');
    const habits = Array.isArray(r.payload?.data) ? r.payload.data : [];
    const habitId = habits[0]?.id || `mcp-toolcheck-habit-${stamp}`;
    await callTool('complete_habit', { habitId, date: new Date().toISOString().slice(0, 10), status: 'complete' });

    await callTool('list_notes');
    await callTool('add_note', { title: `MCP Toolcheck Note ${stamp}`, content: 'Created by MCP all-tools checker.' });

    r = await callTool('list_kb_folders');
    const folders = Array.isArray(r.payload?.data?.folders) ? r.payload.data.folders : [];
    await callTool('list_kb_files', folders[0] ? { folderId: folders[0].id } : {});

    if (state.projectId) await callTool('delete_project', { id: state.projectId });
    if (state.clientId) await callTool('delete_client', { id: state.clientId });
    if (state.todoId) await callTool('delete_todo', { id: state.todoId });
  } finally {
    client.close();
  }

  const passed = results.filter((r) => r.ok).length;
  const failed = results.filter((r) => !r.ok);
  const summary = {
    toolsAdvertised: state.toolsCount,
    callsAttempted: results.length,
    passed,
    failedCount: failed.length,
    failed,
    createdArtifacts: {
      invoiceId: state.invoiceId || null,
      inventoryId: state.inventoryId || null,
      noteTitle: `MCP Toolcheck Note ${stamp}`,
      transactionDescription: `MCP Toolcheck Transaction ${stamp}`,
    },
  };

  console.log(JSON.stringify(summary, null, 2));
  process.exit(failed.length === 0 ? 0 : 2);
}

run().catch((error) => {
  console.error('MCP all-tools test crashed:', error);
  process.exit(1);
});
