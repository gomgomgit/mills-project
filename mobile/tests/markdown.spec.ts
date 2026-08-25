/**
 * markdown.spec.ts — mobile/src/utils/markdown.ts.
 *
 * Ported from the reference aivena-widget project's src/widget/markdown.js
 * (marked + DOMPurify). Covers: basic markdown -> HTML, and that unsafe
 * markup (script tags) is stripped rather than passed through — the whole
 * reason this wrapper exists instead of raw v-html on the AI response.
 */
import { describe, expect, it } from 'vitest'
import { renderMarkdownToSafeHtml } from '@/utils/markdown'

describe('renderMarkdownToSafeHtml', () => {
  it('renders basic markdown to HTML', () => {
    const html = renderMarkdownToSafeHtml('**tebal** dan _miring_')

    expect(html).toContain('<strong>tebal</strong>')
    expect(html).toContain('<em>miring</em>')
  })

  it('renders a markdown list', () => {
    const html = renderMarkdownToSafeHtml('- satu\n- dua')

    expect(html).toContain('<li>satu</li>')
    expect(html).toContain('<li>dua</li>')
  })

  it('strips unsafe script tags from the rendered output', () => {
    const html = renderMarkdownToSafeHtml('halo <script>alert(1)</script> dunia')

    expect(html).not.toContain('<script>')
    expect(html).not.toContain('alert(1)')
  })

  it('returns an empty string for empty/null/undefined input', () => {
    expect(renderMarkdownToSafeHtml('')).toBe('')
    expect(renderMarkdownToSafeHtml(null)).toBe('')
    expect(renderMarkdownToSafeHtml(undefined)).toBe('')
  })
})
