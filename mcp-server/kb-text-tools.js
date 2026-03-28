/**
 * Knowledge Base Text Tools - Easy file upload/download without base64 hassles
 * 
 * These tools automatically handle base64 encoding/decoding so coding agents
 * can work with plain markdown and XML text directly.
 */

// ============================================================================
// TOOL DEFINITIONS
// ============================================================================

export const toolDefinitions = [
  // Tool 1: Upload plain text file (auto-detects MD or XML from extension)
  {
    name: "upload_kb_file_text",
    description: "Upload a text file to knowledge base without base64 encoding. Automatically handles encoding. Supports .md and .xml files.",
    inputSchema: {
      type: "object",
      properties: {
        folderId: { 
          type: "string", 
          description: "Target folder ID (UUID, required)" 
        },
        filename: { 
          type: "string", 
          description: "File name with extension .md or .xml (required)" 
        },
        content: { 
          type: "string", 
          description: "File content as plain text (markdown or XML). Will be automatically base64 encoded." 
        },
      },
      required: ["folderId", "filename", "content"],
    },
  },

  // Tool 2: Get file with decoded text content
  {
    name: "get_kb_file_text",
    description: "Get a knowledge base file with automatically decoded text content. Returns plain text instead of base64.",
    inputSchema: {
      type: "object",
      properties: {
        id: { 
          type: "string", 
          description: "File ID (UUID, required)" 
        },
      },
      required: ["id"],
    },
  },

  // Tool 3: Update file with plain text
  {
    name: "update_kb_file_text",
    description: "Update a knowledge base file with plain text content. Automatically base64 encodes the content.",
    inputSchema: {
      type: "object",
      properties: {
        id: { 
          type: "string", 
          description: "File ID (UUID, required)" 
        },
        name: { 
          type: "string", 
          description: "New file name (optional, keep .md or .xml extension)" 
        },
        content: { 
          type: "string", 
          description: "New file content as plain text. Will be automatically base64 encoded." 
        },
      },
      required: ["id"],
    },
  },

  // Tool 4: Create formatted Markdown file
  {
    name: "create_kb_md_file",
    description: "Create a new Markdown file with proper formatting and optional YAML frontmatter. Perfect for documentation.",
    inputSchema: {
      type: "object",
      properties: {
        folderId: { 
          type: "string", 
          description: "Target folder ID (UUID, required)" 
        },
        title: { 
          type: "string", 
          description: "Document title (required, used for H1 and filename)" 
        },
        body: { 
          type: "string", 
          description: "Markdown content/body (required)" 
        },
        tags: { 
          type: "array", 
          items: { type: "string" },
          description: "Optional tags array for frontmatter" 
        },
        author: { 
          type: "string", 
          description: "Optional author name for frontmatter" 
        },
        customFilename: { 
          type: "string", 
          description: "Optional custom filename (without .md extension). Auto-generated from title if not provided." 
        },
      },
      required: ["folderId", "title", "body"],
    },
  },

  // Tool 5: Create formatted XML file
  {
    name: "create_kb_xml_file",
    description: "Create a new XML file with proper formatting and optional metadata. Useful for structured data.",
    inputSchema: {
      type: "object",
      properties: {
        folderId: { 
          type: "string", 
          description: "Target folder ID (UUID, required)" 
        },
        title: { 
          type: "string", 
          description: "Document title (required, used for filename)" 
        },
        content: { 
          type: "string", 
          description: "XML content/body (required). Will be wrapped in proper XML structure." 
        },
        tags: { 
          type: "array", 
          items: { type: "string" },
          description: "Optional tags array for metadata" 
        },
        author: { 
          type: "string", 
          description: "Optional author name for metadata" 
        },
        customFilename: { 
          type: "string", 
          description: "Optional custom filename (without .xml extension). Auto-generated from title if not provided." 
        },
      },
      required: ["folderId", "title", "content"],
    },
  },

  // Tool 6: Batch upload multiple text files
  {
    name: "batch_upload_kb_text",
    description: "Upload multiple text files to knowledge base in one call. Automatically handles base64 encoding. Retries failed uploads up to 2 times for name conflicts.",
    inputSchema: {
      type: "object",
      properties: {
        folderId: { 
          type: "string", 
          description: "Target folder ID for all files (UUID, required)" 
        },
        files: { 
          type: "array", 
          description: "Array of files to upload (required)",
          items: {
            type: "object",
            properties: {
              filename: { 
                type: "string", 
                description: "File name with .md or .xml extension" 
              },
              content: { 
                type: "string", 
                description: "File content as plain text" 
              },
            },
            required: ["filename", "content"],
          },
        },
      },
      required: ["folderId", "files"],
    },
  },
];

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Format content as Markdown with YAML frontmatter
 */
function formatAsMarkdown(title, body, tags = [], author = null) {
  const timestamp = new Date().toISOString();
  const safeTags = tags.map(t => `"${t.replace(/"/g, '\\"')}"`).join(", ");
  
  let frontmatter = `---
title: "${title.replace(/"/g, '\\"')}"
date: ${timestamp}
`;
  
  if (author) {
    frontmatter += `author: "${author.replace(/"/g, '\\"')}"\n`;
  }
  
  if (tags.length > 0) {
    frontmatter += `tags: [${safeTags}]\n`;
  }
  
  frontmatter += `---\n\n`;
  
  return frontmatter + `# ${title}\n\n${body}`;
}

/**
 * Format content as XML with metadata
 */
function formatAsXml(title, content, tags = [], author = null) {
  const timestamp = new Date().toISOString();
  
  const escapeXml = (str) => {
    if (!str) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&apos;");
  };
  
  const tagXml = tags.map(t => `    <tag>${escapeXml(t)}</tag>`).join("\n");
  
  return `<?xml version="1.0" encoding="UTF-8"?>
<document>
  <metadata>
    <title>${escapeXml(title)}</title>
    <createdAt>${timestamp}</createdAt>
    ${author ? `<author>${escapeXml(author)}</author>` : ''}
    <tags>
${tagXml || '    '}
    </tags>
  </metadata>
  <content>
${escapeXml(content)}
  </content>
</document>`;
}

/**
 * Generate safe filename from title
 */
function generateFilename(title, extension) {
  const safe = title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
  return `${safe}.${extension}`;
}

/**
 * Validate file extension
 */
function validateExtension(filename) {
  const ext = filename.toLowerCase().split('.').pop();
  return ext === 'md' || ext === 'xml';
}

/**
 * Get file type from extension
 */
function getFileType(filename) {
  const ext = filename.toLowerCase().split('.').pop();
  return ext === 'xml' ? 'xml' : 'markdown';
}

// ============================================================================
// TOOL HANDLERS
// ============================================================================

export const toolHandlers = {
  
  // Handler 1: Upload plain text file
  async upload_kb_file_text(args, apiCall) {
    try {
      // Validate extension
      if (!validateExtension(args.filename)) {
        return {
          success: false,
          error: "Invalid file extension. Only .md and .xml files are allowed.",
        };
      }

      // Encode content to base64
      const encodedContent = Buffer.from(args.content).toString("base64");
      
      const body = {
        folderId: args.folderId,
        name: args.filename,
        content: encodedContent,
      };
      
      const result = await apiCall("knowledge-base.php", "POST", body, { action: "upload_file" });
      
      return {
        content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
      };
    } catch (error) {
      return {
        content: [{ type: "text", text: `Error: ${error.message}` }],
        isError: true,
      };
    }
  },

  // Handler 2: Get file with decoded content
  async get_kb_file_text(args, apiCall) {
    try {
      const result = await apiCall("knowledge-base.php", "GET", null, { action: "get_file", id: args.id });
      
      if (result.success && result.data && result.data.content) {
        // Decode base64 content
        try {
          const decodedContent = Buffer.from(result.data.content, "base64").toString("utf8");
          result.data.textContent = decodedContent;
          // Keep original encoded content too for backward compatibility
        } catch (decodeError) {
          result.warning = "Failed to decode content as base64";
        }
      }
      
      return {
        content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
      };
    } catch (error) {
      return {
        content: [{ type: "text", text: `Error: ${error.message}` }],
        isError: true,
      };
    }
  },

  // Handler 3: Update file with plain text
  async update_kb_file_text(args, apiCall) {
    try {
      const body = {};
      
      if (args.name) {
        // Validate extension if renaming
        if (!validateExtension(args.name)) {
          return {
            success: false,
            error: "Invalid file extension. Only .md and .xml files are allowed.",
          };
        }
        body.name = args.name;
      }
      
      if (args.content) {
        body.content = Buffer.from(args.content).toString("base64");
      }
      
      const result = await apiCall("knowledge-base.php", "PUT", body, { action: "update_file", id: args.id });
      
      return {
        content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
      };
    } catch (error) {
      return {
        content: [{ type: "text", text: `Error: ${error.message}` }],
        isError: true,
      };
    }
  },

  // Handler 4: Create formatted Markdown file
  async create_kb_md_file(args, apiCall) {
    try {
      const filename = args.customFilename 
        ? `${args.customFilename}.md`
        : generateFilename(args.title, "md");
      
      const formattedContent = formatAsMarkdown(
        args.title,
        args.body,
        args.tags || [],
        args.author
      );
      
      const encodedContent = Buffer.from(formattedContent).toString("base64");
      
      const body = {
        folderId: args.folderId,
        name: filename,
        content: encodedContent,
      };
      
      const result = await apiCall("knowledge-base.php", "POST", body, { action: "upload_file" });
      
      // Add metadata about the created file
      if (result.success) {
        result.formattedContent = formattedContent;
        result.generatedFilename = filename;
      }
      
      return {
        content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
      };
    } catch (error) {
      return {
        content: [{ type: "text", text: `Error: ${error.message}` }],
        isError: true,
      };
    }
  },

  // Handler 5: Create formatted XML file
  async create_kb_xml_file(args, apiCall) {
    try {
      const filename = args.customFilename 
        ? `${args.customFilename}.xml`
        : generateFilename(args.title, "xml");
      
      const formattedContent = formatAsXml(
        args.title,
        args.content,
        args.tags || [],
        args.author
      );
      
      const encodedContent = Buffer.from(formattedContent).toString("base64");
      
      const body = {
        folderId: args.folderId,
        name: filename,
        content: encodedContent,
      };
      
      const result = await apiCall("knowledge-base.php", "POST", body, { action: "upload_file" });
      
      // Add metadata about the created file
      if (result.success) {
        result.formattedContent = formattedContent;
        result.generatedFilename = filename;
      }
      
      return {
        content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
      };
    } catch (error) {
      return {
        content: [{ type: "text", text: `Error: ${error.message}` }],
        isError: true,
      };
    }
  },

  // Handler 6: Batch upload with retry logic
  async batch_upload_kb_text(args, apiCall) {
    const results = [];
    const errors = [];
    let successCount = 0;
    let failCount = 0;

    for (const file of args.files) {
      let retries = 0;
      let maxRetries = 2;
      let success = false;
      let lastError = null;

      while (retries <= maxRetries && !success) {
        try {
          // Validate extension
          if (!validateExtension(file.filename)) {
            throw new Error(`Invalid extension for ${file.filename}. Only .md and .xml allowed.`);
          }

          // Encode content
          const encodedContent = Buffer.from(file.content).toString("base64");
          
          const body = {
            folderId: args.folderId,
            name: file.filename,
            content: encodedContent,
          };
          
          const result = await apiCall("knowledge-base.php", "POST", body, { action: "upload_file" });
          
          if (result.success) {
            success = true;
            successCount++;
            results.push({
              filename: file.filename,
              success: true,
              file: result.file,
              retries: retries,
            });
          } else {
            throw new Error(result.error || result.message || "Upload failed");
          }
        } catch (error) {
          lastError = error.message;
          retries++;
          
          // If this is a name conflict error, wait a moment before retry
          if (error.message.includes("already exists") && retries <= maxRetries) {
            await new Promise(resolve => setTimeout(resolve, 500));
          }
        }
      }

      if (!success) {
        failCount++;
        errors.push({
          filename: file.filename,
          error: lastError,
          retries: retries,
        });
      }
    }

    const summary = {
      success: failCount === 0,
      summary: {
        total: args.files.length,
        successful: successCount,
        failed: failCount,
      },
      results,
      errors: errors.length > 0 ? errors : undefined,
    };

    return {
      content: [{ type: "text", text: JSON.stringify(summary, null, 2) }],
      isError: failCount > 0,
    };
  },
};

export default { toolDefinitions, toolHandlers };
