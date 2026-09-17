#!/usr/bin/env python3
"""Split the four 21 BCN suit sheets into 52 card PNGs plus one shared back.

Usage:
  python tools/split_card_sheets.py \
    --spades design/picas.png --clubs design/treboles.png \
    --hearts design/corazones.png --diamonds design/diamantes.png

The script is tuned for the 1448x1086 concept sheets used by this project.
"""

from __future__ import annotations

import argparse
from pathlib import Path
from PIL import Image

RANKS = ["A", "2", "3", "4", "5", "6", "7", "8", "9", "10", "J", "Q", "K"]
X = [31, 232, 433, 633, 834, 1033, 1234]
TOP_Y = 237
BOTTOM_Y = 536
W = 185
H = 276
OUT_SIZE = (370, 552)


def crop_card(image: Image.Image, x: int, y: int) -> Image.Image:
    return image.crop((x, y, x + W, y + H)).resize(OUT_SIZE, Image.Resampling.LANCZOS)


def split_sheet(suit: str, path: Path, out_dir: Path) -> None:
    image = Image.open(path).convert("RGB")
    positions = [(x, TOP_Y) for x in X] + [(x, BOTTOM_Y) for x in X[:6]]
    for rank, (x, y) in zip(RANKS, positions):
        crop_card(image, x, y).save(out_dir / f"{suit}-{rank}.png", optimize=True)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--spades", required=True, type=Path)
    parser.add_argument("--clubs", required=True, type=Path)
    parser.add_argument("--hearts", required=True, type=Path)
    parser.add_argument("--diamonds", required=True, type=Path)
    parser.add_argument("--out", type=Path, default=Path("assets/cards"))
    args = parser.parse_args()

    args.out.mkdir(parents=True, exist_ok=True)
    for suit in ("spades", "clubs", "hearts", "diamonds"):
        split_sheet(suit, getattr(args, suit), args.out)

    image = Image.open(args.spades).convert("RGB")
    crop_card(image, X[-1], BOTTOM_Y).save(args.out / "back.png", optimize=True)
    print(f"Created 53 PNGs in {args.out}")


if __name__ == "__main__":
    main()
