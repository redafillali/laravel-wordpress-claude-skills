---
name: php-code-optimization
description: Refactor and optimize PHP code (Laravel, WordPress, legacy PHP) for readability, maintainability and efficiency without changing behavior — code smells, long methods and god classes, duplicated logic, deep nesting, primitive obsession, algorithmic complexity (O(n²) loops, repeated lookups), memory use with large arrays and generators, modern PHP 8.x features (enums, readonly, match, named args, first-class callables), Laravel collections vs queries, plus safe refactoring workflow with tests, PHPStan/Larastan, Rector and Pint. Use this skill whenever the user asks to refactor, clean up, optimize, simplify, modernize or review the quality of PHP code, shares a long or messy function, mentions legacy PHP 5/7 code to upgrade, or asks "how can I improve this code" — even without saying "refactor".
---

# PHP Code Optimization & Refactoring

Optimized code is code that is **correct, easy to change, and fast enough** — in that order. Refactor in small, behavior-preserving steps protected by tests; optimize speed only where it matters (hot paths, large data), guided by measurement.

## 1. Workflow

1. **Understand the intent**: what does this code do, who calls it, what are the inputs/edge cases? Ask if unclear.
2. **Secure behavior**: if there are no tests, write characterization tests first (feature/unit tests capturing current outputs, including edge cases). Without a safety net, keep refactors mechanical and small.
3. **Static analysis baseline**: `phpstan analyse` (Larastan for Laravel) — fix real type issues uncovered.
4. **Refactor in steps**: one smell at a time, run tests after each step.
5. **Automate the boring parts**: Rector for version upgrades and mechanical rewrites, Pint/PHP-CS-Fixer for style.
6. **Measure performance changes** when speed was the goal (timing, query count, memory).

Tool configs (Rector, PHPStan, Pint) are in [references/tooling.md](references/tooling.md).

## 2. What to look for (priority order)

1. **Bugs hiding in complexity**: unchecked nulls, loose comparisons (`==` with strings/numbers, `in_array` without strict), silent `@` suppression, swallowed exceptions, mutable shared state.
2. **Algorithmic waste**: nested loops over two collections (O(n·m)) → index one side by key (`keyBy`, `array_column`, associative array) for O(n+m); repeated DB/API calls inside loops → batch; recomputing the same value → compute once.
3. **Wrong layer**: filtering/sorting/aggregating in PHP what the DB can do (`->get()->where()` → `->where()->get()`, `count($query->get())` → `->count()`); business logic in controllers/templates.
4. **Structure smells**: long methods (> ~30 lines), deep nesting (> 3 levels), long parameter lists, boolean flag parameters, god classes, duplicated blocks, feature envy, magic strings/numbers.
5. **Modernization**: PHP 8.x features that make intent explicit and remove bugs.

Catalogue of smells with before/after examples: [references/refactoring-catalog.md](references/refactoring-catalog.md).

## 3. Core techniques

- **Guard clauses / early returns** instead of nested `if/else`.
- **Extract method / class** with intention-revealing names; a method does one thing at one level of abstraction.
- **Replace conditionals with polymorphism or `match`** when switching on a type/status repeatedly; backed **enums** with methods for status logic (`$status->label()`, `$status->canTransitionTo()`).
- **Value objects / DTOs** (`readonly` classes) for groups of primitives (Money, DateRange, Address) and for passing validated data between layers.
- **Dependency injection** over `new` / static calls / globals in business logic — makes testing possible.
- **Collections pipeline** (`map/filter/reduce/groupBy/keyBy`) for in-memory transformations — but DB queries for DB work.
- **Generators / lazy collections** (`yield`, `LazyCollection`, `lazyById`) to process large datasets in constant memory.
- **Immutability by default**: `readonly` properties, return new instances instead of mutating arguments.

## 4. Performance micro vs macro

Focus on macro wins first: I/O (queries, HTTP, filesystem) dominates PHP web workloads. Micro-optimizations (`isset` vs `array_key_exists`, string functions) only matter in loops over very large data, after profiling (Xdebug profiler, Blackfire, SPX). Say so when a user asks for micro-optimizations on non-hot code.

Useful real wins:
- Lookups: `isset($map[$key])` on a keyed array instead of `in_array` in loops.
- Build strings with arrays + `implode` or direct concatenation in loops — avoid repeated `array_merge` inside loops (O(n²)); use `[...$a, ...$b]` once or append with `$arr[] =`.
- Cache invariants outside loops (`count()`, regex compilation via static, config lookups).
- Use `array_column`, `array_combine`, `array_flip` for fast reindexing.
- Stream files (`SplFileObject`, `fgetcsv`) instead of `file()` on large files.

## 5. Output format

When asked to optimize/refactor code:

1. **Diagnosis** — numbered list of issues (bug risk / performance / readability), each one line with why it matters.
2. **Refactored code** — complete, runnable, same public behavior (call out any intentional behavior change explicitly).
3. **What changed** — short mapping issue → change.
4. **Tests** — the test(s) that protect the behavior (or characterization tests to add first).
5. **Next steps** — optional larger refactors not done now, with rationale.

Keep the user's framework conventions and code style; don't introduce patterns (repositories, DDD layers) the project doesn't use unless asked — prefer the simplest design that removes the problem.
