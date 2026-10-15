#!/usr/bin/env python3
"""Extract current WordPress-aware Notes POT to an isolated review artifact only.

Do not use this output as a reviewed translation or customer release catalog.
"""
from __future__ import annotations

from pathlib import Path
import os
import tempfile

import catalog

ROOT = Path(__file__).resolve().parents[2]
CONFIG = ROOT / "tools" / "i18n" / "config.json"
REVIEW_DIR = ROOT / "dist" / "i18n-review"


def main() -> None:
    cfg = catalog.load_config(CONFIG)
    if cfg["domain"] != "core-blueprint-notes":
        catalog.fail("unexpected Notes catalog text domain")
    wp = catalog.command("wp")
    git = catalog.command("git")
    version = catalog.source_version(cfg)
    stamp = catalog.source_stamp(cfg, git)
    if REVIEW_DIR.is_symlink():
        catalog.fail("refusing symlink at generated translation review directory")
    REVIEW_DIR.mkdir(parents=True, exist_ok=True)
    output = REVIEW_DIR / (cfg["domain"] + ".pot")
    if output.is_symlink():
        catalog.fail("refusing symlink at generated translation review POT")
    with tempfile.TemporaryDirectory(prefix=".notes-pot-", dir=REVIEW_DIR) as tmp:
        temp = Path(tmp) / output.name
        catalog.generate_pot(cfg, temp, wp, version)
        catalog.rewrite_header(
            temp,
            {"Project-Id-Version": f"{cfg['product']} {version}", "POT-Creation-Date": stamp},
        )
        count = len(catalog.key_set(temp))
        if count < 1:
            catalog.fail("WordPress extraction yielded no translatable source messages")
        os.replace(temp, output)
    print(f"Notes WordPress POT extraction: REVIEW ONLY ({count} messages)")
    print(f"Output: {output}")
    print("CONTENT HOLD: six reviewed PO catalogs and PHP/JS runtime acceptance are missing")


if __name__ == "__main__":
    main()
