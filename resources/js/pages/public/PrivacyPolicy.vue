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
                <p class="terms-lead">Learn what information Planora currently handles, why it is used, and what the project does not yet provide.</p>
                <p class="terms-updated">Last updated <time datetime="2026-11-09">November 9, 2026</time></p>
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
            'This Privacy Policy describes personal information handled by the Planora application in the project as currently implemented. The legal identity and location of the service operator, the applicable jurisdictions, and production hosting arrangements are not specified in the project configuration.',
            'This page is based on the application code and database migrations reviewed for this project. The operator should confirm that it matches the deployed service and complete deployment-specific details before production use.',
        ],
    },
    {
        id: 'information-we-collect',
        title: 'Information We Collect',
        paragraphs: [
            'When an account is registered, the application requests a name, email address, and password. The account database also supports optional first and last names, username, phone number, and profile photo. Administrators can set some account details, including role, status, and moderator permissions.',
            'The application records account timestamps and authentication details such as email verification time and last sign-in time. Administrator tools may also store account status notes and activity records. Temporary registration and password-recovery records include an email address, expiry times, and failed-attempt counts; credential values are hashed in the normal production flow. Local development has a test-only registration-code field.',
        ],
    },
    {
        id: 'how-we-use-your-information',
        title: 'How We Use Your Information',
        paragraphs: [
            'The application uses account information to create and verify accounts, authenticate users, maintain a signed-in session, support password recovery, show account details, and apply account roles and status. Operational activity records are also created for events such as sign-in, registration, and administrator account changes.',
            'Registration currently requires one checkbox stating agreement to the Terms & Conditions and acknowledgment that the Privacy Policy has been read. The server validates that checkbox before beginning registration; the project does not store a separate Privacy Policy acceptance timestamp or record.',
            'The project does not configure an advertising or analytics integration in the reviewed application code. This does not describe any separate tools that an operator may add to a deployed environment.',
        ],
    },
    {
        id: 'account-information',
        title: 'Account Information',
        paragraphs: [
            'Passwords are stored using Laravel’s password-hashing mechanism rather than as readable password text. The application uses verified email status when controlling sign-in. Account roles, status, and management permissions determine which protected areas and administrative actions are available.',
            'Profile and account screens in this project are not all connected to working self-service APIs. The information an account can currently change may therefore differ from fields supported by the database or administrator tools.',
        ],
    },
    {
        id: 'tasks-and-user-content',
        title: 'Tasks and User Content',
        paragraphs: [
            'The interface describes task and project planning features, but the reviewed database migrations do not define task or project content tables, and the current API does not provide task or project persistence endpoints. The related screens are incomplete. This project therefore does not establish that task or project content entered into those screens is saved to Planora’s database.',
            'Do not use unfinished task or project screens as a place to store sensitive information. If persistent user-content features are added, this Policy should be updated to describe the content collected, its purpose, and its retention.',
        ],
    },
    {
        id: 'data-storage-and-security',
        title: 'Data Storage and Security',
        paragraphs: [
            'Account records and operational data are stored using the database configured for the deployment. Passwords are hashed, and temporary authentication codes and recovery credentials are managed through dedicated records with expiry and attempt information. The application uses session-based authentication, CSRF protections, and role checks in its current code.',
            'Administrator-managed profile photos are written to the configured public file-storage disk and may be served through a public storage URL. The actual database, storage provider, server location, transport configuration, backups, and other infrastructure controls depend on deployment and are not established by this repository. No security certification or absolute security guarantee is claimed here.',
        ],
    },
    {
        id: 'data-sharing-and-third-parties',
        title: 'Data Sharing and Third Parties',
        paragraphs: [
            'The project does not identify a specific production hosting, database, file-storage, or email provider. If such providers are configured by the operator, they may process information as needed to host the application, store its data or deliver account emails. Their identities, locations, and terms must be confirmed for the actual deployment.',
            'No third-party advertising or analytics service is configured in the reviewed project code. The application’s administrator tools allow authorized staff to view and manage account records and certain activity records according to role and permissions.',
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
            'Pending registrations expire after one day, and temporary email-verification and password-recovery records have expiry times. The application schedules cleanup commands every five minutes; cleanup only runs when the Laravel scheduler is running in the deployed environment.',
            'User accounts can be soft-deleted by an administrator and later restored. The reviewed project does not define an automated permanent-deletion schedule for soft-deleted accounts or an activity-log retention period. The database operator must set and apply an appropriate retention and deletion policy for the deployment.',
        ],
    },
    {
        id: 'user-rights-and-data-deletion',
        title: 'User Rights and Data Deletion',
        paragraphs: [
            'Privacy rights and request procedures depend on the laws that apply to you and to the service operator. Depending on those laws, you may be able to ask for access to or correction or deletion of personal information. This project does not currently provide a self-service account-deletion endpoint. Administrator deletion is recoverable soft deletion and does not by itself permanently erase account data.',
            'The project configuration does not provide a verified privacy contact address. The operator must publish a contact method and define how applicable requests are handled before relying on this page as the complete privacy notice for a production service.',
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
            'The project settings and repository do not contain a verified privacy or support email address, postal address, or named data controller. No contact details are invented here. The responsible operator must add a working privacy contact and identify the controller for the deployed service before production use.',
        ],
    },
];
</script>

<style src="../../../css/pages/public/terms-and-conditions.css"></style>
