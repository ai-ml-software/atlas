# CHATGPT.md — Mobile App Development Rules

## React Native + Expo — iOS & Android

This file defines the mandatory development rules for ChatGPT when designing, building, reviewing, or modifying this mobile application.

The target is a **production-quality Expo / React Native application for both iOS and Android**.

The application must be:

- Complete
- Visually polished
- Responsive
- Professional
- Consistent
- Accessible
- Secure
- Production-ready
- Fully navigable
- Suitable for iOS and Android
- Built without skipping screens, states, or important user journeys

---

# 1. ALWAYS DO FIRST

Before writing or modifying any mobile application code:

1. Inspect the existing project.
2. Inspect the complete folder structure.
3. Read the existing navigation implementation.
4. Identify the current Expo / React Native version.
5. Identify all installed dependencies.
6. Check whether Expo Router or React Navigation is being used.
7. Check authentication implementation.
8. Check API/backend integrations.
9. Check state-management architecture.
10. Check localization implementation.
11. Check the `assets/` directory.
12. Check any `brand_assets/`, `branding/`, `design/`, or similar folders.
13. Inspect existing reusable components.
14. Inspect existing theme files.
15. Inspect existing typography.
16. Inspect existing icons.
17. Inspect existing images.
18. Inspect existing logos.
19. Inspect existing color tokens.
20. Inspect any design specifications or screenshots provided by the user.

Never immediately rebuild the project from scratch.

Preserve the existing architecture unless there is a clear technical reason to change it.

Do not replace working functionality unnecessarily.

---

# 2. COMPLETE SCREEN INVENTORY — MANDATORY

Before implementation, create a complete screen inventory.

Do not start development until the app structure is understood.

The inventory must include every screen required by:

- Guest users
- Logged-in users
- Employees
- Supervisors
- Managers
- Administrators
- Organization administrators
- Hotel/property administrators
- Instructors/trainers
- Learners
- Any other role supported by the product

For every feature, determine:

- Main screen
- Detail screen
- Create screen
- Edit screen
- Delete confirmation
- Success state
- Empty state
- Loading state
- Error state
- Offline state
- Permission-denied state
- Search state
- Filter state
- No-results state

Never assume that one screen is enough for a feature.

---

# 3. NO SCREEN MAY BE SKIPPED

This is a hard rule.

If a feature exists in the website, specification, API, database, previous application, prototype, or requirements, its mobile equivalent must be considered.

Do not implement only the obvious screens.

Check for hidden and secondary flows.

Examples:

- Splash screen
- Language selection
- Welcome screen
- Onboarding
- Sign in
- Sign up
- Forgot password
- OTP verification
- Reset password
- Email verification
- Account activation
- Invitation acceptance
- Organization selection
- Hotel selection
- Department selection
- Home
- Dashboard
- Notifications
- Notification detail
- Search
- Search results
- Filters
- Profile
- Edit profile
- Settings
- Security settings
- Change password
- Privacy
- Terms
- Help
- Support
- About
- Logout confirmation
- Delete account
- Session expired
- No internet
- Maintenance mode
- App update required
- Permission requests
- Error screens
- Empty states
- Success screens
- Confirmation dialogs

Every user journey must have a complete beginning, middle, and end.

---

# 4. CREATE A SCREEN MATRIX

Maintain a screen matrix during development.

Recommended structure:

| ID | Module | Screen | Role | iOS | Android | Loading | Empty | Error | Complete |
|---|---|---|---|---|---|---|---|---|---|
| AUTH-01 | Authentication | Welcome | All | ✓ | ✓ | N/A | N/A | ✓ | ✓ |
| AUTH-02 | Authentication | Login | All | ✓ | ✓ | ✓ | N/A | ✓ | ✓ |
| AUTH-03 | Authentication | Forgot Password | All | ✓ | ✓ | ✓ | N/A | ✓ | ✓ |

Continue until every application screen has been documented.

A feature is not complete until its related rows are complete.

---

# 5. USER JOURNEY INVENTORY

Create and verify complete journeys.

Examples:

### Authentication Journey

Splash  
→ Language  
→ Welcome  
→ Login  
→ Verification if required  
→ Organization selection  
→ Property selection  
→ Home

### Password Recovery

Login  
→ Forgot password  
→ Enter email/mobile  
→ OTP  
→ New password  
→ Success  
→ Login

### Training Journey

Home  
→ Learning  
→ Course list  
→ Course details  
→ Lesson list  
→ Lesson  
→ Video/content  
→ Quiz  
→ Quiz result  
→ Course progress  
→ Certificate

### Performance Journey

Home  
→ Performance  
→ Dashboard  
→ KPI  
→ KPI detail  
→ History  
→ Feedback  
→ Improvement action

Every journey must be tested from start to finish.

---

# 6. PLATFORM

Use:

**React Native + Expo**

Prefer:

- Expo
- TypeScript
- Expo Router when suitable
- React Native APIs
- Expo-supported packages

Avoid unnecessary native code when Expo can handle the requirement.

The application must run correctly on:

- iPhone
- iPad where appropriate
- Android phones
- Android tablets where appropriate

---

# 7. TYPESCRIPT

Use TypeScript.

Do not create new JavaScript files for application logic unless technically required.

Avoid:

```ts
any
```

where possible.

Create proper interfaces and types for:

- Users
- Roles
- Hotels
- Organizations
- Courses
- Lessons
- Quizzes
- Performance data
- Notifications
- API responses
- Form data
- Navigation parameters

---

# 8. MOBILE-FIRST DESIGN

Design specifically for mobile.

Do not simply convert a desktop website into narrow columns.

The mobile interface should feel native and intentional.

Consider:

- One-handed use
- Thumb reach
- Bottom navigation
- Touch targets
- Keyboard behavior
- Safe areas
- Screen orientation
- Device sizes
- Native gestures
- Bottom sheets
- Mobile navigation conventions

---

# 9. iOS + ANDROID

Every implementation must be reviewed on both platforms.

Never assume that because something works on iOS it works on Android.

Check:

### iOS

- Safe Area
- Dynamic Island
- Notch
- Home indicator
- Native keyboard
- Status bar
- Navigation gestures
- Permissions
- Modal behavior
- Date picker
- Share sheet

### Android

- Status bar
- Navigation bar
- Gesture navigation
- Physical/software back button
- Keyboard resizing
- Permissions
- Date picker
- Share functionality
- Different screen densities

Do not create unnecessary differences between platforms.

Use platform-specific behavior only when it improves compatibility or follows native standards.

---

# 10. SAFE AREA — MANDATORY

Never place important content behind:

- Status bar
- Dynamic Island
- Notch
- Home indicator
- Android navigation controls

Use Expo / React Native safe-area handling properly.

Every full-screen screen must be checked.

---

# 11. NAVIGATION

Navigation must be planned before screens are built.

Possible navigation architecture:

```text
Root
│
├── Splash
├── Authentication
│   ├── Welcome
│   ├── Login
│   ├── Forgot Password
│   ├── OTP
│   └── Reset Password
│
└── Application
    │
    ├── Home
    ├── Learning
    ├── Performance
    ├── Knowledge
    ├── Notifications
    └── Profile
```

Use nested stacks/tabs only where required.

Avoid overly deep navigation.

The Android back button must behave correctly.

Users must never become trapped inside a screen.

---

# 12. BOTTOM NAVIGATION

If bottom navigation is used:

Recommended maximum:

**4–5 primary items.**

Do not place every feature in the bottom navigation.

Secondary functionality should live inside relevant sections.

Navigation must clearly communicate the user's current location.

---

# 13. COMPLETE FEATURE STATES

Every data-driven screen must support at least:

### Loading

Use appropriate:

- Skeleton loaders
- Activity indicators
- Placeholder cards

Avoid blank screens.

### Empty

Explain why content is empty.

Provide a useful next action where appropriate.

### Error

Explain that something failed.

Provide:

**Try Again**

when appropriate.

### Offline

Detect connectivity where required.

Show understandable offline messaging.

### Success

Provide visible confirmation when an action succeeds.

Never make users guess whether something worked.

---

# 14. SKELETON LOADING

Prefer skeleton interfaces instead of full-screen spinners for content-heavy screens.

The skeleton should approximately match the layout being loaded.

Do not use endless loading indicators.

---

# 15. DESIGN SYSTEM

Create or reuse centralized design tokens.

Example:

```ts
const theme = {
  colors: {},
  typography: {},
  spacing: {},
  radius: {},
  shadows: {},
};
```

Do not randomly assign values throughout screens.

Use a consistent visual system.

---

# 16. BRAND ASSETS

Always inspect:

```text
assets/
brand_assets/
branding/
images/
icons/
fonts/
```

before creating UI.

If official assets exist, use them.

Do not replace:

- Logos
- Colors
- Fonts
- Icons
- Photography

with placeholders when correct branded assets are available.

---

# 17. COLOR

Never randomly use default framework colors.

Primary colors must come from the project's brand.

Define semantic colors including:

- Primary
- Secondary
- Accent
- Background
- Surface
- Elevated surface
- Text primary
- Text secondary
- Border
- Success
- Warning
- Error
- Information
- Disabled

Maintain sufficient contrast.

---

# 18. TYPOGRAPHY

Establish a typography scale.

Example:

- Display
- H1
- H2
- H3
- Title
- Subtitle
- Body
- Body Small
- Label
- Caption

Avoid arbitrary font sizes.

Arabic and English typography must both look professional.

---

# 19. RTL + LTR — MANDATORY

The application must properly support:

- Arabic — RTL
- English — LTR

If required by the project, also prepare architecture for additional languages such as:

- Bengali
- Swahili

RTL does not mean only right-aligning text.

Correctly mirror:

- Layout
- Direction
- Icons when appropriate
- Navigation
- Padding
- Margins
- Lists
- Cards
- Form alignment
- Back arrows
- Progress indicators

Do not mirror icons that have universal meaning unless necessary.

---

# 20. TRANSLATION

No visible UI text should be hardcoded when localization exists.

Use translation keys.

Example:

```tsx
<Text>{t('auth.login')}</Text>
```

not:

```tsx
<Text>Login</Text>
```

Provide fallback language handling.

---

# 21. RESPONSIVE DESIGN

The application must work on different device widths.

Do not design only for one iPhone size.

At minimum check:

- Small iPhone
- Standard iPhone
- Large iPhone
- Small Android
- Standard Android
- Large Android

Where tablets are supported, verify tablet layouts too.

Avoid hardcoded widths such as:

```tsx
width: 375
```

unless specifically needed.

Use flexible layout techniques.

---

# 22. TOUCH TARGETS

Clickable elements must be comfortably tappable.

Recommended minimum:

**44×44 points**

Do not create tiny icons requiring precise tapping.

---

# 23. BUTTONS

Create consistent button variants.

Examples:

- Primary
- Secondary
- Outline
- Ghost
- Destructive
- Icon
- Loading
- Disabled

Every button must support:

- Default
- Pressed
- Disabled
- Loading

Prevent accidental double submission.

---

# 24. FORMS

Forms must be fully functional.

Every field must support:

- Label
- Placeholder
- Value
- Validation
- Error message
- Disabled state where appropriate
- Correct keyboard type
- Auto-capitalization behavior
- Secure input where appropriate
- Accessibility label

The keyboard must never cover the active field.

Use appropriate keyboard avoiding behavior.

---

# 25. INPUT TYPES

Use correct mobile keyboards.

Examples:

Email:

```tsx
keyboardType="email-address"
autoCapitalize="none"
```

Phone:

```tsx
keyboardType="phone-pad"
```

Numeric:

```tsx
keyboardType="numeric"
```

Passwords:

```tsx
secureTextEntry
```

---

# 26. KEYBOARD HANDLING

Test every form with the keyboard open.

Verify:

- Input remains visible
- Submit button remains reachable
- Screen scrolls where needed
- Keyboard dismisses correctly
- Next/Done actions behave correctly

This must be tested on both iOS and Android.

---

# 27. ICONS

Use one consistent icon family unless branding requires otherwise.

Do not randomly mix icon styles.

Icons should have consistent:

- Stroke
- Size
- Visual weight
- Alignment

Use labels when icon meaning may be unclear.

---

# 28. IMAGES

Images must:

- Maintain aspect ratio
- Avoid distortion
- Load efficiently
- Use correct resize mode
- Have placeholders/fallbacks when needed
- Handle failure gracefully

Use optimized local images where possible.

For network images, cache where appropriate.

---

# 29. IMAGE TREATMENT

Where the design requires premium presentation, photography can use:

- Gradient overlays
- Darkening layers
- Subtle brand color treatment
- Controlled contrast
- Rounded masks
- Cinematic cropping

Do not overuse effects.

The result should remain professional.

---

# 30. VISUAL QUALITY

When there is no supplied reference, design to a high visual standard.

Target:

- Elegant
- Modern
- Premium
- Corporate
- Clean
- Distinctive
- Realistic
- Cinematic where suitable
- Hospitality-focused where relevant

Avoid generic template appearance.

---

# 31. DEPTH SYSTEM

Use intentional levels:

### Level 0
Application background

### Level 1
Cards / surfaces

### Level 2
Elevated cards

### Level 3
Bottom sheets / dialogs / floating elements

Do not make every surface look equally elevated.

---

# 32. SHADOWS

Avoid generic heavy shadows.

Use subtle platform-appropriate elevation.

iOS and Android rendering differ, so check both platforms.

---

# 33. ANIMATION

Animations should improve usability.

Only animate properties that perform well, primarily:

- Transform
- Opacity

Use short, natural motion.

Avoid decorative animations that slow the application.

Respect reduced-motion accessibility preferences where practical.

Never use animation to hide slow functionality.

---

# 34. HAPTICS

Use haptics selectively for important interactions such as:

- Successful action
- Important toggle
- Confirmation
- Error feedback when appropriate

Do not trigger vibration on every tap.

---

# 35. MODALS

Do not turn every secondary page into a modal.

Use modals for short focused actions.

Examples:

- Confirmation
- Quick selection
- Warning
- Small forms

For longer workflows use dedicated screens.

---

# 36. BOTTOM SHEETS

Bottom sheets are appropriate for:

- Filters
- Sorting
- Selection menus
- Quick actions
- Compact forms

Ensure Android back handling works correctly.

---

# 37. CONFIRMATION

Destructive actions require confirmation.

Examples:

- Delete
- Logout
- Remove user
- Cancel booking/action
- Reset progress
- Remove organization data

The destructive action must be clearly identified.

---

# 38. TOASTS / SNACKBARS

Use temporary messages for lightweight confirmation.

Examples:

- Saved
- Copied
- Updated
- Added
- Removed

Do not use a toast when the user needs to make an important decision.

---

# 39. AUTHENTICATION

Authentication must handle:

- Login
- Logout
- Token storage
- Token refresh where applicable
- Session expiry
- Invalid session
- Password reset
- Authentication errors
- Network failure
- Loading state

Never expose sensitive tokens in logs.

Use secure storage where appropriate.

---

# 40. ROLE-BASED ACCESS CONTROL

The mobile app must respect backend authorization.

Roles may include:

- Learner
- Employee
- Supervisor
- Instructor
- Manager
- Hotel administrator
- Organization administrator
- Platform administrator

UI visibility alone is not security.

Authorization must remain enforced by the backend.

---

# 41. MULTI-TENANT SUPPORT

Where organizations/properties are supported, always preserve:

```text
Organization
→ Property
→ Department
→ User
```

Never accidentally display another organization's data.

Switching context must update all relevant content.

---

# 42. API

Centralize API communication.

Do not scatter fetch calls across components.

Use a clear API/service layer.

Handle:

- Authentication
- Timeout
- Network error
- API errors
- Validation errors
- Rate limits where applicable
- Retry logic where appropriate

---

# 43. DATA FETCHING

Avoid unnecessary duplicate requests.

Use caching where appropriate.

Refresh stale content intentionally.

Support pull-to-refresh on screens where users expect it.

---

# 44. OFFLINE BEHAVIOR

Define what happens when the user loses internet connectivity.

At minimum:

- Detect network failure
- Keep the interface understandable
- Avoid destructive data loss
- Allow retry

For learning content, consider future offline lesson support where required by product requirements.

---

# 45. LISTS

Use efficient list components.

Prefer:

```tsx
FlatList
```

or appropriate optimized list solutions.

Do not render large lists using:

```tsx
ScrollView
```

with hundreds of children.

Implement:

- Pagination
- Infinite loading
- Pull-to-refresh
- Empty state
- Error state

where appropriate.

---

# 46. SEARCH

Search screens must consider:

- Empty search
- Search results
- No results
- Loading
- Error
- Clear search
- Recent searches if part of product requirements
- Filters if relevant

Debounce server-side searches when appropriate.

---

# 47. FILTERS

Users must understand which filters are active.

Provide:

- Apply
- Clear
- Reset
- Active filter indication

Avoid losing filter selections unexpectedly.

---

# 48. NOTIFICATIONS

If notifications exist, include:

- Notification permission
- Notification list
- Read/unread states
- Notification detail or destination
- Mark as read
- Deep link routing
- Empty notifications state
- Error state

Push notification taps must route users to the correct screen.

---

# 49. DEEP LINKS

When supported, handle deep links correctly.

Examples:

```text
Course
Lesson
Notification
Certificate
Profile
Task
Assessment
```

Unauthenticated users must first authenticate and then continue toward the intended destination when possible.

---

# 50. PERMISSIONS

Handle device permissions professionally.

Possible permissions:

- Camera
- Photos
- Notifications
- Microphone
- Files
- Location

Only request permissions when needed.

Explain why the permission is needed before the OS prompt when appropriate.

Provide a way to open device settings after permanent denial.

---

# 51. CAMERA / MEDIA

If media upload exists, support:

- Camera
- Photo library
- File selection
- Preview
- Upload progress
- Upload error
- Retry
- Cancel
- File size validation
- File type validation

---

# 52. VIDEO

For training content, video playback must consider:

- Loading
- Play/pause
- Seek
- Duration
- Progress
- Full screen
- Orientation
- Playback errors
- Resume position
- Completion tracking
- Network interruption

Do not mark a video lesson as completed incorrectly.

---

# 53. LEARNING MODULE

If the application contains learning functionality, consider screens for:

- Learning home
- Categories
- Courses
- Course detail
- Modules
- Lessons
- Video lesson
- Text lesson
- Image lesson
- Document lesson
- Quiz
- Quiz questions
- Quiz results
- Retake
- Progress
- Completion
- Certificates
- Certificate detail
- Download/share certificate
- Bookmarks
- Favorites
- Continue learning

Do not stop at Course List + Course Detail.

---

# 54. KNOWLEDGE MODULE

Where applicable, include:

- Knowledge home
- Categories
- SOP library
- SOP list
- SOP detail
- Search
- Favorites
- Recently viewed
- Documents
- Policies
- Procedures
- Quick guides
- Download/view states
- Version/update information when applicable

---

# 55. PERFORMANCE MODULE

Where applicable, consider:

- Performance home
- Personal performance
- Team performance
- KPI dashboard
- KPI details
- Score/history
- Supervisor feedback
- Employee feedback
- Improvement plans
- Tasks
- Evaluations
- Assessment
- Achievement
- Recognition

Screens shown must depend on user permission.

---

# 56. SUPERVISOR / MANAGER EXPERIENCE

Do not design only for employees.

Supervisors/managers may require:

- Team dashboard
- Employee list
- Employee profile
- Employee progress
- Assign training
- Assignment details
- Assess employee
- Approve/decline
- Feedback
- Team performance
- Reports
- Alerts
- Pending actions
- Escalations

---

# 57. ADMIN EXPERIENCE

If administrative functionality is supported in mobile, identify every required screen.

Do not assume desktop administration replaces mobile unless requirements explicitly say so.

Potential screens:

- Organization
- Hotel/property
- Departments
- Users
- Roles
- Permissions
- Content
- Courses
- Assignments
- Reports
- Configuration
- Audit information

---

# 58. DASHBOARDS

Mobile dashboards must be simplified intentionally.

Do not shrink desktop dashboards.

Prioritize:

- Important KPI
- Trend
- Alerts
- Required actions
- Progress
- Shortcuts

Detailed analytics can open in dedicated screens.

---

# 59. CHARTS

Charts must remain readable on small screens.

Include:

- Proper labels
- Units
- Accessible colors
- Tooltips/details where appropriate
- Horizontal scrolling only if absolutely necessary

Never place desktop-sized charts inside mobile cards.

---

# 60. DATE / TIME

Respect locale.

Support:

- Arabic / English formats
- 12/24-hour preferences where appropriate
- Correct timezone
- Date parsing
- Date selection
- Time selection

Never assume all users use the same date format.

---

# 61. ACCESSIBILITY

Accessibility is mandatory.

Check:

- Screen reader labels
- Button labels
- Image descriptions where relevant
- Input labels
- Contrast
- Touch size
- Dynamic text scaling where practical
- Keyboard/focus behavior
- Reduced motion

Never rely solely on color to communicate status.

---

# 62. STATUS INDICATORS

Statuses must use:

- Color
- Text
- Optional icon

For example:

```text
● Completed
● In Progress
● Overdue
```

Do not communicate important state with color alone.

---

# 63. ERROR MESSAGES

Error messages must be human-readable.

Bad:

```text
Error 422
```

Better:

```text
We couldn't save your changes. Please review the highlighted fields and try again.
```

Technical errors may still be logged for debugging.

---

# 64. SECURITY

Never put secrets into the mobile client.

Do not include:

- Private API keys
- Database admin credentials
- Service-role secrets
- Hardcoded passwords

Anything shipped inside the application package must be assumed discoverable.

---

# 65. ENVIRONMENT VARIABLES

Use proper environment configuration.

Examples:

```text
Development
Staging
Production
```

Do not hardcode production endpoints throughout the application.

---

# 66. LOGGING

Development logging is acceptable.

Production logging must not expose:

- Passwords
- Tokens
- Personal information
- Private identifiers
- Sensitive business data

Remove noisy debugging logs before release.

---

# 67. ANALYTICS

If analytics are included, define events intentionally.

Examples:

```text
login_success
course_started
lesson_completed
quiz_completed
certificate_earned
notification_opened
```

Never collect unnecessary personal data.

---

# 68. CRASH REPORTING

Prepare proper production error reporting if the project requires it.

Never expose raw technical stack traces directly to users.

---

# 69. PERFORMANCE

Monitor:

- Initial startup
- Navigation speed
- List rendering
- Image loading
- Video loading
- Memory use
- Unnecessary re-renders

Do not sacrifice application responsiveness for unnecessary visual effects.

---

# 70. COMPONENT ARCHITECTURE

Do not create giant screen files.

Extract reusable components.

Example:

```text
components/
  Button/
  Card/
  Avatar/
  EmptyState/
  ErrorState/
  Skeleton/
  SearchBar/
  SectionHeader/
```

Avoid extracting components that are used only once unless doing so improves readability significantly.

---

# 71. FEATURE ARCHITECTURE

Prefer feature organization for larger applications.

Example:

```text
features/
  auth/
  home/
  learning/
  knowledge/
  performance/
  notifications/
  profile/
```

Keep related:

- Screens
- Components
- Hooks
- Services
- Types

together where practical.

---

# 72. DESIGN REFERENCES

If the user provides screenshots, Figma designs, website references, or other visual references:

Match them carefully.

Check:

- Layout
- Spacing
- Typography
- Color
- Iconography
- Image positioning
- Border radius
- Shadows
- Alignment
- Information hierarchy

Do not redesign a reference unless the user requests improvement.

---

# 73. WHEN DESIGNING FROM SCRATCH

If there is no exact reference:

Create an original mobile experience.

It should feel:

- Premium
- Modern
- Corporate
- Clean
- Elegant
- Distinctive
- Easy to use

Avoid the appearance of:

- Generic AI interfaces
- Generic admin dashboards
- Bootstrap templates
- Basic student projects
- Website pages copied into mobile

---

# 74. VISUAL CONSISTENCY

All screens must feel like one application.

Do not allow:

- Different button styles
- Random card radii
- Inconsistent headers
- Different spacing systems
- Different icon sets
- Different typography

unless intentionally designed.

---

# 75. SCREEN HEADER SYSTEM

Create consistent patterns for:

- Root screens
- Detail screens
- Modal screens
- Full-screen media
- Search screens

Back behavior should remain predictable.

---

# 76. SCREENSHOT REVIEW — MANDATORY

Where the environment supports simulators/emulators/screenshots:

Run the application and visually inspect it.

Do not judge the final interface only from source code.

Capture key screens on:

- iOS
- Android

Review:

- Spacing
- Safe areas
- Typography
- Image cropping
- Card sizing
- Alignment
- Keyboard behavior
- Bottom navigation
- RTL
- LTR

---

# 77. VISUAL QA ROUNDS

Do not stop after the first render.

Perform at least:

### Round 1
Implementation review

### Round 2
Visual correction

### Round 3
Final consistency check

If a reference design exists, compare against the reference after each major pass.

---

# 78. FUNCTIONAL QA

For each screen test:

- Can I enter the screen?
- Does it load?
- Does data appear?
- Does empty state work?
- Does error state work?
- Do buttons work?
- Does back navigation work?
- Does Android hardware back work?
- Does keyboard behavior work?
- Does RTL work?
- Does loading work?
- Does retry work?

---

# 79. NAVIGATION QA

Test complete journeys rather than isolated screens.

Example:

```text
Login
→ Home
→ Learning
→ Course
→ Lesson
→ Quiz
→ Result
→ Certificate
→ Home
```

A screen working independently does not mean the workflow is complete.

---

# 80. AUTH QA

Test:

- Correct credentials
- Incorrect credentials
- Network unavailable
- Session expiration
- Logout
- Login again
- Token refresh
- App restart while logged in
- App restart while logged out

---

# 81. RTL QA

Every major screen must be checked in:

- Arabic
- English

Do not approve the application after checking only English.

---

# 82. DEVICE QA

Check multiple screen sizes.

At minimum:

### iOS

- Small iPhone
- Modern standard iPhone
- Large iPhone

### Android

- Small Android
- Standard Android
- Large Android

Look specifically for:

- Overflow
- Cropping
- Broken cards
- Truncated text
- Keyboard problems
- Tab overflow

---

# 83. DARK MODE

Do not automatically create dark mode unless required.

If the application supports dark mode, it must be complete.

Never create a partially functioning dark mode.

Test every component in both themes.

---

# 84. TABLET

If tablet support is required:

Do not simply stretch phone screens.

Use larger layouts intentionally.

Possible improvements:

- Two-column layouts
- Master/detail
- Wider cards
- Side navigation

But preserve the same design system.

---

# 85. ORIENTATION

Default to portrait unless product requirements require landscape.

Video full-screen may support landscape.

Do not allow orientation changes that break layouts.

---

# 86. APP ICON + SPLASH

Production preparation should include:

- App icon
- Adaptive Android icon
- Splash screen
- Branding
- Background colors
- Correct scaling
- Dark/light splash if required

Never ship Expo defaults.

---

# 87. STATUS BAR

Configure status-bar content intentionally.

Ensure contrast between status icons and application background.

Check each full-screen experience.

---

# 88. APP CONFIGURATION

Review:

```text
app.json
```

or:

```text
app.config.ts
```

Check:

- App name
- Slug
- Version
- Orientation
- Icon
- Splash
- iOS bundle identifier
- Android package
- Permissions
- Deep links
- Scheme
- Plugins
- Update configuration

---

# 89. EXPO CONFIGURATION

Use Expo-supported libraries where practical.

Check compatibility before adding packages.

Never install a dependency simply because it is popular.

Confirm it works with the project's Expo SDK version.

---

# 90. EAS

Prepare correctly for Expo Application Services where used.

Possible commands:

```bash
eas build
eas submit
eas update
```

Do not execute destructive production actions unless explicitly authorized.

---

# 91. BUILD VALIDATION

Before considering development complete, verify:

```bash
npx expo-doctor
```

and project type checks/lint/tests where configured.

Resolve significant errors.

Do not ignore warnings that affect production functionality.

---

# 92. STORE READINESS

Before release, verify requirements for:

### Apple App Store

- App name
- Icon
- Screenshots
- Privacy information
- Permission descriptions
- Bundle identifier
- Version
- Build
- Sign-in requirements
- Account deletion when required

### Google Play

- Package name
- Icon
- Adaptive icon
- Screenshots
- Privacy information
- Permissions
- Data safety
- Version code
- Target Android version

---

# 93. PRIVACY

If the application collects personal information, document what data is used and why.

Request only information necessary for functionality.

Never expose data belonging to another user or organization.

---

# 94. EMPTY SCREEN POLICY

A blank white screen is never an acceptable application state.

Every screen must intentionally render one of:

- Content
- Loading
- Empty state
- Error state
- Offline state

---

# 95. PLACEHOLDERS

Use placeholders only when real data/assets are unavailable.

Clearly separate placeholder content from production content.

Do not leave:

```text
Lorem ipsum
John Doe
Company XYZ
Test User
```

in production-ready screens unless explicitly intended.

---

# 96. MOCK DATA

Mock data may be used during development.

It must:

- Match real data types
- Be clearly isolated
- Be removable
- Not overwrite real backend behavior

Do not hide incomplete APIs behind permanent mock data.

---

# 97. DO NOT FAKE FUNCTIONALITY

A button that looks functional must actually work.

Do not create:

- Fake search
- Fake filters
- Fake upload
- Fake login
- Fake notifications
- Fake settings
- Fake progress

unless explicitly building a visual-only prototype.

If something cannot work because the backend endpoint does not exist, make that limitation clear in the implementation notes.

---

# 98. NO SILENT FEATURE REMOVAL

Never remove an existing feature because implementing it is difficult.

Never remove:

- Screens
- Buttons
- Menu items
- User roles
- Settings
- States
- API integrations

without explicit reason and documentation.

---

# 99. EXISTING PROJECT PROTECTION

Before major changes:

1. Understand existing implementation.
2. Preserve working functionality.
3. Keep migrations backward-compatible where practical.
4. Avoid unnecessary dependency replacement.
5. Avoid mass refactoring without reason.

Do not rebuild the entire application when a focused modification is sufficient.

---

# 100. COMPLETION AUDIT — MANDATORY

Before saying the app is complete, perform a complete audit.

Check:

### Screens
- Every screen exists
- Every screen is reachable
- No dead routes

### Navigation
- All routes work
- Back navigation works
- Deep links work where supported

### States
- Loading
- Empty
- Error
- Offline
- Success

### Platforms
- iOS checked
- Android checked

### Languages
- Arabic checked
- English checked
- RTL checked
- LTR checked

### User roles
- Employee checked
- Supervisor checked
- Manager checked
- Admin checked
- Other supported roles checked

### Design
- Consistent typography
- Consistent spacing
- Consistent colors
- Consistent icons
- Correct brand assets

### Forms
- Validation
- Keyboard
- Errors
- Submission
- Loading

### Backend
- Real APIs connected
- Errors handled
- Authentication handled
- Authorization respected

### Security
- No secrets exposed
- Secure token storage
- No sensitive logs

---

# 101. FINAL SCREEN AUDIT

Generate the final screen inventory again at the end.

Compare:

```text
PLANNED SCREENS
vs.
IMPLEMENTED SCREENS
```

Every planned screen must be marked as one of:

```text
✓ COMPLETE
△ BLOCKED — backend/API dependency documented
○ NOT REQUIRED — documented reason
```

There must be no unexplained missing screens.

---

# 102. FINAL USER JOURNEY AUDIT

Verify every main journey.

For each journey mark:

```text
✓ Navigation complete
✓ UI complete
✓ API/data complete
✓ Loading state complete
✓ Empty state complete
✓ Error state complete
✓ iOS tested
✓ Android tested
✓ Arabic tested
✓ English tested
```

---

# 103. FINAL REPORT

At the end of development provide:

## Application Summary

What was built.

## Screen Inventory

Total number of screens.

## Screens Completed

List or table.

## User Roles

Roles supported.

## User Journeys

Journeys tested.

## iOS

Status.

## Android

Status.

## Arabic / RTL

Status.

## English / LTR

Status.

## APIs

Integration status.

## Testing

Tests completed.

## Known Issues

Any remaining issues.

## Blocked Items

Anything requiring backend credentials, APIs, external services, or business decisions.

## Release Readiness

What remains before App Store / Google Play deployment.

---

# 104. HARD RULES

These rules are mandatory.

- Do not skip screens.
- Do not skip secondary flows.
- Do not skip loading states.
- Do not skip empty states.
- Do not skip error states.
- Do not skip offline behavior where relevant.
- Do not design only for iOS.
- Do not design only for Android.
- Do not design only for English.
- Do not ignore Arabic RTL.
- Do not ignore safe areas.
- Do not allow the keyboard to cover forms.
- Do not leave dead buttons.
- Do not leave dead navigation links.
- Do not fake working functionality.
- Do not expose secrets.
- Do not hardcode user-specific data.
- Do not rebuild working architecture unnecessarily.
- Do not randomly introduce new design systems.
- Do not leave inconsistent components.
- Do not call the app complete until the screen inventory and user journeys have been audited.

---

# 105. QUALITY TARGET

The final mobile application should feel like a carefully designed commercial product rather than a generated prototype.

Target qualities:

**Beautiful**  
**Professional**  
**Modern**  
**Eye-catching**  
**Corporate**  
**Realistic**  
**Premium**  
**Intuitive**  
**Fast**  
**Consistent**  
**Accessible**  
**Secure**  
**Production-ready**

The experience should feel intentionally created for the product and brand.

---

# 106. MOST IMPORTANT RULE

Before declaring any implementation complete:

**STOP AND ASK:**

> Have I identified, designed, implemented, connected, and tested every screen and every state required by every supported user role on both iOS and Android?

If the answer is no:

**Continue development.**

Do not finish the task while screens or critical states remain incomplete.

---

# 107. DEFINITION OF DONE

The mobile application is considered complete only when:

**Screen Inventory = Complete**  
**Navigation = Complete**  
**User Journeys = Complete**  
**Authentication = Complete**  
**Authorization = Complete**  
**API Integration = Complete**  
**Loading States = Complete**  
**Empty States = Complete**  
**Error States = Complete**  
**iOS QA = Complete**  
**Android QA = Complete**  
**Arabic RTL QA = Complete**  
**English LTR QA = Complete**  
**Visual QA = Complete**  
**Functional QA = Complete**  
**Security Review = Complete**  
**Build Validation = Complete**

**No screen should be skipped.  
No journey should be incomplete.  
No important interaction should be decorative only.**