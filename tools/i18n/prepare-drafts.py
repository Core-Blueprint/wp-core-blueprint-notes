#!/usr/bin/env python3
"""Prepare six UNREVIEWED gettext PO drafts from the extracted source POT.

Never writes to tracked languages/ and never translates messages. A prior draft
directory is not replaced; existing editorial work remains under operator control.
"""
from __future__ import annotations

import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

import catalog

ROOT = Path(__file__).resolve().parents[2]
REVIEW = ROOT / "dist" / "i18n-review"
POT = REVIEW / "core-blueprint-notes.pot"
DRAFTS = REVIEW / "drafts"
LOCALES = catalog.LOCALES


def fail(message: str) -> None:
    raise SystemExit("Notes i18n drafts BLOCKED: " + message)


def require_regular(path: Path, kind: str) -> None:
    if path.is_symlink() or not path.is_file():
        fail(f"expected regular {kind}: {path}")


def ensure_safe_paths() -> None:
    for folder in (ROOT / "dist", REVIEW):
        if folder.is_symlink() or not folder.is_dir():
            fail(f"expected non-symlink review directory: {folder}")
    require_regular(POT, "WordPress-extracted POT")
    if DRAFTS.is_symlink():
        fail("refusing symlink at the draft catalog directory")


def verify_draft(po: Path, keys: set[str], *, must_be_blank: bool) -> None:
    require_regular(po, "PO draft")
    actual = catalog.key_set(po)
    if actual != keys:
        fail(f"source entry mismatch in {po.name} (missing={len(keys - actual)}, extra={len(actual - keys)})")
    if must_be_blank:
        for block in catalog.blocks(po):
            if catalog.entry_key(block) is None:
                continue
            values = catalog.translation_values(block)
            if not values or any(value.strip() for value in values):
                fail(f"unexpected populated translation in new draft {po.name}")


def main() -> None:
    ensure_safe_paths()
    cfg = catalog.load_config(ROOT / "tools" / "i18n" / "config.json")
    if cfg["domain"] != "core-blueprint-notes":
        fail("unexpected catalog domain")
    version = catalog.source_version(cfg)
    header = catalog.header_value(POT, "Project-Id-Version")
    if header != f"{cfg['product']} {version}":
        fail(f"review POT version mismatch: expected {cfg['product']} {version}")
    source = POT.read_bytes()
    digest = hashlib.sha256(source).hexdigest()
    keys = catalog.key_set(POT)
    if not keys:
        fail("source POT has no messages")
    manifest = {"state": "UNREVIEWED", "authority": "review-only",
                "pot_sha256": digest, "message_count": len(keys),
                "locales": list(LOCALES)}
    if DRAFTS.exists():
        if not DRAFTS.is_dir():
            fail("unexpected object at catalog drafts path")
        metadata = DRAFTS / "review-manifest.json"
        require_regular(metadata, "draft manifest")
        try:
            saved = json.loads(metadata.read_text(encoding="utf-8"))
        except (OSError, ValueError) as error:
            fail(f"cannot read existing draft manifest: {error}")
        if saved != manifest:
            fail("POT changed or draft metadata changed; existing translations preserved, manual reconciliation required")
        for locale in LOCALES:
            verify_draft(DRAFTS / f"core-blueprint-notes-{locale}.po", keys, must_be_blank=False)
        print(f"Notes locale review drafts: PRESERVED ({len(keys)} source messages x {len(LOCALES)} locales)")
        print("CONTENT HOLD: review not completed; no source or existing drafts overwritten")
        return
    msginit = catalog.command("msginit")
    stage = Path(tempfile.mkdtemp(prefix=".notes-drafts-", dir=REVIEW))
    try:
        for locale in LOCALES:
            output = stage / f"core-blueprint-notes-{locale}.po"
            command = [msginit, "--no-translator", f"--input={POT}",
                       f"--locale={locale}", f"--output-file={output}"]
            result = subprocess.run(command, cwd=ROOT, text=True, capture_output=True, check=False)
            if result.returncode:
                fail(f"gettext msginit failed for {locale}: {(result.stderr or result.stdout).strip()}")
            verify_draft(output, keys, must_be_blank=True)
        (stage / "review-manifest.json").write_text(
            json.dumps(manifest, indent=2, sort_keys=True) + "\n", encoding="utf-8"
        )
        if DRAFTS.exists() or DRAFTS.is_symlink():
            fail("draft directory appeared during preparation; refusing overwrite")
        os.rename(stage, DRAFTS)
    finally:
        if stage.exists():
            shutil.rmtree(stage)
    print(f"Notes locale review drafts: GENERATED ({len(keys)} messages x {len(LOCALES)} locales)")
    print(f"Output: {DRAFTS}")
    print("CONTENT HOLD: six PO drafts are untranslated, not release catalog sources")


if __name__ == "__main__":
    main()
