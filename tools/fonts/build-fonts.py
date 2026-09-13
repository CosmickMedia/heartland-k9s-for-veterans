#!/usr/bin/env python3
"""Build the self-hosted web fonts for the heartland-k9s theme.

Pipeline (all offline after the first download):

  1. Download the OFL variable TTFs (+ OFL.txt notices) from the google/fonts
     repository into tools/.cache/fonts/ (cached; --force re-downloads).
  2. Instance with fontTools.varLib.instancer:
       Fraunces roman + italic : SOFT=0, WONK=0 pinned; opsz 9:144, wght 300:800 kept
       Inter                   : opsz=14 pinned; wght 400:700 kept
  3. Subset to the Google Fonts "latin" unicode range, keeping the default
     layout features plus ss01..ss20 / onum / lnum / tnum / pnum / case, and
     retaining the variable-font tables (fvar/avar/gvar/HVAR/MVAR/STAT).
  4. Save as WOFF2 into theme/heartland-k9s/assets/fonts/.
  5. Copy the OFL notices next to the fonts and into docs/licenses/.
  6. Write theme/heartland-k9s/assets/src/scss/_fonts.scss (@font-face rules
     + a size table; faces marked "deferred" are listed but not declared) and
     theme/heartland-k9s/assets/fonts/fonts.json (the same faces as data, read
     by inc/assets.php for the deferred loader and its <noscript> fallback).
  7. Verify axes/ranges and glyph coverage of every output; exit non-zero on
     any failure.

Requirements: python3 with fontTools >= 4.44 and brotli importable
(no pip install is performed).

Usage:  python3 tools/fonts/build-fonts.py [--force] [--skip-download]
"""

from __future__ import annotations

import argparse
import json
import io
import os
import shutil
import sys
import urllib.request
from pathlib import Path

try:
    import brotli  # noqa: F401  (needed by fontTools for WOFF2)
    from fontTools import subset
    from fontTools.misc.timeTools import epoch_diff
    from fontTools.ttLib import TTFont
    from fontTools.varLib import instancer
except ImportError as exc:  # pragma: no cover
    sys.exit(f"build-fonts: missing dependency: {exc} (fontTools + brotli are required)")

ROOT = Path(__file__).resolve().parents[2]
CACHE_DIR = ROOT / "tools" / ".cache" / "fonts"
THEME_DIR = ROOT / "theme" / "heartland-k9s"
FONTS_OUT = THEME_DIR / "assets" / "fonts"
SCSS_OUT = THEME_DIR / "assets" / "src" / "scss" / "_fonts.scss"
JSON_OUT = FONTS_OUT / "fonts.json"
LICENSES_OUT = ROOT / "docs" / "licenses"

GF_RAW = "https://raw.githubusercontent.com/google/fonts/main/ofl/"

# Google Fonts "latin" subset.
LATIN_UNICODES = (
    "U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, "
    "U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, "
    "U+2212, U+2215, U+FEFF, U+FFFD"
)

# Text that must be fully covered by every output font (the site tagline).
COVERAGE_TEXT = "So They Never Walk Alone"

# Each entry: one output WOFF2.
FONTS = [
    {
        "out": "fraunces-var.woff2",
        "source": "fraunces/Fraunces[SOFT,WONK,opsz,wght].ttf",
        "family": "Fraunces",
        "style": "normal",
        "weight": "300 800",
        "axes": {"SOFT": 0, "WONK": 0, "opsz": (9, 144), "wght": (300, 800)},
        # Expected fvar axes after instancing: tag -> (min, max).
        "expect_axes": {"opsz": (9, 144), "wght": (300, 800)},
        "license": "fraunces/OFL.txt",
        "license_out": "OFL-Fraunces.txt",
    },
    {
        "out": "fraunces-italic-var.woff2",
        "source": "fraunces/Fraunces-Italic[SOFT,WONK,opsz,wght].ttf",
        "family": "Fraunces",
        "style": "italic",
        "weight": "300 800",
        # Not declared in the CSS: the italic is only used below the fold (footer
        # tagline, testimonial quotes), so the theme loads it with the Font Loading
        # API once the roman faces and the page have loaded (inc/assets.php
        # hk9_deferred_fonts() + theme.js) instead of letting its 80 kB compete
        # with the LCP image and the two preloaded roman faces.
        "deferred": True,
        "axes": {"SOFT": 0, "WONK": 0, "opsz": (9, 144), "wght": (300, 800)},
        "expect_axes": {"opsz": (9, 144), "wght": (300, 800)},
        "license": "fraunces/OFL.txt",
        "license_out": "OFL-Fraunces.txt",
    },
    {
        "out": "inter-var.woff2",
        "source": "inter/Inter[opsz,wght].ttf",
        "family": "Inter",
        "style": "normal",
        "weight": "400 700",
        "axes": {"opsz": 14, "wght": (400, 700)},
        "expect_axes": {"wght": (400, 700)},
        "license": "inter/OFL.txt",
        "license_out": "OFL-Inter.txt",
    },
]


def log(msg: str) -> None:
    print(f"build-fonts: {msg}", flush=True)


def gf_url(rel_path: str) -> str:
    """Build the raw.githubusercontent.com URL for an ofl/ path (brackets encoded)."""
    return GF_RAW + rel_path.replace("[", "%5B").replace("]", "%5D")


def download(rel_path: str, force: bool = False) -> Path:
    """Download ofl/<rel_path> into the cache (skipped when already cached)."""
    dest = CACHE_DIR / rel_path
    if dest.exists() and dest.stat().st_size > 0 and not force:
        log(f"cached   {rel_path} ({dest.stat().st_size:,} bytes)")
        return dest
    dest.parent.mkdir(parents=True, exist_ok=True)
    url = gf_url(rel_path)
    log(f"download {url}")
    req = urllib.request.Request(url, headers={"User-Agent": "heartland-k9s-build-fonts/1.0"})
    with urllib.request.urlopen(req, timeout=120) as resp:
        data = resp.read()
    if not data:
        raise RuntimeError(f"empty download: {url}")
    tmp = dest.with_suffix(dest.suffix + ".part")
    tmp.write_bytes(data)
    tmp.replace(dest)
    log(f"saved    {rel_path} ({len(data):,} bytes)")
    return dest


def instance_font(font: TTFont, axes: dict) -> TTFont:
    """Pin / restrict axes with the varLib instancer (keeps remaining axes variable).

    ``axes`` maps tag -> number (pin) or (min, max) tuple (restrict; the font's
    default is kept when it lies inside the new range).
    """
    instanced = instancer.instantiateVariableFont(
        font,
        dict(axes),
        inplace=True,
        optimize=True,
        overlap=instancer.OverlapMode.KEEP_AND_SET_FLAGS,
    )
    # Round-trip through memory: the instancer drops gvar entries for glyphs
    # whose deltas vanished, but the subsetter expects every glyph to have one.
    # Re-loading rebuilds all tables in a consistent, fully compiled state.
    buffer = io.BytesIO()
    instanced.save(buffer)
    instanced.close()
    buffer.seek(0)
    return TTFont(buffer)


def kept_layout_features() -> list[str]:
    """pyftsubset's default feature list + stylistic sets + figure variants + case forms."""
    features = set(subset.Options().layout_features)
    features.update(f"ss{i:02d}" for i in range(1, 21))
    features.update(["onum", "lnum", "tnum", "pnum", "case"])
    return sorted(features)


def layout_features(font: TTFont) -> set[str]:
    """All GSUB + GPOS feature tags present in a font."""
    tags: set[str] = set()
    for table in ("GSUB", "GPOS"):
        if table in font and font[table].table.FeatureList is not None:
            tags.update(fr.FeatureTag for fr in font[table].table.FeatureList.FeatureRecord)
    return tags


def subset_font(font: TTFont) -> None:
    """Subset to the latin range in place, keeping layout features + variation tables."""
    options = subset.Options()
    options.layout_features = kept_layout_features()
    # Keep the names a UA / licence checker needs (family, style, full, PS name,
    # licence text + URL, typographic family/subfamily).
    options.name_IDs = [0, 1, 2, 3, 4, 5, 6, 13, 14, 16, 17]
    options.name_legacy = False
    options.name_languages = [0x409]
    options.notdef_outline = True
    options.recommended_glyphs = True
    options.glyph_names = False
    options.hinting = False  # variable fonts render unhinted on every modern platform; saves bytes
    options.prune_unicode_ranges = True
    options.recalc_bounds = True
    options.recalc_average_width = True

    subsetter = subset.Subsetter(options=options)
    subsetter.populate(unicodes=subset.parse_unicodes(LATIN_UNICODES))
    subsetter.subset(font)


def build_one(spec: dict, force: bool, skip_download: bool) -> dict:
    if skip_download:
        src = CACHE_DIR / spec["source"]
        if not src.exists():
            raise FileNotFoundError(f"{src} not cached; run without --skip-download")
    else:
        src = download(spec["source"], force=force)

    log(f"instance {spec['source']} -> {spec['axes']}")
    font = TTFont(str(src))
    source_features = layout_features(font)
    # Reproducible output: keep the source's head.modified instead of "now"
    # (fontTools honours SOURCE_DATE_EPOCH when compiling the head table).
    os.environ["SOURCE_DATE_EPOCH"] = str(int(font["head"].modified) + epoch_diff)
    font = instance_font(font, spec["axes"])

    log(f"subset   {spec['out']} (latin)")
    subset_font(font)

    font.flavor = "woff2"
    out_path = FONTS_OUT / spec["out"]
    FONTS_OUT.mkdir(parents=True, exist_ok=True)
    font.save(str(out_path))
    font.close()
    size = out_path.stat().st_size
    log(f"wrote    {out_path.relative_to(ROOT)} ({size:,} bytes)")
    return {"spec": spec, "path": out_path, "size": size, "source_features": source_features}


def verify_one(result: dict) -> list[str]:
    """Return a list of problems (empty == OK) for one output file."""
    spec, path = result["spec"], result["path"]
    problems: list[str] = []
    font = TTFont(str(path))

    if font.flavor != "woff2":
        problems.append(f"{path.name}: flavor is {font.flavor!r}, expected woff2")

    # Variable tables present.
    for table in ("fvar", "gvar"):
        if table not in font:
            problems.append(f"{path.name}: missing {table} table")

    # Axes / ranges.
    axes = {a.axisTag: (a.minValue, a.defaultValue, a.maxValue) for a in font["fvar"].axes} if "fvar" in font else {}
    expected = spec["expect_axes"]
    if set(axes) != set(expected):
        problems.append(f"{path.name}: axes {sorted(axes)} != expected {sorted(expected)}")
    for tag, (lo, hi) in expected.items():
        if tag in axes:
            amin, adef, amax = axes[tag]
            if (amin, amax) != (lo, hi):
                problems.append(f"{path.name}: {tag} range {amin}:{amax} != {lo}:{hi}")
            if not (amin <= adef <= amax):
                problems.append(f"{path.name}: {tag} default {adef} outside {amin}:{amax}")

    # Glyph coverage.
    cmap = font.getBestCmap() or {}
    missing = sorted({c for c in COVERAGE_TEXT if ord(c) not in cmap})
    if missing:
        problems.append(f"{path.name}: missing glyphs for {missing!r}")
    printable_ascii = [chr(c) for c in range(0x20, 0x7F)]
    missing_ascii = [c for c in printable_ascii if ord(c) not in cmap]
    if missing_ascii:
        problems.append(f"{path.name}: missing ASCII glyphs {missing_ascii!r}")
    for cp in (0x2019, 0x2013, 0x2014, 0x00A9, 0x2022):  # ’ – — © •
        if cp not in cmap:
            problems.append(f"{path.name}: missing U+{cp:04X}")

    # Layout features survived: everything on the keep list that the source had.
    for table in ("GSUB", "GPOS"):
        if table not in font:
            problems.append(f"{path.name}: {table} table missing")
    expected_features = result["source_features"] & set(kept_layout_features())
    kept = layout_features(font)
    lost = sorted(expected_features - kept)
    if lost:
        problems.append(f"{path.name}: layout features lost in subset: {lost}")
    if "kern" not in kept:
        problems.append(f"{path.name}: kern feature missing")
    result["features"] = sorted(kept)

    # Family name intact.
    family = font["name"].getDebugName(16) or font["name"].getDebugName(1)
    if not family or not family.startswith(spec["family"]):
        problems.append(f"{path.name}: family name {family!r} does not start with {spec['family']!r}")

    result["axes"] = axes
    result["glyphs"] = len(font.getGlyphOrder())
    font.close()
    return problems


def copy_licenses(force: bool, skip_download: bool) -> list[Path]:
    written: list[Path] = []
    seen: set[str] = set()
    for spec in FONTS:
        if spec["license_out"] in seen:
            continue
        seen.add(spec["license_out"])
        src = CACHE_DIR / spec["license"] if skip_download else download(spec["license"], force=force)
        for dest_dir in (FONTS_OUT, LICENSES_OUT):
            dest_dir.mkdir(parents=True, exist_ok=True)
            dest = dest_dir / spec["license_out"]
            shutil.copyfile(src, dest)
            written.append(dest)
            log(f"license  {dest.relative_to(ROOT)}")
    return written


def axes_label(axes: dict) -> str:
    parts = []
    for tag, (lo, _default, hi) in axes.items():
        fmt = lambda v: f"{v:g}"  # noqa: E731
        parts.append(f"{tag} {fmt(lo)}–{fmt(hi)}")
    return ", ".join(parts)


def write_scss(results: list[dict]) -> None:
    rows = [(r["spec"]["out"], r["size"], axes_label(r.get("axes", {})), r.get("glyphs", 0)) for r in results]
    w_file = max(len(r[0]) for r in rows)
    w_axes = max(len(r[2]) for r in rows)
    lines = [
        "// ---------------------------------------------------------------------------",
        "// Self-hosted web fonts — GENERATED by tools/fonts/build-fonts.py. Do not edit.",
        "//",
        "// Sources: google/fonts (ofl/fraunces, ofl/inter), SIL Open Font License 1.1.",
        "// Notices: assets/fonts/OFL-Fraunces.txt, assets/fonts/OFL-Inter.txt.",
        "// Fraunces: SOFT=0 / WONK=0 pinned; Inter: opsz=14 pinned. Latin subset.",
        "// Compiled CSS lives in assets/dist/, so font URLs are relative to it.",
        "//",
        f"// | {'File'.ljust(w_file)} | {'Bytes'.rjust(9)} | {'Axes'.ljust(w_axes)} | Glyphs |",
        f"// |{'-' * (w_file + 2)}|{'-' * 11}|{'-' * (w_axes + 2)}|--------|",
    ]
    for name, size, axes, glyphs in rows:
        lines.append(f"// | {name.ljust(w_file)} | {f'{size:,}'.rjust(9)} | {axes.ljust(w_axes)} | {glyphs:>6} |")
    total = sum(r[1] for r in rows)
    lines.append(f"// | {'total'.ljust(w_file)} | {f'{total:,}'.rjust(9)} | {''.ljust(w_axes)} |        |")
    lines.append("// ---------------------------------------------------------------------------")
    lines.append("")

    deferred = [r["spec"]["out"] for r in results if r["spec"].get("deferred")]
    if deferred:
        lines += [
            f"// Deferred (declared by the theme at runtime, see fonts.json): {', '.join(deferred)}",
            "",
        ]

    for r in results:
        spec = r["spec"]
        if spec.get("deferred"):
            continue
        lines += [
            "@font-face {",
            f"\tfont-family: '{spec['family']}';",
            f"\tfont-style: {spec['style']};",
            f"\tfont-weight: {spec['weight']};",
            "\tfont-display: swap;",
            f"\tsrc: url('../fonts/{spec['out']}') format('woff2');",
            f"\tunicode-range: {LATIN_UNICODES};",
            "}",
            "",
        ]
    SCSS_OUT.parent.mkdir(parents=True, exist_ok=True)
    SCSS_OUT.write_text("\n".join(lines), encoding="utf-8")
    log(f"scss     {SCSS_OUT.relative_to(ROOT)}")

    manifest = {
        "generated_by": "tools/fonts/build-fonts.py",
        "faces": [
            {
                "family": r["spec"]["family"],
                "style": r["spec"]["style"],
                "weight": r["spec"]["weight"],
                "file": r["spec"]["out"],
                "bytes": r["size"],
                "unicode_range": LATIN_UNICODES,
                "display": "swap",
                "deferred": bool(r["spec"].get("deferred")),
            }
            for r in results
        ],
    }
    JSON_OUT.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    log(f"json     {JSON_OUT.relative_to(ROOT)}")


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    parser.add_argument("--force", action="store_true", help="re-download sources even if cached")
    parser.add_argument("--skip-download", action="store_true", help="never touch the network (cache must exist)")
    args = parser.parse_args(argv)

    CACHE_DIR.mkdir(parents=True, exist_ok=True)

    results = [build_one(spec, args.force, args.skip_download) for spec in FONTS]

    problems: list[str] = []
    for r in results:
        problems += verify_one(r)

    copy_licenses(args.force, args.skip_download)
    write_scss(results)

    print()
    print(f"{'file':32} {'bytes':>9}  axes")
    for r in results:
        print(f"{r['spec']['out']:32} {r['size']:>9,}  {axes_label(r['axes'])}  ({r['glyphs']} glyphs)")
        print(f"{'':32} {'':>9}  features: {' '.join(r.get('features', []))}")
    print(f"{'total':32} {sum(r['size'] for r in results):>9,}")

    if problems:
        print()
        for p in problems:
            print(f"FAIL: {p}")
        return 1
    print("\nbuild-fonts: all outputs verified OK")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
