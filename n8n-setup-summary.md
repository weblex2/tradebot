# n8n MCP & Skills Setup – 2026-03-15

## Was installiert wurde

### 1. n8n-MCP Server
- **Repository:** https://github.com/czlonkowski/n8n-mcp
- **Methode:** npx (Node.js v20.20.1 vorhanden, Docker nicht)
- **Version:** n8n-mcp@2.36.1
- **Telemetrie:** deaktiviert

### 2. n8n Skills für Claude Code
- **Repository:** https://github.com/czlonkowski/n8n-skills
- **Methode:** Manuell geclont, nach `~/.claude/skills/` kopiert

**Installierte Skills (7):**
| Skill | Funktion |
|---|---|
| `n8n-mcp-tools-expert` | Tool-Auswahl & nodeType-Formatierung |
| `n8n-expression-syntax` | `{{}}` Patterns, `$json`, `$node` Variablen |
| `n8n-workflow-patterns` | 5 Architektur-Muster, 2.653+ Templates |
| `n8n-validation-expert` | Fehler-Interpretation & Auto-Sanitization |
| `n8n-node-configuration` | Property-Abhängigkeiten & Operationen |
| `n8n-code-javascript` | JS Data-Access & 10 Production-Patterns |
| `n8n-code-python` | Python Standard-Library & Limitierungen |

### 3. n8n API-Anbindung
- **Instanz:** `http://192.168.178.107:5678`
- **API-Verbindung:** erfolgreich getestet (HTTP 200)
- **WEBHOOK_SECURITY_MODE:** `moderate` (für lokale Instanz)

---

## Konfigurationsdateien

### `~/.claude.json` – mcpServers Eintrag
```json
{
  "n8n-mcp": {
    "command": "npx",
    "args": ["n8n-mcp"],
    "env": {
      "MCP_MODE": "stdio",
      "LOG_LEVEL": "error",
      "DISABLE_CONSOLE_OUTPUT": "true",
      "N8N_API_URL": "http://192.168.178.107:5678",
      "N8N_API_KEY": "<gesetzt>",
      "WEBHOOK_SECURITY_MODE": "moderate"
    }
  }
}
```

### `~/.claude/skills/` – 7 Skill-Ordner
```
~/.claude/skills/
├── n8n-code-javascript/
├── n8n-code-python/
├── n8n-expression-syntax/
├── n8n-mcp-tools-expert/
├── n8n-node-configuration/
├── n8n-validation-expert/
└── n8n-workflow-patterns/
```

---

## Nächste Schritte

- Claude Code neu starten, damit MCP aktiv wird
- API-Key in n8n rotieren (Settings → API Keys), da er im Chat sichtbar war
- n8n-Workflows direkt über Claude Code bauen und deployen
