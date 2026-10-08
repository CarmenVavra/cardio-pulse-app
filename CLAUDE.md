# Project Guide: Laravel Application (Eloquent & Blade)

This document contains strict rules, guidelines, and commands that Claude MUST follow during development, testing, and automated code reviews.

## 1. System Commands & Workflow

Before finalizing any task, creating a commit, or finishing a review, you must execute verification.

- **Backend & Feature Tests:** `php artisan test` (or `./vendor/bin/pest`)
- **Static Analysis:** `php artisan lounge:check` (if using Larastan) or `./vendor/bin/phpstan analyse`
- **Code Style Formatting:** `composer exec php-cs-fixer fix` or `php artisan pint`
- **Frontend Assets:** `npm run build` (Vite compilation for Blade assets)

CRITICAL: Never commit code that breaks the test suite or fails the code style check. Fix errors autonomously.

## 2. Automated Code Review Checklist

### Laravel & Architecture Guidelines

- **Skinny Controllers, Fat Models:** Controllers must only handle HTTP routing and request validation. Delegate business logic to Service Classes, Action Classes, or Eloquent Models.
- **Form Requests:** Always use dedicated Form Request classes (`php artisan make:request`) for incoming data validation. Do not validate directly inside the controller method.
- **Blade Views:** Keep Blade files purely structural. Do not execute heavy SQL queries or complex logic inside Blade. Use Blade Components (`<x-alert />`) for reusable UI elements.

### Eloquent ORM Best Practices

- **N+1 Query Prevention:** Always eager-load relationships using `with()` when loading data that will be looped over. (Tip: Ensure `Model::preventLazyLoading()` is enabled in development).
- **Mass Assignment:** Define `$fillable` or `$guarded` properties on every model. Never use unsafe `request()->all()` directly in `create()` without validation.
- **Database Migrations:** Every migration must be reversible (must contain a working `down()` method). Never modify an existing, committed migration file—always create a new one.
- **Return Parameters from Backend:** Use `compact('variable')` instead of `$variable`

### Security Guidelines

- **XSS Protection:** Use `{{ $variable }}` in Blade for automatic escaping. Only use `{!! $variable !!}` when rendering trusted, sanitized HTML, and explicitly justify its use.
- **CSRF:** Ensure every Blade form includes the `@csrf` directive.

### Testing Requirements

- Every new feature or bug fix must have a corresponding Feature Test (`php artisan make:test WorkTest`).
- Use Laravel's built-in HTTP testing assertions (e.g., `$response->assertStatus(200)`, `$response->assertSee()`).
- Use database transactions (`use RefreshDatabase;` or `use LazilyRefreshDatabase;`) to keep tests isolated.

## 3. Naming & Style Conventions

- **Laravel Standard:** Models are Singular (User), Tables are Plural (users), Controllers are PascalCase (UserController).
- **Coding Style:** Strictly follow Laravel Pint / PSR-12 coding standards.
- **Responsive Design:** Design useable for Desktop, Laptop, Tablet and Smartphone.
- **Accessibility:** Pay attention to accessibility for every HTML-element.
- **Font-Size:** Select a legible font size. Minimum 12px.

## 4. Git & Commit Guidelines

- **Format:** Conventional Commits (e.g., `feat(blade): design new checkout component`, `fix(eloquent): eager load relationships to fix N+1 issue`).
