"use strict";

// ── Utilities ─────────────────────────────────────────────────────────────────

function statusBadge(status) {
  const labels = { written: "✓ Written", stale: "⚠ Stale", not_started: "— Not started" };
  return `<span class="badge badge-${status}">${labels[status] || status}</span>`;
}

function fmtDate(s) {
  if (!s) return "—";
  return s.replace("T", " ").replace("Z", "").substring(0, 16);
}

function esc(s) {
  if (!s) return "";
  return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
}

function renderVal(v) {
  if (v === null || v === undefined) return '<span style="color:var(--text3)">—</span>';
  if (typeof v === "string") return esc(v) || '<span style="color:var(--text3)">—</span>';
  if (typeof v === "number" || typeof v === "boolean") return esc(String(v));
  if (Array.isArray(v)) {
    if (v.length === 0) return '<span style="color:var(--text3)">[ kosong ]</span>';
    if (v.every(i => typeof i === "string"))
      return `<ul class="artifact-list">${v.map(i => `<li>${esc(i)}</li>`).join("")}</ul>`;
    if (v.every(i => typeof i === "object" && i !== null))
      return v.map(renderObject).join("<hr style='border:none;border-top:1px solid var(--border);margin:10px 0'>");
    return `<ul class="artifact-list">${v.map(i => `<li>${renderVal(i)}</li>`).join("")}</ul>`;
  }
  if (typeof v === "object") return renderObject(v);
  return esc(String(v));
}

function renderObject(obj) {
  const skip = ["ver", "meta"];
  const entries = Object.entries(obj).filter(([k]) => !skip.includes(k));
  if (entries.length === 0) return '<span style="color:var(--text3)">—</span>';
  return `<div class="artifact-kv">${
    entries.map(([k, v]) =>
      `<div class="artifact-kv-row">
        <span class="artifact-kv-key">${esc(k)}</span>
        <span class="artifact-kv-val">${renderVal(v)}</span>
      </div>`
    ).join("")
  }</div>`;
}

// ── Table renderer ────────────────────────────────────────────────────────────

const TABLE_SCHEMA = {
  "goals":       [{ key: "item", label: "Goal" },          { key: "reason",      label: "Reason",      cls: "tbl-muted" }],
  "non_goals":   [{ key: "item", label: "Non-goal" },      { key: "reason",      label: "Reason",      cls: "tbl-muted" }],
  "assumptions": [{ key: "assumption", label: "Assumption" }, { key: "status",   label: "Status",      cls: "tbl-status" }],
  "constraints": [{ key: "type", label: "Type", cls: "tbl-muted" }, { key: "description", label: "Description" }],
  "tech_stack":  [{ key: "layer", label: "Layer", cls: "tbl-muted" }, { key: "choice", label: "Technology" }],
  "nfr":         [{ key: "category", label: "Category", cls: "tbl-muted" }, { key: "requirement", label: "Requirement" }],
  "component_patterns": [{ key: "component", label: "Component", cls: "tbl-muted" }, { key: "notes", label: "Notes" }],
};

// Custom renderers for complex object fields
function renderArchPattern(value) {
  if (!value || typeof value !== "object") return renderVal(value);
  const name  = value.name || "—";
  const desc  = value.description || "";
  const comps = Array.isArray(value.components) ? value.components : [];
  const compTable = comps.length > 0
    ? renderTable(comps, [{ key: "name", label: "Component", cls: "tbl-muted" }, { key: "role", label: "Role" }])
    : "";
  return `<div class="arch-pattern-name">${esc(name)}</div>
    ${desc ? `<div class="arch-pattern-desc">${esc(desc)}</div>` : ""}
    ${compTable ? `<div class="arch-pattern-components">${compTable}</div>` : ""}`;
}


// ── UI/UX custom renderers ────────────────────────────────────────────────────

function renderDesignSystem(value) {
  if (!value || typeof value !== "object") return renderVal(value);
  const parts = [];
  if (Array.isArray(value.color_palette) && value.color_palette.length) {
    parts.push(`<div class="uiux-sub-label">Color Palette</div>`);
    const cpRows = value.color_palette.map(c => ({
      token: c.token,
      desc: [c.value ? `<code style="font-size:12px;color:var(--text2)">${esc(c.value)}</code>` : "",
             c.description ? esc(c.description) : ""].filter(Boolean).join(" — "),
    }));
    const cpHtml = `<table class="artifact-tbl"><thead><tr><th>Token</th><th>Value &amp; Usage</th></tr></thead><tbody>${
      cpRows.map(r => `<tr><td class="tbl-muted">${esc(r.token)}</td><td>${r.desc}</td></tr>`).join("")
    }</tbody></table>`;
    parts.push(cpHtml);
  }
  if (value.typography) {
    const t = value.typography;
    parts.push(`<div class="uiux-sub-label">Typography</div>`);
    const meta = [];
    if (t.font_family) meta.push("Font: " + t.font_family);
    if (t.base_size)   meta.push("Base: " + t.base_size);
    if (t.line_height) meta.push("Line height: " + t.line_height);
    if (meta.length)   parts.push(`<div class="uiux-sub-meta">${meta.map(esc).join(" &middot; ")}</div>`);
    if (Array.isArray(t.scale) && t.scale.length) {
      const scaleRows = t.scale.map(s => ({
        level: s.level,
        spec: [s.size, s.weight].filter(Boolean).join(" / "),
      }));
      parts.push(renderTable(scaleRows, [{key:"level",label:"Level",cls:"tbl-muted"},{key:"spec",label:"Size / Weight"}]));
    }
  }
  if (Array.isArray(value.spacing) && value.spacing.length) {
    parts.push(`<div class="uiux-sub-label">Spacing</div>`);
    parts.push(renderTable(value.spacing, [{key:"name",label:"Name",cls:"tbl-muted"},{key:"value",label:"Value"}]));
  }
  if (Array.isArray(value.border_radius) && value.border_radius.length) {
    parts.push(`<div class="uiux-sub-label">Border Radius</div>`);
    parts.push(renderTable(value.border_radius, [{key:"context",label:"Component",cls:"tbl-muted"},{key:"value",label:"Radius"}]));
  }
  if (Array.isArray(value.shadow) && value.shadow.length) {
    parts.push(`<div class="uiux-sub-label">Shadow</div>`);
    parts.push(renderTable(value.shadow, [{key:"level",label:"Level",cls:"tbl-muted"},{key:"value",label:"Value"}]));
  }
  if (value.icon_library && typeof value.icon_library === "object") {
    parts.push(`<div class="uiux-sub-label">Icon Library</div>`);
    const rows = Object.entries(value.icon_library).filter(([,v])=>v).map(([k,v])=>({key:k.replace(/_/g," "),value:String(v)}));
    parts.push(renderTable(rows, [{key:"key",label:"Property",cls:"tbl-muted"},{key:"value",label:"Value"}]));
  }
  return parts.join(`<div class="uiux-sub-spacer"></div>`);
}

function renderLayout(value) {
  if (!value || typeof value !== "object") return renderVal(value);
  const parts = [];
  if (value.shell_description) {
    parts.push(`<div class="uiux-sub-label">Shell</div>`);
    parts.push(`<div class="uiux-sub-text">${esc(value.shell_description)}</div>`);
  }
  if (Array.isArray(value.navigation_per_role) && value.navigation_per_role.length) {
    parts.push(`<div class="uiux-sub-label">Navigation per Role</div>`);
    const rows = value.navigation_per_role.map(r => ({
      menu: r.menu_item + (r.target ? `<div style="font-size:11px;color:var(--text3);font-family:monospace;margin-top:2px">${esc(r.target)}</div>` : ""),
      roles: Array.isArray(r.roles) ? r.roles.join(", ") : (r.roles || "—"),
    }));
    const navHtml = `<table class="artifact-tbl"><thead><tr><th>Menu Item</th><th>Roles</th></tr></thead><tbody>${
      rows.map(r => `<tr><td class="tbl-muted">${r.menu}</td><td>${esc(r.roles)}</td></tr>`).join("")
    }</tbody></table>`;
    parts.push(navHtml);
  }
  if (value.adaptation && typeof value.adaptation === "object") {
    parts.push(`<div class="uiux-sub-label">Adaptation</div>`);
    const a = value.adaptation;
    const rows = [];
    if (a.strategy) rows.push({key:"Strategy", value:a.strategy});
    if (a.notes)    rows.push({key:"Notes",    value:a.notes});
    if (rows.length) parts.push(renderTable(rows, [{key:"key",label:"",cls:"tbl-muted"},{key:"value",label:""}]));
  }
  return parts.join(`<div class="uiux-sub-spacer"></div>`);
}

function renderScreenTypePatterns(value) {
  if (!Array.isArray(value) || value.length === 0) return '<span style="color:var(--text3)">[ kosong ]</span>';
  return value.map(stp => {
    const rows = [];
    if (stp.description) rows.push({key:"Description", value:stp.description});
    if (stp.layout)      rows.push({key:"Layout",      value:stp.layout});
    if (stp.header_area) rows.push({key:"Header",      value:stp.header_area});
    if (stp.body_area)   rows.push({key:"Body",        value:stp.body_area});
    if (stp.footer_area) rows.push({key:"Footer",      value:stp.footer_area});
    const mainTable = rows.length
      ? renderTable(rows, [{key:"key",label:"",cls:"tbl-muted"},{key:"value",label:""}]) : "";
    let statesHtml = "";
    if (Array.isArray(stp.states) && stp.states.length)
      statesHtml = `<div class="uiux-sub-label" style="margin-top:12px">States</div>` +
        renderTable(stp.states, [{key:"state",label:"State",cls:"tbl-muted"},{key:"display",label:"Display"}]);
    return `<div class="uiux-screen-type">
      <div class="uiux-screen-type-header">${esc(stp.type || "—")}</div>
      ${mainTable}${statesHtml}
    </div>`;
  }).join(`<div class="uiux-sub-spacer"></div>`);
}

function renderAccessibility(value) {
  if (!value || typeof value !== "object") return renderVal(value);
  const parts = [];
  if (value.level) {
    parts.push(`<div class="uiux-sub-label">Compliance Level</div>`);
    parts.push(`<div class="uiux-sub-text">${esc(value.level)}</div>`);
  }
  const objSections = [
    ["color_contrast",       "Color Contrast"],
    ["input_navigation",     "Input Navigation"],
    ["structure_semantics",  "Structure & Semantics"],
    ["text_content",         "Text & Media"],
  ];
  for (const [key, label] of objSections) {
    if (value[key] && typeof value[key] === "object") {
      parts.push(`<div class="uiux-sub-label">${label}</div>`);
      const rows = Object.entries(value[key]).filter(([,v])=>v)
        .map(([k,v])=>({key:k.replace(/_/g," "), value:String(v)}));
      parts.push(renderTable(rows, [{key:"key",label:"Property",cls:"tbl-muted"},{key:"value",label:"Rule"}]));
    }
  }
  return parts.join(`<div class="uiux-sub-spacer"></div>`);
}

function renderTestResults(value) {
  if (!value || typeof value !== "object") return renderVal(value);
  const types = [
    { key: "unit",        label: "Unit",        hasCoverage: true },
    { key: "integration", label: "Integration", hasCoverage: false },
    { key: "component",   label: "Component",   hasCoverage: false },
    { key: "browser",     label: "Browser",     hasCoverage: false },
  ];
  const rows = types.map(t => {
    const r = value[t.key];
    if (!r) return null;
    const notRun = !r.run_at;
    const passed  = notRun ? "—" : String(r.passed);
    const failed  = notRun ? "—" : String(r.failed);
    const runAt   = notRun ? "—" : r.run_at.replace("T", " ").replace("Z", " UTC");
    const coverage = t.hasCoverage
      ? (notRun ? "—" : (r.coverage != null ? r.coverage + "%" : "—"))
      : null;
    const failedCls = (!notRun && r.failed > 0) ? ' style="color:var(--error,#e55);font-weight:600"' : "";
    const passedCls = (!notRun && r.passed > 0 && r.failed === 0) ? ' style="color:var(--ok,#3a3);font-weight:600"' : "";
    return { label: t.label, runAt, passed, passedCls, failed, failedCls, coverage, hasCoverage: t.hasCoverage };
  }).filter(Boolean);

  const hasCovCol = rows.some(r => r.hasCoverage);
  const thead = `<thead><tr><th>Type</th><th>Run At</th><th>Passed</th><th>Failed</th>${hasCovCol ? "<th>Coverage</th>" : ""}</tr></thead>`;
  const tbody = `<tbody>${rows.map(r =>
    `<tr>
      <td class="tbl-muted">${esc(r.label)}</td>
      <td style="font-size:12px;color:var(--text3)">${esc(r.runAt)}</td>
      <td${r.passedCls}>${esc(r.passed)}</td>
      <td${r.failedCls}>${esc(r.failed)}</td>
      ${hasCovCol ? `<td>${r.hasCoverage ? esc(String(r.coverage ?? "—")) : "—"}</td>` : ""}
    </tr>`
  ).join("")}</tbody>`;
  return `<div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">${thead}${tbody}</table></div>`;
}

function renderApiTestSteps(value) {
  if (!Array.isArray(value)) return renderVal(value);
  if (value.length === 0) return '<span style="color:var(--text3)">[ kosong ]</span>';
  const thead = `<thead><tr><th>#</th><th>Method</th><th>Path</th><th>Status</th><th>Error Code</th></tr></thead>`;
  const tbody = `<tbody>${value.map(s => {
    const ep = s.endpoint || {};
    const method = ep.method || "—";
    const methodCls = { GET:"method-get", POST:"method-post", PUT:"method-put", PATCH:"method-patch", DELETE:"method-delete" }[method] || "";
    return `<tr>
      <td class="tbl-muted">${esc(String(s.step ?? ""))}</td>
      <td><span class="method-badge ${methodCls}">${esc(method)}</span></td>
      <td class="tbl-mono">${esc(ep.path || "—")}</td>
      <td>${esc(String(s.expected_status ?? "—"))}</td>
      <td>${s.expected_error_code != null ? esc(String(s.expected_error_code)) : '<span style="color:var(--text3)">—</span>'}</td>
    </tr>`;
  }).join("")}</tbody>`;
  return `<div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">${thead}${tbody}</table></div>`;
}

// ── Testing feature custom renderers ──────────────────────────────────────────

function renderBddScenarios(value) {
  if (!Array.isArray(value) || value.length === 0) return '<span style="color:var(--text3)">[ kosong ]</span>';
  return value.map(s => `
    <div class="bdd-card">
      <div class="bdd-card-title">${esc(s.scenario || "—")}</div>
      <div class="bdd-row"><span class="bdd-label">Given</span><span class="bdd-text">${esc(s.given || "—")}</span></div>
      <div class="bdd-row"><span class="bdd-label">When</span><span class="bdd-text">${esc(s.when || "—")}</span></div>
      <div class="bdd-row"><span class="bdd-label">Then</span><span class="bdd-text">${esc(s.then || "—")}</span></div>
    </div>`).join("");
}

function _renderTestKvRows(fields) {
  return `<div class="artifact-kv">${fields.map(([k, v]) =>
    `<div class="artifact-kv-row"><span class="artifact-kv-key">${esc(k)}</span><span class="artifact-kv-val">${esc(v)}</span></div>`
  ).join("")}</div>`;
}

function _renderNoFrontend() {
  return '<span style="color:var(--text3)">No frontend</span>';
}

function renderTestScenarios(value) {
  if (!Array.isArray(value) || value.length === 0) return '<span style="color:var(--text3)">[ kosong ]</span>';
  return value.map(ts => {
    const apiSteps = Array.isArray(ts.api_test) ? ts.api_test : [];
    const apiHtml  = renderApiTestSteps(apiSteps);
    const hasReqExamples = apiSteps.some(s => s.request_example && Object.keys(s.request_example).length > 0);
    const reqExamplesHtml = hasReqExamples
      ? `<details class="test-scenario-json">
          <summary>Request Example</summary>
          <pre>${esc(JSON.stringify(apiSteps.map(s => ({ step: s.step, request_example: s.request_example })), null, 2))}</pre>
        </details>`
      : "";

    const comp      = ts.component_test || {};
    const compHtml  = Object.keys(comp).length === 0 ? _renderNoFrontend() : _renderTestKvRows([
      ["Component", comp.component || "—"],
      ["Action",    comp.action    || "—"],
      ["Assert",    comp.assert    || "—"],
    ]);

    const browser     = ts.browser_test || {};
    const browserHtml = Object.keys(browser).length === 0 ? _renderNoFrontend() : _renderTestKvRows([
      ["Route",  browser.route  || "—"],
      ["Action", browser.action || "—"],
      ["Assert", browser.assert || "—"],
    ]);

    return `
      <div class="test-scenario-card">
        <div class="test-scenario-header">
          <span class="test-scenario-title">${esc(ts.scenario_ref || "—")}</span>
          <span class="actor-id-badge">${esc(ts.usecase_id || "—")}</span>
        </div>
        <div class="entity-section-label">API Test</div>
        ${apiHtml}
        ${reqExamplesHtml}
        <div class="entity-section-label">Component Test</div>
        ${compHtml}
        <div class="entity-section-label">Browser Test</div>
        ${browserHtml}
      </div>`;
  }).join("");
}

function renderApiContracts(value) {
  if (!Array.isArray(value) || value.length === 0) return '<span style="color:var(--text3)">[ kosong ]</span>';
  const methodCls = { GET: "method-get", POST: "method-post", PUT: "method-put", PATCH: "method-patch", DELETE: "method-delete" };
  return value.map((c, i) => {
    const endpoints = Array.isArray(c.endpoints) ? c.endpoints : [];
    const epHtml = endpoints.length
      ? endpoints.map(ep => `
          <div class="artifact-kv-row">
            <span class="artifact-kv-key"><span class="method-badge ${methodCls[ep.method] || ""}">${esc(ep.method || "—")}</span></span>
            <span class="artifact-kv-val tbl-mono">${esc(ep.path || "—")}${ep.description ? ` — ${esc(ep.description)}` : ""}</span>
          </div>`).join("")
      : '<span style="color:var(--text3)">—</span>';

    const businessLogic = Array.isArray(c.business_logic) && c.business_logic.length
      ? `<ul class="artifact-list">${c.business_logic.map(s => `<li>${esc(s)}</li>`).join("")}</ul>`
      : '<span style="color:var(--text3)">—</span>';

    const unitTests = Array.isArray(c.unit_test_cases) && c.unit_test_cases.length
      ? renderTable(c.unit_test_cases, [
          { key: "description", label: "Description" },
          { key: "given",       label: "Given", cls: "tbl-muted" },
          { key: "expect",      label: "Expect" },
        ])
      : '<span style="color:var(--text3)">[ kosong ]</span>';

    return `
      <details class="api-contract-card"${i === 0 ? " open" : ""}>
        <summary class="api-contract-summary">
          <span class="api-contract-title">${esc(c.usecase_name || c.usecase_id || "—")}</span>
          <span class="actor-id-badge">${esc(c.usecase_id || "—")}</span>
        </summary>
        <div class="api-contract-body">
          <div class="entity-section-label">Endpoints</div>
          ${epHtml}
          <div class="entity-section-label">Business Logic</div>
          ${businessLogic}
          <div class="entity-section-label">Unit Test Cases</div>
          ${unitTests}
        </div>
      </details>`;
  }).join("");
}

const CUSTOM_RENDERER = {
  "architecture_pattern":  renderArchPattern,
  "design_system":         renderDesignSystem,
  "layout":                renderLayout,
  "screen_type_patterns":  renderScreenTypePatterns,
  "accessibility":         renderAccessibility,
  "test_results":          renderTestResults,
  "api_test":              renderApiTestSteps,
  "bdd_scenarios":         renderBddScenarios,
  "test_scenarios":        renderTestScenarios,
  "api_contracts":         renderApiContracts,
};

function renderTable(rows, cols) {
  if (!rows || rows.length === 0) return '<span style="color:var(--text3)">[ kosong ]</span>';
  const thead = `<thead><tr>${cols.map(c => `<th>${esc(c.label)}</th>`).join("")}</tr></thead>`;
  const tbody = `<tbody>${rows.map(row =>
    `<tr>${cols.map(c => {
      const val = row[c.key];
      const cls = c.cls ? ` class="${c.cls}"` : "";
      return `<td${cls}>${val !== undefined && val !== null ? esc(String(val)) : "—"}</td>`;
    }).join("")}</tr>`
  ).join("")}</tbody>`;
  return `<div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">${thead}${tbody}</table></div>`;
}

// ── Field order & labels ──────────────────────────────────────────────────────

const FIELD_LABELS = {
  "overview":"Overview","problem_statement":"Problem Statement","goals":"Goals",
  "non_goals":"Non-goals","initial_actors":"Initial Actors","success_metrics":"Success Metrics",
  "assumptions":"Assumptions","constraints":"Constraints","meta":"Meta",
  "tech_stack":"Tech Stack","architecture":"Architecture","deployment":"Deployment",
  "integrations":"Integrations","security":"Security","scalability":"Scalability",
  "design_system":"Design System","layout":"Layout","screen_type_patterns":"Screen Type Patterns",
  "component_patterns":"Component Patterns","accessibility":"Accessibility","architecture_pattern":"Architecture Pattern","nfr":"Non-Functional Requirements",
  "unit_test":"Unit Test","integration_test":"Integration Test","auto_fix":"Auto-Fix Policy","done_definition":"Definition of Done",
  "bdd_scenarios":"BDD Scenarios","test_priority":"Test Priority",
  "component_test":"Component Test","browser_test":"Browser Test",
  "unit_test_cases":"Unit Test Cases","test_scenarios":"Test Scenarios","test_fixture":"Test Fixture",
};

function sortedFields(displayOrder, keys) {
  const order   = Array.isArray(displayOrder) ? displayOrder : [];
  const ordered = order.filter(k => keys.includes(k));
  const rest    = keys.filter(k => !order.includes(k)).sort();
  return [...ordered, ...rest];
}


// ── Card toggle ───────────────────────────────────────────────────────────────

function toggleCard(cardId) {
  const card  = document.getElementById(cardId);
  if (!card) return;
  card.classList.toggle("collapsed");
}

// ── Artifact content: cards per field ────────────────────────────────────────

async function renderArtifactContent(key) {
  const data = await api.fetchArtifact(key);
  if (!data.content) return `<div class="empty">Artifact belum ditulis (not started).</div>`;

  const content    = data.content;
  const schema     = data.schema || {};
  const tracked    = schema._tracked || [];
  const fieldNodes = data.fieldNodes || {};        // { fieldName: { ver, updated_at, stale } }
  const artifact      = key.split(".")[2] || "";
  const displayOrder  = schema._display_order || [];
  const SKIP          = ["ver"];
  const fields        = sortedFields(displayOrder, Object.keys(content).filter(k => !SKIP.includes(k)));

  return fields.map(field => {
    const isMeta     = field === "meta";
    const isTracked  = tracked.includes(field);
    const label      = FIELD_LABELS[field] || field;
    const cardClass  = isMeta ? "artifact-card artifact-card--meta" : "artifact-card";
    const titleClass = isTracked ? "artifact-card-title tracked" : "artifact-card-title";

    // Field-level dep-graph node
    const node = fieldNodes[field];
    const verHtml = node
      ? `<span class="card-meta-ver">v${node.ver || 0}</span>
         <span class="card-meta-date">${fmtDate(node.updated_at)}</span>`
      : "";

    const tableCols      = TABLE_SCHEMA[field];
    const customRenderer = CUSTOM_RENDERER[field];
    const value          = content[field];
    const bodyHtml = customRenderer
      ? `<div class="artifact-field-value">${customRenderer(value)}</div>`
      : (tableCols && Array.isArray(value))
        ? renderTable(value, tableCols)
        : `<div class="artifact-field-value">${renderVal(value)}</div>`;

    const cardId = "card-" + field;
    return `
      <div class="${cardClass}" id="${cardId}">
        <div class="${titleClass}" onclick="toggleCard('${cardId}')">
          <span class="card-toggle-arrow">▾</span>
          <span class="card-title-text">${esc(label)}</span>
          <span class="card-title-meta">${verHtml}</span>
        </div>
        <div class="card-body">
          <div class="card-body-inner">${bodyHtml}</div>
        </div>
      </div>`;
  }).join("");
}

// ── Node info ─────────────────────────────────────────────────────────────────

function renderNodeInfo(artifact) {
  const ver       = artifact.ver || 0;
  const updatedAt = fmtDate(artifact.updated_at);
  const deps      = artifact.depends_on || {};
  const staleKeys = artifact.stale_keys || [];

  const depsHtml = Object.entries(deps).length === 0
    ? '<span style="color:var(--text3)">— (root)</span>'
    : Object.entries(deps).map(([k, v]) =>
        `<div style="font-family:monospace;font-size:11px;color:var(--text2)">
          ${esc(k)} <span style="color:var(--text3)">@ v${v}</span>
        </div>`
      ).join("");

  const staleHtml = staleKeys.length > 0
    ? `<div style="margin-top:8px">
        <div class="artifact-field-label" style="color:var(--yellow)">Stale karena:</div>
        ${staleKeys.map(k => `<span class="stale-key-tag">${esc(k)}</span>`).join("")}
       </div>`
    : "";

  return `
    <div class="node-info">
      <div class="node-info-row"><span class="node-info-key">Version</span><span class="node-info-val">v${ver}</span></div>
      <div class="node-info-row"><span class="node-info-key">Updated</span><span class="node-info-val">${updatedAt}</span></div>
      <div class="node-info-row"><span class="node-info-key">Status</span><span class="node-info-val">${statusBadge(artifact.status)}</span></div>
      <div class="node-info-row" style="border-bottom:none"><span class="node-info-key">Depends on</span><span></span></div>
      <div style="padding-top:4px">${depsHtml}</div>
      ${staleHtml}
    </div>`;
}

// ── Panel ─────────────────────────────────────────────────────────────────────

function openArtifactPanel(artifact) {
  const tabs = [
    { label: "📋 Dep-graph", content: renderNodeInfo(artifact) },
    { label: "📄 Content",   render: () => renderArtifactContent(artifact.key) },
  ];
  if (artifact.artifact === "uiux-spec" && artifact.status !== "not_started") {
    tabs.push({ label: "🎨 Preview", render: async () => {
      const data = await api.fetchUiuxPreviews();
      if (!data.files || data.files.length === 0) return `<div class="empty">Belum ada file preview HTML.</div>`;
      return `<div class="artifact-field-label" style="margin-bottom:10px">HTML Preview Files</div>
        <div class="preview-grid">${data.files.map(f =>
          `<a class="preview-link" href="${f.url}" target="_blank">🖼 ${esc(f.name.replace(".html","").replace(/-/g," "))}</a>`
        ).join("")}</div>`;
    }});
  }
  openPanel(artifact.key, artifact.label, statusBadge(artifact.status), tabs);
}


// ── JSON Viewer ───────────────────────────────────────────────────────────────

async function viewJson(pageId) {
  const key = PAGE_ARTIFACT_KEY[pageId];
  if (!key) return;
  const modal = document.getElementById("json-modal");
  const titleEl = document.getElementById("json-modal-title");
  const bodyEl  = document.getElementById("json-modal-body");
  titleEl.textContent = key.split(".")[2] + ".json";
  bodyEl.textContent  = "Memuat…";
  modal.classList.add("open");
  try {
    const data = await api.fetchArtifact(key);
    bodyEl.textContent = JSON.stringify(data.content, null, 2);
  } catch (err) {
    bodyEl.textContent = "Error: " + err.message;
  }
}

function closeJsonModal(e) {
  if (e.target === document.getElementById("json-modal"))
    document.getElementById("json-modal").classList.remove("open");
}

// ── Actor Index page ──────────────────────────────────────────────────────────

let _actorData = [];

function _renderActorDetail(actor) {
  if (!actor) return '<div class="empty">Pilih actor di sebelah kiri.</div>';

  const SKIP = ["id", "name"];
  const rows  = Object.entries(actor)
    .filter(([k, v]) => !SKIP.includes(k) && v !== "" && v !== null && v !== undefined)
    .map(([k, v]) => ({ k: k.replace(/_/g, " "), v }));

  if (!rows.length) return '<div class="empty">Tidak ada detail tersedia.</div>';

  return `<div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">
    <thead><tr><th>Key</th><th>Value</th></tr></thead>
    <tbody>${rows.map(r => `<tr>
      <td class="tbl-muted" style="width:160px;white-space:nowrap">${esc(r.k)}</td>
      <td style="white-space:pre-wrap">${Array.isArray(r.v) ? r.v.filter(Boolean).join(", ") || "—" : esc(String(r.v))}</td>
    </tr>`).join("")}</tbody>
  </table></div>`;
}

function selectActor(idx) {
  document.querySelectorAll(".actor-list-item").forEach((el, i) => {
    el.classList.toggle("active", i === idx);
  });
  const panel = document.getElementById("actor-detail-panel");
  if (!panel) return;
  const actor = _actorData[idx];
  panel.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:30px">
      <span class="entity-block-name">${esc(actor.name || actor.id || "—")}</span>
      ${actor.id ? `<span class="actor-id-badge">${esc(actor.id)}</span>` : ""}
    </div>
    ${_renderActorDetail(actor)}`;
}

async function renderActorIndexPage(data) {
  const el = document.getElementById("actor-index-content");
  if (!el) return;

  el.innerHTML = '<div class="empty">Memuat…</div>';

  try {
    const res  = await api.fetchArtifact("project.2-business-spec.actor-index");
    _actorData = res.content && Array.isArray(res.content.actors) ? res.content.actors : [];

    if (!_actorData.length) {
      el.innerHTML = `<div class="empty">Belum ada data actor.</div>`;
      return;
    }

    const listHtml = _actorData.map((actor, i) => `
      <div class="entity-list-item actor-list-item${i === 0 ? " active" : ""}" onclick="selectActor(${i})">
        <span class="entity-list-name">${esc(actor.name || actor.id || "—")}</span>
        ${actor.id ? `<span class="actor-id-badge">${esc(actor.id)}</span>` : ""}
      </div>`
    ).join("");

    const first = _actorData[0];
    el.innerHTML = `<div class="entity-layout">
      <div class="entity-list">${listHtml}</div>
      <div class="entity-detail-panel" id="actor-detail-panel">
        <div class="entity-block-header" style="margin-bottom:30px">
          <span class="entity-block-name">${esc(first.name || first.id || "—")}</span>
          ${first.id ? `<span class="actor-id-badge">${esc(first.id)}</span>` : ""}
        </div>
        ${_renderActorDetail(first)}
      </div>
    </div>`;

  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

// ── Screen Index page ─────────────────────────────────────────────────────────

function _screenPhaseStatus(moduleNodes, screenId, phase) {
  const entry = (moduleNodes || []).find(n => n.screenId === screenId && n.phase === phase);
  if (!entry || entry.node === null) return "not_yet";
  if (entry.node && entry.node.stale) return "stale";
  return "done";
}

function _phaseStatusCell(status) {
  if (status === "done")  return `<span style="color:var(--green);font-weight:600">✓</span>`;
  if (status === "stale") return `<span style="color:var(--red);font-size:11px;font-weight:600">Stale</span>`;
  return `<span style="color:var(--text3)">✗</span>`;
}

function _buildScreenTable(screens, available, showModuleCol, moduleNodes) {
  if (!screens.length) return '<div class="empty">Tidak ada screen.</div>';
  const rows = screens.map(s => {
    const sid     = esc(s.id || "");
    const mid     = esc(s.module_id || "");
    const sname   = (s.name || s.id || "").replace(/'/g, "\\'");
    const hasDetail = available.has(s.id || "");
    const st2 = _screenPhaseStatus(moduleNodes, s.id, "2-business-spec");
    const st3 = _screenPhaseStatus(moduleNodes, s.id, "3-tech-spec");
    const st4 = _screenPhaseStatus(moduleNodes, s.id, "4-implement");
    return `<tr>
      ${showModuleCol ? `<td style="font-family:monospace;font-size:12px;color:var(--text3);white-space:nowrap">${esc(s.module_id || "—")}</td>` : ""}
      <td style="font-family:monospace;font-size:12px;white-space:nowrap">${esc(s.id || "—")}</td>
      <td>${esc(s.name || "—")}</td>
      <td>${esc(s.description || "—")}</td>
      <td style="text-align:center">${_phaseStatusCell(st2)}</td>
      <td style="text-align:center">${_phaseStatusCell(st3)}</td>
      <td style="text-align:center">${_phaseStatusCell(st4)}</td>
      <td style="text-align:center;color:var(--text3);font-size:12px">Placeholder</td>
      <td>${hasDetail ? `<a class="screen-detail-link" href="#screen/${mid}/${sid}" onclick="event.preventDefault();openScreenPage('${sid}','${mid}','${sname}')">Detail →</a>` : ""}</td>
    </tr>`;
  }).join("");
  return `<div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">
    <thead><tr>
      ${showModuleCol ? "<th>Module</th>" : ""}
      <th>ID</th><th>Name</th><th>Description</th>
      <th style="text-align:center;white-space:nowrap">2-Business</th>
      <th style="text-align:center;white-space:nowrap">3-Tech</th>
      <th style="text-align:center;white-space:nowrap">4-Impl</th>
      <th style="text-align:center;white-space:nowrap">Testing</th>
      <th></th>
    </tr></thead>
    <tbody>${rows}</tbody>
  </table></div>`;
}

async function renderScreenIndexPage(data) {
  const el = document.getElementById("screen-index-content");
  if (!el) return;

  el.innerHTML = '<div class="empty">Memuat…</div>';

  try {
    const [res, screenFiles] = await Promise.all([
      api.fetchArtifact("project.2-business-spec.screen-index"),
      api.fetchScreenFiles(),
    ]);
    const available = new Set((screenFiles.ids) || []);
    const screens   = res.content && Array.isArray(res.content.screens) ? res.content.screens : [];

    if (!screens.length) {
      el.innerHTML = `<div class="empty">Belum ada data screen.</div>`;
      return;
    }

    const modules = {};
    for (const s of screens) {
      const mid = s.module_id || "—";
      if (!modules[mid]) modules[mid] = [];
      modules[mid].push(s);
    }
    const moduleNodes = (data && data.moduleNodes) ? data.moduleNodes : [];

    const filterModule = _pendingScreenIndexModule;
    _pendingScreenIndexModule = null;

    const isAll = !filterModule;
    const list  = isAll ? screens : (modules[filterModule] || []);
    const title = isAll
      ? `All Screens <span style="font-size:13px;font-weight:400;color:var(--text3)">(${screens.length})</span>`
      : `${esc(filterModule)} <span style="font-size:13px;font-weight:400;color:var(--text3)">(${list.length})</span>`;

    el.innerHTML = `
      <div class="entity-block-header" style="margin-bottom:30px">
        <span class="entity-block-name">${title}</span>
      </div>
      ${_buildScreenTable(list, available, isAll, moduleNodes)}`;

  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

// ── Global nav: dynamic module submenu ───────────────────────────────────────

let _pendingScreenIndexModule = null;

function openScreenIndexModule(moduleId, el) {
  _pendingScreenIndexModule = (moduleId === "__all__") ? null : moduleId;
  go("screen-index", el || null);
}

async function updateModuleNav() {
  const listEl = document.getElementById("nav-module-list");
  if (!listEl) return;
  try {
    const res = await api.fetchArtifact("project.2-business-spec.module-index");
    const modules = (res.content && Array.isArray(res.content.modules)) ? res.content.modules : [];
    listEl.innerHTML = modules.map(m => {
      const mid = (m.id || "").replace(/'/g, "\\'");
      return `<div class="nav-subitem" data-page="screen-index" onclick="openScreenIndexModule('${mid}', this)">
        <span class="nav-subitem-icon">📦</span>${esc(m.name || m.id || "—")}
      </div>`;
    }).join("");
  } catch {}
}

// ── Screen Detail page ────────────────────────────────────────────────────────

let _currentScreenInfo = { screenId: "", moduleId: "", loaded: {} };

// ── Field labels ─────────────────────────────────────────────────────────────

const _SCREEN_FIELD_LABELS = {
  // 2-business-spec
  description:           "Description",
  actors:                "Actors",
  entry_points:          "Entry Points",
  information_displayed: "Information Displayed",
  available_actions:     "Available Actions",
  business_rules:        "Business Rules",
  usecase_ids:           "Use Case IDs",
  edge_cases:            "Edge Cases",
  test_priority:         "Test Priority",
  open_questions:        "Open Questions",
  bdd_scenarios:         "BDD Scenarios",
  // 3-tech-spec
  route:                 "Route",
  auth_requirement:      "Auth Requirement",
  actor_permissions:     "Actor Permissions",
  api_contracts:         "API Contracts",
  shared_entities:       "Shared Entities",
  screen_dependencies:   "Screen Dependencies",
  implementation_notes:  "Implementation Notes",
  unit_test_cases:       "Unit Test Cases",
  test_scenarios:        "Test Scenarios",
  // 4-implement
  status:                "Status",
  test_results:          "Test Results",
  files_generated:       "Files Generated",
  test_files_generated:  "Test Files Generated",
  fe_files_generated:    "FE Files Generated",
  fe_test_files_generated: "FE Test Files Generated",
  deferred_items:        "Deferred Items",
  known_issues:          "Known Issues",
};

let _screenTabStates = {}; // { phase: { fields, content, screenId } }

function _renderScreenFieldBody(field, value) {
  const customRenderer = CUSTOM_RENDERER[field];
  if (customRenderer) return `<div class="artifact-field-value">${customRenderer(value)}</div>`;
  if (Array.isArray(value) && value.length > 0 && value.every(i => typeof i === "object" && i !== null)) {
    const keys  = [...new Set(value.flatMap(o => Object.keys(o)))];
    const thead = `<thead><tr>${keys.map(k => `<th>${esc(_SCREEN_FIELD_LABELS[k] || k)}</th>`).join("")}</tr></thead>`;
    const tbody = `<tbody>${value.map(row =>
      `<tr>${keys.map(k => `<td>${Array.isArray(row[k]) ? row[k].join(", ") : esc(String(row[k] ?? "—"))}</td>`).join("")}</tr>`
    ).join("")}</tbody>`;
    return `<div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">${thead}${tbody}</table></div>`;
  } else if (Array.isArray(value)) {
    if (!value.length) return `<div class="artifact-field-value" style="color:var(--text3)">—</div>`;
    return `<ul class="artifact-list">${value.map(i => `<li>${esc(String(i))}</li>`).join("")}</ul>`;
  }
  return `<div class="artifact-field-value">${renderVal(value)}</div>`;
}

// ── Tab switching ─────────────────────────────────────────────────────────────

function switchScreenTab(id) {
  document.querySelectorAll(".screen-tab").forEach(t => t.classList.remove("active"));
  document.querySelectorAll(".screen-tab-panel").forEach(p => p.classList.remove("active"));
  const tab   = document.getElementById("screen-tab-" + id);
  const panel = document.getElementById("screen-panel-" + id);
  if (tab)   tab.classList.add("active");
  if (panel) panel.classList.add("active");
}

function _switchScreenDetailTab(id) {
  switchScreenTab(id);
  const { screenId, moduleId, loaded } = _currentScreenInfo;
  if (loaded[id]) return;
  loaded[id] = true;
  const panel = document.getElementById("screen-panel-" + id);
  if (!panel) return;
  if (id === "depgraph") {
    _loadScreenDepgraphTab(panel, screenId, moduleId);
  } else {
    const phaseMap = { business: "2-business-spec", techspec: "3-tech-spec", implement: "4-implement" };
    const phase = phaseMap[id];
    if (phase) _loadScreenArtifactTab(panel, phase, screenId, moduleId);
  }
}

// ── Dep-graph tab ─────────────────────────────────────────────────────────────

function _loadScreenDepgraphTab(panel, screenId, moduleId) {
  const data = api.getCached();
  const moduleNodes = (data && data.moduleNodes) || [];
  const phases = ["2-business-spec", "3-tech-spec", "4-implement"];
  const phaseLabels = { "2-business-spec": "2 · Business Spec", "3-tech-spec": "3 · Tech Spec", "4-implement": "4 · Implement" };

  const screenNodes = phases.map(ph => {
    const entry = moduleNodes.find(n => n.screenId === screenId && n.phase === ph);
    return { phase: ph, node: entry ? entry.node : undefined };
  });

  const rows = screenNodes.map(({ phase, node }) => {
    let statusHtml, ver = "—", updated = "—", staleKeys = "—";
    if (node === undefined) {
      statusHtml = `<span style="color:var(--text3)">✗ Not started</span>`;
    } else if (node === null) {
      statusHtml = `<span style="color:var(--text3)">✗ Not started</span>`;
    } else if (node.stale) {
      statusHtml = `<span style="color:var(--red);font-weight:600">⚠ Stale</span>`;
      ver = node.ver || "—";
      updated = node.updated_at ? node.updated_at.replace("T", " ").replace("Z", "") : "—";
      staleKeys = (node.stale_keys || []).join(", ") || "—";
    } else {
      statusHtml = `<span style="color:var(--green);font-weight:600">✓ Done</span>`;
      ver = node.ver || "—";
      updated = node.updated_at ? node.updated_at.replace("T", " ").replace("Z", "") : "—";
    }
    return `<tr>
      <td style="white-space:nowrap;font-weight:500">${esc(phaseLabels[phase] || phase)}</td>
      <td>${statusHtml}</td>
      <td style="text-align:center;color:var(--text3)">${ver}</td>
      <td style="font-size:12px;color:var(--text3);white-space:nowrap">${updated}</td>
      <td style="font-size:12px;color:var(--red)">${node && node.stale ? esc(staleKeys) : ""}</td>
    </tr>`;
  }).join("");

  panel.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:24px">
      <span class="entity-block-name">Dep-graph Status</span>
      <span class="actor-id-badge">${esc(screenId)}</span>
    </div>
    <div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">
      <thead><tr><th>Phase</th><th>Status</th><th style="text-align:center">Ver</th><th>Updated</th><th>Stale Keys</th></tr></thead>
      <tbody>${rows}</tbody>
    </table></div>`;
}

// ── Artifact tab (generic for all 3 phases) ───────────────────────────────────

function selectScreenField(idx, phase) {
  const panelId = "screen-panel-" + (phase === "2-business-spec" ? "business" : phase === "3-tech-spec" ? "techspec" : "implement");
  const panel = document.getElementById(panelId);
  if (!panel) return;
  panel.querySelectorAll(".screen-field-list-item").forEach((item, i) => item.classList.toggle("active", i === idx));
  const detailPanel = panel.querySelector(".artifact-field-detail-panel");
  if (!detailPanel) return;
  const state = _screenTabStates[phase];
  if (!state) return;
  const field = state.fields[idx];
  detailPanel.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:24px">
      <span class="entity-block-name">${esc(_SCREEN_FIELD_LABELS[field] || field)}</span>
    </div>
    ${_renderScreenFieldBody(field, state.content[field])}`;
}

function selectScreenPreview(screenId) {
  const panel = document.getElementById("screen-panel-business");
  if (!panel) return;
  panel.querySelectorAll(".screen-field-list-item").forEach(i => i.classList.remove("active"));
  const preview = panel.querySelector(".screen-preview-item");
  if (preview) preview.classList.add("active");
  const detailPanel = panel.querySelector(".artifact-field-detail-panel");
  if (!detailPanel) return;
  const sid = screenId || _currentScreenInfo.screenId;
  const url  = `/preview/2-business-spec/screens/html/${encodeURIComponent(sid)}.html`;
  const name = sid.replace(/-/g, " ");
  detailPanel.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:24px">
      <span class="entity-block-name">HTML Preview</span>
    </div>
    <div class="preview-grid">
      <a class="preview-link" href="${url}" target="_blank">🖼 ${esc(name)}</a>
    </div>`;
}

async function _loadScreenArtifactTab(panel, phase, screenId, moduleId) {
  panel.innerHTML = `<div class="empty">Memuat…</div>`;
  try {
    const key = moduleId + "." + screenId + "." + phase;
    const [data, screenFiles] = await Promise.all([
      api.fetchArtifact(key),
      phase === "2-business-spec" ? api.fetchScreenFiles() : Promise.resolve({ ids: [], htmlIds: [] }),
    ]);
    if (!data.content) {
      panel.innerHTML = `<div class="empty">Konten belum tersedia untuk fase ini.</div>`;
      return;
    }
    const content      = data.content;
    const schema       = data.schema || {};
    const displayOrder = schema._display_order || schema._tracked || [];
    const SKIP         = ["ver", "meta", "id", "name", "module_id"];
    const fields       = sortedFields(displayOrder, Object.keys(content).filter(k => !SKIP.includes(k)));

    if (!fields.length) {
      panel.innerHTML = `<div class="empty">Tidak ada data.</div>`;
      return;
    }

    _screenTabStates[phase] = { fields, content, screenId };

    const tabId = phase === "2-business-spec" ? "business" : phase === "3-tech-spec" ? "techspec" : "implement";
    const fieldItems = fields.map((field, i) => `
      <div class="entity-list-item screen-field-list-item${i === 0 ? " active" : ""}" onclick="selectScreenField(${i}, '${phase}')">
        <span class="entity-list-name">${esc(_SCREEN_FIELD_LABELS[field] || field)}</span>
      </div>`
    ).join("");

    const htmlIds = new Set((screenFiles.htmlIds) || []);
    const hasHtml = phase === "2-business-spec" && htmlIds.has(screenId || "");
    const previewItem = hasHtml ? `
      <div class="entity-list-item screen-field-list-item screen-preview-item" onclick="selectScreenPreview('${esc(screenId)}')">
        <span class="entity-list-name">🖼 HTML Preview</span>
      </div>` : "";

    panel.innerHTML = `<div class="entity-layout">
      <div class="entity-list">${fieldItems}${previewItem}</div>
      <div class="entity-detail-panel artifact-field-detail-panel">
        <div class="entity-block-header" style="margin-bottom:24px">
          <span class="entity-block-name">${esc(_SCREEN_FIELD_LABELS[fields[0]] || fields[0])}</span>
        </div>
        ${_renderScreenFieldBody(fields[0], content[fields[0]])}
      </div>
    </div>`;

  } catch (err) {
    panel.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

// ── Fill screen detail page ───────────────────────────────────────────────────

function _fillScreenPage(screenId, moduleId, screenName) {
  _currentScreenInfo = { screenId, moduleId, loaded: {} };
  _screenTabStates   = {};

  const el      = document.getElementById("screen-detail-content");
  const titleEl = document.getElementById("screen-detail-title");
  const idEl    = document.getElementById("screen-detail-id");
  const subEl   = document.getElementById("screen-detail-sub");
  if (titleEl) titleEl.textContent = "🖥 " + (screenName || screenId);
  if (idEl)    idEl.textContent    = screenId;
  if (subEl)   subEl.textContent   = moduleId;
  if (!el) return;

  el.innerHTML = `
    <div class="screen-tabs">
      <div class="screen-tab" id="screen-tab-depgraph"  onclick="_switchScreenDetailTab('depgraph')">Dep Graph</div>
      <div class="screen-tab" id="screen-tab-business"  onclick="_switchScreenDetailTab('business')">2 · Business Spec</div>
      <div class="screen-tab" id="screen-tab-techspec"  onclick="_switchScreenDetailTab('techspec')">3 · Tech Spec</div>
      <div class="screen-tab" id="screen-tab-implement" onclick="_switchScreenDetailTab('implement')">4 · Implementation</div>
    </div>
    <div class="screen-tab-body">
      <div class="screen-tab-panel" id="screen-panel-depgraph"><div class="empty">Memuat…</div></div>
      <div class="screen-tab-panel" id="screen-panel-business"><div class="empty">Memuat…</div></div>
      <div class="screen-tab-panel" id="screen-panel-techspec"><div class="empty">Memuat…</div></div>
      <div class="screen-tab-panel" id="screen-panel-implement"><div class="empty">Memuat…</div></div>
    </div>`;

  _switchScreenDetailTab("depgraph");
}

function openScreenPage(screenId, moduleId, screenName) {
  go("screen-detail", null, "screen/" + moduleId + "/" + screenId);
  _fillScreenPage(screenId, moduleId, screenName);
}

async function viewScreenJson() {
  const { screenId, moduleId } = _currentScreenInfo;
  if (!screenId) return;
  const modal   = document.getElementById("json-modal");
  const titleEl = document.getElementById("json-modal-title");
  const bodyEl  = document.getElementById("json-modal-body");
  titleEl.textContent = screenId + ".json";
  bodyEl.textContent  = "Memuat…";
  modal.classList.add("open");
  try {
    const key  = moduleId + "." + screenId + ".2-business-spec";
    const data = await api.fetchArtifact(key);
    bodyEl.textContent = JSON.stringify(data.content, null, 2);
  } catch (err) {
    bodyEl.textContent = "Error: " + err.message;
  }
}

// ── Entity Catalog page ───────────────────────────────────────────────────────

let _entityCatalogData = [];

function _renderEntityDetail(entity) {
  if (!entity) return '<div class="empty" style="margin-top:40px">Pilih entity di sebelah kiri.</div>';

  const fields = Array.isArray(entity.fields) && entity.fields.length
    ? `<div class="entity-section-label">Fields</div>
       <div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">
         <thead><tr><th>Field</th><th>Type</th><th style="text-align:center;width:72px">Required</th><th>Description</th></tr></thead>
         <tbody>${entity.fields.map(f => `<tr>
           <td class="tbl-muted" style="font-family:monospace;white-space:nowrap">${esc(f.name || "—")}</td>
           <td><code style="font-size:12px;color:var(--text2)">${esc(f.type || "—")}</code></td>
           <td style="text-align:center;color:${f.required ? "var(--green)" : "var(--text3)"}">${f.required ? "✓" : "—"}</td>
           <td>${esc(f.description || "")}</td>
         </tr>`).join("")}</tbody>
       </table></div>`
    : "";

  const relItems = Array.isArray(entity.relationships) ? entity.relationships.filter(r => r.entity_id) : [];
  const rels = relItems.length
    ? `<div class="entity-section-label">Relationships</div>
       <div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">
         <thead><tr><th>Entity</th><th>Type</th><th>Description</th></tr></thead>
         <tbody>${relItems.map(r => `<tr>
           <td class="tbl-muted" style="font-family:monospace;white-space:nowrap">${esc(r.entity_id)}</td>
           <td style="white-space:nowrap">${esc(r.type || "—")}</td>
           <td>${esc(r.description || "")}</td>
         </tr>`).join("")}</tbody>
       </table></div>`
    : "";

  const constraintItems = Array.isArray(entity.constraints) ? entity.constraints.filter(Boolean) : [];
  const constraints = constraintItems.length
    ? `<div class="entity-section-label">Constraints</div>
       <ul class="artifact-list">${constraintItems.map(c => `<li>${esc(c)}</li>`).join("")}</ul>`
    : "";

  return `
    <div class="entity-block-header">
      <span class="entity-block-name">${esc(entity.name || entity.id || "—")}</span>
      ${entity.id ? `<span class="actor-id-badge">${esc(entity.id)}</span>` : ""}
    </div>
    ${entity.description ? `<div class="entity-block-desc">${esc(entity.description)}</div>` : ""}
    ${fields}${rels}${constraints}`;
}

function selectEntity(idx) {
  document.querySelectorAll(".entity-list-item").forEach((el, i) => {
    el.classList.toggle("active", i === idx);
  });
  const detailEl = document.getElementById("entity-detail-panel");
  if (detailEl) detailEl.innerHTML = _renderEntityDetail(_entityCatalogData[idx]);
}

// ── Mermaid ER Diagram ────────────────────────────────────────────────────────

function _loadScript(url) {
  return new Promise((resolve, reject) => {
    const s   = document.createElement("script");
    s.src     = url;
    s.onload  = resolve;
    s.onerror = () => reject(new Error("Gagal memuat: " + url));
    document.head.appendChild(s);
  });
}

function diagramZoomIn()  { window._diagramPZ && window._diagramPZ.zoomIn(); }
function diagramZoomOut() { window._diagramPZ && window._diagramPZ.zoomOut(); }
function diagramReset()   { window._diagramPZ && window._diagramPZ.reset(); }

function _initDiagramPanZoom(container) {
  const svgEl = container.querySelector("svg");
  if (!svgEl) return;

  let scale = 1, panX = 0, panY = 0;
  let dragging = false, startX = 0, startY = 0, startPanX = 0, startPanY = 0;

  svgEl.style.transformOrigin = "center center";
  svgEl.style.transition      = "none";
  container.style.cursor      = "grab";
  container.style.overflow    = "hidden";
  container.style.userSelect  = "none";

  function applyTransform() {
    svgEl.style.transform = `translate(${panX}px,${panY}px) scale(${scale})`;
  }

  // Scroll to zoom (zoom toward cursor position)
  container.addEventListener("wheel", e => {
    e.preventDefault();
    const rect   = container.getBoundingClientRect();
    const factor = e.deltaY < 0 ? 1.15 : 0.87;
    const newScale = Math.max(0.15, Math.min(6, scale * factor));
    // Adjust pan so zoom anchors at mouse position
    const mx = e.clientX - rect.left - rect.width  / 2;
    const my = e.clientY - rect.top  - rect.height / 2;
    panX = mx - (mx - panX) * (newScale / scale);
    panY = my - (my - panY) * (newScale / scale);
    scale = newScale;
    applyTransform();
  }, { passive: false });

  // Drag to pan
  container.addEventListener("mousedown", e => {
    if (e.button !== 0) return;
    dragging = true;
    startX = e.clientX; startY = e.clientY;
    startPanX = panX;   startPanY = panY;
    container.style.cursor = "grabbing";
  });
  window.addEventListener("mousemove", e => {
    if (!dragging) return;
    panX = startPanX + (e.clientX - startX);
    panY = startPanY + (e.clientY - startY);
    applyTransform();
  });
  window.addEventListener("mouseup", () => {
    if (!dragging) return;
    dragging = false;
    container.style.cursor = "grab";
  });

  window._diagramPZ = {
    zoomIn:  () => { scale = Math.min(6,    scale * 1.2); applyTransform(); },
    zoomOut: () => { scale = Math.max(0.15, scale / 1.2); applyTransform(); },
    reset:   () => { scale = 1; panX = 0; panY = 0; applyTransform(); },
  };
}

function _generateMermaidER(entities) {
  const lines = ["erDiagram"];

  for (const entity of entities) {
    const eid    = entity.id.replace(/-/g, "_").toUpperCase();
    const fields = Array.isArray(entity.fields) ? entity.fields.filter(f => f.name) : [];
    lines.push(`  ${eid} {`);
    for (const f of fields) {
      // Strip enum(...) and special chars from type
      const type  = (f.type || "string").split("(")[0].trim().replace(/[^a-zA-Z0-9_]/g, "_") || "string";
      const fname = (f.name || "field").replace(/[^a-zA-Z0-9_]/g, "_");
      lines.push(`    ${type} ${fname}`);
    }
    lines.push(`  }`);
  }

  for (const entity of entities) {
    const fromId = entity.id.replace(/-/g, "_").toUpperCase();
    const rels   = Array.isArray(entity.relationships) ? entity.relationships.filter(r => r.entity_id) : [];
    for (const rel of rels) {
      const toId  = rel.entity_id.replace(/-/g, "_").toUpperCase();
      const arrow = rel.type === "many-to-many" ? "}o--o{" :
                    rel.type === "one-to-one"   ? "||--||" : "||--o{";
      const label = (rel.type || "relates").replace(/"/g, "'");
      lines.push(`  ${fromId} ${arrow} ${toId} : "${label}"`);
    }
  }

  return lines.join("\n");
}

async function renderEntityDiagramView() {
  const el = document.getElementById("entity-diagram-view");
  if (!el) return;

  if (_entityCatalogData.length === 0) {
    el.innerHTML = '<div class="empty">Belum ada data entity untuk diagram.</div>';
    return;
  }

  el.innerHTML = '<div class="empty">Merender diagram…</div>';

  try {
    if (!window.mermaid) await _loadScript("https://cdnjs.cloudflare.com/ajax/libs/mermaid/10.6.1/mermaid.min.js");

    const isDark = !document.body.classList.contains("light");
    window.mermaid.initialize({ startOnLoad: false, theme: isDark ? "dark" : "default" });

    const code     = _generateMermaidER(_entityCatalogData);
    const renderId = "mermaid-er-" + Date.now();
    const { svg }  = await window.mermaid.render(renderId, code);

    el.innerHTML = `
      <div class="diagram-toolbar">
        <button class="diagram-btn" onclick="diagramZoomIn()">＋ Zoom In</button>
        <button class="diagram-btn" onclick="diagramZoomOut()">－ Zoom Out</button>
        <button class="diagram-btn" onclick="diagramReset()">⟳ Reset</button>
        <span class="diagram-hint">Scroll untuk zoom · Drag untuk pan</span>
      </div>
      <div class="entity-diagram-wrap" id="entity-diagram-wrap">${svg}</div>`;

    requestAnimationFrame(() => {
      const wrap = document.getElementById("entity-diagram-wrap");
      if (wrap) _initDiagramPanZoom(wrap);
    });

  } catch (err) {
    const code = _entityCatalogData.length ? _generateMermaidER(_entityCatalogData) : "";
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal render diagram: ${esc(err.message)}</div>`
      + (code ? `<pre class="entity-diagram-code">${esc(code)}</pre>` : "");
  }
}

function switchEntityView(view) {
  const splitView = document.getElementById("entity-split-view");
  const diagView  = document.getElementById("entity-diagram-view");
  const tabDetail = document.getElementById("entity-tab-detail");
  const tabDiag   = document.getElementById("entity-tab-diagram");
  if (!splitView || !diagView) return;

  if (view === "diagram") {
    splitView.style.display = "none";
    diagView.style.display  = "block";
    tabDetail.classList.remove("active");
    tabDiag.classList.add("active");
    renderEntityDiagramView();
  } else {
    splitView.style.display = "flex";
    diagView.style.display  = "none";
    tabDetail.classList.add("active");
    tabDiag.classList.remove("active");
  }
}

async function renderEntityCatalogPage(data) {
  const el = document.getElementById("entity-catalog-content");
  if (!el) return;

  el.innerHTML = '<div class="empty">Memuat…</div>';

  try {
    const res = await api.fetchArtifact("project.3-tech-spec.entity-catalog");
    _entityCatalogData = res.content && Array.isArray(res.content.entities) ? res.content.entities : [];

    if (_entityCatalogData.length === 0) {
      el.innerHTML = `<div class="empty">Belum ada data entity.</div>`;
      return;
    }

    const listHtml = _entityCatalogData.map((entity, i) => `
      <div class="entity-list-item${i === 0 ? " active" : ""}" onclick="selectEntity(${i})">
        <span class="entity-list-name">${esc(entity.name || entity.id || "—")}</span>
        ${entity.id ? `<span class="actor-id-badge">${esc(entity.id)}</span>` : ""}
      </div>`
    ).join("");

    el.innerHTML = `
      <div class="entity-view-tabs">
        <button class="entity-view-tab active" id="entity-tab-detail" onclick="switchEntityView('detail')">Detail</button>
        <button class="entity-view-tab" id="entity-tab-diagram" onclick="switchEntityView('diagram')">⬡ ER Diagram</button>
      </div>
      <div id="entity-split-view" class="entity-layout">
        <div class="entity-list">${listHtml}</div>
        <div class="entity-detail-panel" id="entity-detail-panel">${_renderEntityDetail(_entityCatalogData[0])}</div>
      </div>
      <div id="entity-diagram-view" style="display:none"></div>`;

  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

// ── Shared Decisions page ────────────────────────────────────────────────────

const DECISION_NAV = [
  { key: "auth",               icon: "🔐", label: "Auth" },
  { key: "error_format",       icon: "⚠️",  label: "Error Format" },
  { key: "pagination",         icon: "📄", label: "Pagination" },
  { key: "naming_conventions", icon: "📝", label: "Naming Conventions" },
  { key: "integrations",       icon: "🔌", label: "Integrations" },
  { key: "other_decisions",    icon: "📌", label: "Other Decisions" },
];

let _sharedDecisionsContent = null;

function _renderDecisionDetail(key, content) {
  const value = content ? content[key] : null;

  if (value === null || value === undefined || value === "") {
    return '<div class="empty">Belum ada data.</div>';
  }

  // other_decisions → array of strings
  if (key === "other_decisions") {
    const items = Array.isArray(value) ? value.filter(Boolean) : [];
    if (!items.length) return '<div class="empty">Belum ada data.</div>';
    return `<ul class="artifact-list">${items.map(i => `<li>${esc(i)}</li>`).join("")}</ul>`;
  }

  // integrations → array of objects
  if (key === "integrations") {
    const items = Array.isArray(value) ? value.filter(i => i.name) : [];
    if (!items.length) return '<div class="empty">Belum ada integrasi.</div>';
    return items.map(intg => {
      const rows = [
        { k: "Auth Method", v: intg.auth_method },
        { k: "Key Config",  v: intg.key_config  },
        { k: "Notes",       v: intg.notes        },
      ].filter(r => r.v);
      return `<div class="decision-integration">
        <div class="decision-integration-name">${esc(intg.name)}</div>
        ${rows.length ? `<div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">
          <tbody>${rows.map(r => `<tr>
            <td class="tbl-muted" style="width:130px;white-space:nowrap">${esc(r.k)}</td>
            <td style="white-space:pre-wrap">${esc(r.v)}</td>
          </tr>`).join("")}</tbody>
        </table></div>` : ""}
      </div>`;
    }).join(`<div style="height:20px"></div>`);
  }

  // plain object → key-value table with Key / Value header
  if (typeof value === "object" && !Array.isArray(value)) {
    const rows = Object.entries(value).filter(([, v]) => v !== "" && v !== null && v !== undefined);
    if (!rows.length) return '<div class="empty">Belum ada data.</div>';
    return `<div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">
      <thead><tr><th>Key</th><th>Value</th></tr></thead>
      <tbody>${rows.map(([k, v]) => `<tr>
        <td class="tbl-muted" style="width:160px;white-space:nowrap">${esc(k.replace(/_/g, " "))}</td>
        <td style="white-space:pre-wrap">${esc(String(v))}</td>
      </tr>`).join("")}</tbody>
    </table></div>`;
  }

  return `<div class="artifact-field-value">${renderVal(value)}</div>`;
}

function selectDecision(key) {
  document.querySelectorAll(".decision-list-item").forEach(el => {
    el.classList.toggle("active", el.dataset.key === key);
  });
  const panel = document.getElementById("decision-detail-panel");
  if (!panel) return;
  const nav = DECISION_NAV.find(d => d.key === key);
  panel.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:30px">
      <span class="entity-block-name">${nav ? nav.icon + " " + nav.label : key}</span>
    </div>
    ${_renderDecisionDetail(key, _sharedDecisionsContent)}`;
}

async function renderSharedDecisionsPage(data) {
  const el = document.getElementById("shared-decisions-content");
  if (!el) return;

  el.innerHTML = '<div class="empty">Memuat…</div>';

  try {
    const res = await api.fetchArtifact("project.3-tech-spec.shared-decisions");
    _sharedDecisionsContent = res.content || null;

    if (!_sharedDecisionsContent) {
      el.innerHTML = `<div class="empty">Artifact belum ditulis.</div>`;
      return;
    }

    const listHtml = DECISION_NAV.map((item, i) => `
      <div class="entity-list-item decision-list-item${i === 0 ? " active" : ""}"
           data-key="${item.key}" onclick="selectDecision('${item.key}')">
        <span class="decision-list-icon">${item.icon}</span>
        <span class="entity-list-name">${item.label}</span>
      </div>`
    ).join("");

    const first = DECISION_NAV[0];
    el.innerHTML = `<div class="entity-layout">
      <div class="entity-list">${listHtml}</div>
      <div class="entity-detail-panel" id="decision-detail-panel">
        <div class="entity-block-header" style="margin-bottom:30px">
          <span class="entity-block-name">${first.icon} ${first.label}</span>
        </div>
        ${_renderDecisionDetail(first.key, _sharedDecisionsContent)}
      </div>
    </div>`;

  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

// ── API Index page ────────────────────────────────────────────────────────────

let _apiEndpoints   = [];
let _apiScreenOrder = [];

const METHOD_COLOR = {
  GET:    { color: "var(--green)",  bg: "var(--green-dim)"  },
  POST:   { color: "var(--blue)",   bg: "var(--blue-dim)"   },
  PUT:    { color: "var(--yellow)", bg: "var(--yellow-dim)" },
  PATCH:  { color: "var(--yellow)", bg: "var(--yellow-dim)" },
  DELETE: { color: "var(--red)",    bg: "var(--red-dim)"    },
};

function _methodBadge(method) {
  const m = (method || "").toUpperCase();
  const c = METHOD_COLOR[m] || { color: "var(--text2)", bg: "var(--bg3)" };
  return `<span class="api-method-badge" style="color:${c.color};background:${c.bg}">${esc(m)}</span>`;
}

function _renderApiEndpointsTable(endpoints) {
  if (!endpoints.length) return '<div class="empty">Tidak ada endpoint.</div>';
  return `<div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">
    <thead><tr><th style="width:90px">Method</th><th>Path</th><th>Description</th><th style="width:100px">Use Case</th><th style="text-align:center;width:60px">Auth</th><th>Actors</th></tr></thead>
    <tbody>${endpoints.map(ep => `<tr>
      <td>${_methodBadge(ep.method)}</td>
      <td style="font-family:monospace;font-size:12px;white-space:nowrap">${esc(ep.path || "—")}</td>
      <td>${esc(ep.description || "")}</td>
      <td style="font-family:monospace;font-size:11px;color:var(--text3)">${esc(ep.usecase_id || "—")}</td>
      <td style="text-align:center;color:${ep.auth_required ? "var(--green)" : "var(--text3)"}">${ep.auth_required ? "✓" : "—"}</td>
      <td style="font-size:12px;color:var(--text2)">${Array.isArray(ep.actor_ids) ? ep.actor_ids.filter(Boolean).join(", ") || "—" : "—"}</td>
    </tr>`).join("")}</tbody>
  </table></div>`;
}

function selectApiScreen(screenId) {
  document.querySelectorAll(".api-list-item").forEach(el => {
    el.classList.toggle("active", el.dataset.screen === screenId);
  });
  const panel = document.getElementById("api-detail-panel");
  if (!panel) return;

  const endpoints = screenId === "__all__"
    ? _apiEndpoints
    : _apiEndpoints.filter(ep => ep.screen_id === screenId);

  const title = screenId === "__all__"
    ? `All Endpoints <span style="font-size:13px;font-weight:400;color:var(--text3)">(${_apiEndpoints.length})</span>`
    : `${esc(screenId)} <span style="font-size:13px;font-weight:400;color:var(--text3)">(${endpoints.length})</span>`;

  panel.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:30px">
      <span class="entity-block-name">${title}</span>
    </div>
    ${_renderApiEndpointsTable(endpoints)}`;
}

async function renderApiIndexPage(data) {
  const el = document.getElementById("api-index-content");
  if (!el) return;

  el.innerHTML = '<div class="empty">Memuat…</div>';

  try {
    const res = await api.fetchArtifact("project.3-tech-spec.api-index");
    _apiEndpoints = res.content && Array.isArray(res.content.endpoints)
      ? res.content.endpoints.filter(ep => ep.method || ep.path)
      : [];

    if (!_apiEndpoints.length) {
      el.innerHTML = `<div class="empty">Artifact belum ditulis atau belum ada endpoint.</div>`;
      return;
    }

    // Collect unique screen_ids preserving insertion order
    _apiScreenOrder = [];
    const seen = new Set();
    for (const ep of _apiEndpoints) {
      const sid = ep.screen_id || "—";
      if (!seen.has(sid)) { seen.add(sid); _apiScreenOrder.push(sid); }
    }

    const listHtml = [
      `<div class="entity-list-item api-list-item active" data-screen="__all__" onclick="selectApiScreen('__all__')">
        <span class="entity-list-name">All Endpoints</span>
        <span class="actor-id-badge">${_apiEndpoints.length}</span>
      </div>`,
      ..._apiScreenOrder.map(sid => {
        const count = _apiEndpoints.filter(ep => (ep.screen_id || "—") === sid).length;
        return `<div class="entity-list-item api-list-item" data-screen="${esc(sid)}" onclick="selectApiScreen('${sid.replace(/'/g,"\\'")}')">
          <span class="entity-list-name">${esc(sid)}</span>
          <span class="actor-id-badge">${count}</span>
        </div>`;
      }),
    ].join("");

    const allTitle = `All Endpoints <span style="font-size:13px;font-weight:400;color:var(--text3)">(${_apiEndpoints.length})</span>`;
    el.innerHTML = `<div class="entity-layout">
      <div class="entity-list">${listHtml}</div>
      <div class="entity-detail-panel" id="api-detail-panel">
        <div class="entity-block-header" style="margin-bottom:30px">
          <span class="entity-block-name">${allTitle}</span>
        </div>
        ${_renderApiEndpointsTable(_apiEndpoints)}
      </div>
    </div>`;

  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

// ── Use Case Index page ───────────────────────────────────────────────────────

async function renderUsecaseIndexPage(data) {
  const el = document.getElementById("usecase-index-content");
  if (!el) return;

  const key = "project.2-business-spec.usecase-index";

  el.innerHTML = '<div class="empty">Memuat…</div>';

  try {
    const [res, screenIndexRes, screenFiles] = await Promise.all([
      api.fetchArtifact(key),
      api.fetchArtifact("project.2-business-spec.screen-index"),
      api.fetchScreenFiles(),
    ]);
    const availableIds  = new Set((screenFiles.ids) || []);
    const screenModules = {};
    ((screenIndexRes.content && screenIndexRes.content.screens) || []).forEach(s => {
      if (s.id) screenModules[s.id] = s.module_id || "";
    });
    const usecases = res.content && Array.isArray(res.content.usecases) ? res.content.usecases : [];

    if (usecases.length === 0) {
      el.innerHTML = `<div class="empty">Belum ada data use case.</div>`;
      return;
    }

    const rows = usecases.map(uc => {
      const screens = Array.isArray(uc.screen_ids) && uc.screen_ids.length
        ? `<ul class="uc-screen-list">${uc.screen_ids.map(s => {
            if (availableIds.has(s)) {
              const mid   = screenModules[s] || "";
              const sname = s.replace(/'/g, "\\'");
              return `<li><a class="screen-detail-link" href="#screen/${mid}/${s}" onclick="event.preventDefault();openScreenPage('${esc(s)}','${esc(mid)}','${sname}')">${esc(s)}</a></li>`;
            }
            return `<li>${esc(s)}</li>`;
          }).join("")}</ul>`
        : '<span style="color:var(--text3)">—</span>';
      const ucId   = (uc.id || "").replace(/'/g, "\\'");
      const ucName = (uc.name || "").replace(/'/g, "\\'");
      return `<tr>
        <td>${esc(uc.id || "—")}</td>
        <td class="name">${esc(uc.name || "—")}</td>
        <td>${screens}</td>
        <td><a class="screen-detail-link" href="#usecase/${esc(uc.id || "")}" onclick="event.preventDefault();openUsecaseDetailPage('${ucId}','${ucName}')">Detail →</a></td>
      </tr>`;
    }).join("");

    el.innerHTML = `
      <div class="tbl-wrap">
        <table class="artifact-tbl artifact-tbl--auto">
          <thead><tr><th>ID</th><th>Use Case</th><th>Screens</th><th>Detail</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

let _currentUsecaseId  = null;
let _usecaseDetailState = { fields: [], content: {}, usecaseId: "" };

const _USECASE_FIELD_LABELS = {
  description:         "Description",
  actors:               "Actors",
  preconditions:        "Preconditions",
  main_flow:            "Main Flow",
  alternative_flows:    "Alternative Flows",
  postconditions:       "Postconditions",
  business_rules:       "Business Rules",
  bdd_scenarios:        "BDD Scenarios",
  related_screen_ids:   "Related Screen IDs",
};

async function _fillUsecasePage(usecaseId, usecaseName) {
  _currentUsecaseId = usecaseId;
  const titleEl = document.getElementById("usecase-detail-title");
  const idEl    = document.getElementById("usecase-detail-id");
  const el      = document.getElementById("usecase-detail-content");
  if (titleEl) titleEl.textContent = "📌 " + (usecaseName || usecaseId);
  if (idEl)    idEl.textContent    = usecaseId;
  if (!el) return;

  el.innerHTML = '<div class="empty">Memuat…</div>';
  const key = `project.2-business-spec.usecases.${usecaseId}`;

  try {
    const data = await api.fetchArtifact(key);
    if (!data.content) {
      el.innerHTML = `<div class="empty">Use case belum ditulis (not started).</div>`;
      return;
    }
    const content      = data.content;
    const schema       = data.schema || {};
    const displayOrder = schema._display_order || schema._tracked || [];
    const SKIP          = ["ver", "meta", "id", "name"];
    const fields        = sortedFields(displayOrder, Object.keys(content).filter(k => !SKIP.includes(k)));

    if (titleEl && !usecaseName) titleEl.textContent = "📌 " + (content.name || usecaseId);

    if (!fields.length) {
      el.innerHTML = `<div class="empty">Tidak ada data.</div>`;
      return;
    }

    _usecaseDetailState = { fields, content, usecaseId };

    const fieldItems = fields.map((field, i) => `
      <div class="entity-list-item screen-field-list-item${i === 0 ? " active" : ""}" onclick="selectUsecaseField(${i})">
        <span class="entity-list-name">${esc(_USECASE_FIELD_LABELS[field] || field)}</span>
      </div>`
    ).join("");

    el.innerHTML = `<div class="entity-layout">
      <div class="entity-list">${fieldItems}</div>
      <div class="entity-detail-panel artifact-field-detail-panel">
        <div class="entity-block-header" style="margin-bottom:24px">
          <span class="entity-block-name">${esc(_USECASE_FIELD_LABELS[fields[0]] || fields[0])}</span>
        </div>
        ${_renderScreenFieldBody(fields[0], content[fields[0]])}
      </div>
    </div>`;

  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

function selectUsecaseField(idx) {
  const el = document.getElementById("usecase-detail-content");
  if (!el) return;
  el.querySelectorAll(".screen-field-list-item").forEach((item, i) => item.classList.toggle("active", i === idx));
  const panel = el.querySelector(".artifact-field-detail-panel");
  if (!panel) return;
  const { fields, content } = _usecaseDetailState;
  const field = fields[idx];
  panel.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:24px">
      <span class="entity-block-name">${esc(_USECASE_FIELD_LABELS[field] || field)}</span>
    </div>
    ${_renderScreenFieldBody(field, content[field])}`;
}

function openUsecaseDetailPage(usecaseId, usecaseName) {
  go("usecase-detail", null, "usecase/" + usecaseId);
  _fillUsecasePage(usecaseId, usecaseName);
}

async function viewUsecaseJson() {
  if (!_currentUsecaseId) return;
  const key     = `project.2-business-spec.usecases.${_currentUsecaseId}`;
  const modal   = document.getElementById("json-modal");
  const titleEl = document.getElementById("json-modal-title");
  const bodyEl  = document.getElementById("json-modal-body");
  titleEl.textContent = _currentUsecaseId + ".json";
  bodyEl.textContent  = "Memuat…";
  modal.classList.add("open");
  try {
    const data = await api.fetchArtifact(key);
    bodyEl.textContent = JSON.stringify(data.content, null, 2);
  } catch (err) {
    bodyEl.textContent = "Error: " + err.message;
  }
}

// ── Dedicated artifact pages ──────────────────────────────────────────────────

const PAGE_ARTIFACT_KEY = {
  "prd":           "project.1-foundation.prd",
  "arch-spec":     "project.1-foundation.arch-spec",
  "uiux-spec":     "project.1-foundation.uiux-spec",
  "test-strategy": "project.1-foundation.test-strategy",
  "actor-index":   "project.2-business-spec.actor-index",
  "usecase-index": "project.2-business-spec.usecase-index",
  "screen-index":  "project.2-business-spec.screen-index",
  "entity-catalog":   "project.3-tech-spec.entity-catalog",
  "shared-decisions": "project.3-tech-spec.shared-decisions",
  "api-index":        "project.3-tech-spec.api-index",
  "scaffold":         "project.4-implement.scaffold",
  "entity-models":    "project.4-implement.entity-models",
  "shared-modules":   "project.4-implement.shared-modules",
};

let _artifactPageState = { pageId: "", fields: [], content: {}, fieldNodes: {}, schema: {}, _previews: null };

function _renderArtifactFieldBody(field, value, schema) {
  const customRenderer = CUSTOM_RENDERER[field];
  if (customRenderer) return `<div class="artifact-field-value">${customRenderer(value)}</div>`;
  const tableCols = TABLE_SCHEMA[field];
  if (tableCols && Array.isArray(value)) return renderTable(value, tableCols);
  return `<div class="artifact-field-value">${renderVal(value)}</div>`;
}

function _renderArtifactFieldPanel(field) {
  const { content, fieldNodes, schema } = _artifactPageState;
  const label     = FIELD_LABELS[field] || field;
  const node      = fieldNodes[field];
  const isTracked = (schema._tracked || []).includes(field);
  const metaHtml  = node
    ? `<div style="display:flex;align-items:center;gap:8px;margin-bottom:30px">
        ${isTracked ? `<span class="actor-id-badge">tracked</span>` : ""}
        <span style="font-size:12px;color:var(--text3)">v${node.ver || 0} · ${fmtDate(node.updated_at)}</span>
       </div>`
    : "";
  return `
    <div class="entity-block-header" style="margin-bottom:${metaHtml ? "8px" : "24px"}">
      <span class="entity-block-name">${esc(label)}</span>
    </div>
    ${metaHtml}
    ${_renderArtifactFieldBody(field, content[field], schema)}`;
}

function selectArtifactField(idx) {
  const { pageId, fields } = _artifactPageState;
  const contentEl = document.getElementById(pageId + "-content");
  if (!contentEl) return;
  contentEl.querySelectorAll(".artifact-field-list-item").forEach((el, i) => {
    el.classList.toggle("active", i === idx);
  });
  const panel = contentEl.querySelector(".artifact-field-detail-panel");
  if (!panel) return;
  panel.innerHTML = _renderArtifactFieldPanel(fields[idx]);
}

function selectArtifactPreview() {
  const { pageId, _previews } = _artifactPageState;
  const contentEl = document.getElementById(pageId + "-content");
  if (!contentEl) return;
  const items = contentEl.querySelectorAll(".artifact-field-list-item");
  items.forEach(el => el.classList.remove("active"));
  if (items.length) items[items.length - 1].classList.add("active");
  const panel = contentEl.querySelector(".artifact-field-detail-panel");
  if (!panel || !_previews) return;
  panel.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:24px">
      <span class="entity-block-name">HTML Previews</span>
    </div>
    <div class="preview-grid">${_previews.map(f =>
      `<a class="preview-link" href="${f.url}" target="_blank">🖼 ${esc(f.name.replace(".html","").replace(/-/g," "))}</a>`
    ).join("")}</div>`;
}

async function renderArtifactPage(pageId, data) {
  const key = PAGE_ARTIFACT_KEY[pageId];
  const el  = document.getElementById(pageId + "-content");
  if (!el || !key) return;

  el.innerHTML = '<div class="empty">Memuat konten…</div>';

  try {
    const res = await api.fetchArtifact(key);
    if (!res.content) {
      el.innerHTML = `<div class="empty">Artifact belum ditulis (not started).</div>`;
      return;
    }

    const content      = res.content;
    const schema       = res.schema || {};
    const fieldNodes   = res.fieldNodes || {};
    const displayOrder = schema._display_order || [];
    const SKIP         = ["ver"];
    const fields       = sortedFields(displayOrder, Object.keys(content).filter(k => !SKIP.includes(k)));

    _artifactPageState = { pageId, fields, content, fieldNodes, schema, _previews: null };

    const listHtml = fields.map((field, i) => {
      const label = FIELD_LABELS[field] || field;
      const node  = fieldNodes[field];
      const verBadge = node
        ? `<span style="font-size:10px;color:var(--text3);flex-shrink:0;margin-left:auto">v${node.ver || 0}</span>`
        : "";
      return `<div class="entity-list-item artifact-field-list-item${i === 0 ? " active" : ""}" onclick="selectArtifactField(${i})">
        <span class="entity-list-name">${esc(label)}</span>
        ${verBadge}
      </div>`;
    }).join("");

    el.innerHTML = `<div class="entity-layout">
      <div class="entity-list" id="${pageId}-field-list">${listHtml}</div>
      <div class="entity-detail-panel artifact-field-detail-panel">
        ${_renderArtifactFieldPanel(fields[0])}
      </div>
    </div>`;

    // uiux-spec: append preview item to list
    if (pageId === "uiux-spec") {
      try {
        const previews = await api.fetchUiuxPreviews();
        if (previews.files && previews.files.length > 0) {
          _artifactPageState._previews = previews.files;
          const listEl = document.getElementById("uiux-spec-field-list");
          if (listEl) {
            listEl.innerHTML += `<div class="entity-list-item artifact-field-list-item" onclick="selectArtifactPreview()">
              <span class="entity-list-name">🖼 HTML Previews</span>
            </div>`;
          }
        }
      } catch {}
    }

  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

// ── Screen Impl Index page ────────────────────────────────────────────────────

let _implScreensData = { screens: [], modules: {}, moduleOrder: [] };

function _implStatusBadge(status) {
  const map = {
    complete: { label: "Complete", color: "var(--green)",  bg: "var(--green-dim)"  },
    partial:  { label: "Partial",  color: "var(--yellow)", bg: "var(--yellow-dim)" },
    wip:      { label: "WIP",      color: "var(--blue)",   bg: "var(--blue-dim)"   },
  };
  const s = map[status];
  if (!s) return `<span style="color:var(--text3)">—</span>`;
  return `<span style="font-size:11px;font-weight:600;padding:2px 7px;border-radius:4px;color:${s.color};background:${s.bg}">${s.label}</span>`;
}

function _buildImplScreenTable(screens) {
  if (!screens.length) return '<div class="empty">Tidak ada screen.</div>';
  const rows = screens.map(s => {
    const sid   = esc(s.id   || "");
    const mid   = esc(s.module_id || "");
    const sname = (s.name || s.id || "").replace(/'/g, "\\'");
    return `<tr>
      <td style="font-family:monospace;font-size:12px;white-space:nowrap">${esc(s.id || "—")}</td>
      <td>${esc(s.name || "—")}</td>
      <td>${_implStatusBadge(s.status)}</td>
      <td><a class="screen-detail-link" href="#screen-impl/${mid}/${sid}" onclick="event.preventDefault();openScreenImplPage('${sid}','${mid}','${sname}')">Detail →</a></td>
    </tr>`;
  }).join("");
  return `<div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">
    <thead><tr><th>ID</th><th>Name</th><th>Status</th><th></th></tr></thead>
    <tbody>${rows}</tbody>
  </table></div>`;
}

function selectImplModule(moduleId) {
  document.querySelectorAll(".impl-module-item").forEach(el => {
    el.classList.toggle("active", el.dataset.module === moduleId);
  });
  const panel = document.getElementById("screen-impl-index-detail");
  if (!panel) return;
  const { screens, modules } = _implScreensData;
  const isAll = moduleId === "__all__";
  const list  = isAll ? screens : (modules[moduleId] || []);
  const title = isAll
    ? `All Screens <span style="font-size:13px;font-weight:400;color:var(--text3)">(${list.length})</span>`
    : `${esc(moduleId)} <span style="font-size:13px;font-weight:400;color:var(--text3)">(${list.length})</span>`;
  panel.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:30px">
      <span class="entity-block-name">${title}</span>
    </div>
    ${_buildImplScreenTable(list)}`;
}

async function renderScreenImplIndexPage(data) {
  const el = document.getElementById("screen-impl-index-content");
  if (!el) return;

  el.innerHTML = '<div class="empty">Memuat…</div>';

  try {
    const res     = await api.fetchImplScreens();
    const screens = res.screens || [];

    if (!screens.length) {
      el.innerHTML = `<div class="empty">Belum ada data screen implementation.</div>`;
      return;
    }

    const moduleOrder = [];
    const modules = {};
    for (const s of screens) {
      const mid = s.module_id || "—";
      if (!modules[mid]) { modules[mid] = []; moduleOrder.push(mid); }
      modules[mid].push(s);
    }
    _implScreensData = { screens, modules, moduleOrder };

    const listHtml = [
      `<div class="entity-list-item impl-module-item active" data-module="__all__" onclick="selectImplModule('__all__')">
        <span class="entity-list-name">All Screens</span>
        <span class="actor-id-badge">${screens.length}</span>
      </div>`,
      ...moduleOrder.map(mid => `
        <div class="entity-list-item impl-module-item" data-module="${esc(mid)}" onclick="selectImplModule('${mid.replace(/'/g, "\\'")}')">
          <span class="entity-list-name">${esc(mid)}</span>
          <span class="actor-id-badge">${modules[mid].length}</span>
        </div>`),
    ].join("");

    const allTitle = `All Screens <span style="font-size:13px;font-weight:400;color:var(--text3)">(${screens.length})</span>`;
    el.innerHTML = `<div class="entity-layout">
      <div class="entity-list">${listHtml}</div>
      <div class="entity-detail-panel" id="screen-impl-index-detail">
        <div class="entity-block-header" style="margin-bottom:30px">
          <span class="entity-block-name">${allTitle}</span>
        </div>
        ${_buildImplScreenTable(screens)}
      </div>
    </div>`;

  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

// ── Screen Impl Detail page ───────────────────────────────────────────────────

let _currentScreenImplKey = null;

const _IMPL_FIELD_LABELS = {
  status:                  "Status",
  files_generated:         "Files Generated",
  test_files_generated:    "Test Files Generated",
  fe_files_generated:      "FE Files Generated",
  fe_test_files_generated: "FE Test Files Generated",
  implementation_notes:    "Implementation Notes",
  deferred_items:          "Deferred Items",
  known_issues:            "Known Issues",
};

const _IMPL_FIELD_ORDER = [
  "status", "files_generated", "test_files_generated",
  "fe_files_generated", "fe_test_files_generated",
  "implementation_notes", "deferred_items", "known_issues",
];

let _screenImplDetailState = { fields: [], content: {}, screenId: "", moduleId: "" };

function _renderImplFieldBody(field, value) {
  if (field === "status") return _implStatusBadge(value);
  if (Array.isArray(value)) {
    if (!value.length) return `<div class="artifact-field-value" style="color:var(--text3)">—</div>`;
    return `<ul class="artifact-list">${value.map(i => `<li>${esc(String(i))}</li>`).join("")}</ul>`;
  }
  return `<div class="artifact-field-value">${renderVal(value)}</div>`;
}

function selectScreenImplField(idx) {
  const el = document.getElementById("screen-impl-detail-content");
  if (!el) return;
  el.querySelectorAll(".screen-impl-field-item").forEach((item, i) => item.classList.toggle("active", i === idx));
  const panel = el.querySelector(".screen-impl-detail-panel");
  if (!panel) return;
  const { fields, content } = _screenImplDetailState;
  const field = fields[idx];
  panel.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:24px">
      <span class="entity-block-name">${esc(_IMPL_FIELD_LABELS[field] || field)}</span>
    </div>
    ${_renderImplFieldBody(field, content[field])}`;
}

function selectScreenImplPreview(screenId) {
  const el = document.getElementById("screen-impl-detail-content");
  if (!el) return;
  const items = el.querySelectorAll(".screen-impl-field-item");
  items.forEach(i => i.classList.remove("active"));
  if (items.length) items[items.length - 1].classList.add("active");
  const panel = el.querySelector(".screen-impl-detail-panel");
  if (!panel) return;
  const url  = `/preview/2-business-spec/screens/html/${encodeURIComponent(screenId)}.html`;
  const name = screenId.replace(/-/g, " ");
  panel.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:24px">
      <span class="entity-block-name">HTML Preview</span>
    </div>
    <div class="preview-grid">
      <a class="preview-link" href="${url}" target="_blank">🖼 ${esc(name)}</a>
    </div>`;
}

async function _loadScreenImplDetail(el, key, screenId, moduleId) {
  try {
    const [data, screenFiles] = await Promise.all([
      api.fetchArtifact(key),
      api.fetchScreenFiles(),
    ]);
    const htmlIds = new Set(screenFiles.htmlIds || []);

    if (!data.content) {
      el.innerHTML = `<div class="empty">Konten belum tersedia.</div>`;
      return;
    }
    const content = data.content;
    const SKIP    = ["id", "name", "module_id", "ver"];
    const fields  = sortedFields(_IMPL_FIELD_ORDER, Object.keys(content).filter(k => !SKIP.includes(k)));

    if (!fields.length) { el.innerHTML = `<div class="empty">Tidak ada data.</div>`; return; }

    _screenImplDetailState = { fields, content, screenId: screenId || "", moduleId: moduleId || "" };

    const listHtml = fields.map((field, i) => `
      <div class="entity-list-item screen-impl-field-item${i === 0 ? " active" : ""}" onclick="selectScreenImplField(${i})">
        <span class="entity-list-name">${esc(_IMPL_FIELD_LABELS[field] || field)}</span>
      </div>`
    ).join("");

    const hasHtml = htmlIds.has(screenId || "");
    const previewItem = hasHtml ? `
      <div class="entity-list-item screen-impl-field-item" onclick="selectScreenImplPreview('${esc(screenId)}')">
        <span class="entity-list-name">🖼 HTML Preview</span>
      </div>` : "";

    el.innerHTML = `<div class="entity-layout">
      <div class="entity-list">${listHtml}${previewItem}</div>
      <div class="entity-detail-panel screen-impl-detail-panel">
        <div class="entity-block-header" style="margin-bottom:24px">
          <span class="entity-block-name">${esc(_IMPL_FIELD_LABELS[fields[0]] || fields[0])}</span>
        </div>
        ${_renderImplFieldBody(fields[0], content[fields[0]])}
      </div>
    </div>`;

  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

function _fillScreenImplPage(screenId, moduleId, screenName) {
  _currentScreenImplKey = moduleId + "." + screenId + ".4-implement";
  const el      = document.getElementById("screen-impl-detail-content");
  const titleEl = document.getElementById("screen-impl-detail-title");
  const idEl    = document.getElementById("screen-impl-detail-id");
  const subEl   = document.getElementById("screen-impl-detail-sub");
  if (titleEl) titleEl.textContent = "🖥️ " + (screenName || screenId);
  if (idEl)    idEl.textContent    = screenId;
  if (subEl)   subEl.textContent   = moduleId;
  if (el) { el.innerHTML = '<div class="empty">Memuat…</div>'; _loadScreenImplDetail(el, _currentScreenImplKey, screenId, moduleId); }
}

function openScreenImplPage(screenId, moduleId, screenName) {
  go("screen-impl-detail", null, "screen-impl/" + moduleId + "/" + screenId);
  _fillScreenImplPage(screenId, moduleId, screenName);
}

async function viewScreenImplJson() {
  if (!_currentScreenImplKey) return;
  const modal   = document.getElementById("json-modal");
  const titleEl = document.getElementById("json-modal-title");
  const bodyEl  = document.getElementById("json-modal-body");
  const parts   = _currentScreenImplKey.split(".");
  titleEl.textContent = (parts[1] || "screen") + ".json";
  bodyEl.textContent  = "Memuat…";
  modal.classList.add("open");
  try {
    const data = await api.fetchArtifact(_currentScreenImplKey);
    bodyEl.textContent = JSON.stringify(data.content, null, 2);
  } catch (err) {
    bodyEl.textContent = "Error: " + err.message;
  }
}

// ── Stale page ────────────────────────────────────────────────────────────────

function renderStalePage(data) {
  const el    = document.getElementById("stale-content");
  const stale = data.artifacts.filter(a => a.status === "stale");
  if (!el) return;
  if (stale.length === 0) { el.innerHTML = `<div class="empty" style="color:var(--green)">✓ Tidak ada artifact yang stale.</div>`; return; }
  el.innerHTML = `<div class="tbl-wrap"><table>
    <thead><tr><th>Artifact</th><th>Phase</th><th>Ver</th><th>Updated</th><th>Stale karena</th></tr></thead>
    <tbody>${stale.map(a => `
      <tr onclick='openArtifactPanel(${JSON.stringify(a).replace(/'/g,"&#39;")})'>
        <td class="name">${esc(a.label)}</td><td>${esc(a.phase_label)}</td>
        <td style="text-align:center">${a.ver || "—"}</td><td>${fmtDate(a.updated_at)}</td>
        <td>${a.stale_keys.map(k => `<span class="stale-key-tag">${esc(k)}</span>`).join(" ")}</td>
      </tr>`).join("")}
    </tbody></table></div>`;
}

// ── Nav dots ──────────────────────────────────────────────────────────────────

function updateNavDots(data) {
  if (!data || !data.artifacts) return;
  const dotMap = {
    "dot-prd":           "project.1-foundation.prd",
    "dot-arch-spec":     "project.1-foundation.arch-spec",
    "dot-uiux-spec":     "project.1-foundation.uiux-spec",
    "dot-test-strategy": "project.1-foundation.test-strategy",
    "dot-actor-index":    "project.2-business-spec.actor-index",
    "dot-usecase-index":  "project.2-business-spec.usecase-index",
    "dot-entity-catalog":   "project.3-tech-spec.entity-catalog",
    "dot-shared-decisions": "project.3-tech-spec.shared-decisions",
    "dot-api-index":        "project.3-tech-spec.api-index",
    "dot-scaffold":         "project.4-implement.scaffold",
    "dot-entity-models":    "project.4-implement.entity-models",
    "dot-shared-modules":   "project.4-implement.shared-modules",
  };
  for (const [dotId, key] of Object.entries(dotMap)) {
    const dot = document.getElementById(dotId);
    if (!dot) continue;
    const a = data.artifacts.find(a => a.key === key);
    dot.className = "nav-status-dot " + (a ? a.status : "not_started");
  }
}

// ── Dashboard ─────────────────────────────────────────────────────────────────

function _dashModuleScreenSummary(data) {
  const moduleSet = new Set();
  const screenSet = new Set();
  const done = { "2-business-spec": new Set(), "3-tech-spec": new Set(), "4-implement": new Set() };
  (data.moduleNodes || []).forEach(n => {
    const key = n.moduleId + "." + n.screenId;
    moduleSet.add(n.moduleId);
    screenSet.add(key);
    if (n.node && done[n.phase]) done[n.phase].add(key);
  });
  const screenTotal = screenSet.size;
  return {
    moduleCount: moduleSet.size,
    screenTotal,
    bizDone:  done["2-business-spec"].size,
    techDone: done["3-tech-spec"].size,
    implDone: done["4-implement"].size,
  };
}

async function _dashLoadTestSummary() {
  const el = document.getElementById("dash-test-summary");
  if (!el) return;
  try {
    const implScreensRes = await api.fetchImplScreens();
    const resultsScreens = (implScreensRes.screens || []).filter(s => s.test_results);
    if (!resultsScreens.length) {
      el.innerHTML = '<div class="empty">Belum ada hasil test (test_results kosong di semua screen implementasi).</div>';
      return;
    }
    el.innerHTML = _r2SummaryCards(resultsScreens);
  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

function renderDashboard(data) {
  const s = data.stats;
  const subEl = document.getElementById("dashboard-sub");
  if (subEl) subEl.textContent = `Development dashboard for "${data.project.name}", built with the Agentic-SDLC framework.`;

  document.getElementById("metrics").innerHTML = [
    { label: "Total Artifacts", value: s.total,       cls: "" },
    { label: "Written",         value: s.written,     cls: s.written === s.total ? "green" : "" },
    { label: "Not Started",     value: s.not_started, cls: s.not_started > 0 ? "gray" : "green" },
    { label: "Stale",           value: s.stale,       cls: s.stale > 0 ? "yellow" : "" },
  ].map(m => `<div class="metric-card"><div class="metric-label">${m.label}</div><div class="metric-value ${m.cls}">${m.value}</div></div>`).join("");

  const sm = _dashModuleScreenSummary(data);
  document.getElementById("dash-module-summary").innerHTML = [
    { label: "Modules", value: sm.moduleCount, cls: "" },
    { label: "Screens",  value: sm.screenTotal, cls: "" },
    { label: "Business Spec",  value: `${sm.bizDone}/${sm.screenTotal}`,  cls: (sm.screenTotal && sm.bizDone === sm.screenTotal) ? "green" : "" },
    { label: "Tech Spec",      value: `${sm.techDone}/${sm.screenTotal}`, cls: (sm.screenTotal && sm.techDone === sm.screenTotal) ? "green" : "" },
    { label: "Implementation", value: `${sm.implDone}/${sm.screenTotal}`, cls: (sm.screenTotal && sm.implDone === sm.screenTotal) ? "green" : "" },
  ].map(m => `<div class="metric-card"><div class="metric-label">${m.label}</div><div class="metric-value ${m.cls}">${m.value}</div></div>`).join("");

  document.getElementById("phase-progress").innerHTML = data.phases.map(p => {
    const pct = p.total > 0 ? Math.round((p.written / p.total) * 100) : 0;
    return `<div class="phase-card">
      <div class="phase-label">${esc(p.label)}</div>
      <div class="phase-bar-track"><div class="phase-bar-fill" style="width:${pct}%"></div></div>
      <div class="phase-counts">${p.written} / ${p.total} written</div>
    </div>`;
  }).join("");

  _dashLoadTestSummary();

  const staleArtifacts = data.artifacts.filter(a => a.status === "stale");
  const staleEl = document.getElementById("dash-stale");
  if (staleArtifacts.length === 0) { staleEl.innerHTML = ""; return; }
  staleEl.innerHTML = `<div class="sec-title" style="color:var(--yellow)">⚠ Stale Artifacts</div>
    <div class="tbl-wrap"><table>
      <thead><tr><th>Artifact</th><th>Phase</th><th>Stale because</th></tr></thead>
      <tbody>${staleArtifacts.map(a => `
        <tr onclick="openArtifactPanel(${JSON.stringify(a).replace(/"/g,'&quot;')})">
          <td class="name">${esc(a.label)}</td><td>${esc(a.phase_label)}</td>
          <td>${a.stale_keys.map(k => `<span class="stale-key-tag">${esc(k)}</span>`).join(" ")}</td>
        </tr>`).join("")}
      </tbody></table></div>`;
}

// ── Testing page (integrated: BDD, Unit, Integration, Component, Browser, Results) ──

function _testingMethodBadge(method) {
  const cls = { GET: "method-get", POST: "method-post", PUT: "method-put", PATCH: "method-patch", DELETE: "method-delete" }[method] || "";
  return `<span class="method-badge ${cls}">${esc(method || "—")}</span>`;
}

function _testingScreenLink(r) {
  const mid   = r.moduleId || "";
  const sname = (r.screenName || r.screenId || "").replace(/'/g, "\\'");
  return `<a class="screen-detail-link" href="#screen/${esc(mid)}/${esc(r.screenId)}" onclick="event.preventDefault();openScreenPage('${esc(r.screenId)}','${esc(mid)}','${sname}')">${esc(r.screenName || r.screenId)}</a>`;
}

function _groupByUsecase(rows) {
  const groups = {};
  const order  = [];
  rows.forEach(r => {
    const key = r.usecaseId || "—";
    if (!groups[key]) { groups[key] = []; order.push(key); }
    groups[key].push(r);
  });
  return order.map(key => ({
    usecaseId:   key,
    usecaseName: groups[key][0].usecaseName || key,
    rows:        groups[key],
  }));
}

function _usecaseGroupHeader(g) {
  return `<div class="usecase-group-header"><span class="usecase-group-name">${esc(g.usecaseName)}</span><span class="usecase-pill">Use Case</span></div>`;
}

function _renderScreenSpecUnitSection(rows) {
  if (!rows.length) return '<div class="empty" style="color:var(--text3);margin-bottom:16px">Belum ada unit test case.</div>';
  return _groupByUsecase(rows).map(g => {
    const thead = `<thead><tr><th>Description</th><th>Given</th><th>Expect</th></tr></thead>`;
    const tbody = `<tbody>${g.rows.map(r => `<tr>
      <td>${esc(r.description || "—")}</td>
      <td>${esc(r.given || "—")}</td>
      <td>${esc(r.expect || "—")}</td>
    </tr>`).join("")}</tbody>`;
    return `<div style="margin-bottom:30px">${_usecaseGroupHeader(g)}<div class="tbl-wrap" style="margin-bottom:0"><table class="artifact-tbl artifact-tbl--auto">${thead}${tbody}</table></div></div>`;
  }).join("");
}

function _renderScreenSpecIntegrationSection(rows) {
  if (!rows.length) return '<div class="empty" style="color:var(--text3);margin-bottom:16px">Belum ada integration test (api_test).</div>';
  return _groupByUsecase(rows).map(g => {
    const thead = `<thead><tr><th>Scenario</th><th>#</th><th>Method</th><th>Path</th><th>Status</th><th>Error Code</th></tr></thead>`;
    const tbody = `<tbody>${g.rows.map(r => {
      const ep = r.endpoint || {};
      return `<tr>
        <td>${esc(r.scenarioRef || "—")}</td>
        <td class="tbl-muted">${esc(String(r.step ?? ""))}</td>
        <td>${_testingMethodBadge(ep.method)}</td>
        <td class="tbl-mono">${esc(ep.path || "—")}</td>
        <td>${esc(String(r.expected_status ?? "—"))}</td>
        <td>${r.expected_error_code != null ? esc(String(r.expected_error_code)) : '<span style="color:var(--text3)">—</span>'}</td>
      </tr>`;
    }).join("")}</tbody>`;
    return `<div style="margin-bottom:30px">${_usecaseGroupHeader(g)}<div class="tbl-wrap" style="margin-bottom:0"><table class="artifact-tbl artifact-tbl--auto">${thead}${tbody}</table></div></div>`;
  }).join("");
}

function _renderScreenSpecComponentSection(rows) {
  if (!rows.length) return '<div class="empty" style="color:var(--text3);margin-bottom:16px">Belum ada component test (atau screen ini tanpa frontend).</div>';
  return _groupByUsecase(rows).map(g => {
    const thead = `<thead><tr><th>Scenario</th><th>Component</th><th>Action</th><th>Assert</th></tr></thead>`;
    const tbody = `<tbody>${g.rows.map(r => `<tr>
      <td>${esc(r.scenarioRef || "—")}</td>
      <td class="tbl-mono">${esc(r.component || "—")}</td>
      <td>${esc(r.action || "—")}</td>
      <td>${esc(r.assert || "—")}</td>
    </tr>`).join("")}</tbody>`;
    return `<div style="margin-bottom:30px">${_usecaseGroupHeader(g)}<div class="tbl-wrap" style="margin-bottom:0"><table class="artifact-tbl artifact-tbl--auto">${thead}${tbody}</table></div></div>`;
  }).join("");
}

function _renderScreenSpecBrowserSection(rows) {
  if (!rows.length) return '<div class="empty" style="color:var(--text3);margin-bottom:16px">Belum ada browser test (atau screen ini tanpa frontend).</div>';
  return _groupByUsecase(rows).map(g => {
    const thead = `<thead><tr><th>Scenario</th><th>Route</th><th>Action</th><th>Assert</th></tr></thead>`;
    const tbody = `<tbody>${g.rows.map(r => `<tr>
      <td>${esc(r.scenarioRef || "—")}</td>
      <td class="tbl-mono">${esc(r.route || "—")}</td>
      <td>${esc(r.action || "—")}</td>
      <td>${esc(r.assert || "—")}</td>
    </tr>`).join("")}</tbody>`;
    return `<div style="margin-bottom:30px">${_usecaseGroupHeader(g)}<div class="tbl-wrap" style="margin-bottom:0"><table class="artifact-tbl artifact-tbl--auto">${thead}${tbody}</table></div></div>`;
  }).join("");
}

// ── Usecase Test Spec page (BDD, split view per use case) ───────────────────

let _usecaseTestSpecData = [];

function _renderUsecaseTestSpecDetail(uc) {
  const scenarios = Array.isArray(uc.content.bdd_scenarios) ? uc.content.bdd_scenarios : [];
  return `
    <div class="entity-block-header" style="margin-bottom:24px">
      <span class="entity-block-name">${esc(uc.content.name || uc.id)}</span>
      <span class="actor-id-badge">${esc(uc.id)}</span>
    </div>
    ${renderBddScenarios(scenarios)}`;
}

function selectUsecaseTestSpec(idx) {
  document.querySelectorAll("#usecase-test-spec-content .entity-list-item").forEach((el, i) => {
    el.classList.toggle("active", i === idx);
  });
  const panel = document.getElementById("usecase-test-spec-detail");
  if (!panel || !_usecaseTestSpecData[idx]) return;
  panel.innerHTML = _renderUsecaseTestSpecDetail(_usecaseTestSpecData[idx]);
}

async function renderUsecaseTestSpecPage(data) {
  const el = document.getElementById("usecase-test-spec-content");
  if (!el) return;
  el.innerHTML = '<div class="empty">Memuat…</div>';

  try {
    const ucFiles = await api.fetchUsecaseFiles();
    const usecaseIds = ucFiles.ids || [];
    const usecaseDatas = await Promise.all(usecaseIds.map(id =>
      api.fetchArtifact("project.2-business-spec.usecases." + id).then(r => ({ id, content: r.content }))
    ));
    const valid = usecaseDatas.filter(uc => uc.content);

    if (!valid.length) {
      el.innerHTML = `<div class="empty">Belum ada data use case.</div>`;
      return;
    }

    _usecaseTestSpecData = valid;

    const listHtml = valid.map((uc, i) => {
      const count = Array.isArray(uc.content.bdd_scenarios) ? uc.content.bdd_scenarios.length : 0;
      return `<div class="entity-list-item${i === 0 ? " active" : ""}" onclick="selectUsecaseTestSpec(${i})">
        <span class="entity-list-name">${esc(uc.content.name || uc.id)}</span>
        <span class="actor-id-badge">${count}</span>
      </div>`;
    }).join("");

    el.innerHTML = `<div class="entity-layout">
      <div class="entity-list">${listHtml}</div>
      <div class="entity-detail-panel" id="usecase-test-spec-detail">
        ${_renderUsecaseTestSpecDetail(valid[0])}
      </div>
    </div>`;

  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

// ── Screen Test Spec page (module tabs → screen split view → 4 sections) ────

let _screenTestSpecState = { moduleList: [], moduleScreens: {}, screenCache: {} };

async function renderScreenTestSpecPage(data) {
  const tabsEl = document.getElementById("screen-test-spec-module-tabs");
  const bodyEl = document.getElementById("screen-test-spec-body");
  if (!tabsEl || !bodyEl) return;

  tabsEl.innerHTML = "";
  bodyEl.innerHTML = '<div class="empty">Memuat…</div>';

  try {
    const [moduleIndexRes, screenIndexRes] = await Promise.all([
      api.fetchArtifact("project.2-business-spec.module-index"),
      api.fetchArtifact("project.2-business-spec.screen-index"),
    ]);
    const modules = (moduleIndexRes.content && Array.isArray(moduleIndexRes.content.modules)) ? moduleIndexRes.content.modules : [];
    const screens = (screenIndexRes.content && Array.isArray(screenIndexRes.content.screens)) ? screenIndexRes.content.screens : [];

    const moduleScreens = {};
    screens.forEach(s => {
      const mid = s.module_id || "—";
      if (!moduleScreens[mid]) moduleScreens[mid] = [];
      moduleScreens[mid].push(s);
    });

    // Modules from module-index that actually have screens, plus any module_id
    // referenced by a screen but missing from module-index (safety fallback).
    const moduleList = modules.filter(m => (moduleScreens[m.id] || []).length > 0);
    Object.keys(moduleScreens).forEach(mid => {
      if (!moduleList.some(m => m.id === mid)) moduleList.push({ id: mid, name: mid });
    });

    if (!moduleList.length) {
      bodyEl.innerHTML = `<div class="empty">Belum ada data screen.</div>`;
      return;
    }

    _screenTestSpecState = { moduleList, moduleScreens, screenCache: {} };

    tabsEl.innerHTML = moduleList.map((m, i) => {
      const mid = m.id.replace(/'/g, "\\'");
      return `<div class="testing-tab${i === 0 ? " active" : ""}" id="screen-test-spec-module-tab-${esc(m.id)}" onclick="selectScreenTestSpecModule('${mid}')">${esc(m.name || m.id)}</div>`;
    }).join("");

    selectScreenTestSpecModule(moduleList[0].id);

  } catch (err) {
    bodyEl.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

function selectScreenTestSpecModule(moduleId) {
  document.querySelectorAll("#screen-test-spec-module-tabs .testing-tab").forEach(t => {
    t.classList.toggle("active", t.id === "screen-test-spec-module-tab-" + moduleId);
  });

  const screens = _screenTestSpecState.moduleScreens[moduleId] || [];
  const bodyEl  = document.getElementById("screen-test-spec-body");
  if (!bodyEl) return;

  if (!screens.length) {
    bodyEl.innerHTML = `<div class="empty">Tidak ada screen di module ini.</div>`;
    return;
  }

  const listHtml = screens.map((s, i) => `
    <div class="entity-list-item${i === 0 ? " active" : ""}" onclick="selectScreenTestSpecScreen('${esc(s.id)}','${esc(moduleId)}',${i})">
      <span class="entity-list-name">${esc(s.name || s.id)}</span>
    </div>`).join("");

  bodyEl.innerHTML = `<div class="entity-layout">
    <div class="entity-list">${listHtml}</div>
    <div class="entity-detail-panel" id="screen-test-spec-detail"><div class="empty">Memuat…</div></div>
  </div>`;

  _loadScreenTestSpecDetail(screens[0].id, moduleId);
}

function selectScreenTestSpecScreen(screenId, moduleId, idx) {
  document.querySelectorAll("#screen-test-spec-body .entity-list-item").forEach((el, i) => {
    el.classList.toggle("active", i === idx);
  });
  _loadScreenTestSpecDetail(screenId, moduleId);
}

async function _loadScreenTestSpecDetail(screenId, moduleId) {
  const panel = document.getElementById("screen-test-spec-detail");
  if (!panel) return;
  panel.innerHTML = '<div class="empty">Memuat…</div>';

  try {
    const cacheKey = moduleId + "." + screenId;
    let content = _screenTestSpecState.screenCache[cacheKey];
    if (content === undefined) {
      const r = await api.fetchArtifact(moduleId + "." + screenId + ".3-tech-spec");
      content = r.content;
      _screenTestSpecState.screenCache[cacheKey] = content;
    }

    if (!content) {
      panel.innerHTML = `<div class="empty">Tech spec belum ditulis untuk screen ini.</div>`;
      return;
    }

    const usecaseNameMap = {};
    (Array.isArray(content.api_contracts) ? content.api_contracts : []).forEach(c => {
      if (c.usecase_id) usecaseNameMap[c.usecase_id] = c.usecase_name || c.usecase_id;
    });
    const _ucName = id => usecaseNameMap[id] || id || "—";

    const unitRows = [], integrationRows = [], componentRows = [], browserRows = [];
    (Array.isArray(content.api_contracts) ? content.api_contracts : []).forEach(c => {
      (Array.isArray(c.unit_test_cases) ? c.unit_test_cases : []).forEach(t => unitRows.push({
        usecaseId: c.usecase_id, usecaseName: _ucName(c.usecase_id),
        description: t.description, given: t.given, expect: t.expect,
      }));
    });
    (Array.isArray(content.test_scenarios) ? content.test_scenarios : []).forEach(ts => {
      (Array.isArray(ts.api_test) ? ts.api_test : []).forEach(step => integrationRows.push({
        scenarioRef: ts.scenario_ref, usecaseId: ts.usecase_id, usecaseName: _ucName(ts.usecase_id),
        step: step.step, endpoint: step.endpoint,
        expected_status: step.expected_status, expected_error_code: step.expected_error_code,
      }));
      const comp = ts.component_test || {};
      if (Object.keys(comp).length) componentRows.push({
        scenarioRef: ts.scenario_ref, usecaseId: ts.usecase_id, usecaseName: _ucName(ts.usecase_id),
        component: comp.component, action: comp.action, assert: comp.assert,
      });
      const browser = ts.browser_test || {};
      if (Object.keys(browser).length) browserRows.push({
        scenarioRef: ts.scenario_ref, usecaseId: ts.usecase_id, usecaseName: _ucName(ts.usecase_id),
        route: browser.route, action: browser.action, assert: browser.assert,
      });
    });

    panel.innerHTML = `
      <div class="entity-block-header" style="margin-bottom:24px">
        <span class="entity-block-name">${esc(content.name || screenId)}</span>
        <span class="actor-id-badge">${esc(screenId)}</span>
      </div>
      <div class="testing-tabs">
        <div class="testing-tab active" id="screen-test-spec-section-tab-unit"        onclick="selectScreenTestSpecSection('unit')">Unit Test</div>
        <div class="testing-tab"        id="screen-test-spec-section-tab-integration" onclick="selectScreenTestSpecSection('integration')">Integration Test</div>
        <div class="testing-tab"        id="screen-test-spec-section-tab-component"   onclick="selectScreenTestSpecSection('component')">Component Test</div>
        <div class="testing-tab"        id="screen-test-spec-section-tab-browser"     onclick="selectScreenTestSpecSection('browser')">Browser Test</div>
      </div>
      <div class="testing-tab-body">
        <div class="testing-tab-panel active" id="screen-test-spec-section-panel-unit">${_renderScreenSpecUnitSection(unitRows)}</div>
        <div class="testing-tab-panel" id="screen-test-spec-section-panel-integration">${_renderScreenSpecIntegrationSection(integrationRows)}</div>
        <div class="testing-tab-panel" id="screen-test-spec-section-panel-component">${_renderScreenSpecComponentSection(componentRows)}</div>
        <div class="testing-tab-panel" id="screen-test-spec-section-panel-browser">${_renderScreenSpecBrowserSection(browserRows)}</div>
      </div>`;

  } catch (err) {
    panel.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

function selectScreenTestSpecSection(id) {
  document.querySelectorAll("#screen-test-spec-detail .testing-tab").forEach(t => t.classList.remove("active"));
  document.querySelectorAll("#screen-test-spec-detail .testing-tab-panel").forEach(p => p.classList.remove("active"));
  const tab   = document.getElementById("screen-test-spec-section-tab-" + id);
  const panel = document.getElementById("screen-test-spec-section-panel-" + id);
  if (tab)   tab.classList.add("active");
  if (panel) panel.classList.add("active");
}

// ── Testing summary cards (per test-type pass rate) — used by Screen Test Result 3 ─

function _r2PassRate(passed, failed) {
  const total = passed + failed;
  if (total === 0) return null;
  return Math.round((passed / total) * 100);
}

function _r2Bar(passed, failed) {
  const total = passed + failed;
  if (total === 0) return `<div class="r2-bar"><div class="r2-bar-empty"></div></div>`;
  const passedPct = (passed / total) * 100;
  return `<div class="r2-bar">
    <div class="r2-bar-passed" style="width:${passedPct}%"></div>
    <div class="r2-bar-failed" style="width:${100 - passedPct}%"></div>
  </div>`;
}

function _r2SummaryCards(resultsScreens) {
  const types = [
    { key: "unit",        label: "Unit" },
    { key: "integration", label: "Integration" },
    { key: "component",   label: "Component" },
    { key: "browser",     label: "Browser" },
  ];
  const cards = types.map(t => {
    let passed = 0, failed = 0, ran = 0, covSum = 0, covCount = 0;
    resultsScreens.forEach(s => {
      const r = s.test_results && s.test_results[t.key];
      if (r && r.run_at) {
        ran++;
        passed += r.passed || 0;
        failed += r.failed || 0;
        if (t.key === "unit" && r.coverage != null) { covSum += r.coverage; covCount++; }
      }
    });
    const pct    = _r2PassRate(passed, failed);
    const avgCov = covCount ? Math.round(covSum / covCount) : null;
    const covCls = avgCov == null ? "var(--text3)" : (avgCov < 60 ? "var(--red)" : avgCov < 80 ? "var(--yellow)" : "var(--green)");
    return `<div class="r2-summary-card">
      <div class="r2-summary-card-label">${t.label}</div>
      <div class="r2-summary-card-value">${pct == null ? "—" : pct + "%"}</div>
      ${_r2Bar(passed, failed)}
      <div class="r2-summary-card-sub">${ran} screen · ${passed}<span style="color:var(--green)">✓</span> ${failed}<span style="color:${failed ? "var(--red)" : "var(--text3)"}">✗</span></div>
      ${avgCov != null ? `<div class="r2-summary-card-sub" style="color:${covCls}">Coverage rata-rata: ${avgCov}%</div>` : ""}
    </div>`;
  }).join("");
  return `<div class="r2-summary-row">${cards}</div>`;
}

// ── Screen Test Result 3 (experimental: flat sortable matrix table) ─────────────

let _r3State = { rows: [], moduleList: [], currentModule: "__all__", sortKey: "name", sortDir: 1, failingOnly: false };

function _r3RowHasFail(row) { return row.failed > 0; }

function _r3TypePct(r) {
  if (!r || !r.run_at) return null;
  const total = (r.passed || 0) + (r.failed || 0);
  if (total === 0) return null;
  return Math.round((r.passed / total) * 100);
}

function _r3BuildRows(resultsScreens, moduleNames) {
  return resultsScreens.map(s => {
    const tr = s.test_results || {};
    const cells = {
      unit:        { r: tr.unit,        pct: _r3TypePct(tr.unit) },
      integration: { r: tr.integration, pct: _r3TypePct(tr.integration) },
      component:   { r: tr.component,   pct: _r3TypePct(tr.component) },
      browser:     { r: tr.browser,     pct: _r3TypePct(tr.browser) },
    };
    let passed = 0, failed = 0;
    ["unit", "integration", "component", "browser"].forEach(k => {
      const r = tr[k];
      if (r && r.run_at) { passed += r.passed || 0; failed += r.failed || 0; }
    });
    const overallPct = (passed + failed) === 0 ? null : Math.round((passed / (passed + failed)) * 100);
    return {
      id: s.id, name: s.name || s.id,
      moduleId: s.module_id || "—",
      moduleName: moduleNames[s.module_id] || s.module_id || "—",
      cells, overallPct, passed, failed,
    };
  });
}

function _r3SortValue(row, key) {
  if (key === "name")   return (row.name || "").toLowerCase();
  if (key === "module") return (row.moduleName || "").toLowerCase();
  if (key === "overall") return row.overallPct == null ? -1 : row.overallPct;
  return row.cells[key] && row.cells[key].pct != null ? row.cells[key].pct : -1;
}

function _r3Sort(rows, key, dir) {
  return rows.slice().sort((a, b) => {
    const av = _r3SortValue(a, key), bv = _r3SortValue(b, key);
    if (typeof av === "string") return av.localeCompare(bv) * dir;
    return (av - bv) * dir;
  });
}

function _r3SetSort(key) {
  if (_r3State.sortKey === key) {
    _r3State.sortDir *= -1;
  } else {
    _r3State.sortKey = key;
    _r3State.sortDir = 1;
  }
  _r3RenderTable();
}

function _r3BaseRows() {
  return _r3State.failingOnly ? _r3State.rows.filter(_r3RowHasFail) : _r3State.rows;
}

function _r3BuildModuleList(rows) {
  const moduleMap   = {};
  const moduleOrder = [];
  rows.forEach(r => {
    if (!moduleMap[r.moduleId]) { moduleMap[r.moduleId] = { id: r.moduleId, name: r.moduleName, count: 0 }; moduleOrder.push(r.moduleId); }
    moduleMap[r.moduleId].count++;
  });
  return moduleOrder.map(mid => moduleMap[mid]);
}

function _r3FilteredRows() {
  const base = _r3BaseRows();
  if (_r3State.currentModule === "__all__") return base;
  return base.filter(r => r.moduleId === _r3State.currentModule);
}

function _r3SetFailingOnly(checked) {
  _r3State.failingOnly = checked;
  _r3RenderLayout();
}

function _r3FilterRow() {
  return `<label class="r2-filter-row">
    <input type="checkbox" ${_r3State.failingOnly ? "checked" : ""} onchange="_r3SetFailingOnly(this.checked)">
    Hanya tampilkan yang gagal
  </label>`;
}

function _r3SelectModule(moduleId) {
  document.querySelectorAll("#screen-test-result-3-modules .entity-list-item").forEach(el => {
    el.classList.toggle("active", el.dataset.module === moduleId);
  });
  _r3State.currentModule = moduleId;
  _r3RenderTable();
}

function _r3RenderLayout() {
  const el = document.getElementById("screen-test-result-3-splitview");
  if (!el) return;

  const baseRows  = _r3BaseRows();
  const moduleList = _r3BuildModuleList(baseRows);
  _r3State.moduleList = moduleList;

  if (_r3State.currentModule !== "__all__" && !moduleList.some(m => m.id === _r3State.currentModule)) {
    _r3State.currentModule = "__all__";
  }

  if (!baseRows.length) {
    el.innerHTML = `<div class="empty">Tidak ada module dengan screen gagal.</div>`;
    return;
  }

  const listHtml = [
    `<div class="entity-list-item${_r3State.currentModule === "__all__" ? " active" : ""}" data-module="__all__" onclick="_r3SelectModule('__all__')">
      <span class="entity-list-name">All Modules</span>
      <span class="actor-id-badge">${baseRows.length}</span>
    </div>`,
    ...moduleList.map(m => `
      <div class="entity-list-item${_r3State.currentModule === m.id ? " active" : ""}" data-module="${esc(m.id)}" onclick="_r3SelectModule('${m.id.replace(/'/g, "\\'")}')">
        <span class="entity-list-name">${esc(m.name)}</span>
        <span class="actor-id-badge">${m.count}</span>
      </div>`),
  ].join("");

  el.innerHTML = `<div class="entity-layout">
    <div class="entity-list" id="screen-test-result-3-modules">${listHtml}</div>
    <div class="entity-detail-panel" id="screen-test-result-3-detail"></div>
  </div>`;

  _r3RenderTable();
}

function _r3ModuleTitle(rowCount) {
  if (_r3State.currentModule === "__all__") {
    return `All Modules <span style="font-size:13px;font-weight:400;color:var(--text3)">(${rowCount})</span>`;
  }
  const m     = _r3State.moduleList.find(x => x.id === _r3State.currentModule);
  const label = m ? m.name : _r3State.currentModule;
  return `${esc(label)} <span style="font-size:13px;font-weight:400;color:var(--text3)">(${rowCount})</span>`;
}

function _r3HeaderCell(label, key) {
  const active = _r3State.sortKey === key;
  const arrow  = active ? (_r3State.sortDir === 1 ? " ▲" : " ▼") : "";
  return `<th style="cursor:pointer;user-select:none;white-space:nowrap" onclick="_r3SetSort('${key}')">${esc(label)}${arrow}</th>`;
}

function _r3CellHtml(cellObj, hasCoverage) {
  const { r } = cellObj;
  if (!r || !r.run_at) return `<span style="color:var(--text3)">—</span>`;
  const cls    = r.failed > 0 ? "color:var(--red);font-weight:600" : "color:var(--green);font-weight:600";
  const covTxt = hasCoverage && r.coverage != null
    ? `<div style="font-size:11px;color:var(--text3);margin-top:2px">${r.coverage}% cov</div>` : "";
  return `<div style="${cls}">${r.passed}/${r.passed + r.failed}</div>${covTxt}`;
}

function _r3OverallHtml(row) {
  if (row.overallPct == null) return `<span style="color:var(--text3)">—</span>`;
  const cls = row.failed > 0 ? "var(--red)" : "var(--green)";
  return `<span style="color:${cls};font-weight:700">${row.overallPct}%</span>`;
}

function _r3RenderTable() {
  const el = document.getElementById("screen-test-result-3-detail");
  if (!el) return;

  const rows = _r3FilteredRows();

  if (!rows.length) {
    el.innerHTML = `
      <div class="entity-block-header" style="margin-bottom:20px"><span class="entity-block-name">${_r3ModuleTitle(0)}</span></div>
      <div class="empty">Tidak ada screen yang cocok.</div>`;
    return;
  }

  const sorted = _r3Sort(rows, _r3State.sortKey, _r3State.sortDir);

  const thead = `<thead><tr>
    ${_r3HeaderCell("Screen", "name")}
    ${_r3HeaderCell("Module", "module")}
    ${_r3HeaderCell("Unit", "unit")}
    ${_r3HeaderCell("Integration", "integration")}
    ${_r3HeaderCell("Component", "component")}
    ${_r3HeaderCell("Browser", "browser")}
    ${_r3HeaderCell("Overall", "overall")}
  </tr></thead>`;

  const tbody = `<tbody>${sorted.map(row => `<tr>
    <td>${_testingScreenLink({ screenId: row.id, screenName: row.name, moduleId: row.moduleId })}</td>
    <td class="tbl-muted">${esc(row.moduleName)}</td>
    <td>${_r3CellHtml(row.cells.unit, true)}</td>
    <td>${_r3CellHtml(row.cells.integration, false)}</td>
    <td>${_r3CellHtml(row.cells.component, false)}</td>
    <td>${_r3CellHtml(row.cells.browser, false)}</td>
    <td>${_r3OverallHtml(row)}</td>
  </tr>`).join("")}</tbody>`;

  el.innerHTML = `
    <div class="entity-block-header" style="margin-bottom:20px"><span class="entity-block-name">${_r3ModuleTitle(rows.length)}</span></div>
    <div class="tbl-wrap"><table class="artifact-tbl artifact-tbl--auto">${thead}${tbody}</table></div>`;
}

async function renderScreenTestResultPage3(data) {
  const el = document.getElementById("screen-test-result-3-content");
  if (!el) return;
  el.innerHTML = '<div class="empty">Memuat…</div>';

  try {
    const [implScreensRes, moduleIndexRes] = await Promise.all([
      api.fetchImplScreens(),
      api.fetchArtifact("project.2-business-spec.module-index"),
    ]);
    const resultsScreens = (implScreensRes.screens || []).filter(s => s.test_results);
    const moduleNames = {};
    (moduleIndexRes.content && Array.isArray(moduleIndexRes.content.modules) ? moduleIndexRes.content.modules : [])
      .forEach(m => { moduleNames[m.id] = m.name || m.id; });

    if (!resultsScreens.length) {
      el.innerHTML = '<div class="empty">Belum ada hasil test (test_results kosong di semua screen implementasi).</div>';
      return;
    }

    const rows = _r3BuildRows(resultsScreens, moduleNames);

    _r3State = { rows, moduleList: [], currentModule: "__all__", sortKey: "name", sortDir: 1, failingOnly: false };

    el.innerHTML = `
      <div style="margin-bottom:24px">${_r2SummaryCards(resultsScreens)}</div>
      ${_r3FilterRow()}
      <div id="screen-test-result-3-splitview"></div>`;

    _r3RenderLayout();
  } catch (err) {
    el.innerHTML = `<div class="empty" style="color:var(--red)">Gagal memuat: ${esc(err.message)}</div>`;
  }
}

// ── Dep-graph ─────────────────────────────────────────────────────────────────

function renderDepGraph(data) {
  const artifacts = data.artifacts, phases = data.phases;
  const NODE_W=160,NODE_H=48,COL_GAP=100,ROW_GAP=16,PAD_X=40,PAD_Y=40;
  const phaseOrder = phases.map(p => p.id);
  const positions  = {};
  phaseOrder.forEach((phaseId, colIdx) => {
    artifacts.filter(a => a.phase === phaseId).forEach((a, rowIdx) => {
      positions[a.key] = { x: PAD_X+colIdx*(NODE_W+COL_GAP), y: PAD_Y+rowIdx*(NODE_H+ROW_GAP), w: NODE_W, h: NODE_H };
    });
  });
  const maxCol=phaseOrder.length, maxRow=Math.max(...phases.map(p=>artifacts.filter(a=>a.phase===p.id).length),1);
  const svgW=PAD_X*2+maxCol*NODE_W+(maxCol-1)*COL_GAP, svgH=PAD_Y*2+maxRow*NODE_H+(maxRow-1)*ROW_GAP+48;
  const colors = {
    not_started:{fill:"var(--gray-dim)",stroke:"var(--gray)",text:"var(--text3)"},
    written:    {fill:"var(--green-dim)",stroke:"var(--green)",text:"var(--green)"},
    stale:      {fill:"var(--yellow-dim)",stroke:"var(--yellow)",text:"var(--yellow)"},
  };
  const phaseLabels = phases.map((p,i) =>
    `<text x="${PAD_X+i*(NODE_W+COL_GAP)+NODE_W/2}" y="${PAD_Y-16}" text-anchor="middle"
      fill="var(--text3)" font-size="10" font-weight="700" font-family="system-ui" letter-spacing=".06em">${esc(p.label.toUpperCase())}</text>`
  ).join("");
  const arrows = artifacts.flatMap(a =>
    Object.keys(a.depends_on||{}).map(dk => {
      const from=positions[dk],to=positions[a.key]; if(!from||!to) return "";
      const x1=from.x+from.w,y1=from.y+from.h/2,x2=to.x,y2=to.y+to.h/2,cx=(x1+x2)/2;
      const isStale=a.status==="stale"&&a.stale_keys.includes(dk);
      return `<path d="M${x1},${y1} C${cx},${y1} ${cx},${y2} ${x2},${y2}" fill="none"
        stroke="${isStale?"var(--yellow)":"var(--border)"}" stroke-width="1.5"
        marker-end="url(#arrow${isStale?"-stale":""})" opacity="0.7"/>`;
    })
  ).join("");
  const nodes = artifacts.map(a => {
    const pos=positions[a.key]; if(!pos) return "";
    const c=colors[a.status]||colors.not_started;
    const lbl=a.label.length>18?a.label.substring(0,17)+"…":a.label;
    const da=JSON.stringify(a).replace(/"/g,"&quot;");
    return `<g class="dg-node" onclick='openArtifactPanel(${da.replace(/'/g,"&#39;")})'>
      <rect x="${pos.x}" y="${pos.y}" width="${pos.w}" height="${pos.h}" rx="8" fill="${c.fill}" stroke="${c.stroke}" stroke-width="1.5"/>
      <text x="${pos.x+pos.w/2}" y="${pos.y+20}" text-anchor="middle" fill="${c.text}" font-size="12" font-weight="700" font-family="system-ui">${esc(lbl)}</text>
      <text x="${pos.x+pos.w/2}" y="${pos.y+34}" text-anchor="middle" fill="var(--text3)" font-size="10" font-family="monospace">${esc(a.artifact)}${a.ver?` v${a.ver}`:""}</text>
    </g>`;
  }).join("");
  const svg=document.getElementById("depgraph-svg");
  svg.setAttribute("viewBox",`0 0 ${svgW} ${svgH}`);
  svg.setAttribute("width",svgW); svg.setAttribute("height",svgH);
  svg.innerHTML=`<defs>
    <marker id="arrow"       markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto"><path d="M0,0 L0,6 L8,3 z" fill="var(--border)"/></marker>
    <marker id="arrow-stale" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto"><path d="M0,0 L0,6 L8,3 z" fill="var(--yellow)"/></marker>
  </defs>${phaseLabels}${arrows}${nodes}`;
}

// ── Page loader ───────────────────────────────────────────────────────────────

function loadPage(pageId) {
  const data = api.getCached();
  if (!data) return;
  if (pageId === "dashboard") renderDashboard(data);
  if (pageId === "depgraph")  renderDepGraph(data);
  if (pageId === "stale")     renderStalePage(data);
  if (["prd","arch-spec","uiux-spec","test-strategy"].includes(pageId)) renderArtifactPage(pageId, data);
  if (["scaffold","entity-models","shared-modules"].includes(pageId)) renderArtifactPage(pageId, data);
  if (pageId === "api-index") renderApiIndexPage(data);
  if (pageId === "entity-catalog")    renderEntityCatalogPage(data);
  if (pageId === "shared-decisions")  renderSharedDecisionsPage(data);
  if (pageId === "actor-index")   renderActorIndexPage(data);
  if (pageId === "usecase-index") renderUsecaseIndexPage(data);
  if (pageId === "usecase-detail") {
    const h     = window.location.hash.replace("#", "");
    const parts = h.split("/");
    if (parts.length === 2 && parts[0] === "usecase") {
      _fillUsecasePage(parts[1], parts[1]);
    }
  }
  if (pageId === "screen-index")  renderScreenIndexPage(data);
  if (pageId === "screen-impl-index") renderScreenImplIndexPage(data);
  if (pageId === "usecase-test-spec") renderUsecaseTestSpecPage(data);
  if (pageId === "screen-test-spec")  renderScreenTestSpecPage(data);
  if (pageId === "screen-test-result-3") renderScreenTestResultPage3(data);
  if (pageId === "screen-detail") {
    const h     = window.location.hash.replace("#", "");
    const parts = h.split("/");
    if (parts.length === 3 && parts[0] === "screen") {
      _fillScreenPage(parts[2], parts[1], parts[2]);
    }
  }
  if (pageId === "screen-impl-detail") {
    const h     = window.location.hash.replace("#", "");
    const parts = h.split("/");
    if (parts.length === 3 && parts[0] === "screen-impl") {
      _fillScreenImplPage(parts[2], parts[1], parts[2]);
    }
  }
}

window.renderScreenImplIndexPage = renderScreenImplIndexPage;
window.selectImplModule          = selectImplModule;
window.openScreenImplPage        = openScreenImplPage;
window.selectScreenImplField     = selectScreenImplField;
window.selectScreenImplPreview   = selectScreenImplPreview;
window.viewScreenImplJson        = viewScreenImplJson;
window.renderEntityCatalogPage  = renderEntityCatalogPage;
window.selectEntity             = selectEntity;
window.switchEntityView         = switchEntityView;
window.renderSharedDecisionsPage = renderSharedDecisionsPage;
window.selectDecision            = selectDecision;
window.renderApiIndexPage        = renderApiIndexPage;
window.selectApiScreen           = selectApiScreen;
window.selectActor               = selectActor;
window.selectArtifactField       = selectArtifactField;
window.selectScreenField         = selectScreenField;
window.selectScreenPreview       = selectScreenPreview;
window.selectArtifactPreview     = selectArtifactPreview;
window.diagramZoomIn           = diagramZoomIn;
window.diagramZo