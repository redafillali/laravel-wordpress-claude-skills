#!/usr/bin/env python3
"""Validate every skill under skills/ and the marketplace manifest.

Checks: SKILL.md present with YAML frontmatter, allowed keys only, kebab-case
name matching its folder, description length and characters, and that every
skill is registered in .claude-plugin/marketplace.json.
"""
import json
import re
import sys
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parent.parent
SKILLS_DIR = ROOT / "skills"
ALLOWED = {"name", "description", "license", "allowed-tools", "metadata", "compatibility"}


def validate(skill_dir: Path) -> list[str]:
    errors = []
    skill_md = skill_dir / "SKILL.md"
    if not skill_md.exists():
        return ["SKILL.md not found"]
    text = skill_md.read_text(encoding="utf-8")
    m = re.match(r"^---\n(.*?)\n---", text, re.DOTALL)
    if not m:
        return ["missing or malformed YAML frontmatter"]
    try:
        fm = yaml.safe_load(m.group(1))
    except yaml.YAMLError as e:
        return [f"invalid YAML: {e}"]
    if not isinstance(fm, dict):
        return ["frontmatter must be a mapping"]

    extra = set(fm) - ALLOWED
    if extra:
        errors.append(f"unexpected keys: {', '.join(sorted(extra))}")

    name = str(fm.get("name", "")).strip()
    if not name:
        errors.append("missing name")
    elif not re.fullmatch(r"[a-z0-9]+(-[a-z0-9]+)*", name) or len(name) > 64:
        errors.append(f"name '{name}' must be kebab-case, max 64 chars")
    elif name != skill_dir.name:
        errors.append(f"name '{name}' does not match folder '{skill_dir.name}'")

    desc = str(fm.get("description", "")).strip()
    if not desc:
        errors.append("missing description")
    elif len(desc) > 1024:
        errors.append(f"description too long ({len(desc)} > 1024)")
    elif "<" in desc or ">" in desc:
        errors.append("description cannot contain angle brackets")

    lines = text.count("\n")
    if lines > 500:
        errors.append(f"SKILL.md is {lines} lines; move detail to references/ (target < 500)")

    for ref in re.findall(r"\((references/[^)\s]+|assets/[^)\s]+|scripts/[^)\s]+)\)", text):
        if not (skill_dir / ref).exists():
            errors.append(f"linked file not found: {ref}")
    return errors


def main() -> int:
    failed = False
    skills = sorted(p for p in SKILLS_DIR.iterdir() if p.is_dir())
    for d in skills:
        errs = validate(d)
        status = "OK " if not errs else "ERR"
        print(f"[{status}] {d.name}")
        for e in errs:
            print(f"       - {e}")
        failed |= bool(errs)

    manifest = ROOT / ".claude-plugin" / "marketplace.json"
    registered = set()
    for plugin in json.loads(manifest.read_text())["plugins"]:
        registered |= {Path(s).name for s in plugin.get("skills", [])}
    missing = {d.name for d in skills} - registered
    unknown = registered - {d.name for d in skills}
    if missing:
        print(f"[ERR] not registered in marketplace.json: {', '.join(sorted(missing))}")
        failed = True
    if unknown:
        print(f"[ERR] marketplace.json lists unknown skills: {', '.join(sorted(unknown))}")
        failed = True

    print("\nAll skills valid." if not failed else "\nValidation failed.")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
