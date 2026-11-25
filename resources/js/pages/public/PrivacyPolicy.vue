<template>
    <div class="terms-page">
        <header class="terms-header">
            <RouterLink class="terms-brand" :to="{ name: 'home' }" aria-label="Planora home">
                <span class="terms-brand-mark" aria-hidden="true">P</span>
                <span>Planora</span>
            </RouterLink>
            <div class="terms-header-actions">
                <RouterLink class="terms-registration-link" :to="{ name: 'register' }"><span aria-hidden="true">←</span> Back to Registration</RouterLink>
                <RouterLink class="terms-home-link" :to="{ name: 'home' }">Back to Home</RouterLink>
            </div>
        </header>

        <main class="terms-main">
            <section class="terms-intro" aria-labelledby="privacy-title">
                <p class="terms-eyebrow"><span aria-hidden="true"></span> Planora policies</p>
                <h1 id="privacy-title">Privacy Policy</h1>
                <p class="terms-lead">Learn what information Planora currently handles, why it is used, and which deployment details still need confirmation.</p>
                <p class="terms-updated">Last updated <time datetime="2026-11-25">November 25, 2026</time></p>
            </section>

            <div class="terms-layout">
                <nav class="terms-toc" aria-label="On this page">
                    <p>On this page</p>
                    <a v-for="(section, index) in sections" :key="section.id" :href="`#${section.id}`">{{ String(index + 1).padStart(2, '0') }} <span>{{ section.title }}</span></a>
                </nav>

                <article class="terms-content">
                    <section v-for="(section, index) in sections" :id="section.id" :key="section.id" class="terms-section">
                        <div class="terms-section-number">{{ String(index + 1).padStart(2, '0') }}</div>
                        <div>
                            <h2>{{ section.title }}</h2>
                            <p v-for="paragraph in section.paragraphs" :key="paragraph">{{ paragraph }}</p>
                        </div>
                    </section>

                    <div class="terms-end-card">
                        <span class="terms-end-mark" aria-hidden="true">✓</span>
                        <p>This notice describes the current project implementation. Deployment-specific details should be confirmed by the service operator.</p>
                        <div class="terms-end-actions">
                            <RouterLink :to="{ name: 'register' }"><span aria-hidden="true">←</span> Back to Registration</RouterLink>
                            <RouterLink :to="{ name: 'home' }">Back to Home <span aria-hidden="true">→</span></RouterLink>
                        </div>
                    </div>
                </article>
            </div>
        </main>

        <footer class="terms-footer"><RouterLink :to="{ name: 'home' }">Planora</RouterLink><span>Privacy Policy</span></footer>
    </div>
</template>

<script setup>
const sections = [
    {
        id: 'introduction',
        title: 'Introduction',
        paragraphs: [
            'This notice describes information processed by the Planora application as implemented in this project. The service operator’s legal identity, location, applicable jurisdictions, and production infrastructure are not specified here.',
            'The operator must confirm this notice against the deployed service, its providers, and actual retention practices before production use. This page does not claim a legal certification or guarantee.',
        ],
    },
    {
        id: 'information-we-collect',
        title: 'Information We Collect',
        paragraphs: [
            'Registration requests a name, email address, and password. Before verification, a pending registration contains the submitted name and email, a password hash, expiry details, and a hashed verification code. Accounts can also contain first and last names, username, phone number, profile photo, role, status, moderator permissions, verification and sign-in timestamps, and administrator status-change details.',
            'Planora stores tasks (title, description, status, priority, due date, owner, assignee, project and category references), projects (name, description, status and dates), and categories (name, description and color). These records can contain information users choose to enter. The application also creates notifications and activity records.',
            'Registration and password recovery use temporary email codes and attempt/expiry metadata. Admin security data includes encrypted TOTP secrets, MFA enrollment/activation timestamps and verification counters, hashed recovery codes, MFA attempt/lock records, and short-lived authentication challenges. Do not submit information that should not be stored in a workspace.',
        ],
    },
    {
        id: 'how-we-use-your-information',
        title: 'How We Use Your Information',
        paragraphs: [
            'Account data supports registration, email verification, sign-in, account and profile management, password recovery, authorization, and security. Tasks, projects, categories, and notifications support the workspace features. Activity metadata supports security review and account activity monitoring.',
            'Admin MFA data is used to enroll and verify administrator authenticators, prevent reuse of a time-based code, provide recovery, and limit repeated attempts. TOTP secrets are encrypted by the application before database storage; recovery codes and OTPs are stored as hashes for verification. MFA challenge state is held temporarily in the server-side session.',
            'Registration requires a Terms checkbox and acknowledgment of the Privacy Policy. The server validates the checkbox, but the application does not save a separate acceptance timestamp or durable acceptance record.',
            'No advertising or analytics integration is configured in the reviewed application code. An operator may configure separate deployment tools; those must be disclosed based on the actual deployment.',
        ],
    },
    {
        id: 'account-information',
        title: 'Account Information',
        paragraphs: [
            'Passwords are stored using Laravel password hashing. OTPs and recovery codes are hashed for checking; administrator TOTP secrets are encrypted at rest by Laravel application encryption. Email verification, account status, role, and permissions control access to protected features.',
            'The profile page allows users to update supported name, phone, photo, and password fields. Email changes are not available in the profile flow. Profile photos are stored on the configured public file disk and may be served at a public URL; do not upload images that must remain private.',
        ],
    },
    {
        id: 'tasks-and-user-content',
        title: 'Tasks and User Content',
        paragraphs: [
            'The application persists tasks, projects, and categories. Users can create and manage workspace content through authenticated interfaces and APIs. Access is governed by authentication, role, ownership, and authorization checks; administrator and moderator access is permission-controlled.',
            'Tasks and categories support soft deletion; projects do not have a soft-delete field in the reviewed model. The account deletion flow soft-deletes the user and revokes authentication, but does not permanently erase the account or automatically remove related tasks or projects. On a separate permanent user deletion, database foreign-key rules null task/project user references; the application’s reviewed account workflow does not perform that permanent deletion. No general permanent content-erasure schedule was found.',
            'Workspace content may include personal information entered by an account holder. Use the workspace only for information appropriate to the service and its intended access controls.',
        ],
    },
    {
        id: 'data-storage-and-security',
        title: 'Data Storage and Security',
        paragraphs: [
            'Account and workspace records use the deployment-configured database. Passwords, OTPs, and recovery codes are not stored as readable credential values; MFA TOTP secrets are encrypted before storage. The application uses session-based authentication, CSRF protections, rate limits, and server-side role/permission checks.',
            'Activity logs can include actor/subject identifiers and names, event details, request IP address, User-Agent, method, path, request ID, and timestamp. Sensitive context keys such as passwords, OTPs, tokens, secrets, cookies, and session data are filtered by the logger. Authorized administrative tools can access activity records subject to permissions.',
            'The application does not define an activity-log retention period. The operator must decide and enforce a retention schedule. Database, storage, server location, transport, backups, and infrastructure controls depend on deployment; no certification or absolute security guarantee is claimed.',
        ],
    },
    {
        id: 'data-sharing-and-third-parties',
        title: 'Data Sharing and Third Parties',
        paragraphs: [
            'The project does not identify a specific production hosting, database, file-storage, or email provider. Authentication email delivery uses Laravel mail configuration; the configured provider and its location must be confirmed by the operator. Those providers may process information required to operate the service.',
            'No third-party advertising or analytics service is configured in the reviewed code. Authorized administrators and moderators may access account, workspace, or activity information according to their assigned permissions.',
        ],
    },
    {
        id: 'cookies-and-session-management',
        title: 'Cookies and Session Management',
        paragraphs: [
            'The first-party application uses session and CSRF cookies to support sign-in and protect requests. The current session configuration marks the session cookie as HTTP-only and uses SameSite=Lax. The default session lifetime is 120 minutes of inactivity; a “remember me” sign-in can use a persistent authentication cookie with a configured default of 30 days. Deployment settings may override these defaults.',
            'These cookies support core account functionality. Blocking them may prevent sign-in or protected actions from working. No optional advertising or analytics cookies are configured in the reviewed application code.',
        ],
    },
    {
        id: 'data-retention',
        title: 'Data Retention',
        paragraphs: [
            'Pending registrations expire after one day. Registration and password-recovery OTP records have configurable expirations; scheduled cleanup commands run every five minutes when the Laravel scheduler is operating. Admin login challenges default to five minutes and enrollment challenges to ten minutes, subject to deployment configuration. MFA attempt rows are cleaned hourly: challenge rows older than one day and unlocked account-level counters older than 90 days are removed. Locked account-level rows are not removed by this cleanup command and are cleared after a successful verification.',
            'Recovery-code hashes remain while those recovery codes are configured and are replaced or deleted when codes are regenerated or MFA is disabled. TOTP secrets remain while enrollment or MFA is configured; no dedicated scheduled cleanup for expired enrollment secrets was found. Session lifetime and data cleanup depend partly on deployment configuration.',
            'Admin account deletion is recoverable soft deletion. The reviewed project has no automated permanent deletion schedule for accounts/content and no activity-log retention period. The operator must confirm and apply those retention decisions. Backups may retain information under deployment-specific settings.',
        ],
    },
    {
        id: 'user-rights-and-data-deletion',
        title: 'User Rights and Data Deletion',
        paragraphs: [
            'Rights and request procedures depend on the laws that apply to the user and operator. The application does not provide self-service account deletion or a data export request workflow. Profile fields can be updated through account features; account deletion is administrator-managed and recoverable, and does not itself permanently erase related content.',
            'No verified privacy contact address or named controller is provided in the project. The operator must supply a working contact and define how applicable requests are handled before production use.',
        ],
    },
    {
        id: 'childrens-privacy',
        title: 'Children’s Privacy',
        paragraphs: [
            'The registration form does not request a date of birth, and the project does not implement a dedicated age-verification flow. The appropriate minimum age and any safeguards depend on the service’s intended audience and applicable law; those requirements have not been specified in the project. The operator should set and publish an age policy before offering the service to children.',
        ],
    },
    {
        id: 'changes-to-this-privacy-policy',
        title: 'Changes to This Privacy Policy',
        paragraphs: [
            'This Policy may need to change when Planora’s features, data flows, providers, or applicable requirements change. The operator should publish an updated version and date when the notice changes, and provide any notice required by applicable law.',
        ],
    },
    {
        id: 'contact-information',
        title: 'Contact Information',
        paragraphs: [
            'The project does not provide a verified privacy/support email, postal address, named controller, or service operator identity. No contact details are invented here. The operator must identify the responsible entity and add a monitored contact method before production use.',
        ],
    },
];
</script>

<style src="../../../css/pages/public/terms-and-conditions.css"></style>
