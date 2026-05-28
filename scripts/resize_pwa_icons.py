#!/usr/bin/env python3
"""
Один исходник → icon-192.png и icon-512.png для PWA.
Требует macOS `sips`. Логотип уменьшается и центрируется с отступами,
чтобы на телефоне (круглая/скруглённая маска) не обрезались края.

Положите мастер-файл: public/icons/icon-source.png (лучше 1024×1024, PNG).
"""

import shutil
import subprocess
import sys
from pathlib import Path

# Минимум для установки PWA (Chrome / Android). Браузер сам не «угадывает» размеры из одного PNG в manifest.
SIZES = (192, 512)
# Доля холста под логотип; остальное — поля (safe zone под маску Android/iOS)
LOGO_SCALE = 0.72
ROOT = Path(__file__).resolve().parents[1]
ICONS = ROOT / "public" / "icons"
SOURCE = ICONS / "icon-source.png"
PAD_COLOR = "FFFFFF"


def run_sips(args: list[str]) -> None:
    subprocess.run(["sips", *args], check=True, stdout=subprocess.DEVNULL)


def main() -> int:
    if not SOURCE.is_file():
        print(f"Missing {SOURCE}")
        print("Скопируйте логотип: cp your-logo.png public/icons/icon-source.png")
        return 1

    if shutil.which("sips") is None:
        print("sips not found (macOS only).")
        return 1

    master = ICONS / ".pwa-master-512.png"
    # 1) Квадрат 512×512 с полями вокруг логотипа
    run_sips([
        "--padColor", PAD_COLOR,
        "--padToHeightWidth", "512", "512",
        str(SOURCE), "--out", str(master),
    ])
    inner = max(64, int(512 * LOGO_SCALE))
    run_sips(["-Z", str(inner), str(master)])
    run_sips([
        "--padColor", PAD_COLOR,
        "--padToHeightWidth", "512", "512",
        str(master), "--out", str(master),
    ])

    for size in SIZES:
        out = ICONS / f"icon-{size}.png"
        run_sips(["-z", str(size), str(size), str(master), "--out", str(out)])
        print(f"OK {out.name} ({size}×{size})")

    master.unlink(missing_ok=True)
    print("Готово. Удалите старое PWA с телефона и установите снова.")
    print("Chrome: DevTools → Application → Clear site data")
    return 0


if __name__ == "__main__":
    sys.exit(main())
