{{-- Full privacy notice text. Shared by the /privacy page, the login-page popup and the first-login acknowledgment popup. --}}
<style>
  .pn-content, .pn-content * { font-family:-apple-system,BlinkMacSystemFont,'SF Pro Text','SF Pro Display','Helvetica Neue',Helvetica,Arial,sans-serif; }
  .pn-content { --pn-ink:#111; --pn-muted:#6b6b6b; --pn-border:#e5e5e5; --pn-green:#1F6E4A; color:var(--pn-ink); line-height:1.6; text-align:left; }
  .pn-content h2 { color:var(--pn-green); font-size:.95rem; font-weight:600; margin:22px 0 6px; }
  .pn-content h2:first-child { margin-top:0; }
  .pn-content p, .pn-content li { font-size:.9rem; }
  .pn-content p { margin-bottom:10px; }
  .pn-content ul { margin:0 0 10px 20px; }
  .pn-content li { margin-bottom:4px; }
  .pn-content a { color:var(--pn-green); }
  .pn-content table { width:100%; border-collapse:collapse; font-size:.84rem; margin:6px 0 12px; }
  .pn-content th, .pn-content td { text-align:left; padding:8px 8px 8px 0; border-bottom:1px solid var(--pn-border); vertical-align:top; }
  .pn-content th { color:var(--pn-muted); font-weight:500; font-size:.76rem; }
</style>
<div class="pn-content">
    <h2>Who we are</h2>
    <p>
      This system is operated by the QSU Student Government to store, organise and archive its records. The Student
      Government is the personal information controller for the information described below.
    </p>

    <h2>What information we collect</h2>
    <table>
      <thead><tr><th>Information</th><th>From whom</th><th>Why</th></tr></thead>
      <tbody>
        <tr>
          <td>Name, email address, position, profile photo, and your password (stored only as a one-way hash)</td>
          <td>Officers and advisers with an account</td>
          <td>To create your account and let you sign in.</td>
        </tr>
        <tr>
          <td>Activity log: your name, what you did in the system, the date and time, and your IP address</td>
          <td>Signed-in users</td>
          <td>Security, accountability and investigating misuse or errors.</td>
        </tr>
        <tr>
          <td>Session data: IP address and browser details</td>
          <td>Signed-in users</td>
          <td>To keep you signed in and to detect unauthorised access.</td>
        </tr>
        <tr>
          <td>Questions and answers with the built-in Help Assistant</td>
          <td>Signed-in users</td>
          <td>To answer your questions and improve the guide.</td>
        </tr>
        <tr>
          <td>Documents, files and scanned hard copies, including text read from scans (OCR), and the sender or recipient named on them</td>
          <td>Whoever appears in Student Government records</td>
          <td>To keep, search and archive official Student Government records.</td>
        </tr>
        <tr>
          <td>Name, email address, student ID number and message</td>
          <td>Students who use the contact form</td>
          <td>To read and reply to your message.</td>
        </tr>
        <tr>
          <td>A browser notification token (no name, email or student ID)</td>
          <td>Students who tap "Notify me" on the public page</td>
          <td>To alert your browser when a new announcement or public document is posted.</td>
        </tr>
        <tr>
          <td>Anonymous usage statistics: which announcements and documents are viewed, words typed into the document search, the time, and whether the device is a phone, tablet or computer. No name, IP address, cookie or device ID is stored, and nothing is recorded if your browser sends a "Do Not Track" or "Global Privacy Control" signal.</td>
          <td>Visitors of the public student page</td>
          <td>To learn what students need, so announcements and documents can be shared better.</td>
        </tr>
      </tbody>
    </table>
    <p>
      Some scanned records may contain sensitive personal information (for example about education, health or
      government-issued IDs). Such records can be restricted so only authorised officers can open them.
    </p>

    <h2>How we use it</h2>
    <p>
      Only for the purposes listed above: running the system, keeping records secure, and administering the Student
      Government. We do not sell your information and we do not use it for advertising.
    </p>

    <h2>Who receives it</h2>
    <ul>
      <li><strong>Google (Gemini)</strong> receives the questions you type into the Help Assistant, so it can produce an answer. Please do not put personal or sensitive details in those questions. Google's servers are outside the Philippines. Scanned documents are read on our own server and are <em>not</em> sent to Google.</li>
      <li><strong>Supabase</strong> hosts our cloud copy of stored files and backups. Backups are encrypted.</li>
      <li><strong>Our email provider</strong> delivers password-reset emails and contact-form messages.</li>
      <li>System administrators (the Adviser and President) can see activity logs and manage accounts. Officers can only open the records they have been given access to.</li>
    </ul>

    <h2>How long we keep it</h2>
    <ul>
      <li>Activity logs: {{ number_format(config('retention.activity_log_days')) }} days, then automatically deleted.</li>
      <li>Help Assistant questions and answers: {{ number_format(config('retention.help_bot_log_days')) }} days, then automatically deleted.</li>
      <li>Deleted files and documents stay in the Trash for {{ config('trash.retention_days') }} days before permanent removal.</li>
      <li>Official records and archives are kept for as long as the Student Government needs them for its records.</li>
      <li>Account details are kept while you hold an officer account.</li>
      <li>Browser notification tokens: until you turn notifications off or your browser revokes them, after which they are removed automatically.</li>
      <li>Anonymous usage statistics from the public page: {{ number_format(config('retention.engagement_event_days')) }} days, then automatically deleted.</li>
    </ul>

    <h2>How we protect it</h2>
    <p>
      Passwords are hashed; sign-in attempts are rate-limited; access is role-based with restricted folders; sessions
      time out when idle; archived records are checked for tampering; backups are encrypted; and important actions
      are written to an audit log.
    </p>

    <h2>Your rights</h2>
    <p>Under the Data Privacy Act you have the right to:</p>
    <ul>
      <li>be informed about how your personal data is processed;</li>
      <li>access the personal data we hold about you;</li>
      <li>correct inaccurate data (officers can edit their own profile);</li>
      <li>object to processing, or ask for your data to be blocked or deleted where appropriate;</li>
      <li>data portability, and to claim compensation for damages from unlawful processing; and</li>
      <li>lodge a complaint with the National Privacy Commission (privacy.gov.ph).</li>
    </ul>

    <h2>Contact</h2>
    @if (config('privacy.contact_email'))
      <p>
        For questions or to exercise your rights, contact
        @if (config('privacy.dpo_name')) {{ config('privacy.dpo_name') }} @endif
        at <a href="mailto:{{ config('privacy.contact_email') }}">{{ config('privacy.contact_email') }}</a>.
      </p>
    @else
      <p>
        For questions or to exercise your rights, please contact the Student Government office or the Adviser. A
        Data Protection Officer contact will be published here once designated.
      </p>
    @endif
</div>
