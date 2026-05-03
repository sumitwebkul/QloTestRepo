---
name: unit-test-generator
description: Automatically generates, runs, and fixes PHPUnit unit tests for a QloApps class. Full loop: read class → generate tests → run → fix → repeat until passing (max 3 attempts). Invoke with the class name as argument.
argument-hint: "ClassName"
---

# Generate Unit Tests for QloApps

Target class: **$ARGUMENTS**

---

## Step 1: Read the Class File

Find the class file in this order:
- `classes/$ARGUMENTS.php`
- `classes/**/$ARGUMENTS.php`
- `override/classes/$ARGUMENTS.php`
- `modules/**/classes/$ARGUMENTS.php`

**QloApps naming convention:** Core class files use the `ClassNameCore` suffix inside the file (e.g., `classes/Customer.php` defines `CustomerCore`, not `Customer`). The usable class name is always without the `Core` suffix — use `Customer`, not `CustomerCore`, when instantiating in tests.

Read the full file. Identify all public methods, the parent class, and all external dependencies (Db, Context, Configuration, Tools, Validate). Note any static properties used as caches — these need ReflectionProperty resets in setUp and tearDown.

Also check `tests/Unit/` for any existing test files to understand patterns already in use.

---

## Step 2: Generate the Test File

Before writing any code, **read all six reference files** using the Read tool:
- `.claude/skills/unit-test-generator/reference/test-structure.md`
- `.claude/skills/unit-test-generator/reference/mock-patterns.md`
- `.claude/skills/unit-test-generator/reference/objectmodel-patterns.md`
- `.claude/skills/unit-test-generator/reference/assertions-guide.md`
- `.claude/skills/unit-test-generator/reference/module-patterns.md`
- `.claude/skills/unit-test-generator/reference/advanced-patterns.md`

Then write the test file to `tests/Unit/$ARGUMENTSTest.php` following those patterns.

### Generation Rules

| Class Type | Reference | Key focus |
|-----------|-----------|-----------|
| Extends `ObjectModel` | objectmodel-patterns.md | `$definition`, constructor, static finders, state mutations, multilang/multishop |
| Extends `Module` or `PaymentModule` | module-patterns.md | install/uninstall, hook handlers, getContent, configuration |
| Static utility (`Validate`, `Tools`) | mock-patterns.md + assertions-guide.md | Call statically, `#[DataProvider]` for all inputs |
| Plain class, no DB | advanced-patterns.md | Instantiate directly, test all branches |
| Abstract class | advanced-patterns.md | Anonymous subclass to exercise concrete methods |
| Class with protected business logic | advanced-patterns.md | `ReflectionMethod` to call directly, or test via public interface |
| Class with fluent interface (`return $this`) | advanced-patterns.md | `willReturnSelf()`, chain assertions |
| Class with date/time logic | advanced-patterns.md | Fixed timestamps, `strtotime()` with known inputs |
| Class calling `Hook::exec()` with return | advanced-patterns.md | Stub Hook return value, test conditional branches |

- Use `PHPUnit\Framework\TestCase`; import attributes with `use PHPUnit\Framework\Attributes\DataProvider;` etc.
- Add OSL-3.0 license header
- Test method naming: `test{MethodName}{Scenario}`
- Coverage per method is governed by the 12-point checklist below — not a fixed count
- **PHPUnit 10+ rules — no exceptions:**
  - All `@annotation` forms are deprecated — use `#[Attribute]` forms only
  - Data provider methods **MUST be `public static`**
  - Use `#[DataProvider('methodName')]` attribute, not `@dataProvider`
  - Use `#[DoesNotPerformAssertions]` attribute, not `expectNotToPerformAssertions()`
  - Use `#[TestWith([...])]` for inline single-dataset tests without a provider method
- Do **NOT** define constants (`_DB_PREFIX_`, `_PS_ROOT_DIR_`, etc.) — already in `tests/bootstrap.php`
- Do **NOT** declare inline stub classes — add missing stubs to `tests/Unit/stubs/CoreStubs.php`
- Always configure `$this->dbMock->method('escape')->willReturnArgument(0)` in setUp — `pSQL()` calls `Db::escape()` and SQL assertions will fail if it returns null
- Always use `Tools::encrypt(...)` in password assertions — never `md5(...)` directly
- Use `logicalAnd(stringContains(...), stringContains(...))` when asserting SQL contains multiple predicates
- Use `assertEqualsWithDelta($expected, $actual, 0.001)` for float/price assertions — never `assertSame()` on floats
- Reset `ObjectModel::$updateResult = true` and `Group::setFeatureActive(true)` in tearDown if used
- Read `reference/assertions-guide.md` to choose the most precise assertion for each scenario

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

**11. Data-driven cases** — use `#[DataProvider('methodName')]` (with a `public static` provider method) for any method that behaves differently across a range of input values (validation, formatting, calculation). Use `#[TestWith([...])]` for simple inline single-row cases.

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

To debug a single failing test without re-running the full suite:
```bash
./vendor/bin/phpunit -c tests/phpunit.xml --filter testMethodNameHere
```

| Error | Fix |
|-------|-----|
| SQL assertion fails / empty value in query | Add `$this->dbMock->method('escape')->willReturnArgument(0)` to setUp — this is the most common failure |
| `Class 'X' not found` | Add stub to `tests/Unit/stubs/CoreStubs.php` |
| `Call to undefined method` | Add missing `->method()->willReturn()` in setUp |
| `Failed asserting that X is Y` | Re-read the class body — wrong expected value or wrong branch |
| `Cannot redeclare constant` | Remove — constants are already defined in bootstrap.php |
| `Declaration of X::method() must be compatible` | Remove or loosen return type annotations on the stub method in CoreStubs.php |
| Static property leaking between tests | Add `ReflectionProperty` reset for the static cache in both `setUp()` and `tearDown()` |
| `willReturnArgument is not a method` | PHPUnit version too old — check root `composer.json`, not `tests/composer.json` |
| `assertSame(42, $obj->id)` fails after `add()` | The stub `ObjectModel::add()` sets `$this->id` from `Insert_ID()` — stub `Insert_ID()->willReturn(42)` |
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
