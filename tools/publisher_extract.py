"""Read text from a PDF; no network access, OCR, or execution of embedded data."""
import json
import sys
from pypdf import PdfReader

try:
    reader = PdfReader(sys.argv[1])
    if reader.is_encrypted:
        raise ValueError("Password protected PDFs are not supported. Export an unlocked copy.")
    if len(reader.pages) > 150:
        raise ValueError("Use a PDF with at most 150 pages.")
    pages = []
    total = 0
    for index, page in enumerate(reader.pages):
        text = page.extract_text() or ""
        total += len(text)
        if total > 120000:
            raise ValueError("Split the document into smaller files (120,000 characters maximum).")
        pages.append(f"[Page {index + 1}]\n{text}")
    if sum(len(p.split("\n", 1)[-1].strip()) for p in pages) < 30:
        raise ValueError("This PDF has no readable text. Run OCR or upload a text/DOCX version.")
    print(json.dumps({"ok": True, "text": "\n\n".join(pages), "pages": len(pages)}, ensure_ascii=True))
except Exception as exc:
    print(json.dumps({"ok": False, "error": str(exc)}, ensure_ascii=True))
    sys.exit(1)
