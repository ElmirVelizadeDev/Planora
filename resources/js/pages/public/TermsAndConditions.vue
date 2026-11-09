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
                <p class="terms-lead">These terms explain the basic rules for creating an account and using the Planora service.</p>
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

                    <section id="contact-information" class="terms-section">
                        <div class="terms-section-number">12</div>
                        <div>
                            <h2>Contact Information</h2>
                            <p>Planora’s project configuration does not currently provide a verified legal or support contact address. A contact channel and the responsible service operator should be added here before the service is offered as a production service.</p>
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
            'Planora is a personal productivity application intended to help people organize work in a workspace. These Terms & Conditions (“Terms”) apply when you visit or use Planora. In these Terms, “Planora”, “we” and “our” refer to the service; the legal identity of its operator has not been provided in the project configuration.',
            'Please read these Terms before using the service. They should be reviewed and completed by the service operator for the jurisdictions in which Planora will be offered.',
        ],
    },
    {
        id: 'acceptance-of-terms',
        title: 'Acceptance of Terms',
        paragraphs: [
            'By selecting the acceptance checkbox during registration or by continuing to use Planora, you confirm that you have read and agree to these Terms. If you do not agree, do not create an account or use the service.',
        ],
    },
    {
        id: 'account-registration',
        title: 'Account Registration',
        paragraphs: [
            'Some parts of Planora require an account. You must provide information requested by the registration form and complete the email verification step before the account is created. Keep your sign-in credentials private and tell the service operator promptly if you believe your account has been accessed without your permission.',
            'You are responsible for activity performed through your account, subject to applicable law and the service operator’s responsibilities. Do not impersonate another person or provide an email address you are not authorized to use.',
        ],
    },
    {
        id: 'user-responsibilities',
        title: 'User Responsibilities',
        paragraphs: [
            'Use Planora lawfully and follow these Terms. Keep account information reasonably accurate, protect access to your account, and use care when entering or sharing information in your workspace. You remain responsible for ensuring that information you submit is appropriate for your use of the service.',
        ],
    },
    {
        id: 'task-management-and-platform-usage',
        title: 'Task Management and Platform Usage',
        paragraphs: [
            'Planora is designed as a productivity workspace. The available tools and actions may vary as the product is developed and by account permissions. Use only the features currently presented as available to your account, and do not rely on the service as the sole copy of important information.',
        ],
    },
    {
        id: 'prohibited-activities',
        title: 'Prohibited Activities',
        paragraphs: [
            'You may not use Planora to break the law, attempt unauthorized access to accounts or systems, interfere with the service or its security, introduce malicious code, abuse or disrupt the service, or misrepresent your identity or authority. Do not use the service in a way that infringes another person’s rights.',
        ],
    },
    {
        id: 'user-content-and-data',
        title: 'User Content and Data',
        paragraphs: [
            'You remain responsible for content and information that you submit through Planora. You should only submit information that you are entitled to use. Planora processes account information to provide account and workspace functionality, including sign-in and account security. Details about personal information handling, retention, and any rights available to you should be set out in a separate Privacy Policy before production use.',
        ],
    },
    {
        id: 'account-suspension-and-termination',
        title: 'Account Suspension and Termination',
        paragraphs: [
            'Access to an account may be restricted or an account may be disabled where necessary to protect the service, respond to misuse, or comply with applicable law. The project includes administrative account status controls. The operator should provide a verified contact channel for account questions and requests; see Contact Information below.',
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
            'To the extent permitted by applicable law, the service is provided on an available basis, and the operator is not responsible for indirect or consequential loss arising from use of the service. Nothing in these Terms excludes or limits liability that cannot lawfully be excluded or limited. This section requires review against the law applicable to the service and its users.',
        ],
    },
    {
        id: 'changes-to-these-terms',
        title: 'Changes to These Terms',
        paragraphs: [
            'These Terms may be updated as Planora changes. When they are updated, the revised version and its effective date should be published on this page. Continued use after an update takes effect constitutes acceptance only to the extent allowed by applicable law.',
        ],
    },
];
</script>

<style src="../../../css/pages/public/terms-and-conditions.css"></style>
