// The same folding the API uses (App\Support\ArabicText), so what the palette
// highlights is what the server matched: "احمد" lights up inside "أحمد".
const FOLD = {
  'أ': 'ا', 'إ': 'ا', 'آ': 'ا', 'ٱ': 'ا', 'ٲ': 'ا', 'ٳ': 'ا',
  'ة': 'ه', 'ى': 'ي', 'ئ': 'ي', 'ؤ': 'و', 'ـ': '',
  'ؐ': '', 'ً': '', 'ٌ': '', 'ٍ': '', 'َ': '', 'ُ': '',
  'ِ': '', 'ّ': '', 'ْ': '', 'ٓ': '', 'ٔ': '', 'ٕ': '', 'ٰ': '',
  '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
}

const foldChar = (ch) => (ch in FOLD ? FOLD[ch] : ch.toLowerCase())

export function fold(text) {
  return Array.from(String(text ?? '')).map(foldChar).join('')
}

/**
 * Splits `text` into [{ text, hit }] around every occurrence of any term,
 * matching on the folded form but cutting the original, so the reader sees
 * their own spelling highlighted rather than the folded one.
 */
export function highlightParts(text, terms) {
  const source = String(text ?? '')
  const needles = terms.map(fold).filter(Boolean)
  if (!source || !needles.length) return [{ text: source, hit: false }]

  // Folded string plus, for each folded character, where it came from.
  let folded = ''
  const origin = []
  let offset = 0
  for (const ch of Array.from(source)) {
    const f = foldChar(ch)
    for (let i = 0; i < f.length; i++) origin.push(offset)
    folded += f
    offset += ch.length
  }
  origin.push(source.length)

  const marks = new Array(source.length).fill(false)
  for (const needle of needles) {
    let at = folded.indexOf(needle)
    while (at !== -1) {
      const start = origin[at]
      // Marks that were folded away after the match belong to it too.
      let end = origin[at + needle.length]
      for (let i = start; i < end; i++) marks[i] = true
      at = folded.indexOf(needle, at + needle.length)
    }
  }

  const parts = []
  for (let i = 0; i < source.length; i++) {
    const last = parts[parts.length - 1]
    if (last && last.hit === marks[i]) last.text += source[i]
    else parts.push({ text: source[i], hit: marks[i] })
  }
  return parts
}
