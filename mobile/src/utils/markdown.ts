import { marked } from 'marked'
import DOMPurify from 'dompurify'

/**
 * renderMarkdownToSafeHtml — ported from the reference aivena-widget
 * project's src/widget/markdown.js. Renders an AI chat response's
 * markdown to sanitized HTML (DOMPurify) for safe v-html rendering in
 * AiAssistantPanel.vue. Falls back to the raw (escaped-by-Vue-anyway,
 * since callers only use this for v-html — not applicable here) text if
 * parsing throws, per spec: "Jika markdown gagal parse -> fallback ke
 * plain text".
 */
export function renderMarkdownToSafeHtml(source: string | null | undefined): string {
  try {
    const raw = marked.parse(source ?? '', { async: false })
    return DOMPurify.sanitize(raw)
  } catch {
    return DOMPurify.sanitize(String(source ?? ''))
  }
}
