"""Generates the AI Publisher extraction fixtures in application/tests/fixtures/publisher/.

  python tools/publisher_fixtures.py

text.pdf            two pages of embedded English text
scanned.pdf         one image-only page (needs OCR)
mixed.pdf           embedded text page + image-only page
arabic.pdf          embedded Arabic text (shaped, as real Arabic PDFs are)
arabic-scanned.pdf  image-only Arabic page (needs ara OCR data)
malformed.pdf       PDF header followed by damaged content
"""
import os
import fitz

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(os.path.dirname(HERE), 'application', 'tests', 'fixtures', 'publisher')
EN = ['Always wear PPE before cleaning hotel bathrooms. Follow approved safety procedures.',
      'Report every chemical spill to the duty manager and record it in the hazard log.']
AR = 'يجب ارتداء معدات الوقاية الشخصية قبل تنظيف حمامات الفندق واتباع الإجراءات المعتمدة'


def text_page(doc, line):
    page = doc.new_page()
    page.insert_text((50, 80), line, fontsize=14)
    return page


def arabic_page(doc):
    page = doc.new_page()
    page.insert_htmlbox(fitz.Rect(50, 50, 550, 400), '<p dir="rtl" style="font-size:22px">%s</p>' % AR)
    return page


def image_of(build):
    """Renders a page built by build() into a PNG so the resulting page has no text layer."""
    tmp = fitz.open()
    build(tmp)
    png = tmp[0].get_pixmap(matrix=fitz.Matrix(2.5, 2.5), colorspace=fitz.csGRAY).tobytes('jpg', jpg_quality=70)
    tmp.close()
    return png


def image_page(doc, png):
    page = doc.new_page()
    page.insert_image(page.rect, stream=png)


def save(doc, name):
    doc.save(os.path.join(OUT, name))
    doc.close()


def main():
    os.makedirs(OUT, exist_ok=True)
    english = image_of(lambda d: text_page(d, EN[0]))
    arabic = image_of(arabic_page)
    doc = fitz.open(); [text_page(doc, line) for line in EN]; save(doc, 'text.pdf')
    doc = fitz.open(); image_page(doc, english); save(doc, 'scanned.pdf')
    doc = fitz.open(); text_page(doc, EN[1]); image_page(doc, english); save(doc, 'mixed.pdf')
    doc = fitz.open(); arabic_page(doc); save(doc, 'arabic.pdf')
    doc = fitz.open(); image_page(doc, arabic); save(doc, 'arabic-scanned.pdf')
    with open(os.path.join(OUT, 'malformed.pdf'), 'wb') as handle:
        handle.write(b'%PDF-1.7\n1 0 obj << /Type /Catalog /Pages 2 0 R >>\nendobj\n\x00\xff garbage without xref or trailer')
    print('Fixtures written to', OUT)


if __name__ == '__main__':
    main()
