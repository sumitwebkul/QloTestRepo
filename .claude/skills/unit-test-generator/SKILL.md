---
name: unit-test-generator
description: Automatically generates, runs, and fixes PHPUnit unit tests for a QloApps class. Full loop: read class → generate tests → run → fix → repeat until passing (max 3 attempts). Invoke with the class name as argument.
disable-model-invocation: true
argument-hint: [ClassName]
---

# Generate Unit Tests for QloApps

Target class: **$ARGUMENTS**

---

## Step 1: Read the Class File

Find the class file:
- Check `classes/$ARGUMENTS.php`
- Check subdirectories: `classes/**/$ARGUMENTS.php`
- Check module classes: `modules/**/classes/$ARGUMENTS.php`

Read the full file. Identify all public methods, the parent class, and all external dependencies (Db, Context, Configuration, Tools, Validate).

Also check `tests/Unit/` for any existing test files to understand patterns already in use.

---

## Step 2: Generate the Test File

Before writing any code, **read all three reference files** using the Read tool:
- `.claude/skills/unit-test-generator/reference/test-structure.md`
- `.claude/skills/unit-test-generator/reference/mock-patterns.md`
- `.claude/skills/unit-test-generator/reference/objectmodel-patterns.md`

Then write the test file to `tests/Unit/$ARGUMENTSTest.php` following those patterns.

### Generation Rules

| Class Type | Approach |
|-----------|---------|
| Extends ObjectModel | Use objectmodel-patterns.md |
| Static utility (Validate, Tools) | Call static methods directly, use data providers |
| Extends Module | Mock Db, test install/uninstall return values |
| Plain class, no DB | Instantiate directly, no mocking needed |

- Use `PHPUnit\Framework\TestCase` (PHPUnit 10 namespace)
- Add OSL-3.0 license header
- Mock ALL external dependencies in `setUp()` — no real DB calls
- Test method naming: `test{MethodName}{Scenario}`
- At least 2 test cases per public method

### Required Coverage Per Method

For every public method, cover ALL of the following that apply:

**1. Happy path** — call with valid inputs, assert the expected return value or state.

**2. Input validation** — invalid types, missing required fields, out-of-range values.

**3. Edge cases / boundary** — empty string, `null`, `0`, negative numbers, maximum allowed length.

**4. Conditional branches** — read the method body. For every `if/else`, `switch`, or ternary, write one test that enters each branch. Name the test after the condition, e.g. `testSaveReturnsFalseWhenDbInsertFails`.

**5. Exception handling** — if the method throws or should throw, use `expectException()` / `expectExceptionMessage()`. If it catches and rethrows, verify the exception type propagates.

**6. DB / dependency interaction** — use `expects($this->once())->method('insert')` (or `executeS`, `getRow`, etc.) to verify the method actually calls the dependency with the right arguments. Don't just mock return values — assert the call happened.

**7. Failure scenarios** — stub Db/external dependency to return `false`, `null`, `[]`, or throw an exception. Assert the method handles it gracefully (returns false, throws, logs, etc.).

**8. State change assertions** — after calling a method that mutates an object, assert the changed properties. After a save/update/delete, assert the object's `id` or status fields.

**9. Business logic** — for QloApps domain methods (room pricing, availability, tax, commission, discount, date ranges), assert the calculated value is mathematically correct. Use known inputs and hand-computed expected outputs.

**10. Permission / access control** — if the method checks employee permissions or group access (calls `Employee::hasAccess()`, `Group::getGroups()`, checks `$this->context->employee`), write one test where access is granted and one where it is denied.

**11. Data-driven cases** — use `@dataProvider` for any method that behaves differently across a range of input values (validation, formatting, calculation).

**12. Cleanup / isolation** — `tearDown()` MUST reset every static/singleton state touched in the test. Never leave Db, Context, or Configuration state set between tests.

---

## Step 3: Run Tests + Fix Loop (max 3 attempts)

Repeat up to **3 times**:

### 3a. Spawn the `test-runner` sub-agent

Use the **Agent tool** to spawn the `test-runner` sub-agent:
- `subagent_type`: `test-runner`
- `prompt` (task): `tests/Unit/$ARGUMENTSTest.php`

### 3b. Evaluate the result

**If `STATUS: PASS`** → stop, go to Step 4 ✅

**If `STATUS: FAIL`** → apply fixes based on `FAILING_TESTS` and `SUGGESTED_FIX`, then repeat.

| Error | Fix |
|-------|-----|
| `Class 'Db' not found` | Add `require_once` or create a stub class |
| `Call to undefined method` | Add missing `->method()->willReturn()` in setUp |
| `Failed asserting that X is Y` | Re-read the class — wrong expected value |
| `Cannot redeclare constant` | Wrap `define()` in `if (!defined(...))` |
| `Class not found` | Add autoload require or stub at top of test file |
| PHP fatal / syntax error | Fix syntax in the test file |

---

## Step 4: Report Final Result

**If passing:**
```
✅ Tests generated and passing: tests/Unit/$ARGUMENTSTest.php
Tests run: N | Assertions: N
```

**If max attempts reached:**
```
❌ Could not fully fix after 3 attempts.
File: tests/Unit/$ARGUMENTSTest.php
Remaining failures: [from last test-runner output]
Next step: [manual action needed]
```
