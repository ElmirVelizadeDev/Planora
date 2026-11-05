# Planora Architecture

## 1. Backend Architecture

Laravel 10.50.4 and PHP 8.1.10 own HTTP API endpoints, session authentication, request validation, database access, business rules, and server-side authorization. Existing Laravel directories remain responsible for their established roles:

- app/Http/Controllers: HTTP boundary and response orchestration.
- app/Http/Requests: request validation.
- app/Http/Resources: API response serialization.
- app/Models: Eloquent entities.
- app/Policies and middleware: server-side access control.
- app/Services: reusable domain/application operations.
- database: schema, seeders, and factories.
- routes/api.php and routes/api/v1.php: API route registration.

Controllers and service classes for most product domains are still scaffolds. Do not treat declared frontend pages as evidence that a corresponding backend operation exists.

## 2. Frontend Architecture

Vue 3 is the single frontend application, built by Vite 5. Vue Router owns browser paths, layouts, page loading, route metadata, and client-side redirects. Pinia owns frontend state. Axios is accessed through one instance in resources/js/shared/services/api.js.

- resources/js/pages: User, Admin, authentication, and error page components.
- resources/js/layouts: separate User (DashboardLayout), Admin, and authentication layouts.
- resources/js/features: feature-specific services, stores, and components.
- resources/js/shared: shared components, composables, and the API client.
- resources/css: shared tokens/base styles plus layout and individual page styles.

Page components remain minimal scaffolds. No dashboard or product UI behavior was added.

## 3. Vue Router Responsibilities

Vue Router uses HTML5 history mode and lazy-loaded route components in resources/js/router/routes.js. Routes are grouped under the User, Admin, and Auth layouts. The Admin route group carries requiresAuth and requiresRole metadata. The global guard loads the current user through the API, redirects unauthenticated users to Login, and denies users without the admin role to the 403 page. Unknown frontend paths load Error404.

These guards improve navigation and user experience only. Laravel must independently enforce authentication, role checks, policies, and data isolation for every protected API operation.

## 4. Laravel API Responsibilities and Versioning

The RouteServiceProvider mounts routes/api.php under Laravel's API middleware and /api prefix. That file delegates to routes/api/v1.php under the v1 prefix, so API routes have the /api/v1/... form.

Currently registered application endpoints:

- POST /api/v1/auth/login — session login.
- POST /api/v1/auth/register — validated account registration.
- POST /api/v1/auth/logout — authenticated session logout.
- GET /api/v1/user — authenticated current-user resource.

No task, project, reporting, or Admin API endpoints exist yet. New user endpoints belong in the versioned user route group. Admin endpoints must be placed under /api/v1/admin and protected by server-side admin middleware/policies once the role/permission model is decided. No API endpoint may rely on Vue guards for security.

## 5. SPA Entry Point and Fallback

resources/views/welcome.blade.php is the single Blade shell. It contains the app mount point, CSRF meta tag, and Vite assets. routes/web.php serves this shell at / and for other frontend paths. Its fallback pattern excludes api and sanctum path prefixes, so unknown API URLs are not converted into HTML pages; Laravel returns its normal API 404 response. The Sanctum CSRF-cookie endpoint remains available from Laravel.

No Blade view is created per Vue page.

## 6. Authentication Flow

Sanctum uses Laravel's web session guard for first-party SPA requests. The API middleware group enables EnsureFrontendRequestsAreStateful. The frontend Axios instance sends credentials, uses XSRF cookie/header support, and requests JSON responses.

Before login or registration requests, the auth API requests /sanctum/csrf-cookie. Laravel validates login and registration through Form Requests, regenerates the session after successful login/registration, and invalidates the session on logout. The current-user route is protected by auth:sanctum and serializes only the public user fields through UserResource.

Login/register page components remain presentation scaffolds and are not wired to forms. Password reset and email-verification API operations are not yet implemented.

## 7. Authorization Flow

The Vue guard obtains the current session user and checks the returned role information for the Admin route group. This check is not a security boundary. Laravel's auth:sanctum middleware protects current-user and logout endpoints. Product/admin API authorization must be implemented server-side using middleware, policies, and scoped queries.

The current User model and schema do not define roles or permissions. Therefore, Admin authorization and Admin API access remain incomplete until that architecture is selected. No Admin API endpoints are currently exposed.

## 8. Axios Configuration

resources/js/shared/services/api.js is the only Axios instance. It uses /api/v1 as its base URL, with credentials and XSRF support enabled, and sends Accept: application/json. Its response interceptor emits a planora:api-error browser event with the status and response data for HTTP failures. Route authentication handles 401 by refreshing current-user state; view-level consumers can handle 403, 404, 422, and 500 errors from the same response object.

There is no global window.axios instance and no per-feature Axios configuration.

## 9. User and Admin Separation

User pages are nested under DashboardLayout. Admin pages are nested under AdminLayout and have admin-role route metadata. Both use the same global stylesheet/token entry. Authentication pages use AuthLayout. Admin/User page components and route groups remain distinct.

## 10. Removed Files and Reasons

- resources/js/bootstrap.js was removed after confirming it was imported only by resources/js/app.js and its only active behavior installed window.axios. No other code referenced window.axios. The centralized Axios instance replaces that behavior.

No page scaffolds, Laravel files, dependencies, migrations, models, controllers, or existing project documentation were deleted. No packages were removed.

## 11. Remaining Architectural Decisions

- Define roles and permissions, how the role is serialized, and the server-side middleware/policies for Admin APIs.
- Decide whether user roles live in first-party tables or a permission package; no schema or dependency was added.
- Implement password reset and email verification API endpoints, and connect the auth page forms in a later application phase.
- Add versioned task, project, category, tag, notification, report, and Admin API operations as domain work is approved.
- Confirm production SPA origin and Sanctum stateful domains/CORS configuration for deployment.
- Decide whether Statistics and Productivity should coexist or share one route.
