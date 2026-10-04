# ALTUS mobile screen inventory

Final browser UI audit: 110 registered routes; each captured in English and Arabic. Every route is listed below. **Native iOS and Android device checks are pending.** A rendered screen is not proof of a completed live enterprise workflow. See FINAL-AUDIT.md for the release contract.

Live integration legend: API = connected to authenticated domain services; local = device/session preference or reading copy; web = existing secure web flow; preview = native live contract remains to be implemented. The launch screen is email/password login; native device/email and authenticator verification are handled inside that flow. Advanced administration opens the appropriate web console. Media and camera device behavior still require native validation.

| # | Module | Route | Screen | UI | Live integration | Benefit |
|---|---|---|---|---|---|---|
| 001 | Access | /splash | People. Knowledge. Performance. | Captured EN + AR | Preview | A confident, recognizable ALTUS arrival. |
| 002 | Access | /welcome | Extraordinary service starts with you. | Captured EN + AR | Preview | Connect every employee to the purpose of hospitality. |
| 003 | Access | /introduction | Knowledge that makes a difference. | Captured EN + AR | Preview | Explain learning, application and measurable performance. |
| 004 | Access | /language | Your language. Your experience. | Captured EN + AR | Local/session | English and Arabic interfaces; expandable global locale catalog. |
| 005 | Access | /sign-in | Welcome back | Captured EN + AR | API · password/session/device/MFA | Sign in with email/password and open the workspace permitted by your account. |
| 006 | Access | /forgot-password | Recover your account | Captured EN + AR | Web-managed security | Use the existing secure web account recovery flow. |
| 007 | Access | /reset-password | Set a new password | Captured EN + AR | Web-managed security | Complete password recovery with the account provider. |
| 008 | Access | /otp | Verify your identity | Captured EN + AR | Web-managed security | Confirm a new device and pass the platform authenticator or recovery-code policy when required. |
| 009 | Access | /first-setup | Make it yours | Captured EN + AR | Preview | Set a preferred name and region. |
| 010 | Access | /organization | Your hotel group | Captured EN + AR | Preview | Identify an authorized organization. |
| 011 | Access | /property | Your property | Captured EN + AR | Preview | Keep property learning and SOPs in the correct context. |
| 012 | Access | /role | Your workspace | Captured EN + AR | Preview | Confirm server-issued roles; demonstrate role-specific experiences. |
| 013 | Access | /consent | A foundation of trust | Captured EN + AR | Preview | Review privacy and terms before accepting. |
| 014 | Access | /notification-permission | Stay one step ahead | Captured EN + AR | Preview | Request notification permission at a meaningful moment. |
| 015 | Access | /setup-success | Your journey starts here | Captured EN + AR | Preview | Confirm setup and guide the employee to the next action. |
| 016 | Learner | /home | Your next step, made clear. | Captured EN + AR | API | One prioritized next action, required learning and operational knowledge. |
| 017 | Learning | /learning | A little learning. A lasting difference. | Captured EN + AR | API | Search and filter an actionable learning plan. |
| 018 | Learning | /mandatory | Mandatory learning | Captured EN + AR | API | A focused view of the employee learning journey. |
| 019 | Learning | /assigned | Assigned courses | Captured EN + AR | API | A focused view of the employee learning journey. |
| 020 | Learning | /in-progress | In progress | Captured EN + AR | API | A focused view of the employee learning journey. |
| 021 | Learning | /completed | Completed learning | Captured EN + AR | API | A focused view of the employee learning journey. |
| 022 | Learning | /overdue | Upcoming & overdue | Captured EN + AR | API | A focused view of the employee learning journey. |
| 023 | Learning | /recommended | Recommended for you | Captured EN + AR | API | A focused view of the employee learning journey. |
| 024 | Learning | /paths | Learning paths | Captured EN + AR | Preview | A focused view of the employee learning journey. |
| 025 | Learning | /programs | Programs | Captured EN + AR | Preview | A focused view of the employee learning journey. |
| 026 | Learning | /course-history | Course history | Captured EN + AR | API | A focused view of the employee learning journey. |
| 027 | Learning | /catalog | Elevate your everyday. | Captured EN + AR | API | Discover hospitality learning across professional domains. |
| 028 | Learning | /categories | Professional domains | Captured EN + AR | Preview | Explore every core hospitality discipline. |
| 029 | Learning | /course | Course details | Captured EN + AR | API | Understand outcomes, competencies, lessons and certificate rules. |
| 030 | Learning | /lesson | Learn. Apply. Remember. | Captured EN + AR | API | A focused native text/video player with progress and next steps. |
| 031 | Learning | /transcript | Lesson transcript | Captured EN + AR | Preview | Read alongside the lesson for accessibility and comprehension. |
| 032 | Learning | /resources | Learning resources | Captured EN + AR | Preview | Bring practical reference material into the workflow. |
| 033 | Assessment | /quiz | Put your knowledge to work. | Captured EN + AR | API | Scenario and knowledge questions with pass rules and clear feedback. |
| 034 | Assessment | /quiz-result | Your assessment result | Captured EN + AR | API | Understand the score, explanations and permitted next step. |
| 035 | Assessment | /assessment-history | Assessment history | Captured EN + AR | Preview | Review past attempts and improvement. |
| 036 | Knowledge | /knowledge | Confidence, at your fingertips. | Captured EN + AR | API | Find current approved SOPs, policies, standards and checklists. |
| 037 | Knowledge | /policies | Policies & standards | Captured EN + AR | Preview | Find operational guidance in the right content category. |
| 038 | Knowledge | /checklists | Operational checklists | Captured EN + AR | Preview | Find operational guidance in the right content category. |
| 039 | Knowledge | /articles | Articles & best practices | Captured EN + AR | Preview | Find operational guidance in the right content category. |
| 040 | Knowledge | /forms | Forms & templates | Captured EN + AR | Preview | Find operational guidance in the right content category. |
| 041 | Knowledge | /department-resources | Department resources | Captured EN + AR | Preview | Find operational guidance in the right content category. |
| 042 | Knowledge | /sop | The standard behind great service. | Captured EN + AR | API | Read versioned procedures, safety notes and confirm understanding. |
| 043 | Knowledge | /search | What would you like to know? | Captured EN + AR | API | Search courses and authorized operational sources together. |
| 044 | Knowledge | /favorites | Saved for the moment you need it. | Captured EN + AR | Preview | Keep useful courses and guidance close at hand. |
| 045 | Knowledge | /recent | Recently viewed | Captured EN + AR | Preview | Return quickly to useful operational content. |
| 046 | Knowledge | /saved-searches | Saved searches | Captured EN + AR | Preview | Repeat useful queries without retyping. |
| 047 | Knowledge | /acknowledgments | Read & confirmed | Captured EN + AR | Preview | Record acknowledgment against the current approved version. |
| 048 | Assistant | /assistant | Good questions. Trusted answers. | Captured EN + AR | API | Retrieve approved guidance with citations and honest coverage limits. |
| 049 | Assistant | /conversation-history | Your conversations | Captured EN + AR | Local/session | Return to previous questions and sources. |
| 050 | Performance | /progress | See how far you have come. | Captured EN + AR | API | Track learning, readiness, activity and evidence. |
| 051 | Performance | /competencies | Turn knowledge into capability. | Captured EN + AR | API | Understand strengths, gaps and recommended next learning. |
| 052 | Performance | /readiness | Ready, with evidence. | Captured EN + AR | API | Explain readiness using actual policy checks. |
| 053 | Performance | /actions | Your improvement plan | Captured EN + AR | API | Make identified gaps actionable. |
| 054 | Performance | /action-detail | Improvement action | Captured EN + AR | API | Review deadlines, evidence and review status. |
| 055 | Performance | /certificates | Achievement, recognized. | Captured EN + AR | API | Find issued credentials and expiry details. |
| 056 | Performance | /certificate | Your achievement | Captured EN + AR | API | Review and share an issued certificate. |
| 057 | Performance | /certificate-verify | Verify a credential | Captured EN + AR | API | Check a certificate using the trusted verification service. |
| 058 | Personal | /notifications | Your updates | Captured EN + AR | API | Read assignment reminders, revisions and announcements. |
| 059 | Personal | /notification-detail | Update details | Captured EN + AR | API | Understand a notification and open the relevant destination. |
| 060 | Personal | /announcements | From your property | Captured EN + AR | Preview | Keep employees aligned on important operational updates. |
| 061 | Personal | /calendar | Make time for growth. | Captured EN + AR | Preview | See training sessions and upcoming deadlines. |
| 062 | Personal | /event | Training session | Captured EN + AR | Preview | Review the session, location and attendance action. |
| 063 | Personal | /qr-attendance | Check in to learning | Captured EN + AR | Preview | Scan an attendance or authorized learning link. |
| 064 | Personal | /downloads | Knowledge that travels with you. | Captured EN + AR | Local/session | Review saved offline reading and remove local downloads. |
| 065 | Personal | /download-detail | Offline content | Captured EN + AR | Local/session | Read locally saved content and see its version. |
| 066 | Collaboration | /discussion | Better, together. | Captured EN + AR | Preview | Read and contribute to learning conversations. |
| 067 | Collaboration | /discussion-detail | Learning conversation | Captured EN + AR | Preview | Review instructor and colleague contributions. |
| 068 | Collaboration | /new-discussion | Ask your team | Captured EN + AR | Preview | Post a focused learning question. |
| 069 | Personal | /profile | Your space to grow. | Captured EN + AR | API | Find account details, preferences and every personal tool. |
| 070 | Personal | /edit-profile | Your profile | Captured EN + AR | Preview | Maintain personal display details. |
| 071 | Personal | /settings | Make it work for you. | Captured EN + AR | Local/session | Change language, theme and accessibility without signing out. |
| 072 | Personal | /notification-settings | Notification preferences | Captured EN + AR | Local/session | Choose useful reminder categories. |
| 073 | Personal | /security | Account security | Captured EN + AR | Web-managed security | Manage credentials, password and MFA using the existing identity system. |
| 074 | Personal | /privacy | Privacy | Captured EN + AR | Local/session | Understand how workplace account and learning information is used. |
| 075 | Personal | /terms | Terms of use | Captured EN + AR | Local/session | Review the platform terms. |
| 076 | Personal | /about | People. Knowledge. Performance. | Captured EN + AR | Local/session | Explain ALTUS positioning and the app version. |
| 077 | Personal | /delete-account | Account deletion request | Captured EN + AR | Preview | Request account deletion with employer retention considerations. |
| 078 | Management | /management | Great teams. Measurable progress. | Captured EN + AR | API | See who needs attention and act on training and readiness. |
| 079 | Management | /team | The people behind the experience. | Captured EN + AR | API | Find authorized employees and their readiness. |
| 080 | Management | /learner-detail | Employee development | Captured EN + AR | API | Review the employee learning and competency record. |
| 081 | Management | /assign | Give growth a direction. | Captured EN + AR | API | Assign a course to authorized people with a deadline. |
| 082 | Management | /reports | From insight to improvement. | Captured EN + AR | API | Review capability gaps and property performance. |
| 083 | Management | /gaps | Where to focus next | Captured EN + AR | API | Prioritize open competency gaps. |
| 084 | Management | /assessor-queue | Practical assessment queue | Captured EN + AR | Preview | Find employees ready for practical evaluation. |
| 085 | Management | /practical | Observe. Assess. Develop. | Captured EN + AR | Preview | Record criterion-based observed evidence. |
| 086 | Management | /branding | Your brand. ALTUS capability. | Captured EN + AR | Preview | Preview property identity using centralized design tokens. |
| 087 | Administration | /admin | A portfolio of potential. | Captured EN + AR | Secure web console | Reach central content, governance, property and system tools. |
| 088 | Administration | /clients | Client organizations | Captured EN + AR | Secure web console | Review governed platform operations in the authorized scope. |
| 089 | Administration | /properties | Properties | Captured EN + AR | Secure web console | Review governed platform operations in the authorized scope. |
| 090 | Administration | /users | Users & access | Captured EN + AR | Secure web console | Review governed platform operations in the authorized scope. |
| 091 | Administration | /roles | Roles & permissions | Captured EN + AR | Secure web console | Review governed platform operations in the authorized scope. |
| 092 | Administration | /content | Content library | Captured EN + AR | Secure web console | Review governed platform operations in the authorized scope. |
| 093 | Administration | /versions | Versions & approvals | Captured EN + AR | Secure web console | Review governed platform operations in the authorized scope. |
| 094 | Administration | /portfolio | Cross-property analytics | Captured EN + AR | Secure web console | Review governed platform operations in the authorized scope. |
| 095 | Administration | /audit | Audit activity | Captured EN + AR | Secure web console | Review governed platform operations in the authorized scope. |
| 096 | Administration | /course-editor | Shape the learning experience. | Captured EN + AR | Secure web console | Prepare a course draft without overwriting published content. |
| 097 | Administration | /version-detail | Review with confidence. | Captured EN + AR | Secure web console | Review revision metadata and approval separation. |
| 098 | Support | /support | We are here to help. | Captured EN + AR | Preview | Find FAQs, issue reporting and support requests. |
| 099 | Support | /faq | A little clarity | Captured EN + AR | Preview | Resolve common learning and account questions. |
| 100 | Support | /tickets | Your support requests | Captured EN + AR | Preview | Track the status of submitted support requests. |
| 101 | Support | /new-ticket | Let us help you move forward. | Captured EN + AR | Preview | Describe an issue and attach a file. |
| 102 | Support | /ticket-detail | Support request | Captured EN + AR | Preview | Read a submitted issue and its status. |
| 103 | Support | /report-issue | Report an issue | Captured EN + AR | Preview | Report a technical or content issue with context. |
| 104 | System | /offline | You are offline | Captured EN + AR | Preview | A clear, recoverable system state. |
| 105 | System | /session-expired | Please sign in again | Captured EN + AR | Preview | A clear, recoverable system state. |
| 106 | System | /maintenance | A little care, behind the scenes. | Captured EN + AR | Preview | A clear, recoverable system state. |
| 107 | System | /update-required | A better experience awaits. | Captured EN + AR | Preview | A clear, recoverable system state. |
| 108 | System | /permission-denied | Access is restricted | Captured EN + AR | Preview | A clear, recoverable system state. |
| 109 | System | /error | Let us try that again. | Captured EN + AR | Preview | A clear, recoverable system state. |
| 110 | System | /not-found | This page is unavailable | Captured EN + AR | Preview | A clear, recoverable system state. |
