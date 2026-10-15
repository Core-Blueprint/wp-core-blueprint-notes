#!/usr/bin/env python3
"""Install pinned Notes i18n runtime *candidates*; never confer editorial approval.

Operator must supply downloaded verified archive. Only writes previously absent
languages/*.pot and *.po after checks against fresh WP-CLI source extraction.
"""
from __future__ import annotations

import hashlib
from pathlib import Path
import shutil
import subprocess
import sys
import zipfile

sys.dont_write_bytecode = True
import catalog

ROOT = Path(__file__).resolve().parents[2]
ARCHIVE_SHA256 = "b87d3fb77e324a77af431c6d6f5be3a809b243b2cc25f01f6c8692aaf5c4b5bc"
LANG = ROOT / "languages"
LOCALES = catalog.LOCALES
NAME = "core-blueprint-notes"


def refuse(message: str) -> None:
    raise SystemExit("Notes i18n import BLOCKED: " + message)


def main() -> None:
    if len(sys.argv) != 2:
        refuse("use tools/i18n/import-candidate <downloaded-verified-zip>")
    source = Path(sys.argv[1]).resolve()
    if not source.is_file() or source.is_symlink():
        refuse("regular ZIP required")
    if hashlib.sha256(source.read_bytes()).hexdigest() != ARCHIVE_SHA256:
        refuse("archive SHA-256 differs from reviewed immutable import fixture")
    result = subprocess.run(
        ["git", "-C", str(ROOT), "branch", "--show-current"], capture_output=True, text=True,
        check=True,
    )
    if result.stdout.strip() != "feature/notes-rc1-i18n-foundation-v1":
        refuse("wrong branch")
    status = subprocess.run(
        ["git", "-C", str(ROOT), "status", "--porcelain", "--untracked-files=normal"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    if status:
        refuse("tracked/untracked source changes detected; keep existing work untouched")
    if LANG.is_symlink() or (LANG.exists() and list(LANG.iterdir())):
        refuse("languages/ already populated, never overwrite editorial work")
    # Source is authoritative: regenerate WP-CLI POT after the GitHub source patch.
    subprocess.run(["bash", str(ROOT / "tools/i18n/prepare-review")], check=True)
    fresh = ROOT / "dist/i18n-review/core-blueprint-notes.pot"
    keys = catalog.key_set(fresh)
    if len(keys) != 305:
        refuse(f"unexpected current WP-extracted key count {len(keys)}; expected 305")
    files = {f"{NAME}-{locale}.po" for locale in LOCALES}
    with zipfile.ZipFile(source) as archive:
        if len(archive.namelist()) != len(set(archive.namelist())):
            refuse("duplicate ZIP entries")
        if any(info.filename.startswith("/") or ".." in Path(info.filename).parts
               or info.is_dir() or info.file_size > 250000
               for info in archive.infolist()):
            refuse("unsafe archive entry")
        if not files.issubset({Path(n).name for n in archive.namelist()}):
            refuse("missing locale candidate PO")
        expected = {"languages/" + file for file in files}
        if not expected.issubset(set(archive.namelist())):
            refuse("missing expected language paths")
        # Validate every extracted candidate before any repository write.
        import tempfile
        with tempfile.TemporaryDirectory(prefix="cb-notes-po-") as tmp:
            temp = Path(tmp)
            for file in sorted(files):
                po = temp / file
                po.write_bytes(archive.read("languages/" + file))
                locale = file[len(NAME) + 1 : -3]
                if catalog.header_value(po, "Project-Id-Version") != "Core Blueprint Notes 0.1.0-alpha26":
                    refuse(f"invalid release identity in {file}")
                if catalog.header_value(po, "Language") != locale:
                    refuse(f"wrong language in {file}")
                if catalog.key_set(po) != keys:
                    refuse(f"source key drift in {file}")
                catalog.validate_po(po, keys, catalog.command("msgfmt"))
            if LANG.exists():
                refuse("languages/ exists; refusing cleanup/overwrite")
            LANG.mkdir()
            try:
                shutil.copyfile(fresh, LANG / fresh.name)
                for file in sorted(files):
                    shutil.copyfile(temp / file, LANG / file)
            except BaseException:
                # Do not remove partial catalog files without explicit operator review.
                refuse("install interrupted; inspect newly created languages/ before retry")
    print("Notes i18n candidate import: PASS (six locale PO files and fresh WP-CLI POT)")
    print("EDITORIAL HOLD: candidates are AI-reviewed, operator acceptance remains required")
    print("No merge, customer release or editorial approval is implied")


if __name__ == "__main__":
    main()
