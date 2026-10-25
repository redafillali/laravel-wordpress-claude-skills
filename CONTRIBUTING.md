# Contributing

Thanks for helping improve these skills!

## Adding or editing a skill

1. Create `skills/<kebab-case-name>/SKILL.md` with YAML frontmatter:
   ```yaml
   ---
   name: my-skill-name          # kebab-case, max 64 chars, must match the folder name
   description: What it does AND when Claude should use it (max 1024 chars, no angle brackets).
   ---
   ```
2. Keep `SKILL.md` focused (ideally under 500 lines). Move deep-dive material to `references/*.md` and point to it from `SKILL.md` with a sentence saying *when* to read it.
3. Put copy-paste templates (workflows, config files, boilerplate) in `assets/`.
4. Explain the **why** behind rules — Claude follows reasoning better than bare MUSTs.
5. Register the skill in `.claude-plugin/marketplace.json` (`plugins[0].skills`) and in the README table.
6. Run `python scripts/validate_skills.py` before opening a PR.

## Writing good descriptions

The `description` is what makes Claude pick the skill. Name the concrete situations and keywords users actually type ("functions.php", "N+1", "301 redirects", "GitHub Actions FTP"). Slightly "pushy" descriptions trigger more reliably than modest ones.

## Pull requests

- One skill or one topic per PR.
- Include a short example prompt you tested the skill with, and what changed in Claude's answer.
- Code samples must target supported versions (PHP 8.2+, Laravel 11+, WordPress 6.x) and be secure by default.
