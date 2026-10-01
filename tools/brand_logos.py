"""
Builds web-ready Altus Gulf logo files from the supplied JPEGs in logo/.

    python tools/brand_logos.py

The supplied files are JPEGs on a near-white background, which cannot sit on a
coloured header, and the stacked lockup (palm above the wordmark) is too tall
for a 56px masthead. This script:

  * keys the background out to real transparency (soft ramp, no halo),
  * trims each mark to its content,
  * builds a horizontal lockup (palm + wordmark side by side) for headers,
  * writes favicon / touch icons from the palm mark.

Output: uploads/system/altus-*.png and favicon files.
"""
import pathlib
from PIL import Image, ImageChops

ROOT = pathlib.Path(__file__).resolve().parent.parent
SRC = ROOT / 'logo'
OUT = ROOT / 'uploads' / 'system'


def keyed(path, lo=10, hi=42):
    """Background -> transparent. Alpha ramps with the distance from the corner colour."""
    im = Image.open(path).convert('RGB')
    bg = im.getpixel((4, 4))
    diff = ImageChops.difference(im, Image.new('RGB', im.size, bg)).convert('L')
    alpha = diff.point(lambda d: 0 if d <= lo else 255 if d >= hi else int((d - lo) * 255 / (hi - lo)))
    out = im.convert('RGBA')
    out.putalpha(alpha)
    return out


def trim(im, pad=0):
    box = im.getchannel('A').point(lambda a: 255 if a > 24 else 0).getbbox()
    box = (max(0, box[0] - pad), max(0, box[1] - pad), min(im.width, box[2] + pad), min(im.height, box[3] + pad))
    return im.crop(box)


def rows_with_ink(im):
    a = im.getchannel('A')
    return [any(a.getpixel((x, y)) > 40 for x in range(0, im.width, 3)) for y in range(im.height)]


def bands(flags, gap=12):
    """Vertical runs of ink, merging gaps smaller than `gap` rows."""
    runs, start, blank = [], None, 0
    for y, f in enumerate(flags):
        if f:
            if start is None:
                start = y
            blank = 0
        elif start is not None:
            blank += 1
            if blank > gap:
                runs.append((start, y - blank))
                start, blank = None, 0
    if start is not None:
        runs.append((start, len(flags) - 1))
    return runs


def horizontal(stacked):
    """Palm on the left, ALTUS / GULF on the right; the long tagline is dropped (unreadable at header size)."""
    runs = bands(rows_with_ink(stacked), gap=18)
    # runs: [diamond+palm..., ALTUS, GULF(+rules), tagline]; the palm is everything above "ALTUS".
    big = max(runs, key=lambda r: r[1] - r[0])           # the palm is the tallest band
    after = [r for r in runs if r[0] > big[1]]
    palm = trim(stacked.crop((0, runs[0][0], stacked.width, big[1] + 1)))
    words = trim(stacked.crop((0, after[0][0], stacked.width, after[1][1] + 1)))   # ALTUS + GULF
    h = words.height
    palm = palm.resize((round(palm.width * h * 1.05 / palm.height), round(h * 1.05)), Image.LANCZOS)
    gap = round(h * 0.28)
    canvas = Image.new('RGBA', (palm.width + gap + words.width, max(palm.height, h)), (0, 0, 0, 0))
    canvas.alpha_composite(palm, (0, (canvas.height - palm.height) // 2))
    canvas.alpha_composite(words, (palm.width + gap, (canvas.height - h) // 2))
    return canvas


def on_dark(im, ivory=(247, 245, 241)):
    """Light-on-dark version: the navy wordmark becomes ivory; palm green, gold and copper keep their colour.

    The supplied white-logo.jpeg cannot be used: its white wordmark is drawn on a
    near-white background, so it has no separable outline.
    """
    out = im.copy()
    px = out.load()
    for y in range(out.height):
        for x in range(out.width):
            r, g, b, a = px[x, y]
            if a and max(r, g, b) < 120 and b >= g - 4:  # navy ink is blue-dominant; palm green is green-dominant
                px[x, y] = ivory + (a,)
    return out


def fit(im, height):
    return im.resize((round(im.width * height / im.height), height), Image.LANCZOS)


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    color = trim(keyed(SRC / 'color-logo.jpeg'), 8)
    white = on_dark(color)
    mark = trim(keyed(SRC / 'favicon.jpeg'), 6)

    fit(color, 360).save(OUT / 'altus-logo-stacked.png', optimize=True)
    fit(white, 360).save(OUT / 'altus-logo-stacked-white.png', optimize=True)
    fit(horizontal(color), 120).save(OUT / 'altus-logo-horizontal.png', optimize=True)
    fit(horizontal(white), 120).save(OUT / 'altus-logo-horizontal-white.png', optimize=True)
    fit(mark, 256).save(OUT / 'altus-mark.png', optimize=True)

    square = Image.new('RGBA', (512, 512), (0, 0, 0, 0))
    m = mark.copy(); m.thumbnail((440, 440), Image.LANCZOS)
    square.alpha_composite(m, ((512 - m.width) // 2, (512 - m.height) // 2))
    square.resize((64, 64), Image.LANCZOS).save(OUT / 'altus-favicon.png', optimize=True)
    touch = Image.new('RGBA', (180, 180), (247, 245, 241, 255))
    t = mark.copy(); t.thumbnail((140, 140), Image.LANCZOS)
    touch.alpha_composite(t, ((180 - t.width) // 2, (180 - t.height) // 2))
    touch.convert('RGB').save(OUT / 'apple-touch-icon.png', optimize=True)
    square.save(ROOT / 'favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)])
    for f in sorted(OUT.glob('altus-*.png')):
        print(f.name, Image.open(f).size)


if __name__ == '__main__':
    main()
