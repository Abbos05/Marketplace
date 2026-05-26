#!/usr/bin/env python3
"""Resize icon-source.png to all PWA sizes (square). Requires macOS `sips`."""

import shutil
import subprocess
import sys
from pathlib import Path

SIZES = (72, 96, 128, 144, 152, 192, 384, 512)
ROOT = Path(__file__).resolve().parents[1]
ICONS = ROOT / "public" / "icons"
SOURCE = ICONS / "icon-source.png"


def run_sips(args: list[str]) -> None:
    subprocess.run(["sips", *args], check=True, stdout=subprocess.DEVNULL)


def main() -> int:
    if not SOURCE.is_file():
        print(f"Missing {SOURCE}")
        print("Copy your logo: cp logo.png public/icons/icon-source.png")
        return 1

    if shutil.which("sips") is None:
        print("sips not found (macOS only).")
        return 1

    tmp = ICONS / ".pwa-resize-tmp.png"
    run_sips([
        "--padColor", "FFFFFF",
        "--padToHeightWidth", "512", "512",
        str(SOURCE), "--out", str(tmp),
    ])

    for size in SIZES:
        out = ICONS / f"icon-{size}.png"
        run_sips(["-z", str(size), str(size), str(tmp), "--out", str(out)])
        print(f"OK {out.name} ({size}x{size})")

    tmp.unlink(missing_ok=True)
    print("Done. Clear site data in Chrome DevTools → Application.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
