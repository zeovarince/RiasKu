function escapeHtml(text) {
  return String(text || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}

function formatInlineMarkdown(escapedLine) {
  return escapedLine
    .replace(/`([^`]+)`/g, '<code class="chat-inline-code">$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[^*])\*([^*]+)\*(?!\*)/g, '$1<em>$2</em>')
}

export function formatChatMarkdown(rawText) {
  const escaped = escapeHtml(String(rawText || '').replace(/\r\n?/g, '\n')).trim()
  if (!escaped) return ''

  const lines = escaped.split('\n')
  const html = []
  let listType = null // 'ul' | 'ol' | null

  const closeList = () => {
    if (listType) {
      html.push(`</${listType}>`)
      listType = null
    }
  }

  for (const rawLine of lines) {
    const line = rawLine.trim()
    if (!line) {
      closeList()
      continue
    }

    const headingMatch = line.match(/^(#{1,3})\s+(.+)$/)
    if (headingMatch) {
      closeList()
      const level = Math.min(headingMatch[1].length + 2, 5)
      html.push(`<h${level} class="chat-md-heading">${formatInlineMarkdown(headingMatch[2])}</h${level}>`)
      continue
    }

    const bulletMatch = line.match(/^(?:•|-|\*)\s+(.+)$/)
    if (bulletMatch) {
      if (listType !== 'ul') {
        closeList()
        html.push('<ul class="chat-md-list">')
        listType = 'ul'
      }
      html.push(`<li>${formatInlineMarkdown(bulletMatch[1])}</li>`)
      continue
    }

    const orderedMatch = line.match(/^\d+\.\s+(.+)$/)
    if (orderedMatch) {
      if (listType !== 'ol') {
        closeList()
        html.push('<ol class="chat-md-list ordered">')
        listType = 'ol'
      }
      html.push(`<li>${formatInlineMarkdown(orderedMatch[1])}</li>`)
      continue
    }

    closeList()
    html.push(`<p class="chat-md-p">${formatInlineMarkdown(line)}</p>`)
  }

  closeList()
  return html.join('')
}
