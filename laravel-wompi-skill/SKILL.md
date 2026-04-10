---
name: laravel-wompi-integration
description: >-
  Integrates Wompi El Salvador (payment links, 3DS, tokenization, webhooks) in Laravel
  using rmirandasv/laravel-wompi. Use this skill when the user wants to implement
  checkout flows, validate payment redirects, handle webhooks, or manage subscriptions.
---

# 🚀 Wompi Laravel Integration Skill

This skill provides expert guidance for integrating the **Wompi El Salvador** payment gateway into Laravel applications using the `rmirandasv/laravel-wompi` package.

## 🤖 AI Agent Instructions

When this skill is active, you MUST:
1.  **Prioritize Package Version**: Ensure the project uses `rmirandasv/laravel-wompi >= 1.0.1` to avoid hash validation issues in Payment Links.
2.  **Follow DTO Patterns**: Use the provided Request/Response DTOs for type safety.
3.  **Security First**: Always implement `validateRedirectParams` in return controllers and use the `wompi.webhook` middleware for notification endpoints.
4.  **Idempotency**: Ensure webhook handlers are idempotent (check if the order/payment is already processed).
5.  **UX Consistency**: Implement polling or interactive status checks for the user returning from a redirect, as webhooks might be delayed.

## 🛠 Integration Workflows

### 1. Payment Link (Checkout)
- **Method**: `Wompi::createPaymentLink($data)` or `Wompi::createPaymentLink(PaymentLinkRequestDTO::fromArray($data))`
- **Key Field**: `identificadorEnlaceComercio`. Use a unique, searchable ID (e.g., Order UUID).
- **Redirection**: For Inertia.js use `Inertia::location($url)`, for Blade use `redirect()->away($url)`.

### 2. Redirect Validation (Mandatory)
Wompi sends users back to your `urlRedirect` with query parameters and a `hash`.
- **Validation**: Use `Wompi::validateRedirectParams($request->query(), $request->input('hash'))`.
- **Note**: This validation uses your `WOMPI_CLIENT_SECRET`.

### 3. Webhook Handling
- **Route**: Exclude from CSRF and apply `wompi.webhook` middleware.
- **Events**: Listen for `WompiPaymentProcessed` or `WompiWebhookReceived` for a clean, decoupled architecture.

## 📋 Prompting Examples for Users

- *"Integrate Wompi checkout: create a payment link for my 'Order' model, handle the return URL with hash validation, and set up a webhook listener using events."*
- *"Add subscription support using Wompi tokenization: store the card token and implement a monthly recurring charge."*
- *"Fix my Wompi redirect validation: it always returns false when coming back from a payment link."*

## 📚 References
- [Official Documentation](https://docs.wompi.sv/)
- [Package Repository](https://github.com/rmirandasv/laravel-wompi)

## 🔧 AI Tool Compatibility
- **Cursor/Windsurf**: Place this file in `.cursor/rules/wompi.md` or `.windsurf/rules/wompi.md`.
- **Gemini CLI**: Activate via `activate_skill('laravel-wompi-integration')` or include in your `GEMINI.md`.
