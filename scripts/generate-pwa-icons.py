#!/usr/bin/env python3
"""DEPRECATED: placeholder icons only. Use resize_pwa_icons.py with icon-source.png."""

import struct
import zlib
from pathlib import Path

SIZES = (72, 96, 128, 144, 152, 192, 384, 512)
BG = (255, 46, 99)  # #FF2E63
FG = (255, 255, 255)
OUT = Path(__file__).resolve().parents[1] / "public" / "icons"


def png_chunk(chunk_type: bytes, data: bytes) -> bytes:
    chunk = chunk_type + data
    return (
        struct.pack(">I", len(data))
        + chunk
        + struct.pack(">I", zlib.crc32(chunk) & 0xFFFFFFFF)
    )


def create_png(size: int, path: Path) -> None:
    pixels = []
    margin = max(2, size // 10)
    inner = size - margin * 2
    cx = cy = size // 2
    radius = inner // 2

    for y in range(size):
        row = [0]
        for x in range(size):
            dx = x - cx
            dy = y - cy
            dist = (dx * dx + dy * dy) ** 0.5
            if dist <= radius:
                row.extend(FG)
            else:
                row.extend(BG)
        pixels.append(bytes(row))

    raw = b"".join(pixels)
    compressed = zlib.compress(raw, 9)

    ihdr = struct.pack(">IIBBBBB", size, size, 8, 2, 0, 0, 0)
    png = (
        b"\x89PNG\r\n\x1a\n"
        + png_chunk(b"IHDR", ihdr)
        + png_chunk(b"IDAT", compressed)
        + png_chunk(b"IEND", b"")
    )
    path.write_bytes(png)


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    for size in SIZES:
        create_png(size, OUT / f"icon-{size}.png")
        print(f"Wrote {OUT / f'icon-{size}.png'}")


if __name__ == "__main__":
    main()
