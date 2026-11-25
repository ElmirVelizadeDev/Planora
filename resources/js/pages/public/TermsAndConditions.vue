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
            <section class="terms-intro" aria-labelledby="terms-title">
                <p class="terms-eyebrow"><span aria-hidden="true"></span> Planora policies</p>
                <h1 id="terms-title">Terms &amp; Conditions</h1>
                <p class="terms-lead">These terms describe the rules for using Planora’s account and workspace features. Operator-specific items that need confirmation are identified below.</p>
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
                        <p>Thank you for choosing Planora. We aim to make the rules for using your workspace clear and straightforward.</p>
                        <RouterLink :to="{ name: 'home' }">Back to Home <span aria-hidden="true">→</span></RouterLink>
                    </div>
                </article>
            </div>
        </main>

        <footer class="terms-footer"><RouterLink :to="{ name: 'home' }">Planora</RouterLink><span>Terms &amp; Conditions</span></footer>
    </div>
</template>

<script setup>
const sections = [
    {
        id: 'introduction',
        title: 'Introduction',
        paragraphs: [
            'Planora is a productivity application with accounts and workspace features for tasks, projects, and categories. These Terms describe use of the application. The project does not identify the legal entity or individual operating the deployed service.',
            'PRE-PRODUCTION ITEM: the operator must identify the responsible service provider and confirm these Terms for the jurisdictions where the service will be offered.',
        ],
    },
    {
        id: 'acceptance-of-terms',
        title: 'Acceptance of Terms',
        paragraphs: [
            'To register, you must select the Terms acceptance checkbox. The server validates that selection before it starts registration, and the account is created only after email verification. The application does not store a durable record or timestamp of this acceptance. Do not register if you do not agree to these Terms.',
        ],
    },
    {
        id: 'eligibility-and-registration',
        title: 'Eligibility and Account Registration',
        paragraphs: [
            'Registration currently requires a name, supported email address, password, confirmation, and Terms checkbox. You must complete email verification before an account is created. The service does not implement age verification or specify a minimum age; the operator must determine and publish an eligibility rule before production use.',
            'Provide information you are authorized to use. Accounts are assigned the regular user role at registration; elevated roles are managed through protected administrative controls.',
        ],
    },
    {
        id: 'account-responsibilities-and-security',
        title: 'Account Responsibilities and Security',
        paragraphs: [
            'Keep your sign-in credentials confidential, use a unique password, and notify the service operator through its verified contact channel if you believe your account has been compromised. The application may require administrators to use multi-factor authentication.',
            'You are responsible for activity under your account to the extent permitted by applicable law. Keep account details reasonably accurate and use care when entering information into a workspace.',
        ],
    },
    {
        id: 'permitted-use',
        title: 'Permitted Use',
        paragraphs: [
            'Use Planora for lawful personal or workspace planning with the features enabled for your account. Tasks can include descriptions, status, priority, due date, project, and category; projects include descriptions, status, and dates. Do not rely on Planora as the only copy of important information.',
        ],
    },
    {
        id: 'prohibited-activities',
        title: 'Prohibited Activities',
        paragraphs: [
            'Do not use Planora to violate law or others’ rights, impersonate another person, access accounts or systems without authorization, bypass permissions, probe or disrupt service security, introduce malicious code, or interfere with service availability.',
        ],
    },
    {
        id: 'tasks-projects-and-user-content',
        title: 'Tasks, Projects, and User Content',
        paragraphs: [
            'You retain responsibility for the information you enter, including task, project, and category content. Submit only content you are entitled to use and avoid sensitive information that is not necessary for planning work.',
            'You retain any rights you have in your content. You allow the service to store, reproduce, display, and process it only as needed to operate, secure, and support the workspace features you use. The project does not define a broader content license; applicable law may provide additional rights.',
        ],
    },
    {
        id: 'intellectual-property',
        title: 'Intellectual Property',
        paragraphs: [
            'The Planora application name, interface, and software are part of the application project. The project does not identify the owner or licensing terms for all branding and software assets. No ownership transfer is stated by these Terms. The operator must confirm and publish the applicable ownership and license information before production use.',
        ],
    },
    {
        id: 'account-suspension-and-termination',
        title: 'Account Suspension and Termination',
        paragraphs: [
            'The application provides administrative controls to change account status and to soft-delete or restore accounts. Access may therefore be restricted or disabled under these controls. The operator must define the notice, review, and appeal process for the deployed service and publish a verified contact method.',
        ],
    },
    {
        id: 'account-deletion-and-data',
        title: 'Account Deletion and Data Handling',
        paragraphs: [
            'The application does not offer self-service account deletion. Administrator deletion is a recoverable soft deletion and revokes authentication; it does not itself permanently erase the account or all related workspace content. Task records support soft deletion. Related records may remain, and user references may be nulled according to database relationships.',
            'The project defines no general permanent deletion schedule for soft-deleted accounts, workspace content, or activity logs. The operator must decide and publish retention and deletion practices. See the Privacy Policy for current implementation details.',
        ],
    },
    {
        id: 'service-availability',
        title: 'Service Availability',
        paragraphs: [
            'Planora is under active development. Features may change, be temporarily unavailable, or be interrupted for maintenance or technical reasons. We do not promise uninterrupted or error-free availability. Back up information that is important to you.',
        ],
    },
    {
        id: 'limitation-of-liability',
        title: 'Limitation of Liability',
        paragraphs: [
            'Planora is provided as available and may contain errors or interruptions. To the extent permitted by applicable law, the operator does not accept liability for indirect or consequential loss arising from use of the service. Nothing in these Terms excludes liability that cannot lawfully be excluded or limited. This clause requires review by the operator for the applicable law and service model before production use.',
        ],
    },
    {
        id: 'disclaimers',
        title: 'Disclaimers',
        paragraphs: [
            'Planora is a planning tool and does not guarantee that tasks will be completed, information will always be available, or the service will meet every individual requirement. The operator must confirm any additional disclaimers for the actual service and applicable law.',
        ],
    },
    {
        id: 'changes-to-these-terms',
        title: 'Changes to These Terms',
        paragraphs: [
            'The operator may publish an updated version when the application or its practices change. The revised text and effective date should be shown here, with any notice or renewed consent required by applicable law. This application does not currently keep a durable record of Terms acceptance.',
        ],
    },
    {
        id: 'governing-law-and-jurisdiction',
        title: 'Governing Law and Jurisdiction',
        paragraphs: [
            'The project does not establish a governing law or court jurisdiction. PRE-PRODUCTION ITEM: the operator must select and publish the governing law and jurisdiction based on the operator, intended users, and applicable legal advice. No jurisdiction is implied by this document.',
        ],
    },
    {
        id: 'contact-information',
        title: 'Contact Information',
        paragraphs: [
            'The project contains no verified operator name, postal address, or support/legal email. PRE-PRODUCTION ITEM: the operator must identify the responsible entity or person and provide a monitored contact method for account, Terms, and privacy requests. No contact details are invented here.',
        ],
    },
];
</script>

<style src="../../../css/pages/public/terms-and-conditions.css"></style>
